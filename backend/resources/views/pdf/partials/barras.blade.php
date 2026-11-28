{{-- Gráfico de barras horizontales. $filas: [['etiqueta', 'valor', 'texto', 'detalle' (opcional)]] --}}
@php($maximo = collect($filas)->max('valor') ?? 0)
<table class="barras">
    @forelse($filas as $f)
    <tr>
        <td class="etiqueta">{{ $f['etiqueta'] }}</td>
        <td class="pista">@include('pdf.partials.tramos', ['valores' => [$f['valor']], 'maximo' => $maximo, 'ancho' => 250])</td>
        @isset($f['detalle'])<td class="num detalle">{{ $f['detalle'] }}</td>@endisset
        <td class="num cifra">{{ $f['texto'] }}</td>
    </tr>
    @empty
    <tr>
        <td>{{ $vacio ?? 'Sin datos en el período.' }}</td>
    </tr>
    @endforelse
</table>