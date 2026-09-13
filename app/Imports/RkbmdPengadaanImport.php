<?php

namespace App\Imports;

use App\Models\RkbmdPengadaan;
use DB;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\ImportFailed;

// Catatan: sengaja TIDAK mengimplementasikan ShouldQueue — impor berjalan sinkron
// (inline) karena environment dev tidak menjalankan worker queue. Kalau class ini
// mengimplementasikan ShouldQueue, Maatwebsite tetap meng-queue chunk-nya walau
// dipanggil lewat Excel::import (lihat ChunkReader::read).
class RkbmdPengadaanImport implements ToCollection, WithChunkReading, WithEvents, WithHeadingRow
{
    protected $importId;

    protected $nextGeneratedId;

    public function __construct(string $importId)
    {
        $this->importId = $importId;
        // ID auto-generate dipakai saat file tidak menyediakan kolom id_pengadaan.
        // Basis: ID tertinggi yang pernah ada, lalu berjalan menurun (negatif)
        // supaya tidak bentrok dengan data asli dari SIPD yang selalu positif.
        $max = (int) DB::table('dev.rkbmd_pengadaan')->max('id_pengadaan');
        $this->nextGeneratedId = -($max + 1);
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function (AfterImport $event) {
                DB::table('dev.import_statuses')
                    ->where('id', $this->importId)
                    ->update(['status' => 'completed', 'updated_at' => now()]);
            },
            ImportFailed::class => function (ImportFailed $event) {
                DB::table('dev.import_statuses')
                    ->where('id', $this->importId)
                    ->update([
                        'status' => 'failed',
                        'error_message' => $event->getException()->getMessage(),
                        'updated_at' => now(),
                    ]);
            },
        ];
    }

    /**
     * Bersihkan nilai kolom numerik yang sering terisi format Indonesia
     * ("1.070", "1.070,00", "10 unit", spasi) menjadi integer murni.
     * Mencegah error PostgreSQL SQLSTATE[22P02] saat insert/upsert.
     */
    protected function cleanInt($value, int $default = 0): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        // Format "1.070" → 1070 · "1.070,00" → 1070 · "10 unit" → 10 · "Rp 5.000" → 5000
        $clean = (string) $value;
        $clean = trim(str_replace(['Rp ', 'rp ', ' '], '', $clean));
        $clean = str_replace('.', '', $clean);      // pemisah ribuan
        $clean = str_replace(',', '.', $clean);     // koma desimal → titik

        // Ambil angka di awal string ("10 unit" → 10)
        if (preg_match('/^-?\d+(\.\d+)?/', $clean, $m)) {
            return (int) round((float) $m[0]);
        }

        return $default;
    }

    public function collection(Collection $rows): void
    {
        $dataToUpsert = [];

        foreach ($rows as $row) {
            // Baris kosong total → lewati
            if (empty($row['nama_barang']) && empty($row['kode_fikasi'])) {
                continue;
            }

            // Auto-generate ID bila file tidak menyediakan kolom id_pengadaan
            $idPengadaan = $this->cleanInt($row['id_pengadaan'] ?? null, 0);
            if ($idPengadaan <= 0) {
                $idPengadaan = $this->nextGeneratedId--;
            }

            $dataToUpsert[] = [
                'id_pengadaan' => $idPengadaan,
                'id_instansi' => $this->cleanInt($row['id_instansi'] ?? null, 0) ?: null,
                'id_renja' => $this->cleanInt($row['id_renja'] ?? null, 0) ?: null,
                'kode_fikasi' => isset($row['kode_fikasi']) ? (string) $row['kode_fikasi'] : null,
                'nama_barang' => $row['nama_barang'] ?? null,
                'jumlah_barang' => $this->cleanInt($row['jumlah_barang'] ?? null, 0),
                'satuan' => $row['satuan'] ?? null,
                'jumlah_maksimum' => $this->cleanInt($row['jumlah_maksimum'] ?? null, 0),
                'keterangan' => $row['keterangan'] ?? null,
                'id_status' => $this->cleanInt($row['id_status'] ?? null, 0) ?: null,
                'periode' => $this->cleanInt($row['periode'] ?? null, 2026),
                'nm_status' => $row['nm_status'] ?? null,
                'cara_pemenuhan' => $row['cara_pemenuhan'] ?? null,
                'target' => isset($row['target']) ? (string) $row['target'] : null,
                'nama_giat_nama_giat' => $row['nama_giat_nama_giat'] ?? null,
                'nama_sub_giat_nama_sub_giat' => $row['nama_sub_giat_nama_sub_giat'] ?? null,
                'id_kebutuhan' => $this->cleanInt($row['id_kebutuhan'] ?? null, 0) ?: null,
                'id_sub' => $this->cleanInt($row['id_sub'] ?? null, 0) ?: null,
                'nomekelatur' => isset($row['nomekelatur']) ? (string) $row['nomekelatur'] : null,
                'outputbaru' => isset($row['outputbaru']) ? (string) $row['outputbaru'] : null,
                'id_status_kebutuhan' => $this->cleanInt($row['id_status_kebutuhan'] ?? null, 0) ?: null,
                'catatan_notulen' => $row['catatan_notulen'] ?? null,
                'kode_program' => isset($row['kode_program']) ? (string) $row['kode_program'] : null,
                'kode_giat' => isset($row['kode_giat']) ? (string) $row['kode_giat'] : null,
                'kode_sub_giat' => isset($row['kode_sub_giat']) ? (string) $row['kode_sub_giat'] : null,
                'status_barang_ds' => $this->cleanInt($row['status_barang_ds'] ?? null, 0),
                'status_barang_pp' => $this->cleanInt($row['status_barang_pp'] ?? null, 0),
                'nama_program' => $row['nama_program'] ?? null,
                'nama_skpd' => $row['nama_skpd'] ?? null,
                'nama_sub_skpd' => $row['nama_sub_skpd'] ?? null,
                'kode_skpd' => isset($row['kode_skpd']) ? (string) $row['kode_skpd'] : null,
                'kode_sub_skpd' => isset($row['kode_sub_skpd']) ? (string) $row['kode_sub_skpd'] : null,
            ];
        }

        if (! empty($dataToUpsert)) {
            // Jalankan 1 query INSERT/UPDATE batch per chunk
            RkbmdPengadaan::upsert(
                $dataToUpsert,
                ['id_pengadaan'], // Primary key pengecekan duplikat
                [
                    'id_instansi', 'id_renja', 'kode_fikasi', 'nama_barang', 'jumlah_barang',
                    'satuan', 'jumlah_maksimum', 'keterangan', 'id_status', 'periode',
                    'nm_status', 'cara_pemenuhan', 'target', 'nama_giat_nama_giat',
                    'nama_sub_giat_nama_sub_giat', 'id_kebutuhan', 'id_sub', 'nomekelatur',
                    'outputbaru', 'id_status_kebutuhan', 'catatan_notulen', 'kode_program',
                    'kode_giat', 'kode_sub_giat', 'status_barang_ds', 'status_barang_pp',
                    'nama_program', 'nama_skpd', 'nama_sub_skpd', 'kode_skpd', 'kode_sub_skpd',
                ]
            );
        }
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
