<?php

namespace App\Modules\Reportes\Application;

use Carbon\CarbonImmutable;

/** Textos de montos y meses que comparten los reportes en PDF y Excel */
final class Formato
{
    /** Colores de las series */
    public const SERIE_1 = '#B8343C';

    public const SERIE_2 = '#1F87A8';

    /** $1.250.000 */
    public static function pesos(int|float|string|null $valor): string
    {
        $n = (int) $valor;

        return ($n < 0 ? '-$' : '$').number_format(abs($n), 0, ',', '.');
    }

    /** $1,3 M o $850 mil, para las etiquetas de los gráficos */
    public static function pesosCortos(int|float|string|null $valor): string
    {
        $n = (int) $valor;
        $signo = $n < 0 ? '-$' : '$';
        $abs = abs($n);

        return match (true) {
            $abs >= 1_000_000 => $signo.str_replace(',0', '', number_format($abs / 1_000_000, 1, ',', '.')).' M',
            $abs >= 1_000 => $signo.round($abs / 1_000).' mil',
            default => $signo.$abs,
        };
    }

    /**
     * Imagen SVG hecha de rectángulos [x, y, ancho, alto, color], lista para un <img>.
     * dompdf alinea una imagen con exactitud; no así varias cajas en línea de distinto alto.
     *
     * @param  array<int, array{0: float, 1: float, 2: float, 3: float, 4: string}>  $rectangulos
     */
    public static function svg(float $ancho, float $alto, array $rectangulos): string
    {
        $cuerpo = implode('', array_map(
            fn (array $r) => sprintf('<rect x="%.2F" y="%.2F" width="%.2F" height="%.2F" fill="%s"/>', ...$r),
            $rectangulos,
        ));
        $svg = sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%1$.2F" height="%2$.2F" viewBox="0 0 %1$.2F %2$.2F">%3$s</svg>', $ancho, $alto, $cuerpo);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /** «nov 26» a partir de «2026-11» */
    public static function mesCorto(string $mes): string
    {
        return str_replace('.', '', CarbonImmutable::createFromFormat('!Y-m', $mes)->locale('es')->translatedFormat('M y'));
    }

    /** «Noviembre de 2026» a partir de «2026-11» */
    public static function mesLargo(string $mes): string
    {
        return ucfirst(CarbonImmutable::createFromFormat('!Y-m', $mes)->locale('es')->translatedFormat('F \d\e Y'));
    }
}
