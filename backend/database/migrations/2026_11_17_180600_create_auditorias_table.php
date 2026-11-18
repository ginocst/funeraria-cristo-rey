<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Bitácora: sin edición ni borrado. No lleva campos de trazabilidad.

        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->restrictOnDelete()->restrictOnUpdate();
            $table->string('accion', 60);
            $table->string('entidad', 60);
            $table->string('entidad_id', 40)->nullable();
            $table->jsonb('antes')->nullable();
            $table->jsonb('despues')->nullable();
            $table->string('motivo', 250)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('fecha_utc', 0)->default(DB::raw("(now() AT TIME ZONE 'utc')"));
            $table->index(['entidad', 'entidad_id'], 'auditorias_entidad_idx');
        });

        DB::statement('CREATE INDEX auditorias_fecha_idx ON auditorias (fecha_utc DESC)');

        DB::statement(<<<'SQL'
            CREATE TRIGGER auditorias_inmutable BEFORE UPDATE OR DELETE ON auditorias
                FOR EACH ROW EXECUTE FUNCTION fn_registro_inmutable()
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};