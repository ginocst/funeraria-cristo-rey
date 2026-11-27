<?php

namespace App\Modules\Reportes\Infrastructure\Exports;

use App\Shared\Infrastructure\HoraLocal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Reporte mensual en Excel: resumen con indicadores y desgloses, y una hoja de
 * detalle por servicios, pagos, gastos y urnas.
 */
class ReporteMensualExport implements Export, WithMultipleSheets
{
    private const ESTADOS = ['aceptada' => 'Aceptada', 'ejecutada' => 'Ejecutada'];

    public function __construct(private array $datos, private array $detalle) {}

    public function sheets(): array
    {
        return [
            $this->resumen(),
            $this->servicios(),
            $this->pagos(),
            $this->gastos(),
            $this->urnas(),
        ];
    }

    private function resumen(): HojaReporte
    {
        $t = $this->datos['totales'];
        // Un expediente puede tener más de un servicio en el mes: su saldo se cuenta una vez
        $expedientes = collect($this->datos['servicios'])->unique('codigo');
        $contratado = (int) $expedientes->sum('total');
        $dato = fn (string $nombre, mixed $valor, string $tipo) => [$nombre, ['valor' => $valor, 'tipo' => $tipo]];

        return $this->hoja('Resumen', 'Resumen del mes', [
            [
                'titulo' => 'Indicadores',
                'columnas' => [['Indicador', 'texto'], ['Valor', 'texto']],
                'filas' => [
                    $dato('Servicios realizados o aceptados', $t['servicios'], 'entero'),
                    $dato('Ingresos del mes', $t['ingresos'], 'moneda'),
                    $dato('Gastos del mes', $t['gastos'], 'moneda'),
                    $dato('Resultado (ingresos menos gastos)', $t['resultado'], 'moneda'),
                    $dato('Margen sobre los ingresos', $this->parte($t['resultado'], $t['ingresos']), 'porcentaje'),
                    $dato('Total contratado en los servicios del mes', $contratado, 'moneda'),
                    $dato('Valor promedio por expediente', $expedientes->count() ? (int) round($contratado / $expedientes->count()) : 0, 'moneda'),
                    $dato('Pagado a la fecha por esos servicios', (int) $expedientes->sum('pagado'), 'moneda'),
                    $dato('Saldo por cobrar de esos servicios', (int) $expedientes->sum('saldo'), 'moneda'),
                ],
            ],
            $this->desglose('Ingresos por medio de pago', 'Medio de pago', 'Pagos', collect($this->datos['ingresos_por_medio']), 'medio', $t['ingresos']),
            $this->desglose('Gastos por categoría', 'Categoría', 'Registros', collect($this->datos['gastos_por_categoria']), 'categoria', $t['gastos']),
            [
                'titulo' => 'Servicios por sucursal',
                'columnas' => [['Sucursal', 'texto'], ['Servicios', 'entero'], ['Total contratado', 'moneda']],
                'filas' => collect($this->datos['servicios'])->groupBy('sucursal')->sortKeys()
                    ->map(fn (Collection $g, string $sucursal) => [$sucursal, $g->count(), (int) $g->unique('codigo')->sum('total')])
                    ->values()->all(),
                'totales' => [1, 2],
            ],
        ]);
    }

    /** Tabla «concepto, cantidad, total y porcentaje del total» */
    private function desglose(string $titulo, string $concepto, string $cantidad, Collection $filas, string $campo, int $total): array
    {
        return [
            'titulo' => $titulo,
            'columnas' => [[$concepto, 'texto'], [$cantidad, 'entero'], ['Total', 'moneda'], ['% del total', 'porcentaje']],
            'filas' => $filas->map(fn ($r) => [$r->{$campo}, (int) $r->cantidad, (int) $r->total, $this->parte((int) $r->total, $total)])->all(),
            'totales' => [1, 2, 3],
        ];
    }

    private function servicios(): HojaReporte
    {
        return $this->hoja('Servicios', 'Detalle de servicios', [[
            'columnas' => [
                ['Expediente', 'texto'], ['Difunto', 'texto'], ['Cliente', 'texto'], ['Sucursal', 'texto'], ['Traslado', 'fechahora'],
                ['Lugar de sepultura', 'texto'], ['Estado', 'texto'], ['Total', 'moneda'], ['Pagado', 'moneda'], ['Saldo', 'moneda'], ['% pagado', 'porcentaje'],
            ],
            'filas' => collect($this->datos['servicios'])->map(fn ($s) => [
                $s->codigo, $s->difunto, $s->cliente, $s->sucursal, $this->fechaHora($s->traslado_at), $s->lugar_sepultura,
                self::ESTADOS[$s->estado] ?? $s->estado, (int) $s->total, (int) $s->pagado, (int) $s->saldo, $this->parte((int) $s->pagado, (int) $s->total),
            ])->all(),
            'totales' => [7, 8, 9],
        ]]);
    }

    private function pagos(): HojaReporte
    {
        return $this->hoja('Pagos', 'Detalle de pagos recibidos y anulaciones', [[
            'columnas' => [
                ['Fecha', 'fechahora'], ['Comprobante', 'texto'], ['Expediente', 'texto'], ['Cliente', 'texto'], ['Medio de pago', 'texto'],
                ['Tipo', 'texto'], ['Referencia', 'texto'], ['Registrado por', 'texto'], ['Monto', 'moneda'],
            ],
            'filas' => collect($this->detalle['pagos'])->map(fn ($p) => [
                $this->fechaHora($p->fecha), $p->folio, $p->codigo, $p->cliente, $p->medio,
                $p->tipo === 'anulacion' ? 'Anulación' : 'Pago', $p->referencia, $p->registrado_por, (int) $p->monto,
            ])->all(),
            'totales' => [8],
        ]]);
    }

    private function gastos(): HojaReporte
    {
        return $this->hoja('Gastos', 'Detalle de gastos', [[
            'columnas' => [
                ['Fecha', 'fecha'], ['Categoría', 'texto'], ['Descripción', 'texto'], ['Proveedor', 'texto'], ['Sucursal', 'texto'], ['Monto', 'moneda'],
            ],
            'filas' => collect($this->detalle['gastos'])->map(fn ($g) => [
                Date::PHPToExcel(CarbonImmutable::parse($g->fecha)), $g->categoria, $g->descripcion, $g->proveedor, $g->sucursal, (int) $g->monto,
            ])->all(),
            'totales' => [5],
        ]]);
    }

    private function urnas(): HojaReporte
    {
        $urnas = collect($this->datos['urnas_vendidas']);
        $monto = (int) $urnas->sum('monto');

        return $this->hoja('Urnas', 'Urnas y ataúdes vendidos', [[
            'columnas' => [['Código', 'texto'], ['Producto', 'texto'], ['Cantidad', 'entero'], ['Monto', 'moneda'], ['% del monto', 'porcentaje']],
            'filas' => $urnas->map(fn ($u) => [$u->codigo, $u->nombre, (int) $u->cantidad, (int) $u->monto, $this->parte((int) $u->monto, $monto)])->all(),
            'totales' => [2, 3, 4],
        ]]);
    }

    private function hoja(string $pestana, string $titulo, array $tablas): HojaReporte
    {
        $mes = CarbonImmutable::createFromFormat('!Y-m', $this->datos['mes'])->locale('es')->translatedFormat('F \d\e Y');
        $generado = HoraLocal::formato(CarbonImmutable::now('UTC'));

        return new HojaReporte($pestana, "Funeraria Cristo Rey · $titulo", ucfirst($mes)." · Generado el $generado", $tablas);
    }

    /** Fecha UTC de la base convertida a hora local, como número de fecha de Excel */
    private function fechaHora(string $utc): float
    {
        return (float) Date::PHPToExcel(HoraLocal::aLocal(CarbonImmutable::parse($utc, 'UTC')));
    }

    private function parte(int $valor, int $total): float
    {
        return $total !== 0 ? $valor / $total : 0.0;
    }
}
