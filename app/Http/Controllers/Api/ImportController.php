<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\RkbmdPemeliharaanImport;
use App\Imports\RkbmdPengadaanImport;
use DB;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Str;

class ImportController extends Controller
{
    public function importRkbmdPengadaan(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:20480', // Maksimal 20MB
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

        // 2. Jalankan Job Import via Queue dengan membawa $importId
        Excel::queueImport(new RkbmdPengadaanImport($importId), $filePath);

        // 3. Kembalikan ID Tracking ke Next.js
        return response()->json([
            'success' => true,
            'message' => 'File pengadaan berhasil diunggah dan sedang diproses.',
            'import_id' => $importId,
        ], 200);
    }

    public function importRkbmdPemeliharaan(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:20480', // Limit 20MB
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

        // 2. Jalankan Job Import via Queue dengan membawa $importId
        Excel::queueImport(new RkbmdPemeliharaanImport($importId), $filePath);

        // 3. Kembalikan ID Tracking ke Next.js
        return response()->json([
            'success' => true,
            'message' => 'File pemeliharaan berhasil diunggah dan sedang diproses.',
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
