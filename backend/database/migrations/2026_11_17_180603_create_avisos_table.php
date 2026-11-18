<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Avisos que ve cada usuario dentro del sistema (SSE)

        Schema::create('avisos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('notificacion_id')->nullable()->constrained('notificaciones')->restrictOnDelete()->restrictOnUpdate();
            $table->string('tipo', 30)->default('info');
            $table->string('titulo', 160);
            $table->string('mensaje', 400);
            $table->string('enlace', 200)->nullable();
            $table->timestamp('leido_at', 0)->nullable();
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
        });

        DB::statement("ALTER TABLE avisos ADD CONSTRAINT avisos_tipo_check CHECK (tipo IN ('info', 'exito', 'alerta', 'critico'))");

        DB::statement('CREATE INDEX avisos_usuario_idx ON avisos (usuario_id, id DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos');
    }
};