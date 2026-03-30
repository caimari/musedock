<?php

return [
    'default_disk' => 'local', // Disco por defecto a usar

    'disks' => [
        'local' => [
            'driver' => 'local',
            // Ruta RELATIVA a APP_ROOT donde se guardarán los archivos
            // ¡ASEGÚRATE QUE '/public/assets/uploads' EXISTE Y TIENE PERMISOS DE ESCRITURA!
            'root' => APP_ROOT . '/public/assets/uploads',
            // URL PÚBLICA base para acceder a estos archivos desde el navegador
            // Asume que '/public' es tu DocumentRoot o tienes un alias/symlink
            'url' => '/assets/uploads',
            'visibility' => 'public', // Para que los archivos sean públicamente accesibles
        ],

        // Configuración R2 (para el futuro)
        /*
        'r2' => [
            'driver'                  => 's3',
            'key'                     => env('R2_ACCESS_KEY_ID'),
            'secret'                  => env('R2_SECRET_ACCESS_KEY'),
            'region'                  => 'auto',
            'bucket'                  => env('R2_BUCKET'),
            'url'                     => env('R2_URL'), // URL pública o Custom Domain
            'endpoint'                => env('R2_ENDPOINT'), // Endpoint de tu cuenta R2
            'use_path_style_endpoint' => false,
            'visibility'              => 'public',
        ],
        */
    ],

    // --- Opciones Adicionales (Ejemplos) ---
    'max_upload_size' => 10 * 1024 * 1024, // 10 MB en bytes
    'allowed_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'application/pdf',
        // Añade más tipos MIME si los necesitas
    ],
];