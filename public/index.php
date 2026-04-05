<?php
// public/index.php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', realpath(__DIR__ . '/../')); // httpdocs (RAÍZ del proyecto)
}

// =========== VERIFICACIÓN TEMPRANA DE INSTALACIÓN ===========
// Antes de cargar NADA, verificar si el sistema está instalado
// Esto previene errores fatales cuando vendor/ no existe
$installLockExists = file_exists(__DIR__ . '/../install.lock');
$envExists = file_exists(__DIR__ . '/../.env');
$vendorExists = file_exists(__DIR__ . '/../vendor/autoload.php');

// Si no hay .env, redirigir al instalador (instalación nueva)
if (!$envExists) {
    // Verificar si el instalador existe
    if (file_exists(__DIR__ . '/../install/index.php')) {
        header('Location: /install/');
        exit;
    }

    // Fallback al instalador legacy
    if (file_exists(__DIR__ . '/../core/install.php')) {
        require_once __DIR__ . '/../core/install.php';
        exit;
    }

    // No hay instalador disponible
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><title>Installation Required</title></head><body>';
    echo '<h1>Installation Required</h1>';
    echo '<p>MuseDock CMS is not installed. Please ensure the installer is available at <code>/install/</code></p>';
    echo '</body></html>';
    exit;
}

// Si existe .env pero NO existe vendor/, mostrar error (por seguridad, no redirigir a instalador)
if (!$vendorExists) {
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><title>Composer Dependencies Missing</title></head><body>';
    echo '<h1>Composer Dependencies Missing</h1>';
    echo '<p>The system is configured (.env file exists) but Composer dependencies are not installed.</p>';
    echo '<p><strong>Solution:</strong> Run <code>composer install --no-dev</code> via SSH or from your control panel.</p>';
    echo '<p style="color: red;"><strong>Security Note:</strong> The installer is not accessible when .env exists to prevent unauthorized reinstallation.</p>';
    echo '</body></html>';
    exit;
}

// =========== CARGAR CONFIGURACIÓN ===========
require_once __DIR__ . '/../core/Env.php';
\Screenart\Musedock\Env::load();

// =========== HTML CACHE: PHASE 1 — EARLY EXIT ===========
// Try to serve a cached HTML file BEFORE any heavy bootstrap.
// If cache hit: sends HTML and exits immediately (~0ms PHP).
// If cache miss: starts output buffering for Phase 2 capture.
require_once __DIR__ . '/../core/Cache/HtmlCache.php';
require_once __DIR__ . '/../core/Cache/HtmlCacheMiddleware.php';
\Screenart\Musedock\Cache\HtmlCacheMiddleware::tryServeFromCache();

// Determinar entorno (producción vs desarrollo)
$isProduction = \Screenart\Musedock\Env::get('APP_ENV', 'production') === 'production';
$debug = \Screenart\Musedock\Env::get('APP_DEBUG', false);

// =========== CONFIGURACIÓN INICIAL Y ERRORES ===========
// En producción: NO mostrar errores, solo loguearlos
// En desarrollo: Mostrar todos los errores
ini_set('display_errors', ($isProduction || !$debug) ? '0' : '1');
ini_set('display_startup_errors', ($isProduction || !$debug) ? '0' : '1');
ini_set('log_errors', '1'); // Siempre loguear errores
$logFilePath = dirname(__DIR__) . '/storage/logs/error.log';
ini_set('error_log', $logFilePath);
error_reporting(E_ALL); // Reportar todos los errores (pero no mostrarlos en producción)

// =========== AUTOLOAD Y HELPERS ===========
require_once __DIR__ . '/../vendor/autoload.php';

// =========== CARGAR MODULEAUTOLOADER TEMPRANO ===========
// IMPORTANTE: Cargar e inicializar ModuleAutoloader antes que cualquier cosa que pueda usar clases de módulos
require_once __DIR__ . '/../core/ModuleAutoloader.php';
\Screenart\Musedock\ModuleAutoloader::init();

require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/Helpers/MenuHelper.php'; // Cargar tu MenuHelper
require_once __DIR__ . '/../core/Helpers/csrf.php'; // 🔒 SECURITY: Cargar helpers CSRF
require_once __DIR__ . '/../core/Helpers/hooks.php'; // Sistema de hooks para filtros y acciones

// =========== INICIALIZAR EL LOGGER ===========
// ¡IMPORTANTE! Llamar a init() ANTES de que se use Logger en manejadores o rutas
require_once __DIR__ . '/../core/Logger.php'; // Asegurar que la clase esté cargada
use Screenart\Musedock\Logger;

// Inicializar Logger: DEBUG si APP_DEBUG=true, ERROR si APP_DEBUG=false (producción)
// Esto reduce drásticamente el volumen de logs en producción
$logLevel = $debug ? 'DEBUG' : 'ERROR';
Logger::init($logFilePath, $logLevel);

// =========== MANEJO GLOBAL DE ERRORES/EXCEPCIONES (Refinado) ===========
// Este manejador ahora SÓLO logueará. No enviará salida HTTP.
set_exception_handler(function ($e) {
    // Usar el Logger inicializado (si tuvo éxito)
    Logger::exception($e, 'CRITICAL', ['source' => 'global_exception_handler']);
    // NO enviar http_response_code ni echo aquí para no interferir con respuestas API
    // Si display_errors está activado, PHP debería mostrar el error de todas formas.
    // Si está desactivado, el error queda registrado en el log.
});

// Este manejador de errores ahora SÓLO logueará.
set_error_handler(function ($severity, $message, $file, $line) {
    // No loguear errores menores si no estamos en modo DEBUG estricto
    if (!(error_reporting() & $severity)) {
        return false; // No hacer nada si el error está suprimido por @ o error_reporting
    }
    // Mapear severidad de PHP a nivel de logger (aproximado)
    $level = 'ERROR'; // Por defecto
    switch ($severity) {
        case E_WARNING: case E_CORE_WARNING: case E_COMPILE_WARNING: case E_USER_WARNING: $level = 'WARNING'; break;
        case E_NOTICE: case E_USER_NOTICE: case E_STRICT: case E_DEPRECATED: case E_USER_DEPRECATED: $level = 'INFO'; break; // O 'DEBUG'
        case E_ERROR: case E_PARSE: case E_CORE_ERROR: case E_COMPILE_ERROR: case E_USER_ERROR: $level = 'ERROR'; break;
    }
    Logger::log("[$severity] $message", $level, ['source' => 'global_error_handler', 'file' => $file, 'line' => $line]);
    // NO enviar http_response_code ni echo aquí.
    return false; // Devuelve false para permitir que el manejo de errores estándar de PHP continúe (importante)
});

// Manejador de cierre para errores FATALES (no capturables por los anteriores)
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR])) {
        // Loguear el error fatal usando nuestro Logger
        Logger::log(
            sprintf("FATAL Error [%d]: %s in %s:%d", $error['type'], $error['message'], $error['file'], $error['line']),
            'CRITICAL',
            ['source' => 'shutdown_handler']
        );
        // Aquí SÍ es apropiado intentar enviar una respuesta de error,
        // ya que el script se está muriendo de todas formas.
        if (!headers_sent()) { // Solo si no se ha enviado ya salida
            http_response_code(500);
            header('Content-Type: application/json'); // Asumir JSON para APIs
            echo json_encode(['success' => false, 'message' => 'Error fatal interno del servidor.']);
        }
    }
});

// =========== TIMEZONE ===========
$defaultTimezone = 'UTC'; // Fallback por si 'setting' falla
try {
    if (isset($GLOBALS['tenant']['id'])) {
        $tenantTimezone = setting('timezone', $defaultTimezone);
        date_default_timezone_set($tenantTimezone);
    } else {
        $globalTimezone = setting('timezone', $defaultTimezone);
        date_default_timezone_set($globalTimezone);
    }
    Logger::debug("Timezone establecido a: " . date_default_timezone_get());
} catch (\Exception $e) {
    Logger::error("Error al establecer timezone: " . $e->getMessage());
    date_default_timezone_set($defaultTimezone); // Establecer fallback
}


// =========== VERIFICACIÓN DE INSTALACIÓN COMPLETA ===========
// La verificación básica (env + vendor) ya se hizo al inicio del archivo
// Aquí solo verificamos install.lock y conexión a BD para reinstalaciones parciales
if (!file_exists(__DIR__ . '/../install.lock')) {
    // Verificar si la BD está configurada correctamente
    $installRequired = false;
    try {
        $dbHost = \Screenart\Musedock\Env::get('DB_HOST');
        $dbName = \Screenart\Musedock\Env::get('DB_NAME');
        $dbUser = \Screenart\Musedock\Env::get('DB_USER');

        if (empty($dbHost) || empty($dbName) || empty($dbUser)) {
            $installRequired = true;
        }
    } catch (\Throwable $e) {
        $installRequired = true;
    }

    if ($installRequired) {
        Logger::info("Installation required. Redirecting to installer.");
        if (file_exists(__DIR__ . '/../install/index.php')) {
            header('Location: /install/');
            exit;
        }
    }
}

// =========== CONFIG Y RUTAS ===========
// require_once __DIR__ . '/../config/config.php'; // config.php parece no ser necesario si no se usa aquí

// 1. Iniciar sesión ANTES de resolver tenant y cargar módulos
// Esto permite que los bootstrap de módulos puedan verificar $_SESSION
if (session_status() !== PHP_SESSION_ACTIVE) {
    // Configurar sesión de forma segura
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
    Logger::debug("Sesión iniciada", ['session_id' => session_id()]);
}

// 2. Resolver tenant
// Asegúrate que TenantResolver no genere excepciones fatales no capturadas
try {
    $tenantResolved = (new \Screenart\Musedock\Middlewares\TenantResolver())->handle();
    Logger::debug("TenantResolver ejecutado.", ['resolved' => $tenantResolved, 'tenant_global' => isset($GLOBALS['tenant']) ? $GLOBALS['tenant']['id'] ?? 'N/A' : 'No']);
} catch (\Throwable $e) {
     Logger::exception($e, 'CRITICAL', ['source' => 'TenantResolver']);
     // Decide qué hacer si el resolver falla, ¿mostrar error? ¿continuar sin tenant?
     // Por ahora, solo logueamos y continuamos.
     $tenantResolved = false;
}


// 3. Cargar módulos SIEMPRE (tanto para tenant como para superadmin)
// NOTA: El modules_loader.php internamente decide qué módulos cargar según el contexto
if (isset($GLOBALS['tenant'])) {
    Logger::debug("Cargando módulos para tenant: " . ($GLOBALS['tenant']['id'] ?? 'N/A'));
} else {
    Logger::debug("Cargando módulos para modo superadmin/global");
}
require_once __DIR__ . '/../core/modules_loader.php';

// 3.1 Cargar plugins por tenant (aislados)
// Los plugins son módulos privados específicos de cada tenant
Logger::debug("Cargando plugins por tenant...");
require_once __DIR__ . '/../core/plugins_loader.php';
Logger::debug("Plugins por tenant cargados.");

// 3.2 Cargar plugins de superadmin (aislados del dominio base)
// Los plugins de superadmin solo se cargan cuando NO hay tenant activo
Logger::debug("Cargando plugins de superadmin...");
require_once __DIR__ . '/../core/bootstrap/load_superadmin_plugins.php';
Logger::debug("Plugins de superadmin cargados.");

// === CARGAR CLASES BASE Y REGISTRAR WIDGETS ===
// Importar clases necesarias
use Screenart\Musedock\Widgets\WidgetManager;
use Screenart\Musedock\Widgets\WidgetBase; 

// Cargar explícitamente la clase base ANTES de registrar
require_once APP_ROOT . '/core/Widgets/WidgetBase.php'; 

// Ahora registrar los widgets (WidgetManager usará el autoloader para los Types)
WidgetManager::registerAvailableWidgets();
// ===============================================

// 3. Cargar rutas
Logger::debug("Cargando archivos de rutas...");
require_once __DIR__ . '/../routes/superadmin.php';
require_once __DIR__ . '/../routes/admin.php';
require_once __DIR__ . '/../routes/api_ai.php';
require_once __DIR__ . '/../routes/api_v1.php';
require_once __DIR__ . '/../routes/tenant.php';
require_once __DIR__ . '/../routes/web.php';
Logger::debug("Archivos de rutas cargados.");

// 4. Analytics Middleware (tracking de visitas públicas)
try {
    $__uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    $__method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $__skip_prefixes = ['/musedock/', '/admin/', '/api/', '/assets/', '/vendor/', '/uploads/', '/media/'];
    $__skip_ext = ['.css','.js','.jpg','.jpeg','.png','.gif','.svg','.webp','.ico','.woff','.woff2','.ttf','.pdf','.zip','.xml','.json','.php','.php7','.env','.log','.yml'];
    $__attack = ['/.env','/.git','/wp-','/phpmyadmin','/xmlrpc','/shell','/webshell','/passwd','/setup-config'];
    $__should_skip = false;
    if ($__method !== 'GET') $__should_skip = true;
    foreach ($__skip_prefixes as $p) { if (str_starts_with($__uri, $p)) { $__should_skip = true; break; } }
    if (!$__should_skip) foreach ($__skip_ext as $e) { if (str_ends_with($__uri, $e)) { $__should_skip = true; break; } }
    if (!$__should_skip) foreach ($__attack as $a) { if (stripos($__uri, $a) !== false) { $__should_skip = true; break; } }
    if (!$__should_skip) {
        $__ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        $__bots = ['bot','crawl','spider','slurp','googlebot','bingbot','yandex','baidu','facebookexternalhit'];
        foreach ($__bots as $b) { if (strpos($__ua, $b) !== false) { $__should_skip = true; break; } }
    }
    if (!$__should_skip) {
        // Detectar tenant desde $GLOBALS['tenant'] (ya resuelto por TenantResolver)
        $__tenant_id = isset($GLOBALS['tenant']['id']) ? (int)$GLOBALS['tenant']['id'] : null;
        // Session/visitor IDs
        if (session_status() === PHP_SESSION_NONE) { try { session_start(); } catch (\Throwable $__e) {} }
        if (!isset($_SESSION['_a_sid'])) $_SESSION['_a_sid'] = bin2hex(random_bytes(16));
        $__vid = $_COOKIE['_musedock_vid'] ?? null;
        if (!$__vid) {
            $__vid = bin2hex(random_bytes(16));
            @setcookie('_musedock_vid', $__vid, ['expires'=>time()+63072000,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
        }
        // Referrer
        $__ref = $_SERVER['HTTP_REFERER'] ?? null;
        $__ref_type = 'direct';
        $__ref_domain = null;
        if ($__ref) {
            $__ref_domain = parse_url($__ref, PHP_URL_HOST);
            // Si el referrer es del mismo dominio del tenant, es navegación interna
            $__tenant_domain = $GLOBALS['tenant']['domain'] ?? null;
            if ($__ref_domain && $__tenant_domain && (strcasecmp($__ref_domain, $__tenant_domain) === 0 || strcasecmp($__ref_domain, 'www.' . $__tenant_domain) === 0)) {
                $__ref_type = 'internal';
            } else {
                $__ref_type = 'referral';
                foreach (['google','bing','yahoo','duckduckgo','baidu','yandex'] as $__se) {
                    if ($__ref_domain && stripos($__ref_domain, $__se) !== false) { $__ref_type = 'search'; break; }
                }
                foreach (['facebook.com','twitter.com','x.com','instagram.com','linkedin.com','tiktok.com'] as $__sn) {
                    if ($__ref_domain && stripos($__ref_domain, $__sn) !== false) { $__ref_type = 'social'; break; }
                }
            }
        }
        // Device/browser
        $__ua_raw = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $__ua_l = strtolower($__ua_raw ?? '');
        $__browser = 'Unknown';
        if (stripos($__ua_raw, 'Edge') !== false) $__browser = 'Edge';
        elseif (stripos($__ua_raw, 'Chrome') !== false) $__browser = 'Chrome';
        elseif (stripos($__ua_raw, 'Safari') !== false) $__browser = 'Safari';
        elseif (stripos($__ua_raw, 'Firefox') !== false) $__browser = 'Firefox';
        $__os = 'Unknown';
        if (stripos($__ua_raw, 'Windows') !== false) $__os = 'Windows';
        elseif (stripos($__ua_raw, 'Mac OS X') !== false) $__os = 'macOS';
        elseif (stripos($__ua_raw, 'Android') !== false) $__os = 'Android';
        elseif (stripos($__ua_raw, 'iPhone') !== false || stripos($__ua_raw, 'iPad') !== false) $__os = 'iOS';
        elseif (stripos($__ua_raw, 'Linux') !== false) $__os = 'Linux';
        $__device = preg_match('/(ipad|tablet)/i', $__ua_raw ?? '') ? 'tablet' : (preg_match('/(android|iphone|mobile)/i', $__ua_raw ?? '') ? 'mobile' : 'desktop');
        // IP hash
        $__ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $__ip = explode(',', $__ip)[0];
        $__ip_hash = hash('sha256', trim($__ip) . date('Y-m-d'));
        // INSERT
        try {
            $__db = \Screenart\Musedock\Database::connect();
            $__ins = $__db->prepare("INSERT INTO web_analytics (tenant_id, session_id, visitor_id, ip_hash, page_url, referrer, referrer_domain, referrer_type, user_agent, device_type, browser, os, language, is_bot, is_returning, tracking_enabled, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,false,false,true,NOW())");
            $__ins->execute([$__tenant_id, $_SESSION['_a_sid'], $__vid, $__ip_hash, $_SERVER['REQUEST_URI'] ?? '/', $__ref, $__ref_domain, $__ref_type, $__ua_raw, $__device, $__browser, $__os, $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null]);
        } catch (\Throwable $__e) {
            Logger::error("Analytics INSERT error: " . $__e->getMessage());
        }
    }
} catch (\Throwable $e) {
    Logger::error("AnalyticsMiddleware error: " . $e->getMessage());
}

// 5. Cargar MenuComposer (si aplica)
// require_once __DIR__ . '/../core/Plugins/MenuComposer.php'; // ¿Se usa realmente aquí?

// 5. Inicializar MenuComposer (si aplica)
// try {
//     \Screenart\Musedock\Plugins\MenuComposer::init();
//     Logger::debug("MenuComposer inicializado.");
// } catch (\Exception $e) {
//     Logger::log("Error inicializando MenuComposer: " . $e->getMessage(), 'ERROR');
// }

// =========== HEADERS DE SEGURIDAD ===========
// Protección contra clickjacking
header('X-Frame-Options: SAMEORIGIN');

// Prevenir MIME-type sniffing
header('X-Content-Type-Options: nosniff');

// Activar protección XSS del navegador
header('X-XSS-Protection: 1; mode=block');

// Política de referencia
header('Referrer-Policy: strict-origin-when-cross-origin');

// Content Security Policy (CSP) - MEJORADO PARA SEGURIDAD
// IMPORTANTE: Si tiene problemas con JavaScript o CSS:
// 1. Use archivos .js y .css externos en lugar de inline
// 2. Mueva event handlers inline (onclick, etc.) a addEventListener()
// 3. Si absolutamente necesita unsafe-inline, agregue nonces o hashes
//
// CSP con unsafe-inline necesario para AdminLTE y otros plugins que usan inline styles/scripts
// TODO: Migrar a nonces o hashes para mayor seguridad
$cspScriptSrc = "'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://unpkg.com https://cdnjs.cloudflare.com https://code.jquery.com";
$cspStyleSrc = "'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://cdnjs.cloudflare.com";
$cspConnectSrc = "'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com";

// CSP dinámica: buscar tenant por HTTP_HOST y añadir dominios de su JS personalizado
$_cspHost = $_SERVER['HTTP_HOST'] ?? '';
if ($_cspHost && file_exists(__DIR__ . '/../.env')) {
    try {
        $_envLines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $_envVars = [];
        foreach ($_envLines as $_line) {
            if (strpos(trim($_line), '#') === 0) continue;
            if (strpos($_line, '=') !== false) {
                list($_k, $_v) = explode('=', $_line, 2);
                $_envVars[trim($_k)] = trim($_v);
            }
        }
        $_dbDriver = $_envVars['DB_DRIVER'] ?? 'pgsql';
        $_dbHost = $_envVars['DB_HOST'] ?? '127.0.0.1';
        $_dbPort = $_envVars['DB_PORT'] ?? ($_dbDriver === 'pgsql' ? '5432' : '3306');
        $_dbName = $_envVars['DB_NAME'] ?? $_envVars['DB_DATABASE'] ?? '';
        $_dbUser = $_envVars['DB_USER'] ?? $_envVars['DB_USERNAME'] ?? '';
        $_dbPass = $_envVars['DB_PASS'] ?? $_envVars['DB_PASSWORD'] ?? '';
        if ($_dbName && $_dbUser) {
            $_dsn = $_dbDriver === 'pgsql'
                ? "pgsql:host={$_dbHost};port={$_dbPort};dbname={$_dbName}"
                : "mysql:host={$_dbHost};port={$_dbPort};dbname={$_dbName};charset=utf8mb4";
            $_pdo = new PDO($_dsn, $_dbUser, $_dbPass, [
                PDO::ATTR_TIMEOUT => 2,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            // Buscar tenant por dominio
            $_stmt = $_pdo->prepare("SELECT id, theme FROM tenants WHERE domain = :host LIMIT 1");
            $_stmt->execute([':host' => $_cspHost]);
            $_tenantRow = $_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$_tenantRow) {
                try {
                    $_stmt2 = $_pdo->prepare("SELECT tenant_id FROM domain_aliases WHERE alias_domain = :host LIMIT 1");
                    $_stmt2->execute([':host' => $_cspHost]);
                    $_aliasRow = $_stmt2->fetch(PDO::FETCH_ASSOC);
                    if ($_aliasRow) {
                        $_stmt3 = $_pdo->prepare("SELECT id, theme FROM tenants WHERE id = :id LIMIT 1");
                        $_stmt3->execute([':id' => $_aliasRow['tenant_id']]);
                        $_tenantRow = $_stmt3->fetch(PDO::FETCH_ASSOC);
                    }
                } catch (\Exception $_e2) { /* tabla aliases puede no existir */ }
            }
            if ($_tenantRow) {
                $_tid = $_tenantRow['id'];
                $_ttheme = $_tenantRow['theme'] ?? 'default';
                $_customJsFile = __DIR__ . "/assets/themes/tenant_{$_tid}/{$_ttheme}/js/custom.js";
                if (file_exists($_customJsFile)) {
                    $_jsContent = file_get_contents($_customJsFile);
                    if (preg_match_all('#https://([a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,})#', $_jsContent, $_m)) {
                        foreach (array_unique($_m[0]) as $_extDomain) {
                            $_parsed = parse_url($_extDomain);
                            $_origin = $_parsed['scheme'] . '://' . $_parsed['host'];
                            if (!str_contains($cspScriptSrc, $_origin)) {
                                $cspScriptSrc .= ' ' . $_origin;
                            }
                            if (!str_contains($cspConnectSrc, $_origin)) {
                                $cspConnectSrc .= ' ' . $_origin;
                            }
                        }
                    }
                }
            }
            $_pdo = null;
        }
    } catch (\Exception $_e) {
        // Silencioso — no bloquear la carga si falla la CSP dinámica
    }
}

header("Content-Security-Policy: default-src 'self'; script-src {$cspScriptSrc}; style-src {$cspStyleSrc}; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: https:; media-src 'self' https:; frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com; connect-src {$cspConnectSrc}; object-src 'none'; base-uri 'self'; form-action 'self';");

// Permissions Policy (antes Feature Policy)
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// HSTS (HTTP Strict Transport Security) - Solo si estás usando HTTPS
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

// =========== PSEUDO-CRON (TAREAS PROGRAMADAS) ===========
// Si CRON_MODE=pseudo en .env, ejecutar tareas programadas
// con throttle (no afecta rendimiento del usuario)
if (file_exists(__DIR__ . '/../core/Bootstrap/cron.php')) {
    require_once __DIR__ . '/../core/Bootstrap/cron.php';
}

// =========== INICIALIZAR SISTEMA DE TRADUCCIÓN ===========
use Screenart\Musedock\Services\TranslationService;

// Determinar contexto (superadmin o tenant)
$tenant = tenant();
$context = empty($tenant) ? 'superadmin' : 'tenant';
TranslationService::setContext($context);

// Cargar idioma actual
$locale = TranslationService::getCurrentLocale();
TranslationService::load($locale, $context);

Logger::info("Sistema de traducción inicializado: locale={$locale}, context={$context}");

// =========== EJECUTAR LA RUTA ===========
use Screenart\Musedock\Route; // Asegurarse que Route esté disponible
Logger::info("Resolviendo ruta para URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
Route::resolve();
Logger::info("Resolución de ruta completada.");

// =========== HTML CACHE: PHASE 2 — CAPTURE & STORE ===========
// If Phase 1 started output buffering (cache miss), capture the rendered
// HTML and write it to disk for future requests.
\Screenart\Musedock\Cache\HtmlCacheMiddleware::captureAndCache();
