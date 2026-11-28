<?php

namespace App\Modules\Reportes\Infrastructure\Exports;

use App\Modules\Reportes\Application\Formato;
use App\Shared\Infrastructure\HoraLocal;
use Carbon\CarbonImmutable;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Panel de inicio en Excel: indicadores y una hoja con tabla y gráfico por cada
 * gráfico del panel. Los montos solo se incluyen para quien ve reportes financieros.
 */
class PanelExport implements Export, WithMultipleSheets
{
    public function __construct(private array $datos) {}

    public function sheets(): array
    {
        $conMontos = $this->datos['conMontos'];

        return array_values(array_filter([
            $this->resumen(),
            $this->conGrafico('Servicios por mes', 'Servicios aceptados o ejecutados, últimos 12 meses', [
                'columnas' => [['Mes', 'texto'], ['Servicios', 'entero']],
                'filas' => array_map(fn($m) => [Formato::mesCorto($m['mes']), $m['total']], $this->datos['servicios_por_mes']),
                'totales' => [1],
            ], [1]),
            $conMontos ? $this->conGrafico('Ingresos y gastos', 'Pagos recibidos y gastos registrados, últimos 12 meses', [
                'columnas' => [['Mes', 'texto'], ['Ingresos', 'moneda'], ['Gastos', 'moneda'], ['Resultado', 'moneda']],
                'filas' => array_map(
                    fn($m) => [Formato::mesCorto($m['mes']), $m['ingresos'], $m['gastos'], $m['ingresos'] - $m['gastos']],
                    $this->datos['finanzas_por_mes'],
                ),
                'totales' => [1, 2, 3],
            ], [1, 2]) : null,
            $this->conGrafico('Urnas más elegidas', 'Unidades en los últimos 12 meses', [
                'columnas' => [['Producto', 'texto'], ['Unidades', 'entero'], ...($conMontos ? [['Monto', 'moneda']] : [])],
                'filas' => collect($this->datos['urnas_mas_vendidas'])
                    ->map(fn($u) => [$u->nombre, (int) $u->cantidad, ...($conMontos ? [(int) $u->monto] : [])])->all(),
                'totales' => $conMontos ? [1, 2] : [1],
            ], [1]),
            $this->conGrafico('Solicitudes', 'Solicitudes por estado', [
                'columnas' => [['Estado', 'texto'], ['Solicitudes', 'entero']],
                'filas' => collect($this->datos['estados'])->map(fn($total, $estado) => [$estado, $total])->values()->all(),
                'totales' => [1],
            ], [1]),
        ]));
    }

    private function resumen(): HojaReporte
    {
        $k = $this->datos['kpis'];
        $dato = fn(string $nombre, mixed $valor, string $tipo = 'entero') => [$nombre, ['valor' => $valor, 'tipo' => $tipo]];

        return new HojaReporte('Resumen', $this->titulo('Resumen del panel'), $this->subtitulo(), [
            [
                'titulo' => 'Indicadores',
                'columnas' => [['Indicador', 'texto'], ['Valor', 'texto']],
                'filas' => [
                    $dato('Servicios este mes', $k['servicios_mes']),
                    $dato('Servicios el mes anterior', $k['servicios_mes_anterior']),
                    $dato('Servicios en curso ahora', $k['en_curso']),
                    $dato('Solicitudes por evaluar', $k['en_revision']),
                    $dato('Cuotas atrasadas', $k['cuotas_atrasadas']),
                    $dato('Productos en stock crítico', $k['stock_critico']),
                    ...($this->datos['conMontos'] ? [
                        $dato('Ingresos del mes', $k['ingresos_mes'], 'moneda'),
                        $dato('Gastos del mes', $k['gastos_mes'], 'moneda'),
                        $dato('Resultado del mes', $k['resultado_mes'], 'moneda'),
                        $dato('Saldo por cobrar', $k['saldo_por_cobrar'], 'moneda'),
                    ] : []),
                ],
            ],
            [
                'titulo' => 'Stock crítico',
                'columnas' => [['Producto', 'texto'], ['Código', 'texto'], ['Stock', 'entero'], ['Mínimo', 'entero']],
                'filas' => collect($this->datos['stock_critico'])
                    ->map(fn($p) => [$p->nombre, $p->codigo, (int) $p->stock, (int) $p->umbral_minimo])->all(),
            ],
        ]);
    }

    private function conGrafico(string $pestana, string $descripcion, array $tabla, array $series): HojaConGrafico
    {
        return new HojaConGrafico($pestana, $this->titulo($pestana), "$descripcion · {$this->subtitulo()}", $tabla, $series);
    }

    private function titulo(string $titulo): string
    {
        return "Funeraria Cristo Rey · $titulo";
    }

    private function subtitulo(): string
    {
        return 'Generado el ' . HoraLocal::formato(CarbonImmutable::now('UTC'));
    }
}
