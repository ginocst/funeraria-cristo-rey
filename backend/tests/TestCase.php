<?php

namespace Tests;

use App\Modules\Identidad\Infrastructure\Models\Sucursal;
use App\Modules\Identidad\Infrastructure\Models\Usuario;
use Database\Seeders\CatalogoBaseSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected bool $seed = true;

    protected string $seeder = CatalogoBaseSeeder::class;

    /** Crea un usuario con un rol; para Administrador/Coordinador marca el MFA como configurado */
    protected function usuario(string $rol = 'Administrador', array $atributos = []): Usuario
    {
        $sucursal = Sucursal::firstOrCreate(['nombre' => 'Temuco']);
        static $correlativo = 10_000_000;
        $usuario = Usuario::create([
            'nombre' => $rol.' de prueba',
            'rut' => ++$correlativo.'-9',
            'email' => strtolower($rol).uniqid().'@prueba.test',
            'password' => 'Clave-Segura-2026',
            'sucursal_id' => $sucursal->id,
            'activo' => true,
            ...$atributos,
        ]);
        $usuario->asignarRolBase($rol);
        if (in_array($rol, ['Administrador', 'Coordinador'], true)) {
            $usuario->forceFill(['two_factor_secret' => encrypt('SECRETO'), 'two_factor_confirmed_at' => now()])->save();
        }

        return $usuario->fresh();
    }
}
