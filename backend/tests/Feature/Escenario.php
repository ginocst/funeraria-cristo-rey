<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;

/** Construye datos mínimos para las pruebas de integración */
final class Escenario
{
    public static function base(): array
    {
        $sucursal = DB::table('sucursales')->where('nombre', 'Temuco')->value('id')
            ?? DB::table('sucursales')->insertGetId(['nombre' => 'Temuco', 'created_at' => now(), 'updated_at' => now()]);
        $vehiculo = DB::table('vehiculos')->insertGetId(['nombre' => 'Carroza', 'tipo' => 'carroza', 'origen' => 'propio', 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
        $producto = DB::table('productos')->insertGetId([
            'codigo' => 'AT-' . uniqid(),
            'nombre' => 'Imperial Roble',
            'tipo' => 'ataud',
            'precio' => 500000,
            'stock' => 1,
            'umbral_minimo' => 0,
            'publico' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return compact('sucursal', 'vehiculo', 'producto');
    }

    public static function expediente(int $sucursal): int
    {
        $cliente = DB::table('clientes')->insertGetId(['rut' => random_int(10000000, 25000000) . '-' . random_int(0, 9), 'nombre' => 'Cliente', 'created_at' => now(), 'updated_at' => now()]);
        $difunto = DB::table('difuntos')->insertGetId(['nombre' => 'Difunto', 'fecha_fallecimiento' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('expedientes')->insertGetId(['cliente_id' => $cliente, 'sucursal_id' => $sucursal, 'difunto_id' => $difunto, 'created_at' => now(), 'updated_at' => now()]);
    }

    public static function datosSolicitud(int $expediente, array $base, array $equipo, string $traslado = '+3 days 15:00'): array
    {
        $t = now()->modify($traslado);

        return [
            'expediente_id' => $expediente,
            'sucursal_id' => $base['sucursal'],
            'valor_servicio' => 500000,
            'descuento' => 0,
            'velatorio_inicio' => $t->copy()->subHours(20)->toIso8601String(),
            'lugar_velatorio' => 'Sala Temuco',
            'traslado_at' => $t->toIso8601String(),
            'lugar_sepultura' => 'Cementerio General',
            'sepultura_detalle' => 'Patio 1',
            'productos' => [['producto_id' => $base['producto'], 'cantidad' => 1]],
            'vehiculo_id' => $base['vehiculo'],
            'equipo' => $equipo,
        ];
    }

    public static function pagar(int $expediente, int $monto): void
    {
        $medio = DB::table('medios_pago')->where('nombre', 'Efectivo')->value('id');
        DB::table('pagos')->insert(['expediente_id' => $expediente, 'medio_pago_id' => $medio, 'tipo' => 'abono', 'monto' => $monto, 'created_at' => now(), 'updated_at' => now()]);
    }
}
