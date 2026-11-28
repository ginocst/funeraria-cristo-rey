<?php

namespace App\Modules\Reportes\Infrastructure\Exports;

use App\Modules\Reportes\Application\Formato;
use App\Shared\Infrastructure\HoraLocal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Reporte mensual en Excel: una primera hoja con los mismos datos del PDF, un
 * análisis con porcentajes y hojas de detalle por trabajadores, servicios, pagos, gastos y urnas.
 */
class ReporteMensualExport implements Export, WithMultipleSheets
{
    private const ESTADOS = ['aceptada' => 'Aceptada', 'ejecutada' => 'Ejecutada'];

    public function __construct(private array $datos, private array $detalle) {}

    public function sheets(): array
    {
        return [
            $this->reporte(),
            $this->analisis(),
            $this->trabajadores(),
            $this->servicios(),
            $this->pagos(),
            $this->gastos(),
            $this->urnas(),
        ];
    }

    /** Las mismas secciones del PDF, en el mismo orden y con los mismos datos, en tablas */
    private function reporte(): HojaReporte
    {
        $t = $this->datos['totales'];
        $evolucion = collect($this->datos['evolucion']);

        return $this->hoja('Reporte', 'Reporte mensual', [
            [
                'titulo' => 'Indicadores del mes',
                'columnas' => [['Servicios', 'entero'], ['Ingresos', 'moneda'], ['Gastos', 'moneda'], ['Resultado', 'moneda']],
                'filas' => [[$t['servicios'], $t['ingresos'], $t['gastos'], $t['resultado']]],
            ],
            [
                'titulo' => 'Evolución de los últimos 6 meses',
                'columnas' => [['Mes', 'texto'], ['Servicios', 'entero'], ['Ingresos', 'moneda'], ['Gastos', 'moneda']],
                'filas' => $evolucion->map(fn(array $e) => [Formato::mesCorto($e['mes']), $e['total'], $e['ingresos'], $e['gastos']])->all(),
            ],
            [
                'titulo' => 'Servicios del mes',
                'columnas' => [
                    ['Expediente', 'texto'],
                    ['Fallecido', 'texto'],
                    ['Traslado', 'fechahora'],
                    ['Sepultura', 'texto'],
                    ['Total', 'moneda'],
                    ['Saldo', 'moneda'],
                ],
                'filas' => collect($this->datos['servicios'])->map(fn($s) => [
                    $s->codigo,
                    $s->difunto,
                    $this->fechaHora($s->traslado_at),
                    $s->lugar_sepultura,
                    (int) $s->total,
                    (int) $s->saldo,
                ])->all(),
                'totales' => [4, 5],
            ],
            [
                'titulo' => 'Ingresos por medio de pago',
                'columnas' => [['Medio de pago', 'texto'], ['Pagos', 'entero'], ['Total', 'moneda']],
                'filas' => collect($this->datos['ingresos_por_medio'])->map(fn($r) => [$r->medio, (int) $r->cantidad, (int) $r->total])->all(),
                'totales' => [1, 2],
            ],
            [
                'titulo' => 'Gastos por categoría',
                'columnas' => [['Categoría', 'texto'], ['Registros', 'entero'], ['Total', 'moneda']],
                'filas' => collect($this->datos['gastos_por_categoria'])->map(fn($r) => [$r->categoria, (int) $r->cantidad, (int) $r->total])->all(),
                'totales' => [1, 2],
            ],
            [
                'titulo' => 'Donaciones del mes (incluidas en los gastos)',
                'columnas' => [['Organización', 'texto'], ['Donaciones', 'entero'], ['Monto', 'moneda']],
                'filas' => array_map(fn(array $r) => [$r['nombre'], $r['cantidad'], $r['monto']], $this->datos['donaciones_por_organizacion']),
                'totales' => [1, 2],
            ],
            $this->resumenTrabajadores('Trabajadores del mes'),
            [
                'titulo' => 'Urnas y ataúdes vendidos',
                'columnas' => [['Código', 'texto'], ['Producto', 'texto'], ['Cantidad', 'entero'], ['Monto', 'moneda']],
                'filas' => collect($this->datos['urnas_vendidas'])->map(fn($u) => [$u->codigo, $u->nombre, (int) $u->cantidad, (int) $u->monto])->all(),
                'totales' => [2, 3],
            ],
        ]);
    }

    private function resumenTrabajadores(string $titulo): array
    {
        return [
            'titulo' => $titulo,
            'columnas' => [
                ['Trabajador', 'texto'],
                ['Cargo', 'texto'],
                ['Servicios', 'entero'],
                ['Como conductor', 'entero'],
                ['Como apoyo', 'entero'],
                ['Horas', 'decimal'],
                ['Días ausente', 'entero'],
            ],
            'filas' => collect($this->datos['trabajadores'])->map(fn(array $t) => [
                $t['nombre'],
                $t['cargo'],
                $t['servicios'],
                $t['conductor'],
                $t['apoyo'],
                $t['horas'],
                $t['dias_ausente'],
            ])->all(),
            'totales' => [2, 3, 4, 5, 6],
        ];
    }

    private function analisis(): HojaReporte
    {
        $t = $this->datos['totales'];
        // Un expediente puede tener más de un servicio en el mes: su saldo se cuenta una vez
        $expedientes = collect($this->datos['servicios'])->unique('codigo');
        $contratado = (int) $expedientes->sum('total');
        $dato = fn(string $nombre, mixed $valor, string $tipo) => [$nombre, ['valor' => $valor, 'tipo' => $tipo]];

        return $this->hoja('Análisis', 'Análisis del mes', [
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
                    ->map(fn(Collection $g, string $sucursal) => [$sucursal, $g->count(), (int) $g->unique('codigo')->sum('total')])
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
            'filas' => $filas->map(fn($r) => [$r->{$campo}, (int) $r->cantidad, (int) $r->total, $this->parte((int) $r->total, $total)])->all(),
            'totales' => [1, 2, 3],
        ];
    }

    private function servicios(): HojaReporte
    {
        return $this->hoja('Servicios', 'Detalle de servicios', [[
            'columnas' => [
                ['Expediente', 'texto'],
                ['Difunto', 'texto'],
                ['Cliente', 'texto'],
                ['Sucursal', 'texto'],
                ['Traslado', 'fechahora'],
                ['Lugar de sepultura', 'texto'],
                ['Estado', 'texto'],
                ['Total', 'moneda'],
                ['Pagado', 'moneda'],
                ['Saldo', 'moneda'],
                ['% pagado', 'porcentaje'],
            ],
            'filas' => collect($this->datos['servicios'])->map(fn($s) => [
                $s->codigo,
                $s->difunto,
                $s->cliente,
                $s->sucursal,
                $this->fechaHora($s->traslado_at),
                $s->lugar_sepultura,
                self::ESTADOS[$s->estado] ?? $s->estado,
                (int) $s->total,
                (int) $s->pagado,
                (int) $s->saldo,
                $this->parte((int) $s->pagado, (int) $s->total),
            ])->all(),
            'totales' => [7, 8, 9],
        ]]);
    }

    private function pagos(): HojaReporte
    {
        return $this->hoja('Pagos', 'Detalle de pagos recibidos y anulaciones', [[
            'columnas' => [
                ['Fecha', 'fechahora'],
                ['Comprobante', 'texto'],
                ['Expediente', 'texto'],
                ['Cliente', 'texto'],
                ['Medio de pago', 'texto'],
                ['Tipo', 'texto'],
                ['Referencia', 'texto'],
                ['Registrado por', 'texto'],
                ['Monto', 'moneda'],
            ],
            'filas' => collect($this->detalle['pagos'])->map(fn($p) => [
                $this->fechaHora($p->fecha),
                $p->folio,
                $p->codigo,
                $p->cliente,
                $p->medio,
                $p->tipo === 'anulacion' ? 'Anulación' : 'Pago',
                $p->referencia,
                $p->registrado_por,
                (int) $p->monto,
            ])->all(),
            'totales' => [8],
        ]]);
    }

    private function gastos(): HojaReporte
    {
        return $this->hoja('Gastos', 'Detalle de gastos', [[
            'columnas' => [
                ['Fecha', 'fecha'],
                ['Categoría', 'texto'],
                ['Descripción', 'texto'],
                ['Proveedor u organización', 'texto'],
                ['Sucursal', 'texto'],
                ['Monto', 'moneda'],
            ],
            'filas' => collect($this->detalle['gastos'])->map(fn($g) => [
                Date::PHPToExcel(CarbonImmutable::parse($g->fecha)),
                $g->categoria,
                $g->descripcion,
                $g->proveedor,
                $g->sucursal,
                (int) $g->monto,
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
            'filas' => $urnas->map(fn($u) => [$u->codigo, $u->nombre, (int) $u->cantidad, (int) $u->monto, $this->parte((int) $u->monto, $monto)])->all(),
            'totales' => [2, 3, 4],
        ]]);
    }

    private function trabajadores(): HojaReporte
    {
        return $this->hoja('Trabajadores', 'Trabajo del personal', [
            $this->resumenTrabajadores('Resumen por trabajador'),
            [
                'titulo' => 'Servicios de cada trabajador',
                'columnas' => [
                    ['Trabajador', 'texto'],
                    ['Expediente', 'texto'],
                    ['Difunto', 'texto'],
                    ['Traslado', 'fechahora'],
                    ['Función', 'texto'],
                    ['Vehículo', 'texto'],
                    ['Estado', 'texto'],
                ],
                'filas' => collect($this->detalle['participaciones'])->map(fn($p) => [
                    $p->trabajador,
                    $p->codigo,
                    $p->difunto,
                    $this->fechaHora($p->traslado_at),
                    $p->funcion === 'conductor' ? 'Conductor' : 'Apoyo',
                    $p->vehiculo,
                    self::ESTADOS[$p->estado] ?? $p->estado,
                ])->all(),
            ],
        ]);
    }

    private function hoja(string $pestana, string $titulo, array $tablas): HojaReporte
    {
        return new HojaReporte($pestana, "Funeraria Cristo Rey · $titulo", $this->subtitulo(), $tablas);
    }

    private function subtitulo(): string
    {
        return Formato::mesLargo($this->datos['mes']) . ' · Generado el ' . HoraLocal::formato(CarbonImmutable::now('UTC'));
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
