<?php

namespace App\Jobs;

use App\Imports\RkbmdPemeliharaanImport;
use App\Imports\RkbmdPengadaanImport;
use App\Imports\SipdPenetapanApbdImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Menjalankan impor berkas Excel DI LATAR BELAKANG (queue).
 *
 * Mengapa tidak dikerjakan langsung di request: impor berkas SIPD asli
 * (39.236 baris) memakan ~7-8 menit. Kalau dikerjakan di dalam request,
 * server PHP yang melayani satu permintaan sekaligus ikut membeku, sehingga
 * permintaan lain (polling notifikasi, memuat daftar) gagal dan pengguna
 * melihat pesan "Backend tidak dapat dijangkau".
 *
 * Class import-nya sendiri sengaja TIDAK mengimplementasikan ShouldQueue —
 * Maatwebsite akan mengantrekan per-chunk kalau ShouldQueue dipasang, dan itu
 * merusak logika antar-chunk (mis. penanda `deletedOldData`). Jadi yang
 * diantrekan adalah SATU job utuh yang memanggil Excel::import() di dalam worker.
 *
 * Worker: php artisan queue:work database --timeout=3600
 */
class RunImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Jangan pernah diulang: percobaan kedua akan menduplikasi baris yang
     * sudah masuk (impor SIPD menyisipkan baris baru per versi, RKBMD upsert).
     */
    public int $tries = 1;

    /** Batas waktu worker untuk satu impor (detik). */
    public int $timeout = 3600;

    /**
     * @param  string  $jenis  'sipd_penetapan_apbd' | 'rkbmd_pengadaan' | 'rkbmd_pemeliharaan'
     */
    public function __construct(
        public string $importId,
        public string $jenis,
        public string $filePath,
        public ?int $tahun = null,
        public ?string $versi = null,
    ) {}

    public function handle(): void
    {
        // Worker punya batas memori/waktu sendiri — pastikan cukup untuk puluhan ribu baris.
        @ini_set('memory_limit', '1536M');
        @set_time_limit(0);

        try {
            match ($this->jenis) {
                'sipd_penetapan_apbd' => Excel::import(
                    new SipdPenetapanApbdImport($this->importId, $this->tahun, $this->versi),
                    $this->filePath
                ),
                'rkbmd_pengadaan' => Excel::import(
                    new RkbmdPengadaanImport($this->importId),
                    $this->filePath
                ),
                'rkbmd_pemeliharaan' => Excel::import(
                    new RkbmdPemeliharaanImport($this->importId),
                    $this->filePath
                ),
                default => throw new InvalidArgumentException("Jenis impor tidak dikenal: {$this->jenis}"),
            };
        } catch (Throwable $e) {
            // Tandai gagal SEBELUM dilempar, supaya status di UI pasti berubah
            // walau `failed()` tidak sempat dipanggil (mis. worker dihentikan paksa).
            $this->tandaiGagal($e->getMessage());

            throw $e;
        }
    }

    /**
     * Dipanggil Laravel bila job benar-benar gagal (termasuk saat timeout).
     */
    public function failed(?Throwable $e): void
    {
        $this->tandaiGagal($e?->getMessage() ?? 'Impor gagal tanpa keterangan.');
    }

    private function tandaiGagal(string $pesan): void
    {
        DB::table('dev.import_statuses')
            ->where('id', $this->importId)
            ->update([
                'status' => 'failed',
                'error_message' => $pesan,
                'updated_at' => now(),
            ]);
    }
}
