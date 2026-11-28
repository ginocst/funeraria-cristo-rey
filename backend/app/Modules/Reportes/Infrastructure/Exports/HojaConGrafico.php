<?php

namespace App\Modules\Reportes\Infrastructure\Exports;

use App\Modules\Reportes\Application\Formato;
use Maatwebsite\Excel\Concerns\WithCharts;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Chart\Axis;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

/**
 * Hoja con tablas
 */
class HojaConGrafico extends HojaReporte implements WithCharts
{
    /** Fila del encabezado cuando la hoja tiene una sola tabla sin título (ver HojaReporte) */
    private const ENCABEZADO = 4;

    /** @param  array<int, int>  $series  posiciones (desde 0) de las columnas a graficar */
    public function __construct(string $pestana, string $titulo, string $subtitulo, private array $tabla, private array $series)
    {
        parent::__construct($pestana, $titulo, $subtitulo, [$tabla]);
    }

    protected function combinarTitulo(): bool
    {
        return false;
    }

    /** @return array<int, Chart> */
    public function charts(): array
    {
        if ($this->tabla['filas'] === []) {
            return [];
        }
        $grafico = $this->grafico();
        $izquierda = count($this->tabla['columnas']) + 2;
        $grafico->setTopLeftPosition(Coordinate::stringFromColumnIndex($izquierda).self::ENCABEZADO);
        $grafico->setBottomRightPosition(Coordinate::stringFromColumnIndex($izquierda + 9).(self::ENCABEZADO + 20));

        return [$grafico];
    }

    private function grafico(): Chart
    {
        $filas = count($this->tabla['filas']);
        $hoja = "'".$this->title()."'";
        $rango = fn (string $columna) => "$hoja!\${$columna}\$".(self::ENCABEZADO + 1).":\${$columna}\$".(self::ENCABEZADO + $filas);
        $colores = [Formato::SERIE_1, Formato::SERIE_2];

        $nombres = $categorias = $valores = [];
        foreach ($this->series as $i => $posicion) {
            $columna = Coordinate::stringFromColumnIndex($posicion + 1);
            $nombres[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "$hoja!\${$columna}\$".self::ENCABEZADO, null, 1);
            $categorias[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $rango('A'), null, $filas);
            $serie = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $rango($columna), null, $filas);
            $serie->setFillColor(ltrim($colores[$i], '#'));
            $valores[] = $serie;
        }

        $datos = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED, range(0, count($valores) - 1), $nombres, $categorias, $valores);
        $datos->setPlotDirection(DataSeries::DIRECTION_COL);

        // Los montos se leen en millones en el eje; la tabla conserva las cifras completas
        $ejeValores = new Axis;
        if ($this->tabla['columnas'][$this->series[0]][1] === 'moneda') {
            $ejeValores->setAxisNumberProperties('"$"#,##0.0,," M"', true);
        } elseif ($this->maximo() <= 12) {
            // Cantidades pequeñas: el eje avanza de a uno, sin «medios servicios»
            $ejeValores->setAxisOption('major_unit', 1);
        }

        return new Chart(
            'grafico',
            new Title($this->title()),
            count($valores) > 1 ? new Legend(Legend::POSITION_BOTTOM, null, false) : null,
            new PlotArea(null, [$datos]),
            yAxis: $ejeValores,
        );
    }

    /** Valor de la columna más alta del gráfico */
    private function maximo(): int|float
    {
        return max(array_map(
            fn (array $fila) => max(array_map(fn (int $i) => $fila[$i], $this->series)),
            $this->tabla['filas'],
        ));
    }
}
