<?php

namespace App\Modules\Identidad\Domain;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rechaza contraseñas presentes en la lista local de contraseñas filtradas
 * (resources/seguridad/contrasenas_filtradas.txt).
 */
class ContrasenaSegura implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $ruta = resource_path('seguridad/contrasenas_filtradas.txt');
        if (! is_file($ruta)) {
            return;
        }
        $lista = array_map('trim', file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);
        if (in_array(mb_strtolower((string) $value), array_map('mb_strtolower', $lista), true)) {
            $fail('Esta contraseña es común. Elija otra.');
        }
    }
}
