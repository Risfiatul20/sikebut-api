<?php

namespace App\Imports;

use DB;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\ImportFailed;

// Catatan: sengaja TIDAK mengimplementasikan ShouldQueue — impor berjalan sinkron
// (inline) karena environment dev tidak menjalankan worker queue.
class SipdPenetapanApbdImport implements ToCollection, WithChunkReading, WithEvents, WithHeadingRow
{
    protected $importId;

    protected $tahun;

    protected $namaVersi;

    public function __construct(string $importId, ?int $tahun = null, ?string $namaVersi = null)
    {
        $this->importId = $importId;
        $this->tahun = $tahun;
        $this->namaVersi = $namaVersi;
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

    public function chunkSize(): int
    {
        return 500;
    }

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        // Tentukan tahun file (prioritas dari form impor, fallback ke baris pertama)
        $tahun = $this->tahun ?: (int) ($rows->first()['tahun'] ?? now()->year);

        // Nomor versi baru = max versi numerik tahun bersangkutan + 1
        $nextVersi = (int) DB::table('dev.sipd_penetapan_apbd')
            ->where('tahun', $tahun)
            ->whereRaw("versi ~ '^[0-9]+$'")
            ->max(DB::raw('versi::int'));
        $nextVersi++;

        $namaVersi = $this->namaVersi ?: 'Versi '.$nextVersi.' - Penetapan APBD '.$tahun;

        $now = now();
        $dataToInsert = [];

        foreach ($rows as $row) {
            $kodeSubKegiatan = isset($row['kode_sub_kegiatan']) ? (string) $row['kode_sub_kegiatan'] : '';
            $kodeStandar = isset($row['kode_standar_harga']) ? (string) $row['kode_standar_harga'] : '';

            // Lewati baris kosong / bukan baris data
            if ($kodeSubKegiatan === '' && $kodeStandar === '') {
                continue;
            }

            $rawPagu = isset($row['pagu']) ? (string) $row['pagu'] : '0';

            $dataToInsert[] = [
                'kode_daerah' => isset($row['kode_daerah']) ? (string) $row['kode_daerah'] : null,
                'nama_daerah' => isset($row['nama_daerah']) ? (string) $row['nama_daerah'] : null,
                'tahun' => isset($row['tahun']) ? (int) $row['tahun'] : $tahun,
                'kode_sub_unit' => isset($row['kode_sub_unit']) && $row['kode_sub_unit'] !== ''
                    ? (string) $row['kode_sub_unit']
                    : (isset($row['kode_skpd']) ? (string) $row['kode_skpd'] : null),
                'kode_sub_kegiatan' => $kodeSubKegiatan,
                'kode_standar_harga' => $kodeStandar,
                'kode_rekening' => isset($row['kode_rekening']) ? (string) $row['kode_rekening'] : null,
                'kode_sumber_dana' => isset($row['kode_sumber_dana']) ? (string) $row['kode_sumber_dana'] : null,
                'nama_sumber_dana' => isset($row['nama_sumber_dana']) ? (string) $row['nama_sumber_dana'] : null,
                'pagu' => (float) str_replace([',', ' '], '', $rawPagu),
                'versi' => (string) $nextVersi,
                'created_at' => $now,
            ];
        }

        if (! empty($dataToInsert)) {
            DB::table('dev.sipd_penetapan_apbd')->insert($dataToInsert);
        }
    }
}