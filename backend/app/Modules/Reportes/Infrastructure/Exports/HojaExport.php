<?php

namespace App\Modules\Reportes\Infrastructure\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Hoja genérica: título, encabezados y filas */
class HojaExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(private string $titulo, private array $encabezados, private array $filas) {}

    public function array(): array
    {
        return $this->filas;
    }

    public function headings(): array
    {
        return $this->encabezados;
    }

    public function title(): string
    {
        return mb_substr($this->titulo, 0, 31);
    }
}
