<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Días libres que pide el personal. Al aprobarse se registra la ausencia correspondiente,
        // que es la que impide asignar a la persona a un servicio en esas fechas.

        Schema::create('solicitudes_dias_libres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete()->restrictOnUpdate();
            $table->date('desde');
            $table->smallInteger('dias');
            $table->string('motivo', 250);
            $table->string('estado', 12)->default('pendiente');
            $table->smallInteger('dias_aprobados')->nullable();
            $table->string('motivo_rechazo', 250)->nullable();
            $table->foreignId('resuelta_por')->nullable()->constrained('usuarios')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamp('resuelta_at', 0)->nullable();
            $table->foreignId('ausencia_id')->nullable()->constrained('ausencias')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE solicitudes_dias_libres
                ADD CONSTRAINT dias_libres_estado_check CHECK (estado IN ('pendiente', 'aprobada', 'rechazada')),
                ADD CONSTRAINT dias_libres_dias_check CHECK (dias BETWEEN 1 AND 30),
                ADD CONSTRAINT dias_libres_aprobados_check CHECK (dias_aprobados IS NULL OR dias_aprobados BETWEEN 1 AND dias),
                ADD CONSTRAINT dias_libres_aprobacion_check CHECK (estado <> 'aprobada' OR dias_aprobados IS NOT NULL),
                ADD CONSTRAINT dias_libres_rechazo_check CHECK (estado <> 'rechazada' OR motivo_rechazo IS NOT NULL)
        SQL);

        DB::statement('CREATE INDEX dias_libres_usuario_idx ON solicitudes_dias_libres (usuario_id, estado) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_dias_libres');
    }
};
