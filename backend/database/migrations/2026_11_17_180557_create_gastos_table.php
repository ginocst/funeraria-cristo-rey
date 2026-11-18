<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gastos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->restrictOnDelete()->restrictOnUpdate();
            $table->string('categoria', 40);
            $table->string('descripcion', 250);
            $table->bigInteger('monto');
            $table->date('fecha');
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
        });

        DB::statement('ALTER TABLE gastos ADD CONSTRAINT gastos_monto_check CHECK (monto > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('gastos');
    }
};