<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Claves de idempotencia: un reintento con la misma clave no repite la operación

        Schema::create('claves_idempotencia', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 80);
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete()->restrictOnUpdate();
            $table->string('ruta', 200);
            $table->smallInteger('respuesta_estado')->nullable();
            $table->jsonb('respuesta_cuerpo')->nullable();
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['clave', 'usuario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claves_idempotencia');
    }
};
