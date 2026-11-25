<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Una asignación por solicitud. Se crea al registrar la solicitud con activa = false y se activa al
        // aceptarla; desde ese momento la restricción EXCLUDE impide el traslape del vehículo propio.

        Schema::create('asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->unique()->constrained('solicitudes')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->restrictOnDelete()->restrictOnUpdate();
            $table->boolean('externo')->default(false);
            $table->timestamp('inicio', 0);
            $table->timestamp('fin', 0);
            // Columna que PostgreSQL calcula sola: el rango [inicio, fin) (incluye el inicio y excluye el fin).
            $table->rawColumn('intervalo', "tsrange GENERATED ALWAYS AS (tsrange(inicio, fin, '[)')) STORED");
            $table->boolean('activa')->default(false);
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE asignaciones
                ADD CONSTRAINT asignaciones_intervalo_coherente CHECK (fin > inicio),
                ADD CONSTRAINT asignaciones_sin_traslape_vehiculo EXCLUDE USING gist (vehiculo_id WITH =, intervalo WITH &&)
                    WHERE (activa AND NOT externo AND deleted_at IS NULL)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones');
    }
};
