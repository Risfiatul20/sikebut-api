<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LaporanRekapExport implements FromArray, WithStyles
{
    /**
     * Baris sheet (data mulai baris 6) => level hierarki (1-5). Baris total = 0.
     *
     * @var array<int, int>
     */
    private array $rowLevels = [];

    /**
     * @param  array<string, mixed>  $data  hasil dari LaporanController::rekap()
     */
    public function __construct(private array $data) {}

    /**
     * Baris 1-2 = judul & sub-judul laporan, baris 3-5 = header 3 baris
     * (merge mengikuti template Laporan.xlsx sheet "Rekap"), lalu baris data
     * tree 5 level + baris TOTAL KESELURUHAN. Total 12 kolom:
     * No | Nama hierarki | Pagu | Belanja Non Pengadaan | Belanja Pengadaan |
     * Identifikasi Kebutuhan (Jumlah/Penyedia/Swakelola x Paket/Pagu) | Keterisian.
     *
     * @return list<array<int, mixed>>
     */
    public function array(): array
    {
        $tahun = $this->data['tahun'] ?? date('Y');

        $rows = [
            // Baris 1 — Judul laporan
            ['LAPORAN REKAPITULASI IDENTIFIKASI KEBUTUHAN PENGADAAN', '', '', '', '', '', '', '', '', '', '', ''],
            // Baris 2 — Sub judul
            ["PEMERINTAH PROVINSI SUMATERA BARAT - TAHUN ANGGARAN {$tahun}", '', '', '', '', '', '', '', '', '', '', ''],
            // Baris 3 — label grup
            [
                'No',
                'OPD/ Sub Unit/ Program/ Kegiatan/ Sub Kegiatan',
                'Pagu',
                'Belanja Non Pengadaan',
                'Belanja Pengadaan',
                'Identifikasi Kebutuhan', '', '', '', '', '',
                'Keterisian',
            ],
            // Baris 4 — sub-grup Identifikasi Kebutuhan
            ['', '', '', '', '', 'Jumlah', '', 'Penyedia', '', 'Swakelola', '', ''],
            // Baris 5 — sub-sub Paket/Pagu
            ['', '', '', '', '', 'Paket', 'Pagu', 'Paket', 'Pagu', 'Paket', 'Pagu', ''],
        ];

        // Maatwebsite menganggap 0.0 == null (loose comparison) sehingga sel bernilai 0
        // tidak ditulis. Helper ini memaksa nilai nol ditulis sebagai string '0'.
        $cell = fn ($f) => ((float) $f) == 0 ? '0' : $f;

        $rowIndex = 6;
        $this->rowLevels = [];

        $walk = function (array $node) use (&$rows, &$walk, $cell, &$rowIndex): void {
            $rows[] = [
                $node['no'] ?? '',
                $node['nama'],
                $cell($node['pagu'] ?? 0),
                $cell($node['belanjaNonPengadaan'] ?? 0),
                $cell($node['belanjaPengadaan'] ?? 0),
                $cell($node['identifikasi']['jumlah']['paket'] ?? 0),
                $cell($node['identifikasi']['jumlah']['pagu'] ?? 0),
                $cell($node['identifikasi']['penyedia']['paket'] ?? 0),
                $cell($node['identifikasi']['penyedia']['pagu'] ?? 0),
                $cell($node['identifikasi']['swakelola']['paket'] ?? 0),
                $cell($node['identifikasi']['swakelola']['pagu'] ?? 0),
                $cell($node['keterisian'] ?? 0),
            ];
            $this->rowLevels[$rowIndex] = $node['level'] ?? 1;
            $rowIndex++;

            foreach (($node['children'] ?? []) as $child) {
                $walk($child);
            }
        };

        foreach (($this->data['tree'] ?? []) as $opd) {
            $walk($opd);
        }

        // ---- Baris TOTAL KESELURUHAN (seperti preview web) ----
        $tot = [
            'pagu' => 0.0, 'non' => 0.0, 'peng' => 0.0,
            'jPaket' => 0, 'jPagu' => 0.0,
            'pPaket' => 0, 'pPagu' => 0.0,
            'sPaket' => 0, 'sPagu' => 0.0,
        ];
        foreach (($this->data['tree'] ?? []) as $opd) {
            $tot['pagu'] += $opd['pagu'] ?? 0;
            $tot['non'] += $opd['belanjaNonPengadaan'] ?? 0;
            $tot['peng'] += $opd['belanjaPengadaan'] ?? 0;
            $tot['jPaket'] += $opd['identifikasi']['jumlah']['paket'] ?? 0;
            $tot['jPagu'] += $opd['identifikasi']['jumlah']['pagu'] ?? 0;
            $tot['pPaket'] += $opd['identifikasi']['penyedia']['paket'] ?? 0;
            $tot['pPagu'] += $opd['identifikasi']['penyedia']['pagu'] ?? 0;
            $tot['sPaket'] += $opd['identifikasi']['swakelola']['paket'] ?? 0;
            $tot['sPagu'] += $opd['identifikasi']['swakelola']['pagu'] ?? 0;
        }
        $keterisian = $tot['peng'] > 0 ? round(min(100, ($tot['jPagu'] / $tot['peng']) * 100), 2) : 0.0;

        $rows[] = [
            'TOTAL KESELURUHAN', '',
            $cell($tot['pagu']), $cell($tot['non']), $cell($tot['peng']),
            $cell($tot['jPaket']), $cell($tot['jPagu']),
            $cell($tot['pPaket']), $cell($tot['pPagu']),
            $cell($tot['sPaket']), $cell($tot['sPagu']),
            $cell($keterisian),
        ];
        $this->rowLevels[$rowIndex] = 0; // total tidak ikut grouping

        return $rows;
    }

    /**
     * Styling hitam-putih formal + bertingkat seperti preview web:
     * judul laporan di atas, header 3 baris merged (latar putih, teks hitam tebal),
     * border hitam tipis semua sel, zebra abu sangat muda, format angka uang ribuan,
     * freeze di bawah header, A4 landscape fit-to-width, outline grouping per level
     * hierarki (default tertutup — hanya OPD tampil, bisa dibuka/tutup dengan +/−)
     * + indentasi nama per level + baris TOTAL KESELURUHAN.
     *
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = 'L';

        // --- Merge judul + header 3 baris ---
        foreach (['A1:L1', 'A2:L2', 'A3:A5', 'B3:B5', 'C3:C5', 'D3:D5', 'E3:E5', 'F3:K3', 'F4:G4', 'H4:I4', 'J4:K4', 'L3:L5'] as $range) {
            $sheet->mergeCells($range);
        }

        // --- Judul laporan (baris 1-2) ---
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

        // --- Header 3 baris: latar putih, teks hitam tebal ---
        $sheet->getStyle("A3:{$lastCol}5")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '000000']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getStyle('B3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getRowDimension(3)->setRowHeight(34);
        $sheet->getRowDimension(4)->setRowHeight(22);
        $sheet->getRowDimension(5)->setRowHeight(22);

        // --- Border hitam tipis semua sel ---
        $sheet->getStyle("A1:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
        ]);

        // --- Zebra data (mulai baris 6) + angka uang rata kanan + tinggi baris auto ---
        $moneyCols = ['C', 'D', 'E', 'G', 'I', 'K'];
        $countCols = ['F', 'H', 'J'];
        for ($r = 6; $r <= $lastRow; $r++) {
            if ($r % 2 === 0) {
                $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F2F2');
            }
            foreach ($moneyCols as $col) {
                $sheet->getStyle("{$col}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            foreach ($countCols as $col) {
                $sheet->getStyle("{$col}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            $sheet->getStyle("L{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            // Tinggi baris auto agar teks panjang (nama hierarki) tampil penuh
            $sheet->getRowDimension($r)->setRowHeight(-1);
        }

        // --- Format angka: uang ribuan, keterisian 2 desimal ---
        foreach ($moneyCols as $col) {
            $sheet->getStyle("{$col}6:{$col}{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
        }
        $sheet->getStyle("L6:L{$lastRow}")->getNumberFormat()->setFormatCode('0.00');

        // --- Baris TOTAL KESELURUHAN (abu muda tebal, merged A:B) ---
        $sheet->mergeCells("A{$lastRow}:B{$lastRow}");
        $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '000000']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
        ]);
        $sheet->getStyle("A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("C{$lastRow}:{$lastCol}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($lastRow)->setRowHeight(20);

        // --- Outline grouping bertingkat (seperti tree web) ---
        // Level 1 (OPD) tampil; level 2-5 default tertutup (hidden) + tombol +/−
        // untuk membuka/menutup per level; indentasi nama sesuai level.
        $sheet->setShowSummaryBelow(false);
        $rowNumbers = array_keys($this->rowLevels);
        foreach ($rowNumbers as $i => $r) {
            $lvl = $this->rowLevels[$r];
            // Akses dua langkah (nested ?? tetap memunculkan warning di PHP 8.3)
            $nextKey = $rowNumbers[$i + 1] ?? null;
            $nextLvl = $nextKey === null ? 0 : ($this->rowLevels[$nextKey] ?? 0);
            if ($lvl >= 1) {
                $rd = $sheet->getRowDimension($r);
                $rd->setOutlineLevel(min(7, $lvl));
                $sheet->getStyle("B{$r}")->getAlignment()->setIndent($lvl - 1);
                if ($lvl >= 2) {
                    $rd->setVisible(false);
                }
                if ($nextLvl > $lvl) {
                    $rd->setCollapsed(true);
                }
            }
        }

        // --- Freeze di bawah header ---
        $sheet->freezePane('A6');

        // --- Page setup A4 landscape fit-to-width ---
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()->setLeft(0.4)->setRight(0.4)->setTop(0.6)->setBottom(0.6);

        // --- Lebar kolom (cukup lebar agar angka besar tidak tampil ######) ---
        foreach ([
            'A' => 4.5, 'B' => 42, 'C' => 20, 'D' => 20, 'E' => 20,
            'F' => 8, 'G' => 16, 'H' => 8, 'I' => 16, 'J' => 8, 'K' => 16, 'L' => 11,
        ] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        return [];
    }
}
