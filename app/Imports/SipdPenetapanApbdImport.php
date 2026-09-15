<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\ImportFailed;

class SipdPenetapanApbdImport implements ToCollection, WithChunkReading, WithEvents, WithHeadingRow
{
    protected ?string $importId;

    protected ?int $tahun;

    protected ?string $versi;

    protected string $resolvedVersi;

    protected bool $deletedOldData = false;

    public function __construct(?string $importId = null, ?int $tahun = null, ?string $versi = null)
    {
        $this->importId = $importId;
        $this->tahun = $tahun;
        $this->versi = $versi;
        $this->resolvedVersi = $versi ?: (string) $this->resolveNextVersi($tahun);
    }

    protected function resolveNextVersi(?int $tahun): int
    {
        $tahunResolved = $tahun ?: (int) date('Y');
        $max = (int) DB::table('dev.sipd_penetapan_apbd')
            ->where('tahun', $tahunResolved)
            ->whereRaw("versi ~ '^[0-9]+$'")
            ->max(DB::raw('versi::int'));

        return $max + 1;
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function (AfterImport $event) {
                if ($this->importId) {
                    DB::table('dev.import_statuses')
                        ->where('id', $this->importId)
                        ->update(['status' => 'completed', 'updated_at' => now()]);
                }
            },
            ImportFailed::class => function (ImportFailed $event) {
                if ($this->importId) {
                    DB::table('dev.import_statuses')
                        ->where('id', $this->importId)
                        ->update([
                            'status' => 'failed',
                            'error_message' => $event->getException()->getMessage(),
                            'updated_at' => now(),
                        ]);
                }
            },
        ];
    }

    /**
     * Kolom nama pada tabel referensi bersifat NOT NULL, tetapi berkas SIPD asli
     * memang bisa memuat sel nama yang KOSONG (terbukti pada berkas APBD 2026:
     * 1.567 baris tanpa `nama_standar_harga` dan 32 baris tanpa `nama_sub_kegiatan`).
     *
     * Sebelum ini, satu saja sel kosong membuat seluruh chunk gagal dengan
     * SQLSTATE[23502] (not null violation). Karena setiap chunk punya transaksi
     * sendiri, impor pun berhenti di tengah dan meninggalkan DATA SEPARUH.
     *
     * Baris referensi juga TIDAK boleh dilewati, sebab `sipd_penetapan_apbd`
     * ber-FK ke ref_skpd / ref_sub_kegiatan / ref_standar_harga — kalau dilewati,
     * muncul error berikutnya (SQLSTATE[23503] foreign key violation).
     * Jadi nama kosong diisi cadangan, bukan dibuang.
     */
    protected function namaAtauCadangan(?string $nama, ?string $kode): string
    {
        $nama = $nama === null ? '' : trim($nama);

        return $nama !== '' ? $nama : ($kode !== null && $kode !== '' ? $kode : '(nama kosong)');
    }

    public function chunkSize(): int
    {
        // 1.000 (bukan 500): separuh jumlah chunk → separuh perjalanan bolak-balik
        // ke database + lebih sedikit upsert referensi yang berulang.
        return 1000;
    }

    protected function cleanString(mixed $val): ?string
    {
        if ($val === null) {
            return null;
        }

        $str = trim((string) $val);
        $lower = strtolower($str);

        if (in_array($lower, ['nan', 'none', 'null', ''], true)) {
            return null;
        }

        if (is_numeric($val) && (float) $val == (int) $val && ! str_contains($str, '.')) {
            return (string) ((int) $val);
        }

        return $str;
    }

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $firstRow = $rows->first();
        $tahunRow = isset($firstRow['tahun']) ? (int) $firstRow['tahun'] : null;
        $tahun = $this->tahun ?: ($tahunRow ?: (int) date('Y'));
        $versi = $this->resolvedVersi;

        $urusanList = [];
        $bidangList = [];
        $programList = [];
        $kegiatanList = [];
        $subKegiatanList = [];
        $skpdIndukList = [];
        $skpdSubUnitList = [];
        $standarHargaList = [];
        $transaksiList = [];

        foreach ($rows as $row) {
            $kodeUrusan = $this->cleanString($row['kode_urusan'] ?? null);
            $namaUrusan = $this->cleanString($row['nama_urusan'] ?? null);

            $kodeBidang = $this->cleanString($row['kode_bidang_urusan'] ?? null);
            $namaBidang = $this->cleanString($row['nama_bidang_urusan'] ?? null);

            $kodeProgram = $this->cleanString($row['kode_program'] ?? null);
            $namaProgram = $this->cleanString($row['nama_program'] ?? null);

            $kodeKegiatan = $this->cleanString($row['kode_kegiatan'] ?? null);
            $namaKegiatan = $this->cleanString($row['nama_kegiatan'] ?? null);

            $kodeSubKegiatan = $this->cleanString($row['kode_sub_kegiatan'] ?? null);
            $namaSubKegiatan = $this->cleanString($row['nama_sub_kegiatan'] ?? null);

            $kodeSkpd = $this->cleanString($row['kode_skpd'] ?? null);
            $namaSkpd = $this->cleanString($row['nama_skpd'] ?? null);

            $kodeSubUnit = $this->cleanString($row['kode_sub_unit'] ?? null);
            $namaSubUnit = $this->cleanString($row['nama_sub_unit'] ?? null);

            $kodeStandar = $this->cleanString($row['kode_standar_harga'] ?? null);
            $namaStandar = $this->cleanString($row['nama_standar_harga'] ?? null);

            if ($kodeUrusan) {
                $urusanList[$kodeUrusan] = ['kode_urusan' => $kodeUrusan, 'nama_urusan' => $this->namaAtauCadangan($namaUrusan, $kodeUrusan)];
            }

            if ($kodeBidang) {
                $bidangList[$kodeBidang] = [
                    'kode_bidang_urusan' => $kodeBidang,
                    'kode_urusan' => $kodeUrusan,
                    'nama_bidang_urusan' => $this->namaAtauCadangan($namaBidang, $kodeBidang),
                ];
            }

            if ($kodeProgram) {
                $programList[$kodeProgram] = [
                    'kode_program' => $kodeProgram,
                    'kode_bidang_urusan' => $kodeBidang,
                    'nama_program' => $this->namaAtauCadangan($namaProgram, $kodeProgram),
                ];
            }

            if ($kodeKegiatan) {
                $kegiatanList[$kodeKegiatan] = [
                    'kode_kegiatan' => $kodeKegiatan,
                    'kode_program' => $kodeProgram,
                    'nama_kegiatan' => $this->namaAtauCadangan($namaKegiatan, $kodeKegiatan),
                ];
            }

            if ($kodeSubKegiatan) {
                $subKegiatanList[$kodeSubKegiatan] = [
                    'kode_sub_kegiatan' => $kodeSubKegiatan,
                    'kode_kegiatan' => $kodeKegiatan,
                    'nama_sub_kegiatan' => $this->namaAtauCadangan($namaSubKegiatan, $kodeSubKegiatan),
                ];
            }

            if ($kodeSkpd) {
                $skpdIndukList[$kodeSkpd] = [
                    'kode_skpd' => $kodeSkpd,
                    'nama_skpd' => $this->namaAtauCadangan($namaSkpd, $kodeSkpd),
                    'parent_kode_skpd' => null,
                ];
            }

            if ($kodeSubUnit) {
                $skpdSubUnitList[$kodeSubUnit] = [
                    'kode_skpd' => $kodeSubUnit,
                    'nama_skpd' => $this->namaAtauCadangan($namaSubUnit ?: $namaSkpd, $kodeSubUnit),
                    'parent_kode_skpd' => $kodeSkpd,
                ];
            }

            if ($kodeStandar) {
                $standarHargaList[$kodeStandar] = [
                    'kode_standar_harga' => $kodeStandar,
                    'nama_standar_harga' => $this->namaAtauCadangan($namaStandar, $kodeStandar),
                ];
            }

            if (! $kodeSubKegiatan && ! $kodeStandar) {
                continue;
            }

            $rawPagu = (string) ($row['pagu'] ?? '0');
            $pagu = (float) str_replace([',', ' '], '', $rawPagu);

            $transaksiList[] = [
                'kode_daerah' => $this->cleanString($row['kode_daerah'] ?? null),
                'nama_daerah' => $this->cleanString($row['nama_daerah'] ?? null),
                'tahun' => isset($row['tahun']) && is_numeric($row['tahun']) ? (int) $row['tahun'] : $tahun,
                'kode_sub_unit' => $kodeSubUnit ?: $kodeSkpd,
                'kode_sub_kegiatan' => $kodeSubKegiatan,
                'kode_standar_harga' => $kodeStandar,
                'kode_rekening' => $this->cleanString($row['kode_rekening'] ?? null),
                'kode_sumber_dana' => $this->cleanString($row['kode_sumber_dana'] ?? null),
                'nama_sumber_dana' => $this->cleanString($row['nama_sumber_dana'] ?? null),
                'pagu' => $pagu,
                'versi' => $versi,
                'created_at' => now(),
            ];
        }

        DB::transaction(function () use (
            $urusanList,
            $bidangList,
            $programList,
            $kegiatanList,
            $subKegiatanList,
            $skpdIndukList,
            $skpdSubUnitList,
            $standarHargaList,
            $transaksiList,
            $tahun,
            $versi
        ) {
            // 1. Upsert tabel-tabel master referensi
            if (! empty($urusanList)) {
                DB::table('dev.ref_urusan')->upsert(array_values($urusanList), ['kode_urusan'], ['nama_urusan']);
            }

            if (! empty($bidangList)) {
                DB::table('dev.ref_bidang_urusan')->upsert(
                    array_values($bidangList),
                    ['kode_bidang_urusan'],
                    ['kode_urusan', 'nama_bidang_urusan']
                );
            }

            if (! empty($programList)) {
                DB::table('dev.ref_program')->upsert(
                    array_values($programList),
                    ['kode_program'],
                    ['kode_bidang_urusan', 'nama_program']
                );
            }

            if (! empty($kegiatanList)) {
                DB::table('dev.ref_kegiatan')->upsert(
                    array_values($kegiatanList),
                    ['kode_kegiatan'],
                    ['kode_program', 'nama_kegiatan']
                );
            }

            if (! empty($subKegiatanList)) {
                DB::table('dev.ref_sub_kegiatan')->upsert(
                    array_values($subKegiatanList),
                    ['kode_sub_kegiatan'],
                    ['kode_kegiatan', 'nama_sub_kegiatan']
                );
            }

            if (! empty($skpdIndukList)) {
                DB::table('dev.ref_skpd')->upsert(
                    array_values($skpdIndukList),
                    ['kode_skpd'],
                    ['nama_skpd', 'parent_kode_skpd']
                );
            }

            if (! empty($skpdSubUnitList)) {
                DB::table('dev.ref_skpd')->upsert(
                    array_values($skpdSubUnitList),
                    ['kode_skpd'],
                    ['nama_skpd', 'parent_kode_skpd']
                );
            }

            if (! empty($standarHargaList)) {
                DB::table('dev.ref_standar_harga')->upsert(
                    array_values($standarHargaList),
                    ['kode_standar_harga'],
                    ['nama_standar_harga']
                );
            }

            // 2. Hapus data versi yang sama (hanya sekali di chunk pertama)
            if (! $this->deletedOldData) {
                DB::table('dev.sipd_penetapan_apbd')
                    ->where('tahun', $tahun)
                    ->where('versi', $versi)
                    ->delete();
                $this->deletedOldData = true;
            }

            // 3. Masukkan data transaksi
            if (! empty($transaksiList)) {
                DB::table('dev.sipd_penetapan_apbd')->insert($transaksiList);
            }
        });
    }
}
