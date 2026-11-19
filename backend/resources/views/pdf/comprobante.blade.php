@extends('pdf.layout')
@section('titulo', $pago->tipo === 'anulacion' ? 'Comprobante de anulación' : 'Comprobante de pago')
@section('subtitulo', 'Folio '.$comprobante->folio)
@section('contenido')
<table class="kv" width="100%">
    <tr>
        <td class="k">Folio</td>
        <td><b>{{ $comprobante->folio }}</b></td>
    </tr>
    <tr>
        <td class="k">Fecha</td>
        <td>{{ \App\Shared\Infrastructure\HoraLocal::formato($pago->fecha) }}</td>
    </tr>
    <tr>
        <td class="k">Expediente</td>
        <td>{{ $expediente->codigo }} · Servicio de {{ $expediente->difunto->nombre }}</td>
    </tr>
    <tr>
        <td class="k">Contratante</td>
        <td>{{ $expediente->cliente->nombre }} · RUT {{ \App\Shared\Domain\Rut::formatear($expediente->cliente->rut) }}</td>
    </tr>
    <tr>
        <td class="k">Medio de pago</td>
        <td>{{ $pago->medioPago->nombre }}{{ $pago->referencia ? ' · Ref. '.$pago->referencia : '' }}</td>
    </tr>
    @if($pago->tipo === 'anulacion')
    <tr>
        <td class="k">Motivo de la anulación</td>
        <td>{{ $pago->motivo_anulacion }}</td>
    </tr>
    @endif
    <tr>
        <td class="k">Registrado por</td>
        <td>{{ $pago->registradoPor?->nombre ?? '—' }}</td>
    </tr>
</table>

<table width="100%" style="margin-top:16px">
    <tr>
        <td width="56%"></td>
        <td class="caja">
            <table width="100%">
                <tr>
                    <td>{{ $pago->tipo === 'anulacion' ? 'Monto anulado' : 'Monto recibido' }}</td>
                    <td class="num total">${{ number_format(abs($pago->monto), 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Total del servicio</td>
                    <td class="num">${{ number_format($resumen['total'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Pagado a la fecha</td>
                    <td class="num">${{ number_format($resumen['pagado'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Saldo restante</td>
                    <td class="num total">${{ number_format($resumen['saldo'], 0, ',', '.') }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<p class="sub" style="margin-top:18px">Comprobante interno de control con copia digital para la familia y para la funeraria. No reemplaza la boleta o factura tributaria.</p>

<table class="firma">
    <tr>
        <td>
            <div class="linea">Funeraria Cristo Rey</div>
        </td>
        <td>
            <div class="linea">Recibí conforme</div>
        </td>
    </tr>
</table>
@endsection