<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_convenio', function (Blueprint $table) {
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('convenio_id')->constrained('convenios')->restrictOnDelete()->restrictOnUpdate();
            $table->string('numero_socio', 60)->nullable();
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->primary(['cliente_id', 'convenio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_convenio');
    }
};
