<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Impide modificar o borrar los libros inmutables (pagos, comprobantes, movimientos de stock y bitácora)
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_registro_inmutable() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'El registro de % es inmutable; use un movimiento compensatorio', TG_TABLE_NAME
                    USING ERRCODE = 'P0001';
            END;
            $$ LANGUAGE plpgsql;
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS fn_registro_inmutable()');
    }
};
