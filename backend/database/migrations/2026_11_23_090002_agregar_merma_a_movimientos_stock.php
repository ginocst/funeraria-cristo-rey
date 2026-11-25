<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Nuevo tipo de salida: urnas que se quiebran o dañan (merma). El libro sigue siendo inmutable.
        DB::statement(<<<'SQL'
            ALTER TABLE movimientos_stock
                DROP CONSTRAINT movimientos_stock_tipo_check,
                ADD CONSTRAINT movimientos_stock_tipo_check CHECK (tipo IN ('ingreso', 'salida', 'reposicion', 'ajuste', 'merma'))
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE movimientos_stock
                DROP CONSTRAINT movimientos_stock_tipo_check,
                ADD CONSTRAINT movimientos_stock_tipo_check CHECK (tipo IN ('ingreso', 'salida', 'reposicion', 'ajuste'))
        SQL);
    }
};
