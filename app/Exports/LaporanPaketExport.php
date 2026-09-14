<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export laporan paket per cara pengadaan (Penyedia / Swakelola).
 *
 * Struktur PERSIS template Laporan.xlsx:
 * - Baris 1-2 : judul & sub judul laporan
 * - Baris 3-4 : header 2 baris dengan merge sesuai template
 * - Baris 5+  : data, SATU BARIS PER REKENING (MAK).
 *   Kolom "Pagu" = pagu rekening baris itu, "Total Pagu" = total paket
 *   (diulang di tiap baris rekening paket yang sama), "No" diulang per paket.
 *
 * Penyedia  = 26 kolom (A..Z) · Swakelola = 15 kolom (A..O).
 */
class LaporanPaketExport implements FromArray, WithStyles
{
    private const BULAN = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];

    /**
     * @param  array<string, mixed>  $data  hasil LaporanController::paketPerCara()
     * @param  string  $cara  'penyedia' | 'swakelola'
     */
    public function __construct(private array $data, private string $cara)
    {
    }

    private function isPenyedia(): bool
    {
        return $this->cara === 'penyedia';
    }

    /** Kolom terakhir: Z (26 kolom) untuk Penyedia, O (15 kolom) untuk Swakelola. */
    public function lastColumn(): string
    {
        return $this->isPenyedia() ? 'Z' : 'O';
    }

    /**
     * Waktu format bulan-tahun Indonesia, mis. "Okt 2026".
     * Mengikuti pemilih bulan/tahun di form (WaktuPicker).
     */
    private function fmtWaktu(mixed $v): string
    {
        if (! $v) {
            return '';
        }
        $ts = strtotime((string) $v);
        if ($ts === false) {
            return (string) $v;
        }

        return (self::BULAN[(int) date('n', $ts)] ?? date('M', $ts)).' '.date('Y', $ts);
    }

    /** Rantai hierarki: OPD / Sub Unit / Program / Kegiatan / Sub Kegiatan / Paket. */
    private function rantai(array $r): string
    {
        return implode(' / ', array_filter([
            $r['nama_opd'] ?? '',
            $r['nama_skpd'] ?? '',
            $r['nama_program'] ?? '',
            $r['nama_kegiatan'] ?? '',
            $r['nama_sub_kegiatan'] ?? '',
            $r['nama_paket'] ?? '',
        ]));
    }

    /** Volume + satuan jadi satu kolom ("3 Unit") sesuai template. */
    private function volume(array $r): string
    {
        $v = (float) ($r['volume'] ?? 0);
        $angka = $v == floor($v) ? number_format($v, 0, ',', '.') : number_format($v, 2, ',', '.');
        $satuan = trim((string) ($r['volume_satuan'] ?? ''));

        return trim($angka.' '.$satuan);
    }

    /**
     * Nilai numerik — ditulis "0" sebagai teks agar tidak dianggap null oleh
     * Maatwebsite (0.0 == null pada loose comparison PHP).
     */
    private function angka(mixed $v): float|string
    {
        $f = (float) $v;

        return $f == 0 ? '0' : $f;
    }

    /**
     * Urutkan paket mengikuti hierarki supaya kolom rantai terbaca menurun.
     *
     * @return list<array<string, mixed>>
     */
    private function urut(): array
    {
        $rincian = array_values((array) ($this->data['rincian'] ?? []));

        usort($rincian, function ($a, $b) {
            return [
                $a['nama_opd'] ?? '', $a['nama_skpd'] ?? '', $a['nama_program'] ?? '',
                $a['nama_kegiatan'] ?? '', $a['nama_sub_kegiatan'] ?? '', $a['nama_paket'] ?? '',
            ] <=> [
                $b['nama_opd'] ?? '', $b['nama_skpd'] ?? '', $b['nama_program'] ?? '',
                $b['nama_kegiatan'] ?? '', $b['nama_sub_kegiatan'] ?? '', $b['nama_paket'] ?? '',
            ];
        });

        return $rincian;
    }

    /**
     * @return list<array<int, mixed>>
     */
    public function array(): array
    {
        $rows = $this->headerRows();
        $cell = fn ($f) => ((float) $f) == 0 ? '0' : $f;

        $no = 0;
        foreach ($this->urut() as $r) {
            $no++;

            $makList = array_values((array) ($r['mak'] ?? []));
            if (empty($makList)) {
                $makList = [['kode_rekening' => '', 'pagu' => 0.0]];
            }

            $totalPagu = $this->angka($r['total_pagu'] ?? 0);
            $pemanfaatanMulai = $this->fmtWaktu($r['waktu_pemanfaatan_awal'] ?? null);
            $pemanfaatanDari = $this->fmtWaktu($r['waktu_pemanfaatan_akhir'] ?? null);
            $pelaksanaanMulai = $this->fmtWaktu($r['waktu_pelaksanaan_awal'] ?? null);
            $pelaksanaanDari = $this->fmtWaktu($r['waktu_pelaksanaan_akhir'] ?? null);

            foreach ($makList as $m) {
                $base = [
                    $no,
                    $this->rantai($r),
                    (string) ($r['lokasi_provinsi'] ?? ''),
                    (string) ($r['lokasi_kabupaten'] ?? ''),
                    (string) ($r['lokasi_detail'] ?? ''),
                    $this->volume($r),
                    (string) ($r['uraian'] ?? ''),
                    (string) ($r['spesifikasi'] ?? ''),
                ];

                if ($this->isPenyedia()) {
                    $rows[] = array_merge($base, [
                        (string) ($r['pdn'] ?? ''),
                        (string) ($r['usaha_kecil'] ?? ''),
                        (string) ($r['spp_ekonomi'] ?? ''),
                        (string) ($r['spp_sosial'] ?? ''),
                        (string) ($r['spp_lingkungan'] ?? ''),
                        (string) ($r['pra_dpa'] ?? ''),
                        (string) ($r['metode_pengadaan'] ?? ''),
                        (string) ($r['tersedia_ekatalog'] ?? ''),
                        (string) ($r['sumber_dana'] ?? ''),
                        (string) ($m['kode_rekening'] ?? ''),
                        $cell($m['pagu'] ?? 0),
                        $totalPagu,
                        $pemanfaatanMulai,
                        $pemanfaatanDari,
                        $pelaksanaanMulai,
                        $pelaksanaanDari,
                        $this->fmtWaktu($r['waktu_pemilihan_awal'] ?? null),
                        $this->fmtWaktu($r['waktu_pemilihan_akhir'] ?? null),
                    ]);
                } else {
                    $rows[] = array_merge($base, [
                        (string) ($r['tipe_swakelola'] ?? ''),
                        (string) ($r['sumber_dana'] ?? ''),
                        (string) ($m['kode_rekening'] ?? ''),
                        $cell($m['pagu'] ?? 0),
                        $totalPagu,
                        $pelaksanaanMulai,
                        $pelaksanaanDari,
                    ]);
                }
            }
        }

        // ---- Baris TOTAL ----
        $totalPaguSemua = 0.0;
        foreach (($this->data['rincian'] ?? []) as $r) {
            $totalPaguSemua += (float) ($r['total_pagu'] ?? 0);
        }

        if ($this->isPenyedia()) {
            $rows[] = array_merge(
                ['TOTAL KESELURUHAN', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', $cell($totalPaguSemua), '', '', '', '', '', ''],
            );
        } else {
            $rows[] = ['TOTAL KESELURUHAN', '', '', '', '', '', '', '', '', '', '', '', $cell($totalPaguSemua), '', ''];
        }

        return $rows;
    }

    /**
     * Baris 1-2 (judul) + baris 3-4 (header 2 baris, mengikuti template).
     *
     * @return list<array<int, mixed>>
     */
    private function headerRows(): array
    {
        $tahun = $this->data['tahun'] ?? date('Y');
        // Tanda hubung biasa (bukan em dash) supaya tidak rusak saat dibuka Excel.
        $judul = $this->isPenyedia()
            ? 'LAPORAN RENCANA KEBUTUHAN PENGADAAN - PENYEDIA'
            : 'LAPORAN RENCANA KEBUTUHAN PENGADAAN - SWAKELOLA';

        $lebar = $this->isPenyedia() ? 26 : 15;
        $kosong = array_fill(0, $lebar, '');

        $baris1 = $kosong;
        $baris1[0] = $judul;
        $baris2 = $kosong;
        $baris2[0] = "PEMERINTAH PROVINSI SUMATERA BARAT - TAHUN ANGGARAN {$tahun}";

        if ($this->isPenyedia()) {
            $baris3 = [
                'No',
                'OPD/ Sub Unit/ Program/ Kegiatan/ Sub Kegiatan/ Paket',
                'Lokasi', '', '',
                'Volume',
                'Uraian Pekerjaan',
                'Spesifikasi Pekerjaan',
                'PDN',
                'Usaha Kecil',
                'Sustainable Public Procurement (SPP)', '', '',
                'Pra DIPA/ DPA (Y/T)',
                'Metode',
                'Ketersediaan e-Katalog (Y/T)',
                'Sumber Dana',
                'MAK',
                'Pagu',
                'Total Pagu',
                'Pemanfaatan Barang/Jasa', '',
                'Pelaksanaan Kontrak', '',
                'Pemilihan Penyedia', '',
            ];
            $baris4 = [
                '', '',
                'Provinsi', 'Kab/Kota', 'Detil Lokasi',
                '', '', '', '', '',
                'Ekonomi (Y/T)', 'Sosial (Y/T)', 'Lingkungan (Y/T)',
                '', '', '', '', '', '', '',
                'Mulai', 'Dari', 'Mulai', 'Dari', 'Mulai', 'Dari',
            ];
        } else {
            $baris3 = [
                'No',
                'OPD/ Sub Unit/ Program/ Kegiatan/ Sub Kegiatan/ Paket',
                'Lokasi', '', '',
                'Volume',
                'Uraian Pekerjaan',
                'Spesifikasi Pekerjaan',
                'Tipe',
                'Sumber Dana',
                'MAK',
                'Pagu',
                'Total Pagu',
                'Pelaksanaan Kontrak', '',
            ];
            $baris4 = [
                '', '',
                'Provinsi', 'Kab/Kota', 'Detil Lokasi',
                '', '', '', '', '', '', '', '',
                'Mulai', 'Dari',
            ];
        }

        return [$baris1, $baris2, $baris3, $baris4];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        $lastCol = $this->lastColumn();
        $lastRow = $sheet->getHighestRow();
        $isPenyedia = $this->isPenyedia();

        // --- Merge judul + header 2 baris (sesuai template) ---
        $merges = $isPenyedia
            ? ['A1:Z1', 'A2:Z2', 'A3:A4', 'B3:B4', 'C3:E3', 'F3:F4', 'G3:G4', 'H3:H4', 'I3:I4', 'J3:J4',
                'K3:M3', 'N3:N4', 'O3:O4', 'P3:P4', 'Q3:Q4', 'R3:R4', 'S3:S4', 'T3:T4',
                'U3:V3', 'W3:X3', 'Y3:Z3']
            : ['A1:O1', 'A2:O2', 'A3:A4', 'B3:B4', 'C3:E3', 'F3:F4', 'G3:G4', 'H3:H4', 'I3:I4',
                'J3:J4', 'K3:K4', 'L3:L4', 'M3:M4', 'N3:O3'];
        foreach ($merges as $range) {
            $sheet->mergeCells($range);
        }

        // --- Judul (baris 1-2) ---
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '000000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '000000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(18);

        // --- Header 2 baris: latar putih, teks hitam tebal (rapi & nyaman dibaca) ---
        $sheet->getStyle("A3:{$lastCol}4")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '000000']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getStyle('B3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getRowDimension(3)->setRowHeight(32);
        $sheet->getRowDimension(4)->setRowHeight(24);

        // --- Border hitam tipis semua sel ---
        $sheet->getStyle("A1:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
        ]);

        // --- Kolom uang & angka rata kanan; zebra mulai baris 5 ---
        $moneyCols = $isPenyedia ? ['S', 'T'] : ['L', 'M'];
        for ($r = 5; $r <= $lastRow; $r++) {
            if ($r % 2 === 1) {
                $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F2F2');
            }
            foreach ($moneyCols as $col) {
                $sheet->getStyle("{$col}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            $sheet->getStyle("F{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension($r)->setRowHeight(-1);
        }

        foreach ($moneyCols as $col) {
            $sheet->getStyle("{$col}5:{$col}{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
        }

        // --- Baris TOTAL: abu tebal ---
        $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '000000']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
        ]);
        $sheet->getStyle("A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getRowDimension($lastRow)->setRowHeight(20);

        // --- Freeze & cetak A4 landscape fit-to-width ---
        $sheet->freezePane('A5');
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()->setLeft(0.4)->setRight(0.4)->setTop(0.6)->setBottom(0.6);

        // --- Lebar kolom ---
        $widths = $isPenyedia
            ? ['A' => 4.5, 'B' => 46, 'C' => 16, 'D' => 16, 'E' => 22, 'F' => 10, 'G' => 30, 'H' => 30,
                'I' => 8, 'J' => 9, 'K' => 9, 'L' => 9, 'M' => 10, 'N' => 12, 'O' => 14, 'P' => 13,
                'Q' => 18, 'R' => 20, 'S' => 16, 'T' => 16, 'U' => 11, 'V' => 11, 'W' => 11, 'X' => 11, 'Y' => 11, 'Z' => 11]
            : ['A' => 4.5, 'B' => 46, 'C' => 16, 'D' => 16, 'E' => 22, 'F' => 10, 'G' => 30, 'H' => 30,
                'I' => 14, 'J' => 18, 'K' => 20, 'L' => 16, 'M' => 16, 'N' => 11, 'O' => 11];

        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        return [];
    }
}
