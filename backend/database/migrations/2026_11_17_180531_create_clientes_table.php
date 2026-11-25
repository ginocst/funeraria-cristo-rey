<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('rut', 12);
            $table->string('nombre', 160);
            $table->string('telefono', 30)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('direccion', 200)->nullable();
            $table->string('comuna', 80)->nullable();
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
        });

        DB::statement('CREATE UNIQUE INDEX clientes_rut_unico ON clientes (upper(rut)) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
