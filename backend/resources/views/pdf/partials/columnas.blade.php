{{--
    Gráfico de columnas. $etiquetas: textos del eje. $series: [['nombre', 'color', 'valores', 'textos']].
    Con una serie el valor va sobre cada columna; con varias, en filas bajo el eje (sirven de leyenda).
--}}
@php
$maximo = max(1, collect($series)->flatMap(fn ($s) => $s['valores'])->max() ?? 0);
$alto ??= 90;
$varias = count($series) > 1;
$grosor = 11;
$anchoGrupo = count($series) * ($grosor + 2) - 2;
@endphp
<table class="columnas">
    <tr class="area">
        @if($varias)<td class="nombre"></td>@endif
        @foreach($etiquetas as $i => $etiqueta)
        @php
        $alturas = array_map(fn ($s) => $s['valores'][$i] > 0 ? max(1, $s['valores'][$i] / $maximo * $alto) : 0, $series);
        // La imagen mide lo que la columna más alta del grupo: así el valor queda justo encima
        $altoGrupo = max($alturas);
        $rectangulos = [];
        foreach ($alturas as $n => $altura) {
        if ($altura > 0) {
        $rectangulos[] = [$n * ($grosor + 2), $altoGrupo - $altura, $grosor, $altura, $series[$n]['color']];
        }
        }
        @endphp
        <td style="height:{{ $alto + 14 }}px">
            @unless($varias)<div class="valor">{{ $series[0]['textos'][$i] }}</div>@endunless
            @if($rectangulos)<img class="columna" src="{{ \App\Modules\Reportes\Application\Formato::svg($anchoGrupo, $altoGrupo, $rectangulos) }}" style="width:{{ $anchoGrupo }}px; height:{{ round($altoGrupo, 2) }}px" alt="">@endif
        </td>
        @endforeach
    </tr>
    <tr class="eje">
        @if($varias)<td class="nombre"></td>@endif
        @foreach($etiquetas as $etiqueta)<td>{{ $etiqueta }}</td>@endforeach
    </tr>
    @if($varias)
    @foreach($series as $s)
    <tr class="cifras">
        <td class="nombre"><span class="muestra" style="background:{{ $s['color'] }}"></span> {{ $s['nombre'] }}</td>
        @foreach($s['textos'] as $texto)<td>{{ $texto }}</td>@endforeach
    </tr>
    @endforeach
    @endif
</table>