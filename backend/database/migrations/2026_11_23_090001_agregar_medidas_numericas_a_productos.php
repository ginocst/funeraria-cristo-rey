<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Largo, ancho y alto en centímetros: el largo y el ancho varían según la contextura
        // (la columna `medidas` queda como texto libre para elementos como «4 piezas»)
        Schema::table('productos', function (Blueprint $table) {
            $table->smallInteger('largo_cm')->nullable()->after('medidas');
            $table->smallInteger('ancho_cm')->nullable()->after('largo_cm');
            $table->smallInteger('alto_cm')->nullable()->after('ancho_cm');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE productos
                ADD CONSTRAINT productos_largo_cm_check CHECK (largo_cm IS NULL OR largo_cm BETWEEN 1 AND 400),
                ADD CONSTRAINT productos_ancho_cm_check CHECK (ancho_cm IS NULL OR ancho_cm BETWEEN 1 AND 400),
                ADD CONSTRAINT productos_alto_cm_check CHECK (alto_cm IS NULL OR alto_cm BETWEEN 1 AND 400)
        SQL);

        // Los modelos ya cargados con texto «195 × 60 × 45 cm» conservan sus medidas
        DB::statement(<<<'SQL'
            UPDATE productos p
               SET largo_cm = (s.m)[1]::int,
                   ancho_cm = (s.m)[2]::int,
                   alto_cm = (s.m)[3]::int
              FROM (
                  SELECT id, regexp_match(medidas, '^\s*(\d{1,3})\s*[×xX]\s*(\d{1,3})(?:\s*[×xX]\s*(\d{1,3}))?\s*cm\s*$') AS m
                    FROM productos
                   WHERE medidas IS NOT NULL
              ) s
             WHERE s.id = p.id AND s.m IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE productos DROP CONSTRAINT IF EXISTS productos_largo_cm_check, DROP CONSTRAINT IF EXISTS productos_ancho_cm_check, DROP CONSTRAINT IF EXISTS productos_alto_cm_check');
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['largo_cm', 'ancho_cm', 'alto_cm']);
        });
    }
};
