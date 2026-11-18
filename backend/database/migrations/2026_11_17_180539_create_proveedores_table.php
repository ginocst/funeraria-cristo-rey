<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 160);
            $table->string('rut', 12)->nullable();
            $table->string('rubro', 80)->default('urnas');
            $table->string('contacto', 120)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('direccion', 200)->nullable();
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
        });

        DB::statement('CREATE UNIQUE INDEX proveedores_rut_unico ON proveedores (upper(rut)) WHERE rut IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};