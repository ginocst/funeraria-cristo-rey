<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('producto_solicitud', function (Blueprint $table) {
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('solicitud_id')->constrained('solicitudes')->restrictOnDelete()->restrictOnUpdate();
            $table->integer('cantidad');
            $table->bigInteger('precio_unitario');
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->primary(['producto_id', 'solicitud_id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE producto_solicitud
                ADD CONSTRAINT producto_solicitud_cantidad_check CHECK (cantidad > 0),
                ADD CONSTRAINT producto_solicitud_precio_unitario_check CHECK (precio_unitario >= 0)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_solicitud');
    }
};