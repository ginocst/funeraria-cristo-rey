<?php

namespace App\Modules\Solicitudes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Solicitudes\Application\ReservaSalas;
use App\Modules\Solicitudes\Infrastructure\Models\SalaVelatoria;
use App\Shared\Contratos\IAuditoria;
use App\Shared\Domain\ReglaNegocioException;
use App\Shared\Http\CrudSimple;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Mantenedor de las salas velatorias propias: datos, estado y agenda (solo el Administrador las modifica) */
class SalaVelatoriaController extends Controller
{
    use CrudSimple {
        update as private actualizar;
        destroy as private eliminar;
    }

    public function __construct(private ReservaSalas $salas) {}

    /** Cada sala con los servicios que la tienen reservada de ahora en adelante */
    public function agenda(): JsonResponse
    {
        $reservas = $this->salas->proximas()->groupBy('sala_velatorio_id');

        return response()->json(SalaVelatoria::orderBy('id')->get()
            ->map(fn(SalaVelatoria $s) => [...$s->toArray(), 'reservas' => ($reservas[$s->id] ?? collect())->values()]));
    }

    /** Disponible, en mantenimiento o fuera de servicio, con una nota que explique por qué */
    public function cambiarEstado(Request $request, int $id, IAuditoria $auditoria): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['required', Rule::in(array_keys(SalaVelatoria::ESTADOS))],
            'observaciones' => ['nullable', 'string', 'max:250'],
        ]);

        return DB::transaction(function () use ($id, $datos, $auditoria) {
            $sala = SalaVelatoria::whereKey($id)->lockForUpdate()->firstOrFail();
            $programadas = $this->salas->proximas($id)->pluck('codigo');
            if ($datos['estado'] !== 'disponible' && $programadas->isNotEmpty()) {
                throw ReglaNegocioException::conflicto('La sala tiene servicios programados (' . $programadas->implode(', ') . '). Cambie su lugar antes de sacarla de uso.', 'en_uso');
            }
            $antes = ['estado' => $sala->estado];
            $sala->update(['estado' => $datos['estado'], 'observaciones' => $datos['observaciones'] ?? null]);
            $auditoria->registrar('cambiar_estado_sala', 'salas_velatorias', $id, $antes, ['estado' => $datos['estado']], $sala->observaciones);

            return response()->json($sala);
        });
    }

    /** Al cambiar el nombre o la dirección, las solicitudes vigentes de la sala muestran los nuevos */
    public function update(Request $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $respuesta = $this->actualizar($request, $id);
            DB::table('solicitudes')->where('sala_velatorio_id', $id)->whereIn('estado', ReservaSalas::ESTADOS_QUE_OCUPAN)
                ->update(['lugar_velatorio' => SalaVelatoria::findOrFail($id)->lugar]);

            return $respuesta;
        });
    }

    public function destroy(int $id): JsonResponse
    {
        if (DB::table('solicitudes')->where('sala_velatorio_id', $id)->whereNull('deleted_at')->whereIn('estado', ReservaSalas::ESTADOS_QUE_OCUPAN)->exists()) {
            throw ReglaNegocioException::conflicto('La sala tiene servicios vigentes. Márquela fuera de servicio cuando terminen.', 'en_uso');
        }

        return $this->eliminar($id);
    }

    protected function modelo(): string
    {
        return SalaVelatoria::class;
    }

    /** La sala principal primero (orden de registro) */
    protected function orden(): array
    {
        return ['id', 'asc'];
    }

    protected function buscables(): array
    {
        return ['nombre', 'direccion'];
    }

    protected function reglas(Request $request, $registro = null): array
    {
        // El estado se cambia aparte, porque exige revisar los servicios programados
        return [
            'nombre' => ['required', 'string', 'max:80', Rule::unique('salas_velatorias', 'nombre')->ignore($registro?->id)->whereNull('deleted_at')],
            'direccion' => ['required', 'string', 'max:110'],
            'capacidad' => ['nullable', 'integer', 'between:1,1000'],
            'observaciones' => ['nullable', 'string', 'max:250'],
        ];
    }
}
