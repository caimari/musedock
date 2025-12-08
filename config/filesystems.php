<?php

use Screenart\Musedock\Env;

// Helper function para compatibilidad si no existe
if (!function_exists('env')) {
    function env($key, $default = null) {
        return Env::get($key, $default);
    }
}

return [

    /*
    |--------------------------------------------------------------------------
    | Disco por defecto
    |--------------------------------------------------------------------------
    |
    | Este disco se usará por defecto si no se especifica otro.
    |
    */

    'default_disk' => 'local', // Cambiar a 'r2' o 's3' para usar la nube

    /*
    |--------------------------------------------------------------------------
    | Discos de almacenamiento configurados
    |--------------------------------------------------------------------------
    |
    | Puedes configurar tantos discos como quieras. Ya está listo para Local y S3/R2.
    |
    */

    'disks' => [

        // LEGACY: Almacenamiento antiguo (deprecated, mantener solo para migración)
        'local' => [
            'driver' => 'local',
            'root' => '/public/assets/uploads', // Ruta relativa a APP_ROOT
            'url' => '/assets/uploads', // URL base pública
            'visibility' => 'public',
        ],

        // NUEVO: Almacenamiento seguro fuera de public/
        'avatars' => [
            'driver' => 'local',
            'root' => '/storage/app/public/avatars',
            'url' => '/storage/avatars',
            'visibility' => 'public',
        ],

        'headers' => [
            'driver' => 'local',
            'root' => '/storage/app/public/headers',
            'url' => '/storage/headers',
            'visibility' => 'public',
        ],

        'gallery' => [
            'driver' => 'local',
            'root' => '/storage/app/public/gallery',
            'url' => '/storage/gallery',
            'visibility' => 'public',
        ],

        'posts' => [
            'driver' => 'local',
            'root' => '/storage/app/public/posts',
            'url' => '/storage/posts',
            'visibility' => 'public',
        ],

        'private' => [
            'driver' => 'local',
            'root' => '/storage/app/private',
            'url' => '/storage/private',
            'visibility' => 'private',
        ],

        // NUEVO: Almacenamiento seguro para Media Manager
        // Los archivos se guardan fuera de /public/ pero se sirven via controlador
        'media' => [
            'driver' => 'local',
            'root' => '/storage/app/media', // Ruta relativa a APP_ROOT
            'url' => '/media/file', // URL base para servir archivos (via controlador)
            'visibility' => 'public', // Público pero servido via controlador
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'), // URL pública o custom domain (opcional)
            'endpoint' => env('AWS_ENDPOINT'), // opcional
            'use_path_style_endpoint' => false, // True para Minio, False para S3/R2
            'visibility' => 'public',
        ],

        'r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => 'auto',
            'bucket' => env('R2_BUCKET'),
            'url' => env('R2_URL'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => false,
            'visibility' => 'public',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Opciones adicionales
    |--------------------------------------------------------------------------
    */

    'max_upload_size' => 10 * 1024 * 1024, // 10 MB en bytes

    'allowed_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'application/pdf',
        // Puedes añadir más según necesidades:
        'video/mp4',
        'audio/mpeg',
        'application/zip',
    ],

];
