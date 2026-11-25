<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El folio del comprobante (C-000001) lo entrega una secuencia de la base: no se repite ni se salta por concurrencia.
        DB::statement('CREATE SEQUENCE comprobantes_folio_seq START 1');

        Schema::create('comprobantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->unique()->constrained('pagos')->restrictOnDelete()->restrictOnUpdate();
            $table->string('folio', 20)->default(DB::raw("('C-' || lpad(nextval('comprobantes_folio_seq')::text, 6, '0'))"))->unique();
            $table->timestamp('emitido_at', 0)->default(DB::raw("(now() AT TIME ZONE 'utc')"));
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
        });

        // La secuencia pasa a ser parte de la columna: si la tabla se elimina (down o migrate:fresh), se elimina con ella.
        DB::statement('ALTER SEQUENCE comprobantes_folio_seq OWNED BY comprobantes.folio');

        DB::statement(<<<'SQL'
            CREATE TRIGGER comprobantes_inmutable BEFORE UPDATE OR DELETE ON comprobantes
                FOR EACH ROW EXECUTE FUNCTION fn_registro_inmutable()
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobantes');
    }
};
