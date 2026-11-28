<?php

namespace Database\Seeders;

use App\Shared\Infrastructure\HoraLocal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Donaciones de demostración, con fechas relativas al momento de la carga.
 * No hace nada si ya hay categorías, así que se puede ejecutar sobre una base en uso.
 * Los montos y motivos son de prueba.
 */
class DonacionesDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('categorias_organizacion')->exists()) {
            return;
        }

        DB::transaction(function () {
            $marcas = ['created_at' => now(), 'updated_at' => now()];
            $hoy = CarbonImmutable::now(HoraLocal::zona())->startOfDay();

            $categorias = [];
            foreach (
                [
                    'Organización deportiva' => 'Clubes y equipos deportivos.',
                    'Organización benéfica' => 'Fundaciones y obras de ayuda social.',
                ] as $nombre => $descripcion
            ) {
                $categorias[$nombre] = DB::table('categorias_organizacion')->insertGetId(['nombre' => $nombre, 'descripcion' => $descripcion, ...$marcas]);
            }

            // [categoría, nombre, notas, [[días atrás, monto, motivo]]]
            foreach (
                [
                    ['Organización deportiva', 'Deportes Temuco', 'Datos de demostración.', [
                        [0, 350000, 'Aporte para indumentaria'],
                        [24, 300000, 'Auspicio de la fecha de local'],
                        [53, 300000, 'Auspicio de la fecha de local'],
                        [88, 450000, 'Apoyo a las divisiones menores'],
                    ]],
                    ['Organización benéfica', 'Hogar de Cristo', 'Datos de demostración.', [
                        [3, 200000, 'Campaña de invierno'],
                        [34, 200000, 'Aporte mensual'],
                        [65, 200000, 'Aporte mensual'],
                    ]],
                    ['Organización deportiva', 'Club Deportivo Santa Elena', 'Organización ficticia de demostración.', [
                        [1, 120000, 'Balones y petos'],
                        [47, 150000, 'Campeonato vecinal'],
                    ]],
                ] as [$categoria, $nombre, $notas, $donaciones]
            ) {
                $organizacion = DB::table('organizaciones')->insertGetId(['categoria_id' => $categorias[$categoria], 'nombre' => $nombre, 'notas' => $notas, 'activo' => true, ...$marcas]);
                foreach ($donaciones as [$diasAtras, $monto, $motivo]) {
                    DB::table('donaciones')->insert(['organizacion_id' => $organizacion, 'fecha' => $hoy->subDays($diasAtras)->toDateString(), 'monto' => $monto, 'descripcion' => $motivo, ...$marcas]);
                }
            }
        });
    }
}
