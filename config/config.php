<?php

// Cargar variables de entorno
require_once __DIR__ . '/../core/Env.php';
\Screenart\Musedock\Env::load();

return [
    'app_name' => \Screenart\Musedock\Env::get('APP_NAME', 'MuseDock CMS'),
    'redirect_on_superadmin_fail' => '/musedock/login',
    'superadmin_dashboard_redirect' => '/musedock/dashboard',
    'main_domain' => 'musedock.net',

    // Debug mode - NUNCA activar en producción
    'debug' => \Screenart\Musedock\Env::get('APP_DEBUG', false),

    // Fallbacks si falla la DB
    'multi_tenant_enabled' => \Screenart\Musedock\Env::get('MULTI_TENANT_ENABLED', true),
    'default_theme' => \Screenart\Musedock\Env::get('DEFAULT_THEME', 'default'),
    'enable_multilang' => false,
    'force_lang' => false,
    'default_lang' => \Screenart\Musedock\Env::get('DEFAULT_LANG', 'es'),

    // Base de datos - Credenciales desde .env
    'db' => [
        'driver' => \Screenart\Musedock\Env::get('DB_DRIVER', 'mysql'), // mysql o pgsql
        'host' => \Screenart\Musedock\Env::get('DB_HOST', 'localhost'),
        'port' => \Screenart\Musedock\Env::get('DB_PORT', 3306),
        'name' => \Screenart\Musedock\Env::get('DB_NAME', 'musedocknet'),
        'user' => \Screenart\Musedock\Env::get('DB_USER', 'musedocknet'),
        'pass' => \Screenart\Musedock\Env::get('DB_PASS', ''),
    ],

    // Configuración de seguridad
    'security' => [
        'session_lifetime' => \Screenart\Musedock\Env::get('SESSION_LIFETIME', 7200),
        'session_secure' => \Screenart\Musedock\Env::get('SESSION_SECURE', true),
        'session_httponly' => \Screenart\Musedock\Env::get('SESSION_HTTPONLY', true),
        'session_samesite' => \Screenart\Musedock\Env::get('SESSION_SAMESITE', 'Lax'),
        'rate_limit_attempts' => \Screenart\Musedock\Env::get('RATE_LIMIT_ATTEMPTS', 5),
        'rate_limit_decay' => \Screenart\Musedock\Env::get('RATE_LIMIT_DECAY_MINUTES', 15),
        'password_min_length' => \Screenart\Musedock\Env::get('PASSWORD_MIN_LENGTH', 12),
        'password_reset_expiry' => \Screenart\Musedock\Env::get('PASSWORD_RESET_EXPIRY', 60),
    ],

    // Configuración de uploads
    'upload' => [
        'max_size' => \Screenart\Musedock\Env::get('UPLOAD_MAX_SIZE', 10485760),
        'allowed_image_mimes' => explode(',', \Screenart\Musedock\Env::get('ALLOWED_IMAGE_MIMES', 'image/jpeg,image/png,image/gif,image/webp')),
        'allowed_document_mimes' => explode(',', \Screenart\Musedock\Env::get('ALLOWED_DOCUMENT_MIMES', 'application/pdf')),
        'allowed_extensions' => explode(',', \Screenart\Musedock\Env::get('ALLOWED_EXTENSIONS', 'jpg,jpeg,png,gif,webp,pdf')),
    ],
];
