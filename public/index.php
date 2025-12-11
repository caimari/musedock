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

// Si no hay .env O no hay vendor, redirigir al instalador
if (!$envExists || !$vendorExists) {
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
    if (!$vendorExists) {
        echo '<p><strong>Note:</strong> Composer dependencies are not installed. Run <code>composer install</code> first.</p>';
    }
    echo '</body></html>';
    exit;
}

// =========== CARGAR CONFIGURACIÓN ===========
require_once __DIR__ . '/../core/Env.php';
\Screenart\Musedock\Env::load();

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
require_once __DIR__ . '/../routes/tenant.php';
require_once __DIR__ . '/../routes/web.php';
Logger::debug("Archivos de rutas cargados.");

// 4. Cargar MenuComposer (si aplica)
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

header("Content-Security-Policy: default-src 'self'; script-src {$cspScriptSrc}; style-src {$cspStyleSrc}; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: https:; connect-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; object-src 'none'; base-uri 'self'; form-action 'self';");

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