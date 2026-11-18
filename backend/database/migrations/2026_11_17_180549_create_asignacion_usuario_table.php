<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('asignacion_usuario', function (Blueprint $table) {
            $table->foreignId('asignacion_id')->constrained('asignaciones')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete()->restrictOnUpdate();
            $table->string('funcion', 20)->default('apoyo');
            $table->timestamp('inicio', 0);
            $table->timestamp('fin', 0);
            $table->rawColumn('intervalo', "tsrange GENERATED ALWAYS AS (tsrange(inicio, fin, '[)')) STORED");
            $table->boolean('activa')->default(false);
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->primary(['asignacion_id', 'usuario_id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE asignacion_usuario
                ADD CONSTRAINT asignacion_usuario_funcion_check CHECK (funcion IN ('conductor', 'apoyo')),
                ADD CONSTRAINT asignacion_usuario_intervalo_coherente CHECK (fin > inicio),
                ADD CONSTRAINT asignacion_usuario_sin_traslape EXCLUDE USING gist (usuario_id WITH =, intervalo WITH &&)
                    WHERE (activa AND deleted_at IS NULL)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('asignacion_usuario');
    }
};