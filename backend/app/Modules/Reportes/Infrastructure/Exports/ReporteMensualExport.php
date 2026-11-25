<?php

namespace App\Modules\Reportes\Infrastructure\Exports;

use App\Shared\Infrastructure\HoraLocal;
use Carbon\CarbonImmutable;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ReporteMensualExport implements Export, WithMultipleSheets
{
    public function __construct(private array $datos) {}

    public function sheets(): array
    {
        $d = $this->datos;

        return [
            new HojaExport('Resumen', ['Indicador', 'Valor'], [
                ['Mes', $d['mes']],
                ['Servicios', $d['totales']['servicios']],
                ['Ingresos', $d['totales']['ingresos']],
                ['Gastos', $d['totales']['gastos']],
                ['Resultado', $d['totales']['resultado']],
            ]),
            new HojaExport(
                'Servicios',
                ['Expediente', 'Difunto', 'Cliente', 'Sucursal', 'Traslado', 'Sepultura', 'Estado', 'Total', 'Pagado', 'Saldo'],
                collect($d['servicios'])->map(fn ($s) => [
                    $s->codigo,
                    $s->difunto,
                    $s->cliente,
                    $s->sucursal,
                    HoraLocal::formato(CarbonImmutable::parse($s->traslado_at, 'UTC')),
                    $s->lugar_sepultura,
                    $s->estado,
                    (int) $s->total,
                    (int) $s->pagado,
                    (int) $s->saldo,
                ])->all()
            ),
            new HojaExport('Ingresos', ['Medio de pago', 'Cantidad', 'Total'], collect($d['ingresos_por_medio'])->map(fn ($r) => [$r->medio, (int) $r->cantidad, (int) $r->total])->all()),
            new HojaExport('Gastos', ['Categoría', 'Cantidad', 'Total'], collect($d['gastos_por_categoria'])->map(fn ($r) => [$r->categoria, (int) $r->cantidad, (int) $r->total])->all()),
            new HojaExport('Urnas', ['Código', 'Producto', 'Cantidad', 'Monto'], collect($d['urnas_vendidas'])->map(fn ($r) => [$r->codigo, $r->nombre, (int) $r->cantidad, (int) $r->monto])->all()),
        ];
    }
}
