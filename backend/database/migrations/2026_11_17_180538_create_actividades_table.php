<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes')->restrictOnDelete()->restrictOnUpdate();
            $table->string('tipo', 20);
            $table->string('lugar', 200);
            $table->timestamp('inicio', 0);
            $table->timestamp('fin', 0)->nullable();
            $table->text('notas')->nullable();
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE actividades
                ADD CONSTRAINT actividades_tipo_check CHECK (tipo IN ('retiro', 'velatorio', 'misa', 'traslado', 'sepultacion', 'otra')),
                ADD CONSTRAINT actividades_intervalo_coherente CHECK (fin IS NULL OR fin > inicio)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('actividades');
    }
};
