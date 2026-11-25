<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete()->restrictOnUpdate();
            $table->string('estado', 20)->default('en_revision');
            $table->bigInteger('valor_servicio');
            $table->bigInteger('descuento')->default(0);
            $table->timestamp('velatorio_inicio', 0);
            $table->string('lugar_velatorio', 200);
            $table->timestamp('traslado_at', 0);
            $table->string('lugar_sepultura', 200);
            $table->string('sepultura_detalle', 200)->nullable();
            $table->text('mensaje')->nullable();
            $table->text('comentario')->nullable();
            $table->foreignId('evaluada_por')->nullable()->constrained('usuarios')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamp('evaluada_at', 0)->nullable();
            $table->text('motivo_cancelacion')->nullable();
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE solicitudes
                ADD CONSTRAINT solicitudes_estado_check CHECK (estado IN ('en_revision', 'con_observaciones', 'aceptada', 'ejecutada', 'no_aplica', 'cancelada')),
                ADD CONSTRAINT solicitudes_valor_servicio_check CHECK (valor_servicio >= 0),
                ADD CONSTRAINT solicitudes_descuento_check CHECK (descuento >= 0),
                ADD CONSTRAINT solicitudes_traslado_tras_velatorio CHECK (traslado_at > velatorio_inicio)
        SQL);

        DB::statement('CREATE INDEX solicitudes_estado_idx ON solicitudes (estado) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes');
    }
};
