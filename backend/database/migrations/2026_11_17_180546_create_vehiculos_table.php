<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vehiculos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            $table->string('tipo', 20);
            $table->string('patente', 10)->nullable();
            $table->string('marca', 60)->nullable();
            $table->string('modelo', 60)->nullable();
            $table->smallInteger('anio')->nullable();
            $table->string('origen', 10)->default('propio');
            $table->string('proveedor_externo', 160)->nullable();
            $table->boolean('activo')->default(true);
            $table->text('notas')->nullable();
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE vehiculos
                ADD CONSTRAINT vehiculos_tipo_check CHECK (tipo IN ('carroza', 'instalacion', 'apoyo')),
                ADD CONSTRAINT vehiculos_anio_check CHECK (anio IS NULL OR anio BETWEEN 1950 AND 2100),
                ADD CONSTRAINT vehiculos_origen_check CHECK (origen IN ('propio', 'externo')),
                ADD CONSTRAINT vehiculos_externo_con_proveedor CHECK (origen = 'propio' OR proveedor_externo IS NOT NULL)
        SQL);

        DB::statement('CREATE UNIQUE INDEX vehiculos_patente_unica ON vehiculos (upper(patente)) WHERE patente IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculos');
    }
};