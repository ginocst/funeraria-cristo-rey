<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El código correlativo (EXP-00001) lo entrega una secuencia de la base
        // para que no se repita aunque haya varias copias de la API
        DB::statement('CREATE SEQUENCE expedientes_codigo_seq START 1');

        Schema::create('expedientes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->default(DB::raw("('EXP-' || lpad(nextval('expedientes_codigo_seq')::text, 5, '0'))"))->unique();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('difunto_id')->unique()->constrained('difuntos')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('convenio_id')->nullable()->constrained('convenios')->restrictOnDelete()->restrictOnUpdate();
            $table->string('estado', 20)->default('abierto');
            $table->text('observaciones')->nullable();
            $table->timestamps(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->softDeletes('deleted_at', 0);
        });

        // La secuencia pasa a ser parte de la columna: si la tabla se elimina (down o migrate:fresh), se elimina con ella.
        DB::statement('ALTER SEQUENCE expedientes_codigo_seq OWNED BY expedientes.codigo');

        DB::statement("ALTER TABLE expedientes ADD CONSTRAINT expedientes_estado_check CHECK (estado IN ('abierto', 'en_servicio', 'cerrado', 'anulado'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('expedientes');
    }
};
