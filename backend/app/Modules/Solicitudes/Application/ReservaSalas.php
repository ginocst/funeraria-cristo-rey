<?php

namespace App\Modules\Solicitudes\Application;

use App\Modules\Solicitudes\Infrastructure\Models\SalaVelatoria;
use App\Shared\Domain\ReglaNegocioException;
use App\Shared\Infrastructure\HoraLocal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Salas velatorias de la funeraria: una sala queda ocupada desde el inicio del velatorio
 * hasta el traslado por toda solicitud vigente (en revisión, observada o aceptada)
 */
class ReservaSalas
{
    public const ESTADOS_QUE_OCUPAN = ['en_revision', 'con_observaciones', 'aceptada'];

    /**
     * Identifica la sala propia escrita como lugar del velatorio y exige que esté disponible
     * y libre. Devuelve null si es un lugar externo. Debe llamarse dentro de una transacción.
     */
    public function reservar(string $lugar, CarbonImmutable $inicio, CarbonImmutable $fin, ?int $solicitudId): ?SalaVelatoria
    {
        $sala = $this->identificar($lugar);
        if (! $sala) {
            return null;
        }
        if ($sala->estado !== 'disponible') {
            throw new ReglaNegocioException("La {$sala->nombre} está ".SalaVelatoria::ESTADOS[$sala->estado].'. Elija otro lugar.', 'sala_no_disponible');
        }
        $ocupante = $this->cruces($inicio, $fin, $solicitudId)->where('s.sala_velatorio_id', $sala->id)->first();
        if ($ocupante) {
            $hora = fn (string $utc) => HoraLocal::formato(CarbonImmutable::parse($utc, 'UTC'));
            throw ReglaNegocioException::conflicto("La {$sala->nombre} está ocupada del {$hora($ocupante->velatorio_inicio)} al {$hora($ocupante->traslado_at)} por el expediente {$ocupante->codigo}.", 'sala_ocupada');
        }

        return $sala;
    }

    /** La sala se reconoce por su nombre, su dirección o ambos, sin distinguir mayúsculas ni espacios */
    private function identificar(string $lugar): ?SalaVelatoria
    {
        $normalizar = fn (string $texto) => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $texto)));
        $buscado = $normalizar($lugar);

        // El bloqueo evita que dos registros simultáneos tomen la misma sala
        return SalaVelatoria::lockForUpdate()->orderBy('id')->get()->first(
            fn (SalaVelatoria $s) => in_array($buscado, array_map($normalizar, [$s->nombre, $s->direccion, $s->lugar]), true)
        );
    }

    /** Servicios vigentes que aún no han salido de su sala, del más próximo al más lejano */
    public function proximas(?int $salaId = null): Collection
    {
        return DB::table('solicitudes as s')->join('expedientes as e', 'e.id', '=', 's.expediente_id')->join('difuntos as d', 'd.id', '=', 'e.difunto_id')
            ->whereNull('s.deleted_at')->whereIn('s.estado', self::ESTADOS_QUE_OCUPAN)->where('s.traslado_at', '>', now())
            ->whereNotNull('s.sala_velatorio_id')->when($salaId, fn ($q) => $q->where('s.sala_velatorio_id', $salaId))
            ->orderBy('s.velatorio_inicio')
            ->get(['s.id as solicitud_id', 's.sala_velatorio_id', 's.estado', 'e.codigo', 'd.nombre as difunto', 's.velatorio_inicio', 's.traslado_at']);
    }

    /** @return Collection<int, string> sala ocupada en el intervalo => código del expediente que la ocupa */
    public function ocupadas(CarbonImmutable $inicio, CarbonImmutable $fin, ?int $excluir = null): Collection
    {
        return $this->cruces($inicio, $fin, $excluir)->whereNotNull('s.sala_velatorio_id')->pluck('e.codigo', 's.sala_velatorio_id');
    }

    private function cruces(CarbonImmutable $inicio, CarbonImmutable $fin, ?int $excluir)
    {
        return DB::table('solicitudes as s')->join('expedientes as e', 'e.id', '=', 's.expediente_id')
            ->whereNull('s.deleted_at')->whereIn('s.estado', self::ESTADOS_QUE_OCUPAN)
            ->where('s.velatorio_inicio', '<', $fin->utc())->where('s.traslado_at', '>', $inicio->utc())
            ->when($excluir, fn ($q) => $q->where('s.id', '!=', $excluir))
            ->orderBy('s.velatorio_inicio')
            ->select(['s.sala_velatorio_id', 'e.codigo', 's.velatorio_inicio', 's.traslado_at']);
    }
}
