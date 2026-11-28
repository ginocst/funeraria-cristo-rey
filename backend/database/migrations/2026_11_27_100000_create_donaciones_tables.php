<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Donaciones que entrega la funeraria: categorías, organizaciones y el historial de cada una

        Schema::create('categorias_organizacion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            $table->string('descripcion', 250)->nullable();
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
        });

        Schema::create('organizaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->constrained('categorias_organizacion')->restrictOnDelete()->restrictOnUpdate();
            $table->string('nombre', 120);
            $table->string('contacto', 120)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('notas', 250)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
        });

        Schema::create('donaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->restrictOnDelete()->restrictOnUpdate();
            $table->date('fecha');
            $table->bigInteger('monto');
            $table->string('descripcion', 250)->nullable();
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
        });

        DB::statement('ALTER TABLE donaciones ADD CONSTRAINT donaciones_monto_check CHECK (monto > 0)');
        DB::statement('CREATE UNIQUE INDEX categorias_organizacion_nombre_unico ON categorias_organizacion (lower(nombre)) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX organizaciones_nombre_unico ON organizaciones (lower(nombre)) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX donaciones_fecha_idx ON donaciones (fecha) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX donaciones_organizacion_idx ON donaciones (organizacion_id, fecha) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('donaciones');
        Schema::dropIfExists('organizaciones');
        Schema::dropIfExists('categorias_organizacion');
    }
};
