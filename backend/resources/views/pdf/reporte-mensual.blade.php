@php($m = fn ($v) => '$'.number_format((int) $v, 0, ',', '.'))
@extends('pdf.layout')
@section('titulo', 'Reporte mensual')
@section('subtitulo', \Carbon\Carbon::createFromFormat('!Y-m', $mes)->locale('es')->translatedFormat('F \d\e Y'))
@section('contenido')
<table width="100%" class="caja"><tr>
    <td>Servicios<br><span class="total">{{ $totales['servicios'] }}</span></td>
    <td>Ingresos<br><span class="total">{{ $m($totales['ingresos']) }}</span></td>
    <td>Gastos<br><span class="total">{{ $m($totales['gastos']) }}</span></td>
    <td>Resultado<br><span class="total">{{ $m($totales['resultado']) }}</span></td>
</tr></table>

<h2>Servicios del mes</h2>
<table class="datos">
    <tr><th>Expediente</th><th>Fallecido</th><th>Traslado</th><th>Sepultura</th><th class="num">Total</th><th class="num">Saldo</th></tr>
    @forelse($servicios as $s)
        <tr><td>{{ $s->codigo }}</td><td>{{ $s->difunto }}</td><td>{{ \App\Shared\Infrastructure\HoraLocal::formato(\Carbon\CarbonImmutable::parse($s->traslado_at, 'UTC')) }}</td>
            <td>{{ $s->lugar_sepultura }}</td><td class="num">{{ $m($s->total) }}</td><td class="num">{{ $m($s->saldo) }}</td></tr>
    @empty
        <tr><td colspan="6">Sin servicios en el mes.</td></tr>
    @endforelse
</table>

<table width="100%"><tr>
<td width="50%" style="vertical-align:top; padding-right:8px">
    <h2>Ingresos por medio de pago</h2>
    <table class="datos"><tr><th>Medio</th><th class="num">Pagos</th><th class="num">Total</th></tr>
        @foreach($ingresos_por_medio as $r)<tr><td>{{ $r->medio }}</td><td class="num">{{ $r->cantidad }}</td><td class="num">{{ $m($r->total) }}</td></tr>@endforeach
    </table>
</td>
<td width="50%" style="vertical-align:top; padding-left:8px">
    <h2>Gastos por categoría</h2>
    <table class="datos"><tr><th>Categoría</th><th class="num">Registros</th><th class="num">Total</th></tr>
        @foreach($gastos_por_categoria as $r)<tr><td>{{ $r->categoria }}</td><td class="num">{{ $r->cantidad }}</td><td class="num">{{ $m($r->total) }}</td></tr>@endforeach
    </table>
</td></tr></table>

<h2>Urnas y ataúdes vendidos</h2>
<table class="datos"><tr><th>Código</th><th>Producto</th><th class="num">Cantidad</th><th class="num">Monto</th></tr>
    @foreach($urnas_vendidas as $u)<tr><td>{{ $u->codigo }}</td><td>{{ $u->nombre }}</td><td class="num">{{ $u->cantidad }}</td><td class="num">{{ $m($u->monto) }}</td></tr>@endforeach
</table>
@endsection
