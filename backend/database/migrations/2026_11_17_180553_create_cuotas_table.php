<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cuotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes')->restrictOnDelete()->restrictOnUpdate();
            $table->smallInteger('numero');
            $table->bigInteger('monto');
            $table->date('vencimiento');
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE cuotas
                ADD CONSTRAINT cuotas_numero_check CHECK (numero > 0),
                ADD CONSTRAINT cuotas_monto_check CHECK (monto > 0)
        SQL);

        DB::statement('CREATE UNIQUE INDEX cuotas_numero_unico ON cuotas (expediente_id, numero) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('cuotas');
    }
};