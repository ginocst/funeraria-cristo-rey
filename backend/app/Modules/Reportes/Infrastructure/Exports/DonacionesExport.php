<?php

namespace App\Modules\Reportes\Infrastructure\Exports;

use App\Modules\Reportes\Application\Formato;
use App\Shared\Infrastructure\HoraLocal;
use Carbon\CarbonImmutable;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/** Donaciones del mes en Excel: una hoja con los mismos datos del PDF */
class DonacionesExport implements Export, WithMultipleSheets
{
    public function __construct(private array $datos) {}

    public function sheets(): array
    {
        $grupo = fn(string $titulo, string $concepto, array $filas) => [
            'titulo' => $titulo,
            'columnas' => [[$concepto, 'texto'], ['Donaciones', 'entero'], ['Monto', 'moneda']],
            'filas' => array_map(fn(array $r) => [$r['nombre'], $r['cantidad'], $r['monto']], $filas),
            'totales' => [1, 2],
        ];
        $subtitulo = Formato::mesLargo($this->datos['mes']) . ' · Generado el ' . HoraLocal::formato(CarbonImmutable::now('UTC'));

        return [new HojaReporte('Donaciones', 'Funeraria Cristo Rey · Donaciones del mes', $subtitulo, [
            $grupo('Por organización', 'Organización', $this->datos['por_organizacion']),
            $grupo('Por categoría', 'Categoría', $this->datos['por_categoria']),
            [
                'titulo' => 'Detalle',
                'columnas' => [['Fecha', 'fecha'], ['Organización', 'texto'], ['Categoría', 'texto'], ['Motivo', 'texto'], ['Monto', 'moneda']],
                'filas' => collect($this->datos['donaciones'])->map(fn($d) => [
                    Date::PHPToExcel(CarbonImmutable::parse($d->fecha)),
                    $d->organizacion,
                    $d->categoria,
                    $d->descripcion,
                    (int) $d->monto,
                ])->all(),
                'totales' => [4],
            ],
        ])];
    }
}
