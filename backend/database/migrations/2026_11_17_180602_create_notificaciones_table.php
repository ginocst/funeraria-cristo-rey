<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Outbox transaccional: el evento se escribe en la misma transacción que el cambio;
        // un trabajador asíncrono lo procesa después

        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->string('evento', 60);
            $table->jsonb('payload')->default(DB::raw("'{}'::jsonb"));
            $table->string('estado', 12)->default('pendiente');
            $table->smallInteger('reintentos')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('procesado_at', 0)->nullable();
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
        });

        DB::statement("ALTER TABLE notificaciones ADD CONSTRAINT notificaciones_estado_check CHECK (estado IN ('pendiente', 'procesado', 'fallido'))");

        DB::statement("CREATE INDEX notificaciones_pendientes_idx ON notificaciones (id) WHERE estado = 'pendiente'");
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};