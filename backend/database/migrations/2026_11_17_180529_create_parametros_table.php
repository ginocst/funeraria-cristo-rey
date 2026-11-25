<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Parámetros del negocio que dependen de decisiones del cliente

        Schema::create('parametros', function (Blueprint $table) {
            $table->string('clave', 60)->primary();
            $table->string('valor', 200);
            $table->string('descripcion', 250)->nullable();
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros');
    }
};
