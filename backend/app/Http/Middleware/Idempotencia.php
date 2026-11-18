<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Encabezado Idempotency-Key: si un reintento llega con la misma clave,
 * se devuelve la respuesta original sin repetir la operación
 */
class Idempotencia
{
    public function handle(Request $request, Closure $next): Response
    {
        $clave = (string) $request->header('Idempotency-Key');
        if ($clave === '' || strlen($clave) > 80) {
            return response()->json(['message' => 'Falta el encabezado Idempotency-Key (máximo 80 caracteres).', 'codigo' => 'idempotencia'], 428);
        }
        $usuarioId = $request->user()->id;

        return Cache::lock("idempotencia:$usuarioId:$clave", 30)->block(15, function () use ($request, $next, $clave, $usuarioId) {
            $previa = DB::table('claves_idempotencia')->where(['clave' => $clave, 'usuario_id' => $usuarioId])->first();
            if ($previa) {
                if ($previa->ruta !== $request->path()) {
                    return response()->json(['message' => 'La clave de idempotencia ya se usó en otra operación.', 'codigo' => 'idempotencia'], 422);
                }

                return response()->json(json_decode($previa->respuesta_cuerpo, true), $previa->respuesta_estado)->header('Idempotent-Replayed', 'true');
            }

            $respuesta = $next($request);
            if ($respuesta->isSuccessful()) {
                DB::table('claves_idempotencia')->insert([
                    'clave' => $clave,
                    'usuario_id' => $usuarioId,
                    'ruta' => $request->path(),
                    'respuesta_estado' => $respuesta->getStatusCode(),
                    'respuesta_cuerpo' => $respuesta->getContent(),
                    'created_at' => now(),
                    'updated_at' => now(),
                    'created_by' => $usuarioId,
                ]);
            }

            return $respuesta;
        });
    }
}
