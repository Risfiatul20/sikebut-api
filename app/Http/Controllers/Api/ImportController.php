<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunImportJob;
use DB;
use Illuminate\Http\Request;
use Str;

/**
 * Impor berkas besar (SIPD Penetapan APBD & RKBMD) TIDAK dikerjakan di dalam
 * request ini, melainkan dikirim ke antrean lalu dieksekusi worker.
 *
 * Alasannya: impor berkas SIPD asli (39.236 baris) butuh ~7-8 menit. Kalau
 * dikerjakan sinkron, server PHP yang melayani satu permintaan sekaligus ikut
 * membeku → permintaan lain gagal → pengguna melihat
 * "Backend tidak dapat dijangkau".
 *
 * Jalankan worker: php artisan queue:work database --timeout=3600
 */
class ImportController extends Controller
{
    /** Batas ukuran unggahan (KB) per jenis impor. */
    private const MAKS_RKBMD_KB = 20480; // 20 MB

    private const MAKS_SIPD_KB = 51200; // 50 MB

    public function importRkbmdPengadaan(Request $request)
    {
        $this->validasiBerkas($request, self::MAKS_RKBMD_KB);

        return $this->antrikan($request, 'rkbmd_pengadaan');
    }

    public function importRkbmdPemeliharaan(Request $request)
    {
        $this->validasiBerkas($request, self::MAKS_RKBMD_KB);

        return $this->antrikan($request, 'rkbmd_pemeliharaan');
    }

    public function importSipdPenetapanApbd(Request $request)
    {
        $this->validasiBerkas($request, self::MAKS_SIPD_KB, [
            'tahun' => ['nullable', 'integer'],
            'versi' => ['nullable', 'string', 'max:100'],
            'nama_versi' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->antrikan($request, 'sipd_penetapan_apbd');
    }

    /**
     * Validasi berbasis EKSTENSI asli, bukan deteksi MIME (finfo) yang tidak
     * konsisten untuk CSV (kadang terdeteksi text/plain → ditolak mimes:csv).
     */
    private function validasiBerkas(Request $request, int $maksKb, array $aturanTambahan = []): void
    {
        $request->validate(array_merge([
            'file' => ['required', 'file', "max:{$maksKb}", function ($attribute, $value, $fail) {
                $ext = strtolower($value->getClientOriginalExtension());
                if (! in_array($ext, ['xlsx', 'xls', 'csv'])) {
                    $fail('Format file harus .xlsx, .xls, atau .csv.');
                }
            }],
        ], $aturanTambahan));
    }

    /**
     * Simpan berkas, catat status awal, lalu kirim ke antrean.
     *
     * Balasan 202 (Accepted) dikirim SEKETIKA — pekerjaan beratnya ada di worker.
     * Status dipantau lewat GET /api/v1/import/status/{id}:
     * `processing` → `completed` / `failed`.
     */
    private function antrikan(Request $request, string $jenis)
    {
        $importId = (string) Str::uuid();

        // Simpan dengan EKSTENSI ASLI kiriman pengguna (uuid.csv / uuid.xlsx).
        // `store('imports')` menebak ekstensi dari deteksi MIME, dan untuk berkas
        // CSV hasil ekspor SIPD tebakannya bisa menjadi `.txt` — pembaca Excel
        // (Maatwebsite/PhpSpreadsheet) menentukan tipe reader dari ekstensi berkas,
        // jadi ekstensi yang salah adalah sumber kegagalan impor yang tidak perlu.
        $ext = strtolower($request->file('file')->getClientOriginalExtension());
        $filePath = $request->file('file')->storeAs('imports', "{$importId}.{$ext}");

        DB::table('dev.import_statuses')->insert([
            'id' => $importId,
            'user_id' => auth()->id() ?? null,
            'file_name' => $jenis,
            'status' => 'processing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        RunImportJob::dispatch(
            $importId,
            $jenis,
            $filePath,
            $request->integer('tahun') ?: null,
            $request->input('versi') ?: ($request->string('nama_versi')->toString() ?: null),
        );

        return response()->json([
            'success' => true,
            'message' => 'Berkas diterima dan sedang diproses di latar belakang.',
            'import_id' => $importId,
        ], 202);
    }

    public function checkStatus($id)
    {
        $status = DB::table('dev.import_statuses')->where('id', $id)->first();

        if (! $status) {
            return response()->json([
                'success' => false,
                'message' => 'Data import tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'status' => $status->status, // 'processing', 'completed', atau 'failed'
            'file_name' => $status->file_name,
            'error_message' => $status->error_message,
            'updated_at' => $status->updated_at,
        ], 200);
    }
}
