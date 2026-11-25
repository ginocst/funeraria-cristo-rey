<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web',
    'passwords' => 'usuarios',
    'username' => 'email',
    'email' => 'email',
    'lowercase_usernames' => true,
    'home' => '/',
    // Las rutas de autenticación quedan bajo /api/auth (CloudFront envía /api/* a la API)
    'prefix' => 'api/auth',
    'domain' => null,
    'middleware' => ['web'],
    // Límite progresivo de intentos fallidos
    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
    ],
    // La interfaz es la SPA, fortify solo expone endpoints JSON
    'views' => false,
    'features' => [
        Features::resetPasswords(),
        Features::updatePasswords(),
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => false,
        ]),
    ],
];
