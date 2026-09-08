<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LaporanKebutuhanExport implements Export, WithMultipleSheets
{
    /**
     * @param  array<string, mixed>  $data  hasil dari LaporanController::kebutuhan()
     */
    public function __construct(private array $data)
    {
    }

    /**
     * @return list<SheetFromArray>
     */
    public function sheets(): array
    {
        $bySkpd = collect($this->data['skpd'] ?? [])->map(fn ($r) => [
            $r['kode'], $r['nama'], $r['count'] ?? 0, $r['total'], $r['pengadaan'] ?? 0,
        ])->all();

        $byProgram = collect($this->data['program'] ?? [])->map(fn ($r) => [
            $r['kode'], $r['nama'], $r['count'] ?? 0, $r['total'],
        ])->all();

        $bySumberDana = collect($this->data['sumber_dana'] ?? [])->map(fn ($r) => [
            $r['kode'], $r['nama'], $r['count'] ?? 0, $r['total'],
        ])->all();

        return [
            new SheetFromArray(
                'Per SKPD',
                $bySkpd,
                ['Kode SKPD', 'Nama SKPD', 'Jumlah Rincian', 'Total Pagu', 'Pagu Pengadaan']
            ),
            new SheetFromArray(
                'Per Program',
                $byProgram,
                ['Kode Program', 'Nama Program', 'Jumlah Rincian', 'Total Pagu']
            ),
            new SheetFromArray(
                'Per Sumber Dana',
                $bySumberDana,
                ['Kode Sumber Dana', 'Nama Sumber Dana', 'Jumlah Rincian', 'Total Pagu']
            ),
        ];
    }
}