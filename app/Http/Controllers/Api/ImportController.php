<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\RkbmdPemeliharaanImport;
use App\Imports\RkbmdPengadaanImport;
use App\Imports\SipdPenetapanApbdImport;
use DB;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Str;

class ImportController extends Controller
{
    public function importRkbmdPengadaan(Request $request)
    {
        // Amankan memory untuk file besar (PhpSpreadsheet membaca workbook ke memori).
        @ini_set('memory_limit', '1024M');
        // Impor ribuan–puluhan ribu baris bisa memakan beberapa menit; di produksi
        // php-fpm batas bawaan (30–60 s) akan memutus request di tengah jalan.
        @set_time_limit(0);

        $request->validate([
            // Validasi berbasis EKSTENSI asli, bukan deteksi MIME (finfo) yang tidak
            // konsisten untuk CSV (kadang terdeteksi text/plain → ditolak mimes:csv).
            'file' => ['required', 'file', 'max:20480', function ($attribute, $value, $fail) {
                $ext = strtolower($value->getClientOriginalExtension());
                if (! in_array($ext, ['xlsx', 'xls', 'csv'])) {
                    $fail('Format file harus .xlsx, .xls, atau .csv.');
                }
            }],
        ]);

        $importId = (string) Str::uuid();
        $file = $request->file('file');
        $filePath = $file->store('imports');

        // 1. Catat status awal ke database
        DB::table('dev.import_statuses')->insert([
            'id' => $importId,
            'user_id' => auth()->id() ?? null,
            'file_name' => 'rkbmd_pengadaan',
            'status' => 'processing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Jalankan impor SECARA SINKRON (tanpa worker queue) agar pasti selesai.
        //    Catatan: class import TIDAK mengimplementasikan ShouldQueue, sehingga
        //    Maatwebsite memproses chunk langsung di request ini (lihat ChunkReader).
        //    Setelah selesai, event AfterImport pada job otomatis menandai status 'completed'.
        try {
            Excel::import(new RkbmdPengadaanImport($importId), $filePath);
        } catch (\Throwable $e) {
            DB::table('dev.import_statuses')
                ->where('id', $importId)
                ->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => false,
                'message' => 'Impor gagal: '.$e->getMessage(),
                'import_id' => $importId,
            ], 422);
        }

        // 3. Kembalikan ID Tracking ke Next.js (status sudah 'completed')
        return response()->json([
            'success' => true,
            'message' => 'File pengadaan berhasil diimpor ke database.',
            'import_id' => $importId,
        ], 200);
    }

    public function importRkbmdPemeliharaan(Request $request)
    {
        // Amankan memory untuk file besar (PhpSpreadsheet membaca workbook ke memori).
        @ini_set('memory_limit', '1024M');
        // Impor ribuan–puluhan ribu baris bisa memakan beberapa menit; di produksi
        // php-fpm batas bawaan (30–60 s) akan memutus request di tengah jalan.
        @set_time_limit(0);

        $request->validate([
            // Validasi berbasis EKSTENSI asli, bukan deteksi MIME (finfo) yang tidak
            // konsisten untuk CSV (kadang terdeteksi text/plain → ditolak mimes:csv).
            'file' => ['required', 'file', 'max:20480', function ($attribute, $value, $fail) {
                $ext = strtolower($value->getClientOriginalExtension());
                if (! in_array($ext, ['xlsx', 'xls', 'csv'])) {
                    $fail('Format file harus .xlsx, .xls, atau .csv.');
                }
            }],
        ]);

        $importId = (string) Str::uuid();
        $file = $request->file('file');
        $filePath = $file->store('imports');

        // 1. Catat status awal ke database
        DB::table('dev.import_statuses')->insert([
            'id' => $importId,
            'user_id' => auth()->id() ?? null,
            'file_name' => 'rkbmd_pemeliharaan',
            'status' => 'processing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Jalankan impor SECARA SINKRON (tanpa worker queue) agar pasti selesai.
        //    Catatan: class import TIDAK mengimplementasikan ShouldQueue, sehingga
        //    Maatwebsite memproses chunk langsung di request ini (lihat ChunkReader).
        //    Setelah selesai, event AfterImport pada job otomatis menandai status 'completed'.
        try {
            Excel::import(new RkbmdPemeliharaanImport($importId), $filePath);
        } catch (\Throwable $e) {
            DB::table('dev.import_statuses')
                ->where('id', $importId)
                ->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => false,
                'message' => 'Impor gagal: '.$e->getMessage(),
                'import_id' => $importId,
            ], 422);
        }

        // 3. Kembalikan ID Tracking ke Next.js (status sudah 'completed')
        return response()->json([
            'success' => true,
            'message' => 'File pemeliharaan berhasil diimpor ke database.',
            'import_id' => $importId,
        ], 200);
    }

    public function importSipdPenetapanApbd(Request $request)
    {
        // Amankan memory untuk file besar (PhpSpreadsheet membaca workbook ke memori).
        @ini_set('memory_limit', '1024M');
        // Impor ribuan–puluhan ribu baris bisa memakan beberapa menit; di produksi
        // php-fpm batas bawaan (30–60 s) akan memutus request di tengah jalan.
        @set_time_limit(0);

        $request->validate([
            // Validasi berbasis EKSTENSI asli, bukan deteksi MIME (finfo) yang tidak
            // konsisten untuk CSV (kadang terdeteksi text/plain → ditolak mimes:csv).
            'file' => ['required', 'file', 'max:51200', function ($attribute, $value, $fail) {
                $ext = strtolower($value->getClientOriginalExtension());
                if (! in_array($ext, ['xlsx', 'xls', 'csv'])) {
                    $fail('Format file harus .xlsx, .xls, atau .csv.');
                }
            }],
            'tahun' => ['nullable', 'integer'],
            'versi' => ['nullable', 'string', 'max:100'],
            'nama_versi' => ['nullable', 'string', 'max:100'],
        ]);

        $importId = (string) Str::uuid();
        $file = $request->file('file');
        $filePath = $file->store('imports');

        // 1. Catat status awal ke database
        DB::table('dev.import_statuses')->insert([
            'id' => $importId,
            'user_id' => auth()->id() ?? null,
            'file_name' => 'sipd_penetapan_apbd',
            'status' => 'processing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Jalankan impor SECARA SINKRON (tanpa worker queue) agar pasti selesai.
        //    Setelah selesai, event AfterImport pada job otomatis menandai status 'completed'.
        try {
            Excel::import(
                new SipdPenetapanApbdImport(
                    $importId,
                    $request->integer('tahun') ?: null,
                    $request->input('versi') ?: ($request->string('nama_versi')->toString() ?: null)
                ),
                $filePath
            );
        } catch (\Throwable $e) {
            DB::table('dev.import_statuses')
                ->where('id', $importId)
                ->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => false,
                'message' => 'Impor gagal: '.$e->getMessage(),
                'import_id' => $importId,
            ], 422);
        }

        // 3. Kembalikan ID Tracking ke Next.js (status sudah 'completed')
        return response()->json([
            'success' => true,
            'message' => 'File penetapan APBD berhasil diimpor ke database.',
            'import_id' => $importId,
        ], 200);
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
