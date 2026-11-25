<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->restrictOnDelete()->restrictOnUpdate();
            $table->string('codigo', 30);
            $table->string('nombre', 160);
            $table->string('tipo', 20);
            $table->string('material', 60)->nullable();
            $table->string('color', 60)->nullable();
            $table->string('medidas', 80)->nullable();
            $table->text('descripcion')->nullable();
            $table->bigInteger('precio');
            $table->integer('stock')->default(0);
            $table->integer('umbral_minimo')->default(2);
            $table->boolean('infantil')->default(false);
            $table->boolean('publico')->default(false);
            $table->string('foto_ruta', 255)->nullable();
            $table->jsonb('atributos')->default(DB::raw("'{}'::jsonb"));
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->index('atributos', 'productos_atributos_gin', 'gin');
        });

        DB::statement('CREATE UNIQUE INDEX productos_codigo_unico ON productos (upper(codigo)) WHERE deleted_at IS NULL');

        // El stock nunca queda negativo
        DB::statement(<<<'SQL'
            ALTER TABLE productos
                ADD CONSTRAINT productos_tipo_check CHECK (tipo IN ('ataud', 'urna', 'urna_cremacion', 'elemento')),
                ADD CONSTRAINT productos_precio_check CHECK (precio >= 0),
                ADD CONSTRAINT productos_stock_check CHECK (stock >= 0),
                ADD CONSTRAINT productos_umbral_minimo_check CHECK (umbral_minimo >= 0)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
