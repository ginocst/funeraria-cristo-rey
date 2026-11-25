<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos mínimos para operar: roles, medios de pago y parámetros del negocio.
 * Es idempotente: se puede ejecutar varias veces.
 */
class CatalogoBaseSeeder extends Seeder
{
    public function run(): void
    {
        $ahora = now();

        foreach ([
            ['Administrador', 'Dueño o gerencia: evalúa solicitudes, gestiona usuarios, finanzas y configuración.'],
            ['Coordinador', 'Coordina servicios: registra expedientes, solicitudes, pagos e inventario.'],
            ['Operario', 'Conductor o apoyo: consulta su itinerario e ingresa mercadería.'],
        ] as [$nombre, $descripcion]) {
            DB::table('roles')->updateOrInsert(['nombre' => $nombre], ['descripcion' => $descripcion, 'created_at' => $ahora, 'updated_at' => $ahora]);
        }

        foreach ([
            ['Efectivo', false],
            ['Transferencia', true],
            ['Tarjeta de débito', true],
            ['Tarjeta de crédito', true],
            ['Cheque', true],
            ['Cuota mortuoria', true],
        ] as [$nombre, $referencia]) {
            DB::table('medios_pago')->updateOrInsert(['nombre' => $nombre], ['requiere_referencia' => $referencia, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora]);
        }

        foreach (config('funeraria.parametros') as $clave => $p) {
            if (! DB::table('parametros')->where('clave', $clave)->exists()) {
                DB::table('parametros')->insert(['clave' => $clave, 'valor' => $p['valor'], 'descripcion' => $p['descripcion'], 'created_at' => $ahora, 'updated_at' => $ahora]);
            }
        }
    }
}
