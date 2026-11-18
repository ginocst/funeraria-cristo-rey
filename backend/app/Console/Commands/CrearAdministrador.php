<?php

namespace App\Console\Commands;

use App\Modules\Identidad\Infrastructure\Models\Sucursal;
use App\Modules\Identidad\Infrastructure\Models\Usuario;
use Illuminate\Console\Command;
use App\Shared\Domain\Rut;
use Illuminate\Support\Facades\DB;

/**
 * Crea la primera cuenta de administrador en producción
 * Interactivo: php artisan funeraria:crear-admin correo@dominio.cl "Renato Osses" 12345678-9
 * En AWS (ECS): el entrypoint lo llama con --password-env y --si-no-existe.
 */
class CrearAdministrador extends Command
{
    protected $signature = 'funeraria:crear-admin {email} {nombre} {rut} {--sucursal=Temuco}
        {--password-env= : Variable de entorno que contiene la contraseña inicial}
        {--si-no-existe : No falla si ya hay un administrador}';

    protected $description = 'Crea un usuario Administrador';

    public function handle(): int
    {
        $existe = Usuario::whereRaw('lower(email) = ?', [strtolower($this->argument('email'))])->exists();
        if ($existe) {
            $this->info('El administrador ya existe; no se hace nada.');

            return $this->option('si-no-existe') ? self::SUCCESS : self::FAILURE;
        }

        if (! Rut::formatoValido($this->argument('rut'))) {
            $this->error('El RUT debe tener el formato 12345678-9.');

            return self::FAILURE;
        }

        $variable = $this->option('password-env');
        $password = $variable ? (string) getenv($variable) : (string) $this->secret('Contraseña (mínimo 12 caracteres)');
        if (mb_strlen($password) < 12) {
            $this->error('La contraseña debe tener al menos 12 caracteres.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($password) {
            $sucursal = Sucursal::firstOrCreate(['nombre' => $this->option('sucursal')]);
            $usuario = Usuario::create([
                'nombre' => $this->argument('nombre'),
                'email' => strtolower($this->argument('email')),
                'rut' => Rut::normalizar($this->argument('rut')),
                'password' => $password,
                'sucursal_id' => $sucursal->id,
                'activo' => true
            ]);
            $usuario->asignarRolBase('Administrador');
        });
        $this->info('Administrador creado. En su primer ingreso deberá configurar la verificación en dos pasos y cambiar la contraseña.');

        return self::SUCCESS;
    }
}
