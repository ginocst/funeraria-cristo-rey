<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->nullable()->constrained('expedientes')->restrictOnDelete()->restrictOnUpdate();
            $table->string('tipo', 30);
            $table->string('disco', 30);
            $table->string('clave', 255);
            $table->string('nombre_original', 255);
            $table->string('mime', 100);
            $table->integer('tamano');
            $table->char('sha256', 64);
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE archivos
                ADD CONSTRAINT archivos_tipo_check CHECK (tipo IN ('certificado_defuncion', 'carnet_difunto', 'carnet_contratante', 'contrato', 'otro')),
                ADD CONSTRAINT archivos_tamano_check CHECK (tamano > 0)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('archivos');
    }
};