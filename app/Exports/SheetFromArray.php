<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class SheetFromArray implements FromArray, WithHeadings, WithTitle
{
    /**
     * @param  list<array<int, mixed>>  $rows
     * @param  list<string>  $headings
     */
    public function __construct(
        private string $title,
        private array $rows,
        private array $headings,
    ) {}

    public function title(): string
    {
        return $this->title;
    }

    /**
     * @return list<array<int, mixed>>
     */
    public function array(): array
    {
        return $this->rows;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->headings;
    }
}
