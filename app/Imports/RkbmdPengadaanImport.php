<?php

namespace App\Imports;

use App\Models\RkbmdPengadaan;
use DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\ImportFailed;

class RkbmdPengadaanImport implements ShouldQueue, ToCollection, WithChunkReading, WithEvents, WithHeadingRow
{
    protected $importId;

    public function __construct(string $importId)
    {
        $this->importId = $importId;
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

    public function collection(Collection $rows): void
    {
        $dataToUpsert = [];

        foreach ($rows as $row) {
            if (empty($row['id_pengadaan'])) {
                continue;
            }

            $dataToUpsert[] = [
                'id_pengadaan' => $row['id_pengadaan'],
                'id_instansi' => $row['id_instansi'] ?? null,
                'id_renja' => $row['id_renja'] ?? null,
                'kode_fikasi' => isset($row['kode_fikasi']) ? (string) $row['kode_fikasi'] : null,
                'nama_barang' => $row['nama_barang'] ?? null,
                'jumlah_barang' => $row['jumlah_barang'] ?? 0,
                'satuan' => $row['satuan'] ?? null,
                'jumlah_maksimum' => $row['jumlah_maksimum'] ?? 0,
                'keterangan' => $row['keterangan'] ?? null,
                'id_status' => $row['id_status'] ?? null,
                'periode' => $row['periode'] ?? null,
                'nm_status' => $row['nm_status'] ?? null,
                'cara_pemenuhan' => $row['cara_pemenuhan'] ?? null,
                'target' => isset($row['target']) ? (string) $row['target'] : null,
                'nama_giat_nama_giat' => $row['nama_giat_nama_giat'] ?? null,
                'nama_sub_giat_nama_sub_giat' => $row['nama_sub_giat_nama_sub_giat'] ?? null,
                'id_kebutuhan' => $row['id_kebutuhan'] ?? null,
                'id_sub' => $row['id_sub'] ?? null,
                'nomekelatur' => isset($row['nomekelatur']) ? (string) $row['nomekelatur'] : null,
                'outputbaru' => isset($row['outputbaru']) ? (string) $row['outputbaru'] : null,
                'id_status_kebutuhan' => $row['id_status_kebutuhan'] ?? null,
                'catatan_notulen' => $row['catatan_notulen'] ?? null,
                'kode_program' => isset($row['kode_program']) ? (string) $row['kode_program'] : null,
                'kode_giat' => isset($row['kode_giat']) ? (string) $row['kode_giat'] : null,
                'kode_sub_giat' => isset($row['kode_sub_giat']) ? (string) $row['kode_sub_giat'] : null,
                'status_barang_ds' => $row['status_barang_ds'] ?? 0,
                'status_barang_pp' => $row['status_barang_pp'] ?? 0,
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
