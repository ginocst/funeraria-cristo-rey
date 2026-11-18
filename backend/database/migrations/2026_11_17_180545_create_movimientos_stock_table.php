<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Libro inmutable de entradas (incluidos ingresos de mercadería) y salidas

        Schema::create('movimientos_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('solicitud_id')->nullable()->constrained('solicitudes')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->restrictOnDelete()->restrictOnUpdate();
            $table->string('tipo', 20);
            $table->integer('cantidad');
            $table->integer('stock_resultante');
            $table->string('motivo', 250);
            $table->string('documento', 60)->nullable();
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE movimientos_stock
                ADD CONSTRAINT movimientos_stock_tipo_check CHECK (tipo IN ('ingreso', 'salida', 'reposicion', 'ajuste')),
                ADD CONSTRAINT movimientos_stock_cantidad_check CHECK (cantidad <> 0),
                ADD CONSTRAINT movimientos_stock_stock_resultante_check CHECK (stock_resultante >= 0)
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER movimientos_stock_inmutable BEFORE UPDATE OR DELETE ON movimientos_stock
                FOR EACH ROW EXECUTE FUNCTION fn_registro_inmutable()
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_stock');
    }
};