<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fotos adicionales de un modelo (la foto principal, vista superior, sigue en productos.foto_ruta)
        Schema::create('producto_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete()->restrictOnUpdate();
            $table->string('ruta', 255);
            $table->string('vista', 20);
            $table->smallInteger('orden')->default(0);
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->index(['producto_id', 'orden']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE producto_fotos
                ADD CONSTRAINT producto_fotos_vista_check CHECK (vista IN ('abierta', 'lateral', 'otra'))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_fotos');
    }
};
