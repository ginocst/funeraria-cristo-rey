<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
        ],

        // Bucket privado de archivos (certificados, carnets, contratos)
        // En desarrollo: disco local; en producción: S3 privado con URLs prefirmadas
        'archivos' => env('ARCHIVOS_DRIVER', 'local') === 's3' ? [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('AWS_BUCKET_ARCHIVOS'),
            'visibility' => 'private',
            'throw' => true,
        ] : [
            'driver' => 'local',
            'root' => storage_path('app/private/archivos'),
            'url' => rtrim(env('APP_URL'), '/').'/archivos',
            'serve' => true,
            'throw' => true,
        ],

        // Fotos del catálogo: carpeta catalogo/ del bucket del frontend,
        // servida por CloudFront. En desarrollo: storage/app/public
        'catalogo' => env('CATALOGO_DRIVER', 'local') === 's3' ? [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('AWS_BUCKET_FRONTEND'),
            'root' => 'catalogo',
            'url' => rtrim(env('CATALOGO_URL', ''), '/'),
            'visibility' => 'private',
            'throw' => true,
        ] : [
            'driver' => 'local',
            'root' => storage_path('app/public/catalogo'),
            'url' => rtrim(env('APP_URL'), '/').'/storage/catalogo',
            'visibility' => 'public',
            'throw' => true,
        ],
    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
