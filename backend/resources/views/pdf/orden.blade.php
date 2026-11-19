@php($f = fn ($d) => \App\Shared\Infrastructure\HoraLocal::formato($d))
@php($e = $solicitud->expediente)
@extends('pdf.layout')
@section('titulo', 'Orden de servicio')
@section('subtitulo', 'Expediente '.$e->codigo.' · Estado: '.$solicitud->estado->etiqueta())
@section('contenido')
<table width="100%">
    <tr>
        <td width="50%" style="vertical-align:top">
            <h2>Contratante</h2>
            <table class="kv">
                <tr>
                    <td class="k">Nombre</td>
                    <td>{{ $e->cliente->nombre }}</td>
                </tr>
                <tr>
                    <td class="k">RUT</td>
                    <td>{{ \App\Shared\Domain\Rut::formatear($e->cliente->rut) }}</td>
                </tr>
                <tr>
                    <td class="k">Teléfono</td>
                    <td>{{ $e->cliente->telefono ?? '—' }}</td>
                </tr>
            </table>
        </td>
        <td width="50%" style="vertical-align:top">
            <h2>Fallecido</h2>
            <table class="kv">
                <tr>
                    <td class="k">Nombre</td>
                    <td>{{ $e->difunto->nombre }}</td>
                </tr>
                <tr>
                    <td class="k">RUT</td>
                    <td>{{ $e->difunto->rut ? \App\Shared\Domain\Rut::formatear($e->difunto->rut) : '—' }}</td>
                </tr>
                <tr>
                    <td class="k">Fallecimiento</td>
                    <td>{{ $e->difunto->fecha_fallecimiento?->format('d-m-Y') }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<h2>Servicio</h2>
<table class="kv" width="100%">
    <tr>
        <td class="k">Sucursal</td>
        <td>{{ $solicitud->sucursal->nombre }}</td>
    </tr>
    <tr>
        <td class="k">Velatorio</td>
        <td>{{ $f($solicitud->velatorio_inicio) }} · {{ $solicitud->lugar_velatorio }}</td>
    </tr>
    <tr>
        <td class="k">Traslado</td>
        <td>{{ $f($solicitud->traslado_at) }} (presentación del equipo: {{ $f($presentacion) }})</td>
    </tr>
    <tr>
        <td class="k">Lugar de sepultura</td>
        <td>{{ $solicitud->lugar_sepultura }}{{ $solicitud->sepultura_detalle ? ' · '.$solicitud->sepultura_detalle : '' }}</td>
    </tr>
    <tr>
        <td class="k">Vehículo</td>
        <td>{{ $solicitud->asignacion?->vehiculo?->nombre ?? '—' }}{{ $solicitud->asignacion?->vehiculo?->patente ? ' ('.$solicitud->asignacion->vehiculo->patente.')' : '' }}{{ $solicitud->asignacion?->externo ? ' · externo: '.$solicitud->asignacion->vehiculo->proveedor_externo : '' }}</td>
    </tr>
    <tr>
        <td class="k">Equipo asignado</td>
        <td>{{ $solicitud->asignacion?->equipo->map(fn ($u) => $u->nombre.' ('.$u->pivot->funcion.')')->join(', ') ?: '—' }}</td>
    </tr>
</table>

@if($e->actividades->isNotEmpty())
<h2>Actividades</h2>
<table class="datos">
    <tr>
        <th>Actividad</th>
        <th>Lugar</th>
        <th>Inicio</th>
        <th>Término</th>
    </tr>
    @foreach($e->actividades as $a)
    <tr>
        <td>{{ ucfirst($a->tipo) }}</td>
        <td>{{ $a->lugar }}</td>
        <td>{{ $f($a->inicio) }}</td>
        <td>{{ $a->fin ? $f($a->fin) : '—' }}</td>
    </tr>
    @endforeach
</table>
@endif

<h2>Elementos del servicio</h2>
<table class="datos">
    <tr>
        <th>Código</th>
        <th>Descripción</th>
        <th class="num">Cant.</th>
        <th class="num">Precio</th>
    </tr>
    @foreach($solicitud->productos as $p)
    <tr>
        <td>{{ $p->codigo }}</td>
        <td>{{ $p->nombre }}</td>
        <td class="num">{{ $p->pivot->cantidad }}</td>
        <td class="num">${{ number_format($p->pivot->precio_unitario * $p->pivot->cantidad, 0, ',', '.') }}</td>
    </tr>
    @endforeach
    <tr>
        <td></td>
        <td>Servicio funerario (cruz, tarjetas de agradecimiento, arreglo floral y foto enmarcada)</td>
        <td class="num">1</td>
        <td class="num">${{ number_format($solicitud->valor_servicio, 0, ',', '.') }}</td>
    </tr>
    @if($solicitud->descuento > 0)
    <tr>
        <td></td>
        <td>Descuento por convenio</td>
        <td></td>
        <td class="num">−${{ number_format($solicitud->descuento, 0, ',', '.') }}</td>
    </tr>
    @endif
</table>
<table width="100%" style="margin-top:8px">
    <tr>
        <td></td>
        <td width="44%" class="caja">
            <table width="100%">
                <tr>
                    <td>Total del expediente</td>
                    <td class="num total">${{ number_format($resumen['total'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Pagado</td>
                    <td class="num">${{ number_format($resumen['pagado'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Saldo pendiente</td>
                    <td class="num total">${{ number_format($resumen['saldo'], 0, ',', '.') }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@if($solicitud->mensaje)
<h2>Mensaje adicional</h2>
<div class="caja">{{ $solicitud->mensaje }}</div>
@endif

<table class="firma">
    <tr>
        <td>
            <div class="linea">Funeraria Cristo Rey</div>
        </td>
        <td>
            <div class="linea">{{ $e->cliente->nombre }}<br>Contratante</div>
        </td>
    </tr>
</table>
@endsection