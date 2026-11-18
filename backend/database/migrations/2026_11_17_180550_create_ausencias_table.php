<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ausencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete()->restrictOnUpdate();
            $table->string('tipo', 20);
            $table->date('desde');
            $table->date('hasta');
            $table->string('motivo', 250)->nullable();
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE ausencias
                ADD CONSTRAINT ausencias_tipo_check CHECK (tipo IN ('vacaciones', 'licencia', 'permiso', 'otra')),
                ADD CONSTRAINT ausencias_intervalo_coherente CHECK (hasta >= desde)
        SQL);

        DB::statement('CREATE INDEX ausencias_usuario_idx ON ausencias (usuario_id, desde, hasta) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('ausencias');
    }
};