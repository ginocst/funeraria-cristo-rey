<?php

namespace App\Console\Commands;

use App\Shared\Contratos\IEventos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Revisión diaria: publica un evento por cada cuota que venció ayer sin pagarse */
class RevisarCuotas extends Command
{
    protected $signature = 'cuotas:revisar';

    protected $description = 'Genera alertas de cuotas atrasadas';

    public function handle(IEventos $eventos): int
    {
        $ayer = now(config('funeraria.zona_horaria_local'))->subDay()->toDateString();
        $cuotas = DB::table('cuotas as cu')->join('expedientes as e', 'e.id', '=', 'cu.expediente_id')
            ->leftJoin(DB::raw('(SELECT cuota_id, SUM(monto) AS pagado FROM pagos WHERE cuota_id IS NOT NULL GROUP BY cuota_id) p'), 'p.cuota_id', '=', 'cu.id')
            ->whereNull('cu.deleted_at')->where('cu.vencimiento', $ayer)->whereRaw('COALESCE(p.pagado, 0) < cu.monto')
            ->get(['cu.id', 'cu.numero', 'e.id as expediente_id', 'e.codigo']);
        DB::transaction(fn() => $cuotas->each(fn($c) => $eventos->publicar('cuota.atrasada', (array) $c)));
        $this->info($cuotas->count() . ' cuotas atrasadas');

        return self::SUCCESS;
    }
}
