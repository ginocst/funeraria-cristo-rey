<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Solo los datos mínimos del fallecido

        Schema::create('difuntos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 160);
            $table->string('rut', 12)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->date('fecha_fallecimiento');
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
        });

        DB::statement('ALTER TABLE difuntos ADD CONSTRAINT difuntos_fechas_coherentes CHECK (fecha_nacimiento IS NULL OR fecha_nacimiento <= fecha_fallecimiento)');
    }

    public function down(): void
    {
        Schema::dropIfExists('difuntos');
    }
};