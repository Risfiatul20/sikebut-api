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

class LaporanPaketExport implements FromArray, WithHeadings, WithStyles
{
    /**
     * @param  array<string, mixed>  $data  hasil dari LaporanController::paketPerCara()
     * @param  string  $cara  'penyedia' | 'swakelola'
     */
    public function __construct(private array $data, private string $cara)
    {
    }

    private function fmtTanggal($v): string
    {
        if (! $v) {
            return '';
        }
        try {
            return date('d-m-Y', strtotime((string) $v));
        } catch (\Throwable) {
            return (string) $v;
        }
    }

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

    private function lokasi(array $r): string
    {
        return implode('; ', (array) ($r['lokasi'] ?? []));
    }

    private function mak(array $r): string
    {
        $list = array_map(fn ($m) => (string) ($m['kode_rekening'] ?? ''), (array) ($r['mak'] ?? []));
        return implode(', ', array_filter($list));
    }

    private function paguMak(array $r): string
    {
        $list = array_map(fn ($m) => number_format((float) ($m['pagu'] ?? 0), 0, ',', '.'), (array) ($r['mak'] ?? []));
        return implode(', ', $list);
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
        $isPenyedia = $this->cara === 'penyedia';

        foreach (($this->data['rincian'] ?? []) as $i => $r) {
            $base = [
                $i + 1,
                $this->rantai($r),
                $this->lokasi($r),
                $this->angka($r['volume'] ?? 0),
                (string) ($r['volume_satuan'] ?? 'Unit'),
                (string) ($r['uraian'] ?? ''),
                (string) ($r['spesifikasi'] ?? ''),
            ];

            if ($isPenyedia) {
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
                    $this->mak($r),
                    $this->paguMak($r),
                    $this->angka($r['total_pagu'] ?? 0),
                    $this->fmtTanggal($r['waktu_pemanfaatan_awal'] ?? null),
                    $this->fmtTanggal($r['waktu_pemanfaatan_akhir'] ?? null),
                    $this->fmtTanggal($r['waktu_pelaksanaan_awal'] ?? null),
                    $this->fmtTanggal($r['waktu_pelaksanaan_akhir'] ?? null),
                    $this->fmtTanggal($r['waktu_pemilihan_awal'] ?? null),
                    $this->fmtTanggal($r['waktu_pemilihan_akhir'] ?? null),
                ]);
            } else {
                $rows[] = array_merge($base, [
                    (string) ($r['tipe_swakelola'] ?? ''),
                    (string) ($r['sumber_dana'] ?? ''),
                    $this->mak($r),
                    $this->paguMak($r),
                    $this->angka($r['total_pagu'] ?? 0),
                    $this->fmtTanggal($r['waktu_pelaksanaan_awal'] ?? null),
                    $this->fmtTanggal($r['waktu_pelaksanaan_akhir'] ?? null),
                ]);
            }
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        if ($this->cara === 'penyedia') {
            return [
                'No',
                'OPD / Program / Kegiatan / Sub Kegiatan / Paket',
                'Lokasi',
                'Volume',
                'Satuan',
                'Uraian Pekerjaan',
                'Spesifikasi Pekerjaan',
                'PDN',
                'Usaha Kecil',
                'SPP Ekonomi (Y/T)',
                'SPP Sosial (Y/T)',
                'SPP Lingkungan (Y/T)',
                'Pra DIPA/DPA (Y/T)',
                'Metode',
                'Ketersediaan e-Katalog (Y/T)',
                'Sumber Dana',
                'MAK',
                'Pagu',
                'Total Pagu',
                'Pemanfaatan Mulai',
                'Pemanfaatan Sampai',
                'Pelaksanaan Mulai',
                'Pelaksanaan Sampai',
                'Pemilihan Mulai',
                'Pemilihan Sampai',
            ];
        }

        return [
            'No',
            'OPD / Program / Kegiatan / Sub Kegiatan / Paket',
            'Lokasi',
            'Volume',
            'Satuan',
            'Uraian Pekerjaan',
            'Spesifikasi Pekerjaan',
            'Tipe',
            'Sumber Dana',
            'MAK',
            'Pagu',
            'Total Pagu',
            'Pelaksanaan Mulai',
            'Pelaksanaan Sampai',
        ];
    }

    /**
     * Styling lengkap: header biru tebal, border semua sel, wrap text,
     * zebra striping, freeze baris header, lebar kolom & page setup A4 landscape
     * fit-to-width supaya rapi saat dibuka/dicetak — mirip desain PDF.
     *
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = $sheet->getHighestColumn();

        // Header: tebal, putih di atas biru tua, tengah, wrap
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(34);

        // Semua sel: border tipis + rata atas + wrap
        $sheet->getStyle("A1:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
        ]);

        // Zebra striping baris data
        for ($r = 2; $r <= $lastRow; $r++) {
            if ($r % 2 === 0) {
                $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');
            }
        }

        // Freeze baris header
        $sheet->freezePane('A2');

        // Page setup: A4 landscape, pas 1 halaman lebar
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()->setLeft(0.4)->setRight(0.4)->setTop(0.6)->setBottom(0.6);

        // Lebar kolom sesuai isi
        $widths = $this->cara === 'penyedia'
            ? ['A' => 5, 'B' => 45, 'C' => 25, 'D' => 8, 'E' => 8, 'F' => 35, 'G' => 35, 'H' => 6, 'I' => 9, 'J' => 9, 'K' => 9, 'L' => 9, 'M' => 9, 'N' => 15, 'O' => 11, 'P' => 13, 'Q' => 20, 'R' => 16, 'S' => 16, 'T' => 12, 'U' => 12, 'V' => 12, 'W' => 12, 'X' => 12, 'Y' => 12]
            : ['A' => 5, 'B' => 45, 'C' => 25, 'D' => 8, 'E' => 8, 'F' => 35, 'G' => 35, 'H' => 13, 'I' => 13, 'J' => 20, 'K' => 16, 'L' => 16, 'M' => 13, 'N' => 13];

        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        return [];
    }
}