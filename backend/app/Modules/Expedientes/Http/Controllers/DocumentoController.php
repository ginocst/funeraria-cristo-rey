<?php

namespace App\Modules\Expedientes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Expedientes\Infrastructure\Models\Archivo;
use App\Modules\Expedientes\Infrastructure\Models\Expediente;
use App\Shared\Contratos\IAuditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documentos del expediente (certificado de defunción, carnets, contrato)
 * Lista blanca por tipo real (mimetypes lee el contenido, no la extensión),
 * límite de tamaño, nombre aleatorio y bucket privado.
 */
class DocumentoController extends Controller
{
    public function __construct(private IAuditoria $auditoria) {}

    public function store(Request $request, int $expedienteId): JsonResponse
    {
        Expediente::findOrFail($expedienteId);
        $request->validate([
            'tipo' => ['required', Rule::in(['certificado_defuncion', 'carnet_difunto', 'carnet_contratante', 'contrato', 'otro'])],
            'archivo' => ['required', 'file', 'max:8192', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp'],
        ]);
        $archivo = $request->file('archivo');
        $extension = match ($archivo->getMimeType()) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $clave = "expedientes/$expedienteId/".Str::uuid().'.'.$extension;
        Storage::disk('archivos')->put($clave, file_get_contents($archivo->getRealPath()));

        $registro = Archivo::create([
            'expediente_id' => $expedienteId,
            'tipo' => $request->input('tipo'),
            'disco' => 'archivos',
            'clave' => $clave,
            'nombre_original' => mb_substr($archivo->getClientOriginalName(), 0, 255),
            'mime' => $archivo->getMimeType(),
            'tamano' => $archivo->getSize(),
            'sha256' => hash_file('sha256', $archivo->getRealPath()),
        ]);
        $this->auditoria->registrar('subir_documento', 'archivos', $registro->id, null, ['tipo' => $registro->tipo, 'expediente_id' => $expedienteId]);

        return response()->json($registro, 201);
    }

    /** URL prefirmada de vida corta (S3) - ruta firmada temporal (desarrollo) */
    public function url(int $id): JsonResponse
    {
        $archivo = Archivo::findOrFail($id);
        $minutos = config('funeraria.url_prefirmada_minutos', 5);
        $url = config("filesystems.disks.{$archivo->disco}.driver") === 's3'
            ? Storage::disk($archivo->disco)->temporaryUrl($archivo->clave, now()->addMinutes($minutos))
            : URL::temporarySignedRoute('documentos.descargar', now()->addMinutes($minutos), ['id' => $id]);
        $this->auditoria->registrar('ver_documento', 'archivos', $id);

        return response()->json(['url' => $url, 'vence_en_minutos' => $minutos]);
    }

    /** Solo en desarrollo: entrega el archivo local tras validar la firma temporal */
    public function descargar(int $id): StreamedResponse
    {
        $archivo = Archivo::findOrFail($id);

        return Storage::disk($archivo->disco)->response($archivo->clave, $archivo->nombre_original, ['Content-Type' => $archivo->mime]);
    }

    public function destroy(int $id): JsonResponse
    {
        Archivo::findOrFail($id)->delete();
        $this->auditoria->registrar('eliminar', 'archivos', $id);

        return response()->json(null, 204);
    }
}
