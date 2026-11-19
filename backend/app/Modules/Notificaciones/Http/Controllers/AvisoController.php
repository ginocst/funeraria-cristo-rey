<?php

namespace App\Modules\Notificaciones\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notificaciones\Infrastructure\Models\Aviso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AvisoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $avisos = Aviso::where('usuario_id', $request->user()->id)->orderByDesc('id')->limit(30)->get();

        return response()->json([
            'data' => $avisos,
            'no_leidos' => Aviso::where('usuario_id', $request->user()->id)->whereNull('leido_at')->count(),
        ]);
    }

    public function leer(Request $request, int $id): JsonResponse
    {
        Aviso::where('usuario_id', $request->user()->id)->whereKey($id)->update(['leido_at' => now()]);

        return response()->json(null, 204);
    }

    public function leerTodos(Request $request): JsonResponse
    {
        Aviso::where('usuario_id', $request->user()->id)->whereNull('leido_at')->update(['leido_at' => now()]);

        return response()->json(null, 204);
    }

    /**
     * Server-Sent Events: mantiene la conexión abierta unos 25 segundos y envía
     * los avisos nuevos; el navegador (EventSource) se reconecta solo y envía
     * Last-Event-ID para continuar donde quedó. En el servidor de desarrollo de
     * PHP (php artisan serve), que atiende una petición a la vez, responde una
     * sola vez y termina, para no dejar bloqueada a toda la interfaz.
     */
    public function stream(Request $request): StreamedResponse
    {
        $usuarioId = $request->user()->id;
        $ultimo = (int) ($request->header('Last-Event-ID') ?: $request->query('desde', 0));
        // En la primera conexión solo interesan los avisos que lleguen desde ahora.
        if ($ultimo === 0) {
            $ultimo = (int) Aviso::where('usuario_id', $usuarioId)->max('id');
        }
        // Se libera la sesión para no bloquear otras peticiones del mismo usuario mientras dura el flujo
        $request->session()->save();
        // `php artisan serve` atiende una sola petición a la vez: un flujo de 25 s dejaría sin respuesta al resto de la API
        $corto = PHP_SAPI === 'cli-server';

        return response()->stream(function () use ($usuarioId, $ultimo, $corto) {
            // 25 s: menos que el tiempo de espera de CloudFront (30 s); el navegador reconecta solo
            $fin = time() + 25;
            echo "retry: 3000\n\n";
            // Fija desde el primer momento el último id que conoce el navegador: si la conexión se corta, al reconectar continúa desde aquí
            echo "id: $ultimo\nevent: conectado\ndata: {}\n\n";
            while (time() < $fin && ! connection_aborted()) {
                $nuevos = Aviso::where('usuario_id', $usuarioId)->where('id', '>', $ultimo)->orderBy('id')->get();
                foreach ($nuevos as $aviso) {
                    $ultimo = $aviso->id;
                    echo "id: {$aviso->id}\nevent: aviso\ndata: " . json_encode($aviso, JSON_UNESCAPED_UNICODE) . "\n\n";
                }
                echo ": latido\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                if ($corto) {
                    break;
                }
                sleep(3);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
