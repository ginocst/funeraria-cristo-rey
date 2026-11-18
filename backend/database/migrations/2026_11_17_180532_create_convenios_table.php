<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('convenios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('institucion', 160);
            $table->string('tipo', 30)->default('descuento');
            $table->decimal('descuento_pct', 5, 2)->default(0);
            $table->date('vigencia_desde');
            $table->date('vigencia_hasta')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('contacto', 160)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE convenios
                ADD CONSTRAINT convenios_tipo_check CHECK (tipo IN ('descuento', 'cuota_mortuoria', 'institucional')),
                ADD CONSTRAINT convenios_descuento_pct_check CHECK (descuento_pct BETWEEN 0 AND 100),
                ADD CONSTRAINT convenios_vigencia_coherente CHECK (vigencia_hasta IS NULL OR vigencia_hasta >= vigencia_desde)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('convenios');
    }
};