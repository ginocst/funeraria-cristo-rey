@php($f = \App\Modules\Reportes\Application\Formato::class)
@extends('pdf.layout')
@section('titulo', 'Reporte mensual')
@section('subtitulo', $f::mesLargo($mes))
@section('contenido')
<table width="100%" class="caja">
    <tr>
        <td>Servicios<br><span class="total">{{ $totales['servicios'] }}</span></td>
        <td>Ingresos<br><span class="total">{{ $f::pesos($totales['ingresos']) }}</span></td>
        <td>Gastos<br><span class="total">{{ $f::pesos($totales['gastos']) }}</span></td>
        <td>Resultado<br><span class="total">{{ $f::pesos($totales['resultado']) }}</span></td>
    </tr>
</table>

@php($meses = array_map(fn ($e) => $f::mesCorto($e['mes']), $evolucion))
<div class="bloque">
    <table width="100%">
        <tr>
            <td width="38%" style="vertical-align:top; padding-right:10px">
                <h2>Servicios, últimos 6 meses</h2>
                @include('pdf.partials.columnas', ['etiquetas' => $meses, 'series' => [
                ['nombre' => 'Servicios', 'color' => $f::SERIE_1, 'valores' => array_column($evolucion, 'total'), 'textos' => array_column($evolucion, 'total')],
                ]])
            </td>
            <td width="62%" style="vertical-align:top; padding-left:10px">
                <h2>Ingresos y gastos, últimos 6 meses</h2>
                @include('pdf.partials.columnas', ['etiquetas' => $meses, 'series' => [
                ['nombre' => 'Ingresos', 'color' => $f::SERIE_1, 'valores' => array_column($evolucion, 'ingresos'), 'textos' => array_map([$f, 'pesosCortos'], array_column($evolucion, 'ingresos'))],
                ['nombre' => 'Gastos', 'color' => $f::SERIE_2, 'valores' => array_column($evolucion, 'gastos'), 'textos' => array_map([$f, 'pesosCortos'], array_column($evolucion, 'gastos'))],
                ]])
            </td>
        </tr>
    </table>
</div>

<h2>Servicios del mes</h2>
<table class="datos">
    <tr>
        <th>Expediente</th>
        <th>Fallecido</th>
        <th>Traslado</th>
        <th>Sepultura</th>
        <th class="num">Total</th>
        <th class="num">Saldo</th>
    </tr>
    @forelse($servicios as $s)
    <tr>
        <td>{{ $s->codigo }}</td>
        <td>{{ $s->difunto }}</td>
        <td>{{ \App\Shared\Infrastructure\HoraLocal::formato(\Carbon\CarbonImmutable::parse($s->traslado_at, 'UTC')) }}</td>
        <td>{{ $s->lugar_sepultura }}</td>
        <td class="num">{{ $f::pesos($s->total) }}</td>
        <td class="num">{{ $f::pesos($s->saldo) }}</td>
    </tr>
    @empty
    <tr>
        <td colspan="6">Sin servicios en el mes.</td>
    </tr>
    @endforelse
</table>

<div class="bloque">
    <h2>Ingresos por medio de pago</h2>
    @include('pdf.partials.barras', ['vacio' => 'Sin ingresos en el mes.', 'filas' => collect($ingresos_por_medio)->map(fn ($r) => [
    'etiqueta' => $r->medio, 'valor' => (int) $r->total, 'detalle' => $r->cantidad.($r->cantidad == 1 ? ' pago' : ' pagos'), 'texto' => $f::pesos($r->total),
    ])->all()])
</div>

<div class="bloque">
    <h2>Gastos por categoría</h2>
    @include('pdf.partials.barras', ['vacio' => 'Sin gastos en el mes.', 'filas' => collect($gastos_por_categoria)->map(fn ($r) => [
    'etiqueta' => $r->categoria, 'valor' => (int) $r->total, 'detalle' => $r->cantidad.($r->cantidad == 1 ? ' registro' : ' registros'), 'texto' => $f::pesos($r->total),
    ])->all()])
</div>

<div class="bloque">
    <h2>Donaciones del mes (incluidas en los gastos)</h2>
    @include('pdf.partials.barras', ['vacio' => 'Sin donaciones en el mes.', 'filas' => collect($donaciones_por_organizacion)->map(fn ($r) => [
    'etiqueta' => $r['nombre'], 'valor' => $r['monto'], 'detalle' => $r['cantidad'].($r['cantidad'] == 1 ? ' donación' : ' donaciones'), 'texto' => $f::pesos($r['monto']),
    ])->all()])
</div>

<div class="bloque">
    <h2>Trabajadores del mes</h2>
    <div class="sub" style="margin-bottom:5px">Servicios aceptados o ejecutados en que participa cada persona:
        <span class="muestra" style="background:{{ $f::SERIE_1 }}"></span> como conductor &nbsp;
        <span class="muestra" style="background:{{ $f::SERIE_2 }}"></span> como apoyo
    </div>
    @php($maximo = collect($trabajadores)->max('servicios') ?? 0)
    <table class="datos">
        <tr>
            <th>Trabajador</th>
            <th>Cargo</th>
            <th style="width:130px">Servicios</th>
            <th class="num">Total</th>
            <th class="num">Conductor</th>
            <th class="num">Apoyo</th>
            <th class="num">Horas</th>
            <th class="num">Días ausente</th>
        </tr>
        @forelse($trabajadores as $t)
        <tr>
            <td>{{ $t['nombre'] }}</td>
            <td>{{ $t['cargo'] }}</td>
            <td>@include('pdf.partials.tramos', ['valores' => [$t['conductor'], $t['apoyo']], 'maximo' => $maximo, 'ancho' => 115])</td>
            <td class="num"><b>{{ $t['servicios'] }}</b></td>
            <td class="num">{{ $t['conductor'] }}</td>
            <td class="num">{{ $t['apoyo'] }}</td>
            <td class="num">{{ str_replace('.', ',', (string) $t['horas']) }}</td>
            <td class="num">{{ $t['dias_ausente'] }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="8">Sin trabajadores registrados.</td>
        </tr>
        @endforelse
    </table>
</div>

<div class="bloque">
    <h2>Urnas y ataúdes vendidos</h2>
    @include('pdf.partials.barras', ['vacio' => 'Sin ventas en el mes.', 'filas' => collect($urnas_vendidas)->map(fn ($u) => [
    'etiqueta' => $u->codigo.' · '.$u->nombre, 'valor' => (int) $u->cantidad, 'detalle' => $u->cantidad.($u->cantidad == 1 ? ' unidad' : ' unidades'), 'texto' => $f::pesos($u->monto),
    ])->all()])
</div>
@endsection