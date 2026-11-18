<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete()->restrictOnUpdate();
            $table->string('nombre', 120);
            $table->string('rut', 12);
            $table->string('email', 160);
            $table->string('telefono', 30)->nullable();
            $table->string('password', 255);
            $table->boolean('activo')->default(true);
            $table->string('remember_token', 100)->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at', 0)->nullable();
            $table->timestamp('ultimo_acceso_at', 0)->nullable();
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
        });

        // El correo y el RUT se pueden reutilizar cuando el usuario anterior está eliminado (borrado lógico)
        DB::statement('CREATE UNIQUE INDEX usuarios_email_unico ON usuarios (lower(email)) WHERE deleted_at IS NULL');

        DB::statement('CREATE UNIQUE INDEX usuarios_rut_unico ON usuarios (upper(rut)) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};