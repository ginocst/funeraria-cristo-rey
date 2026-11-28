@php($f = \App\Modules\Reportes\Application\Formato::class)
@extends('pdf.layout')
@section('titulo', 'Panel de gestión')
@section('subtitulo', 'Situación al '.\App\Shared\Infrastructure\HoraLocal::formato(now()))
@section('contenido')
<table width="100%" class="caja">
    <tr>
        <td>Servicios este mes<br><span class="total">{{ $kpis['servicios_mes'] }}</span><br><span class="sub">{{ $kpis['servicios_mes_anterior'] }} el mes pasado</span></td>
        <td>En curso ahora<br><span class="total">{{ $kpis['en_curso'] }}</span></td>
        <td>Por evaluar<br><span class="total">{{ $kpis['en_revision'] }}</span></td>
        <td>Cuotas atrasadas<br><span class="total">{{ $kpis['cuotas_atrasadas'] }}</span></td>
        <td>Stock crítico<br><span class="total">{{ $kpis['stock_critico'] }}</span></td>
    </tr>
</table>
@if($conMontos)
<table width="100%" class="caja" style="margin-top:6px">
    <tr>
        <td>Ingresos del mes<br><span class="total">{{ $f::pesos($kpis['ingresos_mes']) }}</span></td>
        <td>Gastos del mes<br><span class="total">{{ $f::pesos($kpis['gastos_mes']) }}</span></td>
        <td>Resultado del mes<br><span class="total">{{ $f::pesos($kpis['resultado_mes']) }}</span></td>
        <td>Saldo por cobrar<br><span class="total">{{ $f::pesos($kpis['saldo_por_cobrar']) }}</span></td>
    </tr>
</table>
@endif

<div class="bloque">
    <h2>Servicios por mes (aceptados o ejecutados, últimos 12 meses)</h2>
    @include('pdf.partials.columnas', ['etiquetas' => array_map(fn ($m) => $f::mesCorto($m['mes']), $servicios_por_mes), 'series' => [
    ['nombre' => 'Servicios', 'color' => $f::SERIE_1, 'valores' => array_column($servicios_por_mes, 'total'), 'textos' => array_column($servicios_por_mes, 'total')],
    ]])
</div>

@if($conMontos)
<div class="bloque">
    <h2>Ingresos y gastos (últimos 12 meses)</h2>
    @include('pdf.partials.columnas', ['alto' => 110, 'etiquetas' => array_map(fn ($m) => $f::mesCorto($m['mes']), $finanzas_por_mes), 'series' => [
    ['nombre' => 'Ingresos', 'color' => $f::SERIE_1, 'valores' => array_column($finanzas_por_mes, 'ingresos'), 'textos' => array_map([$f, 'pesosCortos'], array_column($finanzas_por_mes, 'ingresos'))],
    ['nombre' => 'Gastos', 'color' => $f::SERIE_2, 'valores' => array_column($finanzas_por_mes, 'gastos'), 'textos' => array_map([$f, 'pesosCortos'], array_column($finanzas_por_mes, 'gastos'))],
    ]])
</div>
@endif

<div class="bloque">
    <h2>Urnas más elegidas (últimos 12 meses)</h2>
    @include('pdf.partials.barras', ['vacio' => 'Sin ventas en el período.', 'filas' => collect($urnas_mas_vendidas)->map(fn ($u) => [
    'etiqueta' => $u->nombre, 'valor' => (int) $u->cantidad, 'texto' => $u->cantidad.($u->cantidad == 1 ? ' unidad' : ' unidades'),
    ...($conMontos ? ['detalle' => $f::pesos($u->monto)] : []),
    ])->all()])
</div>

<div class="bloque">
    <h2>Solicitudes por estado</h2>
    @include('pdf.partials.barras', ['vacio' => 'Sin solicitudes.', 'filas' => collect($estados)->map(fn ($total, $estado) => [
    'etiqueta' => $estado, 'valor' => $total, 'texto' => $total,
    ])->values()->all()])
</div>

<div class="bloque">
    <h2>Stock crítico</h2>
    <table class="datos">
        <tr>
            <th>Código</th>
            <th>Producto</th>
            <th class="num">Stock</th>
            <th class="num">Mínimo</th>
        </tr>
        @forelse($stock_critico as $p)
        <tr>
            <td>{{ $p->codigo }}</td>
            <td>{{ $p->nombre }}</td>
            <td class="num">{{ $p->stock }}</td>
            <td class="num">{{ $p->umbral_minimo }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="4">Todo el inventario está sobre el mínimo.</td>
        </tr>
        @endforelse
    </table>
</div>
@endsection