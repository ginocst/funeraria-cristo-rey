<?php

namespace App\Console\Commands;

use App\Modules\Notificaciones\Application\ProcesarOutbox;
use Illuminate\Console\Command;

/** Trabajador asíncrono del outbox */
class TrabajarOutbox extends Command
{
    protected $signature = 'outbox:trabajar {--una-vez : Procesa un solo lote y termina} {--intervalo=3 : Segundos entre consultas}';

    protected $description = 'Consulta periódicamente el outbox y registra los avisos pendientes';

    public function handle(ProcesarOutbox $procesar): int
    {
        do {
            $procesados = $procesar->lote();
            if ($procesados > 0) {
                $this->info(now()->toDateTimeString() . ": $procesados eventos procesados");
            }
            if ($this->option('una-vez')) {
                break;
            }
            sleep(max(1, (int) $this->option('intervalo')));
        } while (true);

        return self::SUCCESS;
    }
}
