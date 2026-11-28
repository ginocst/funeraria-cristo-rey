@php($f = \App\Modules\Reportes\Application\Formato::class)
@extends('pdf.layout')
@section('titulo', $organizacion ? 'Donaciones a una organización' : 'Reporte de donaciones')
@section('subtitulo', $f::mesLargo($mes))
@section('contenido')
@if($organizacion)
<table class="kv" width="100%">
    <tr>
        <td class="k">Organización</td>
        <td><b>{{ $organizacion->nombre }}</b></td>
    </tr>
    <tr>
        <td class="k">Categoría</td>
        <td>{{ $organizacion->categoria->nombre }}</td>
    </tr>
    @if($organizacion->contacto)<tr>
        <td class="k">Contacto</td>
        <td>{{ $organizacion->contacto }}</td>
    </tr>@endif
    @if($organizacion->telefono)<tr>
        <td class="k">Teléfono</td>
        <td>{{ $organizacion->telefono }}</td>
    </tr>@endif
    @if($organizacion->email)<tr>
        <td class="k">Correo</td>
        <td>{{ $organizacion->email }}</td>
    </tr>@endif
</table>
@endif

<table width="100%" class="caja" style="margin-top:8px">
    <tr>
        <td>Donaciones del mes<br><span class="total">{{ $totales['cantidad'] }}</span></td>
        <td>Monto donado<br><span class="total">{{ $f::pesos($totales['monto']) }}</span></td>
    </tr>
</table>

@unless($organizacion)
@foreach(['Por organización' => ['Organización', $por_organizacion], 'Por categoría' => ['Categoría', $por_categoria]] as $titulo => [$concepto, $filas])
<div class="bloque">
    <h2>{{ $titulo }}</h2>
    <table class="datos">
        <tr>
            <th>{{ $concepto }}</th>
            <th class="num">Donaciones</th>
            <th class="num">Monto</th>
        </tr>
        @forelse($filas as $r)
        <tr>
            <td>{{ $r['nombre'] }}</td>
            <td class="num">{{ $r['cantidad'] }}</td>
            <td class="num">{{ $f::pesos($r['monto']) }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="3">Sin donaciones en el mes.</td>
        </tr>
        @endforelse
    </table>
</div>
@endforeach
@endunless

<h2>Detalle</h2>
<table class="datos">
    <tr>
        <th>Fecha</th>@unless($organizacion)<th>Organización</th>@endunless<th>Motivo</th>
        <th class="num">Monto</th>
    </tr>
    @forelse($donaciones as $d)
    <tr>
        <td>{{ \Carbon\Carbon::parse($d->fecha)->format('d-m-Y') }}</td>@unless($organizacion)<td>{{ $d->organizacion }}</td>@endunless
        <td>{{ $d->descripcion ?? '—' }}</td>
        <td class="num">{{ $f::pesos($d->monto) }}</td>
    </tr>
    @empty
    <tr>
        <td colspan="4">Sin donaciones en el mes.</td>
    </tr>
    @endforelse
</table>
@endsection