<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CatalogoBaseSeeder::class);

        // La demostración solo se carga fuera de producción
        if (! app()->isProduction() && config('funeraria.seed_demo')) {
            $this->call(DemoSeeder::class);
        }
    }
}
