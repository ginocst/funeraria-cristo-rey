<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const VIGENTES = ['en_revision', 'con_observaciones', 'aceptada'];

    /** Nombre genérico que usaban los registros anteriores para la sala de la funeraria */
    private const NOMBRE_ANTERIOR = 'Sala Velatoria Cristo Rey, Temuco';

    public function up(): void
    {
        // Salas velatorias que maneja la funeraria: una sala no admite dos servicios a la vez.
        // Cualquier otro lugar se escribe a mano en la solicitud y no se controla.

        Schema::create('salas_velatorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            $table->string('direccion', 110);
            $table->smallInteger('capacidad')->nullable();
            $table->string('estado', 20)->default('disponible');
            $table->string('observaciones', 250)->nullable();
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
        });
        DB::statement("ALTER TABLE salas_velatorias ADD CONSTRAINT salas_velatorias_estado_check CHECK (estado IN ('disponible', 'mantenimiento', 'fuera_de_servicio'))");
        DB::statement('CREATE UNIQUE INDEX salas_velatorias_nombre_unico ON salas_velatorias (lower(nombre)) WHERE deleted_at IS NULL');

        Schema::table('solicitudes', function (Blueprint $table) {
            // Vacío cuando el velatorio es en un lugar que no es de la funeraria
            $table->foreignId('sala_velatorio_id')->nullable()->constrained('salas_velatorias')->restrictOnDelete()->restrictOnUpdate();
        });

        // La sala está ocupada desde el inicio del velatorio hasta el traslado, mientras la solicitud siga vigente
        DB::statement(<<<'SQL'
            ALTER TABLE solicitudes
                ADD CONSTRAINT solicitudes_sala_sin_traslape
                EXCLUDE USING gist (sala_velatorio_id WITH =, tsrange(velatorio_inicio, traslado_at, '[)') WITH &&)
                WHERE (sala_velatorio_id IS NOT NULL AND deleted_at IS NULL AND estado IN ('en_revision', 'con_observaciones', 'aceptada'))
        SQL);

        $principal = $this->crearSala('Sala Velatoria Principal', 'Calle Lautaro 195, Temuco');
        $this->crearSala('Sala Velatoria Casa Matriz', 'Manuel Montt 1991, Población Santa Elena, Temuco');

        // Lo registrado con el nombre genérico pasa a la sala principal
        $lugar = 'Sala Velatoria Principal, Calle Lautaro 195, Temuco';
        DB::table('actividades')->where('lugar', self::NOMBRE_ANTERIOR)->update(['lugar' => $lugar]);
        DB::table('solicitudes')->where('lugar_velatorio', self::NOMBRE_ANTERIOR)->update(['lugar_velatorio' => $lugar]);
        $this->asociar($principal, $lugar);
    }

    private function crearSala(string $nombre, string $direccion): int
    {
        return DB::table('salas_velatorias')->insertGetId(['nombre' => $nombre, 'direccion' => $direccion, 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Asocia las solicitudes ya registradas en ese lugar. Si dos vigentes se cruzan, la sala queda
     * para la aceptada (o la más antigua); la otra se revisa al aceptarla o modificarla.
     */
    private function asociar(int $sala, string $lugar): void
    {
        $solicitudes = DB::table('solicitudes')->where('lugar_velatorio', $lugar)
            ->orderByRaw("CASE WHEN estado = 'aceptada' THEN 0 ELSE 1 END")->orderBy('id')
            ->get(['id', 'estado', 'velatorio_inicio', 'traslado_at', 'deleted_at']);
        foreach ($solicitudes as $s) {
            $cruzada = ! $s->deleted_at && in_array($s->estado, self::VIGENTES, true)
                && DB::table('solicitudes')->where('sala_velatorio_id', $sala)->whereNull('deleted_at')->whereIn('estado', self::VIGENTES)
                ->where('velatorio_inicio', '<', $s->traslado_at)->where('traslado_at', '>', $s->velatorio_inicio)->exists();
            if (! $cruzada) {
                DB::table('solicitudes')->where('id', $s->id)->update(['sala_velatorio_id' => $sala]);
            }
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE solicitudes DROP CONSTRAINT solicitudes_sala_sin_traslape');
        Schema::table('solicitudes', fn(Blueprint $table) => $table->dropConstrainedForeignId('sala_velatorio_id'));
        Schema::dropIfExists('salas_velatorias');
    }
};
