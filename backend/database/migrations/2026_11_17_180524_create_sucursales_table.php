<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sucursales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('direccion', 200)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
        });

        DB::statement('CREATE UNIQUE INDEX sucursales_nombre_unico ON sucursales (lower(nombre)) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('sucursales');
    }
};