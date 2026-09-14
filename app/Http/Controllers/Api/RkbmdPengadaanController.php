<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RkbmdPengadaanResource;
use App\Models\RefKegiatan;
use App\Models\RefProgram;
use App\Models\RefSkpd;
use App\Models\RefSubKegiatan;
use App\Models\RkbmdPengadaan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RkbmdPengadaanController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = RkbmdPengadaan::query();

        // Scoping SKPD berdasarkan role user login
        $user = $request->user();
        if ($user && $user->kode_skpd) {
            $userRole = strtolower(trim((string) $user->role));
            if (in_array($userRole, ['kepala opd', 'kepala sub unit', 'ppk'], true)) {
                $skpdCodes = RefSkpd::query()
                    ->where('kode_skpd', $user->kode_skpd)
                    ->orWhere('parent_kode_skpd', $user->kode_skpd)
                    ->pluck('kode_skpd')
                    ->all();

                $query->whereIn('kode_skpd', ! empty($skpdCodes) ? $skpdCodes : [$user->kode_skpd]);
            }
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_barang', 'ilike', "%{$search}%")
                    ->orWhere('kode_fikasi', 'ilike', "%{$search}%")
                    ->orWhere('nama_skpd', 'ilike', "%{$search}%");
            });
        }

        if ($kodeSkpd = $request->query('kode_skpd')) {
            $query->where('kode_skpd', $kodeSkpd);
        }

        // Filter by sub kegiatan (kolom sumber: kode_sub_giat) — dipakai popup RKBMD di form Identifikasi.
        if ($kodeSubKegiatan = $request->query('kode_sub_kegiatan')) {
            $query->where('kode_sub_giat', $kodeSubKegiatan);
        }

        if ($periode = $request->query('periode')) {
            $query->where('periode', (int) $periode);
        }

        $perPage = (int) $request->query('per_page', 15);

        $rows = $perPage > 0 ? $query->paginate($perPage) : $query->get();

        // "Diisi" (riwayat pemakaian) — total jumlah yang sudah dipakai usulan lain
        // dari tabel identifikasi_kebutuhan_rkbmd, dikelompokkan per id_pengadaan.
        // Mode edit: usulan yang sedang diedit dikecualikan (query exclude_identifikasi).
        $exclude = $request->query('exclude_identifikasi');
        $used = DB::table('dev.identifikasi_kebutuhan_rkbmd')
            ->select('id_pengadaan')
            ->selectRaw('SUM(jumlah) as total')
            ->where('jenis_rkbmd', 'pengadaan')
            ->when($exclude !== null && $exclude !== '', function ($q) use ($exclude) {
                $q->where('identifikasi_kebutuhan_id', '!=', (int) $exclude);
            })
            ->groupBy('id_pengadaan')
            ->pluck('total', 'id_pengadaan');

        foreach ($rows as $row) {
            $row->sudah_diisi = (int) ($used[$row->id_pengadaan] ?? 0);
        }

        return RkbmdPengadaanResource::collection($rows);
    }

    public function show(int $id): RkbmdPengadaanResource
    {
        $data = RkbmdPengadaan::findOrFail($id);

        return new RkbmdPengadaanResource($data);
    }

    /**
     * Tambah data RKBMD Pengadaan secara manual (tanpa file Excel).
     * Dipakai untuk uji & kelengkapan data di menu RKBMD Pengadaan.
     */
    public function store(Request $request): RkbmdPengadaanResource
    {
        $validated = $request->validate([
            'nama_barang' => ['required', 'string', 'max:255'],
            'kode_fikasi' => ['nullable', 'string', 'max:50'],
            'jumlah_barang' => ['required', 'integer', 'min:0'],
            'jumlah_maksimum' => ['nullable', 'integer', 'min:0'],
            'satuan' => ['nullable', 'string', 'max:50'],
            'cara_pemenuhan' => ['nullable', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string', 'max:500'],
            'kode_skpd' => ['required', 'string', 'max:50'],
            'kode_sub_kegiatan' => ['required', 'string', 'max:50'],
            'periode' => ['required', 'integer', 'in:2025,2026,2027'],
        ]);

        // Auto-fill nama SKPD dari referensi
        $skpd = RefSkpd::find($validated['kode_skpd']);
        $sub = RefSubKegiatan::find($validated['kode_sub_kegiatan']);
        $kegiatan = $sub ? RefKegiatan::find($sub->kode_kegiatan) : null;
        $program = $kegiatan ? RefProgram::find($kegiatan->kode_program) : null;

        if (! $skpd) {
            throw ValidationException::withMessages(['kode_skpd' => 'Kode SKPD tidak ditemukan di referensi.']);
        }
        if (! $sub) {
            throw ValidationException::withMessages(['kode_sub_kegiatan' => 'Kode sub kegiatan tidak ditemukan di referensi.']);
        }

        // ID manual karena tabel tidak auto-increment
        $nextId = (int) RkbmdPengadaan::max('id_pengadaan') + 1;

        $data = RkbmdPengadaan::create([
            'id_pengadaan' => $nextId,
            'kode_fikasi' => $validated['kode_fikasi'] ?? null,
            'nama_barang' => $validated['nama_barang'],
            'jumlah_barang' => $validated['jumlah_barang'],
            'jumlah_maksimum' => $validated['jumlah_maksimum'] ?? $validated['jumlah_barang'],
            'satuan' => $validated['satuan'] ?? null,
            'cara_pemenuhan' => $validated['cara_pemenuhan'] ?? 'Pengadaan',
            'keterangan' => $validated['keterangan'] ?? null,
            'periode' => $validated['periode'],
            'nm_status' => 'Ditetapkan',
            'id_status' => 3,
            'kode_skpd' => $validated['kode_skpd'],
            'nama_skpd' => $skpd->nama_skpd,
            'kode_sub_skpd' => $skpd->parent_kode_skpd ?? null,
            'kode_program' => $kegiatan->kode_program ?? null,
            'nama_program' => $program->nama_program ?? null,
            'kode_giat' => $sub->kode_kegiatan ?? null,
            'nama_giat_nama_giat' => $kegiatan->nama_kegiatan ?? null,
            'kode_sub_giat' => $sub->kode_sub_kegiatan,
            'nama_sub_giat_nama_sub_giat' => $sub->nama_sub_kegiatan,
        ]);

        return new RkbmdPengadaanResource($data);
    }
}
