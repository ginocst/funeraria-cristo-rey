<?php

use App\Modules\Identidad\Infrastructure\Models\Usuario;

return [
    'defaults' => [
        'guard' => 'web',
        'passwords' => 'usuarios',
    ],

    'guards' => [
        'web' => ['driver' => 'session', 'provider' => 'usuarios'],
    ],

    'providers' => [
        'usuarios' => ['driver' => 'eloquent', 'model' => Usuario::class],
    ],

    // Recuperación de acceso, enlace de un solo uso que vence a los 30 minutos
    'passwords' => [
        'usuarios' => [
            'provider' => 'usuarios',
            'table' => 'password_reset_tokens',
            'expire' => 30,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,
];