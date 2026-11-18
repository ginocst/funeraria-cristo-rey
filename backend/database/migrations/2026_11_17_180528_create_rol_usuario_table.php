<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // vigencia_hasta se usa para la suplencia temporal del administrador: al vencer, el rol deja de aplicarse solo

        Schema::create('rol_usuario', function (Blueprint $table) {
            $table->foreignId('rol_id')->constrained('roles')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamp('vigencia_hasta', 0)->nullable();
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->primary(['rol_id', 'usuario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rol_usuario');
    }
};