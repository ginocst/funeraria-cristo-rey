<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pagos: libro inmutable. Una anulación es un pago negativo que apunta al original (anula_pago_id);
        // el original nunca se edita ni se borra.

        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('cuota_id')->nullable()->constrained('cuotas')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('medio_pago_id')->constrained('medios_pago')->restrictOnDelete()->restrictOnUpdate();
            $table->string('tipo', 12);
            $table->bigInteger('monto');
            $table->string('referencia', 80)->nullable();
            $table->foreignId('anula_pago_id')->nullable()->unique()->constrained('pagos')->restrictOnDelete()->restrictOnUpdate();
            $table->string('motivo_anulacion', 250)->nullable();
            $table->timestamp('fecha', 0)->default(DB::raw("(now() AT TIME ZONE 'utc')"));
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->index('expediente_id', 'pagos_expediente_idx');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE pagos
                ADD CONSTRAINT pagos_tipo_check CHECK (tipo IN ('abono', 'anulacion')),
                ADD CONSTRAINT pagos_monto_check CHECK (monto <> 0),
                ADD CONSTRAINT pagos_signo_coherente CHECK (
                    (tipo = 'abono' AND monto > 0 AND anula_pago_id IS NULL)
                    OR (tipo = 'anulacion' AND monto < 0 AND anula_pago_id IS NOT NULL AND motivo_anulacion IS NOT NULL)
                )
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER pagos_inmutable BEFORE UPDATE OR DELETE ON pagos
                FOR EACH ROW EXECUTE FUNCTION fn_registro_inmutable()
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
