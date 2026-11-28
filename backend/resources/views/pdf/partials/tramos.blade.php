{{-- Barra horizontal proporcional; con varios valores se apila. $ancho es el largo del valor máximo --}}
@php
$colores ??= [\App\Modules\Reportes\Application\Formato::SERIE_1, \App\Modules\Reportes\Application\Formato::SERIE_2];
$rectangulos = [];
$x = 0;
foreach (array_values($valores) as $i => $valor) {
if ($valor > 0) {
$largo = max(1.5, $valor / max(1, $maximo) * ($ancho - 2));
$rectangulos[] = [$x, 0, $largo, 9, $colores[$i]];
$x += $largo + 2; // separación entre tramos
}
}
@endphp
@if($rectangulos)<img class="tramo" src="{{ \App\Modules\Reportes\Application\Formato::svg($x - 2, 9, $rectangulos) }}" style="width:{{ round($x - 2, 2) }}px" alt="">@endif