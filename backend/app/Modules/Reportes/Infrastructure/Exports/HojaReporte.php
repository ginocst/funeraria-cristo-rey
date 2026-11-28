<?php

namespace App\Modules\Reportes\Infrastructure\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Hoja con título, una o más tablas con formato y filas de totales
 */
class HojaReporte implements Export, WithEvents, WithTitle
{
    private const VINO = '8F1D24';

    private const VINO_OSCURO = '5E1419';

    private const FONDO = 'F6EEEF';

    private const BORDE = 'E3CFD1';

    private const FORMATOS = [
        'moneda' => '"$"#,##0;[Red]-"$"#,##0',
        'entero' => '#,##0',
        'decimal' => '#,##0.0',
        'porcentaje' => '0.0%',
        'fecha' => 'dd-mm-yyyy',
        'fechahora' => 'dd-mm-yyyy hh:mm',
    ];

    public function __construct(
        private string $pestana,
        private string $titulo,
        private string $subtitulo,
        private array $tablas,
    ) {}

    public function title(): string
    {
        return mb_substr($this->pestana, 0, 31);
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => fn (AfterSheet $evento) => $this->dibujar($evento->sheet->getDelegate())];
    }

    /** El título ocupa el ancho de las tablas; sin combinar, puede seguir sobre las columnas vacías */
    protected function combinarTitulo(): bool
    {
        return true;
    }

    private function dibujar(Worksheet $hoja): void
    {
        $columnas = max(array_map(fn ($t) => count($t['columnas']), $this->tablas));
        $ultima = Coordinate::stringFromColumnIndex($columnas);
        $anchos = array_fill(1, $columnas, 12);

        $this->texto($hoja, 'A1', $this->titulo);
        $this->texto($hoja, 'A2', $this->subtitulo);
        if ($this->combinarTitulo()) {
            $hoja->mergeCells("A1:{$ultima}1")->mergeCells("A2:{$ultima}2");
        }
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB(self::VINO);
        $hoja->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('6D5A5C');
        $hoja->getRowDimension(1)->setRowHeight(24);

        $fila = 4;
        foreach ($this->tablas as $tabla) {
            $fin = Coordinate::stringFromColumnIndex(count($tabla['columnas']));

            if (! empty($tabla['titulo'])) {
                $this->texto($hoja, "A{$fila}", $tabla['titulo']);
                $hoja->getStyle("A{$fila}")->getFont()->setBold(true)->setSize(12)->getColor()->setRGB(self::VINO_OSCURO);
                $fila++;
            }

            $encabezado = $fila;
            foreach ($tabla['columnas'] as $i => [$nombre]) {
                $this->texto($hoja, Coordinate::stringFromColumnIndex($i + 1).$fila, $nombre);
                $anchos[$i + 1] = max($anchos[$i + 1], mb_strlen($nombre) + 4);
            }
            $estilo = $hoja->getStyle("A{$fila}:{$fin}{$fila}");
            $estilo->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::VINO);
            $estilo->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $hoja->getRowDimension($fila)->setRowHeight(20);
            $fila++;

            $primera = $fila;
            foreach ($tabla['filas'] as $n => $datos) {
                foreach (array_values($datos) as $i => $valor) {
                    $tipo = $tabla['columnas'][$i][1];
                    if (is_array($valor)) {
                        ['valor' => $valor, 'tipo' => $tipo] = $valor;
                    }
                    $celda = Coordinate::stringFromColumnIndex($i + 1).$fila;
                    $this->escribir($hoja, $celda, $valor, $tipo);
                    $largo = in_array($tipo, ['texto'], true) ? mb_strlen((string) $valor) + 2 : 16;
                    $anchos[$i + 1] = max($anchos[$i + 1], min($largo, 46));
                }
                if ($n % 2 === 1) {
                    $hoja->getStyle("A{$fila}:{$fin}{$fila}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FBF7F7');
                }
                $fila++;
            }

            if ($tabla['filas'] === []) {
                $this->texto($hoja, "A{$fila}", 'Sin registros.');
                $hoja->mergeCells("A{$fila}:{$fin}{$fila}");
                $hoja->getStyle("A{$fila}")->getFont()->setItalic(true);
                $fila++;
            } elseif (! empty($tabla['totales'])) {
                $ultimaFila = $fila - 1;
                $this->texto($hoja, "A{$fila}", 'Total');
                foreach ($tabla['totales'] as $i) {
                    $col = Coordinate::stringFromColumnIndex($i + 1);
                    $hoja->setCellValue("{$col}{$fila}", "=SUM({$col}{$primera}:{$col}{$ultimaFila})");
                    $hoja->getStyle("{$col}{$fila}")->getNumberFormat()->setFormatCode(self::FORMATOS[$tabla['columnas'][$i][1]] ?? 'General');
                }
                $total = $hoja->getStyle("A{$fila}:{$fin}{$fila}");
                $total->getFont()->setBold(true);
                $total->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::FONDO);
                $total->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::VINO);
                $fila++;
            }

            $hoja->getStyle("A{$encabezado}:{$fin}".($fila - 1))->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::BORDE);

            // En las hojas de una sola tabla: encabezado fijo y filtros
            if (count($this->tablas) === 1) {
                $hoja->freezePane('A'.($encabezado + 1));
                if ($tabla['filas'] !== []) {
                    $hoja->setAutoFilter("A{$encabezado}:{$fin}".($primera + count($tabla['filas']) - 1));
                }
            }

            $fila += 2;
        }

        foreach ($anchos as $i => $ancho) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth($ancho);
        }

        $hoja->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_LETTER)->setFitToWidth(1)->setFitToHeight(0);
        $hoja->setShowGridlines(false);
        $hoja->setSelectedCell('A1');
    }

    /** Los textos se escriben como texto: un nombre que empiece con «=» no se ejecuta como fórmula. */
    private function texto(Worksheet $hoja, string $celda, string $valor): void
    {
        $hoja->setCellValueExplicit($celda, $valor, DataType::TYPE_STRING);
    }

    private function escribir(Worksheet $hoja, string $celda, mixed $valor, string $tipo): void
    {
        if ($valor === null || $valor === '') {
            $this->texto($hoja, $celda, '—');

            return;
        }
        if ($tipo === 'texto') {
            $this->texto($hoja, $celda, (string) $valor);

            return;
        }
        $hoja->setCellValueExplicit($celda, $valor, DataType::TYPE_NUMERIC);
        $hoja->getStyle($celda)->getNumberFormat()->setFormatCode(self::FORMATOS[$tipo]);
        if ($tipo === 'fecha' || $tipo === 'fechahora') {
            $hoja->getStyle($celda)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }
}
