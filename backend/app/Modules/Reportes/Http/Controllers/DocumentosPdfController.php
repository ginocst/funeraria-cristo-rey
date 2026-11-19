<?php

namespace App\Modules\Reportes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Expedientes\Infrastructure\Models\Expediente;
use App\Modules\Finanzas\Infrastructure\Models\Comprobante;
use App\Modules\Reportes\Application\Indicadores;
use App\Modules\Reportes\Infrastructure\Exports\ReporteMensualExport;
use App\Modules\Solicitudes\Infrastructure\Models\Solicitud;
use App\Shared\Contratos\IAuditoria;
use App\Shared\Contratos\IExpediente;
use App\Shared\Infrastructure\Parametros;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Documentos imprimibles y reportes: orden de servicio,
 * comprobante de pago y reporte mensual en PDF y Excel. Los PDF se entregan
 * «inline» para verlos en la interfaz y descargarlos desde ahí
 */
class DocumentosPdfController extends Controller
{
    public function __construct(private IAuditoria $auditoria) {}

    public function ordenServicio(int $id, IExpediente $expedientes, Parametros $parametros): Response
    {
        $s = Solicitud::with([
            'expediente.cliente',
            'expediente.difunto',
            'expediente.actividades',
            'sucursal',
            'productos',
            'asignacion.vehiculo',
            'asignacion.equipo',
            'evaluador'
        ])->findOrFail($id);
        $this->auditoria->registrar('imprimir_orden', 'solicitudes', $id);

        return $this->pdf('pdf.orden', [
            'solicitud' => $s,
            'resumen' => $expedientes->resumenFinanciero($s->expediente_id),
            'presentacion' => $s->traslado_at?->copy()->subMinutes($parametros->entero('presentacion_previa_minutos')),
        ], 'orden-servicio-' . $s->expediente->codigo . '.pdf');
    }

    public function comprobante(int $id, IExpediente $expedientes): Response
    {
        $c = Comprobante::with(['pago.medioPago', 'pago.registradoPor'])->findOrFail($id);
        $expediente = Expediente::with('cliente', 'difunto')->findOrFail($c->pago->expediente_id);

        return $this->pdf('pdf.comprobante', [
            'comprobante' => $c,
            'pago' => $c->pago,
            'expediente' => $expediente,
            'resumen' => $expedientes->resumenFinanciero($expediente->id),
        ], 'comprobante-' . $c->folio . '.pdf');
    }

    public function mensualJson(Request $request, Indicadores $indicadores): JsonResponse
    {
        return response()->json($indicadores->mensual($this->mes($request)));
    }

    public function mensualPdf(Request $request, Indicadores $indicadores): Response
    {
        $datos = $indicadores->mensual($mes = $this->mes($request));
        $this->auditoria->registrar('reporte_mensual_pdf', 'reportes', $mes);

        return $this->pdf('pdf.reporte-mensual', $datos, "reporte-$mes.pdf");
    }

    public function mensualExcel(Request $request, Indicadores $indicadores): BinaryFileResponse
    {
        $datos = $indicadores->mensual($mes = $this->mes($request));
        $this->auditoria->registrar('reporte_mensual_excel', 'reportes', $mes);

        return Excel::download(new ReporteMensualExport($datos), "reporte-$mes.xlsx");
    }

    private function mes(Request $request): string
    {
        return $request->validate(['mes' => ['nullable', 'date_format:Y-m']])['mes'] ?? now(config('funeraria.zona_horaria_local'))->format('Y-m');
    }

    private function pdf(string $vista, array $datos, string $nombre): Response
    {
        $logo = 'data:image/png;base64,' . base64_encode((string) file_get_contents(public_path('img/logo.png')));

        return Pdf::loadView($vista, [...$datos, 'logo' => $logo])->setPaper('letter')->stream($nombre);
    }
}
