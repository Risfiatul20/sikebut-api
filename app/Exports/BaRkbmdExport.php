<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BaRkbmdExport implements FromArray, WithHeadings, WithStyles
{
    /**
     * @param  array<string, mixed>  $data  hasil dari LaporanController::baRkbmd()
     */
    public function __construct(private array $data) {}

    private function rantai(array $r): string
    {
        $bagian = array_filter([
            $r['nama_skpd'] ?? '',
            $r['nama_program'] ?? '',
            $r['nama_kegiatan'] ?? '',
            $r['nama_sub_kegiatan'] ?? '',
            $r['nama_paket'] ?? '',
        ]);

        return implode(' / ', $bagian);
    }

    private function barang(array $r): string
    {
        $list = [];
        foreach ((array) ($r['items'] ?? []) as $it) {
            $list[] = trim((string) ($it['nama_barang'] ?? '').' '.number_format((float) ($it['jumlah'] ?? 0), 0, ',', '.').' '.(string) ($it['satuan'] ?? ''));
        }

        return implode('; ', array_filter($list));
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
     * @return list<array<int, mixed>>
     */
    public function array(): array
    {
        $rows = [];
        foreach (($this->data['paket'] ?? []) as $i => $r) {
            $rows[] = [
                $i + 1,
                $this->rantai($r),
                $this->angka($r['jumlah_item'] ?? 0),
                $this->angka($r['total_unit'] ?? 0),
                $this->barang($r),
                (string) ($r['catatan_pembahasan'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'No',
            'OPD / Program / Kegiatan / Sub Kegiatan / Paket',
            'Jumlah Item',
            'Total Unit',
            'Barang RKBMD',
            'Catatan Pembahasan',
        ];
    }

    /**
     * Styling lengkap: header biru tebal, border semua sel, wrap text,
     * zebra striping, freeze baris header, lebar kolom & page setup A4 landscape
     * fit-to-width — rapi saat dibuka/dicetak.
     *
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = $sheet->getHighestColumn();

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $sheet->getStyle("A1:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
        ]);

        for ($r = 2; $r <= $lastRow; $r++) {
            if ($r % 2 === 0) {
                $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');
            }
        }

        $sheet->freezePane('A2');

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()->setLeft(0.4)->setRight(0.4)->setTop(0.6)->setBottom(0.6);

        foreach (['A' => 5, 'B' => 45, 'C' => 11, 'D' => 13, 'E' => 50, 'F' => 40] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        return [];
    }
}
