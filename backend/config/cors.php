<?php

// CORS restrictivo: solo el dominio de la interfaz puede llamar a la API con cookies.
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173'))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'X-XSRF-TOKEN', 'X-Requested-With', 'Accept', 'Idempotency-Key', 'Last-Event-ID'],
    'exposed_headers' => ['Content-Disposition', 'Idempotent-Replayed'],
    'max_age' => 3600,
    'supports_credentials' => true,
];