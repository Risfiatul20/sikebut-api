<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LaporanRekapExport implements FromArray, WithHeadings, WithStyles
{
    /**
     * @param  array<string, mixed>  $data  hasil dari LaporanController::rekap()
     */
    public function __construct(private array $data)
    {
    }

    /**
     * @return list<array<int, mixed>>
     */
    public function array(): array
    {
        $rows = [];

        $walk = function (array $node, string $indent = '') use (&$rows, &$walk): void {
            $rows[] = [
                $node['no'] ?? '',
                $indent.$node['nama'],
                $node['kode'],
                $node['pagu'],
                $node['belanjaPengadaan'],
                $node['belanjaNonPengadaan'],
                $node['identifikasi']['jumlah']['paket'],
                $node['identifikasi']['jumlah']['pagu'],
                $node['identifikasi']['penyedia']['paket'],
                $node['identifikasi']['penyedia']['pagu'],
                $node['identifikasi']['swakelola']['paket'],
                $node['identifikasi']['swakelola']['pagu'],
                $node['keterisian'],
            ];

            foreach (($node['children'] ?? []) as $child) {
                $walk($child, $indent.'  ');
            }
        };

        foreach (($this->data['tree'] ?? []) as $opd) {
            $walk($opd);
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
            'Nama (hierarki)',
            'Kode',
            'Pagu',
            'Belanja Pengadaan',
            'Belanja Non Pengadaan',
            'Jumlah Paket',
            'Pagu Paket',
            'Paket Penyedia',
            'Pagu Penyedia',
            'Paket Swakelola',
            'Pagu Swakelola',
            'Keterisian (%)',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}