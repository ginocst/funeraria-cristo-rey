<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos de demostración: 12 meses de historial y una operación
 * en curso con fechas relativas al momento de la carga
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('usuarios')->exists()) {
            $this->command?->warn('La base ya tiene usuarios: use "php artisan migrate:fresh --seed" para recargar la demostración.');
            // Las donaciones de demostración se agregan solo si aún no hay ninguna categoría
            $this->call(DonacionesDemoSeeder::class);

            return;
        }
        DB::unprepared(file_get_contents(database_path('sql/002_demo.sql')));
        $this->call(DonacionesDemoSeeder::class);
        $this->command?->info('Demostración cargada. Usuarios: renato.osses@cristorey.test (Administrador), carla.fuentes@cristorey.test (Coordinador), pedro.soto@cristorey.test (Operario). Contraseña: CristoRey#2026!');
    }
}
