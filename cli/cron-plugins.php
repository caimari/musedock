#!/usr/bin/env php
<?php

/**
 * CRON GLOBAL DE PLUGINS - MuseDock CMS
 *
 * Script único que ejecuta las tareas programadas de TODOS los plugins
 * de TODOS los tenants que los tengan activos.
 *
 * Cada plugin define su propia clase CronHandler con el método run().
 * Cada tenant puede activar/desactivar el cron desde la configuración de su plugin.
 *
 * USO (añadir al crontab como root):
 *   cada-15-min: crontab entry with /usr/bin/php path-to/cli/cron-plugins.php
 *
 * OPCIONES:
 *   --plugin=news-aggregator    Solo ejecutar un plugin específico
 *   --tenant=19                 Solo ejecutar para un tenant específico
 *   --dry-run                   Solo mostrar qué se ejecutaría, sin ejecutar
 *   --verbose                   Mostrar más detalle
 *
 * @package Screenart\Musedock\CLI
 */

// Solo CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Solo ejecutable desde CLI');
}

// Medir tiempo total
$startTime = microtime(true);

// Ruta raíz
define('APP_ROOT', dirname(__DIR__));

// Cargar autoloader
require_once APP_ROOT . '/vendor/autoload.php';

// Cargar .env
if (file_exists(APP_ROOT . '/core/Env.php')) {
    require_once APP_ROOT . '/core/Env.php';
    \Screenart\Musedock\Env::load();
}

// Cargar config
if (file_exists(APP_ROOT . '/config/config.php')) {
    require_once APP_ROOT . '/config/config.php';
}

// Cargar helpers
if (file_exists(APP_ROOT . '/core/helpers.php')) {
    require_once APP_ROOT . '/core/helpers.php';
}

use Screenart\Musedock\Database;
use Screenart\Musedock\ModuleAutoloader;

// ============================================================================
// PARSEAR ARGUMENTOS
// ============================================================================

$opts = getopt('', ['plugin:', 'tenant:', 'dry-run', 'verbose']);
$filterPlugin = $opts['plugin'] ?? null;
$filterTenant = isset($opts['tenant']) ? (int) $opts['tenant'] : null;
$dryRun = isset($opts['dry-run']);
$verbose = isset($opts['verbose']);

// ============================================================================
// BANNER
// ============================================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║  MUSEDOCK CMS - Cron Global de Plugins                  ║\n";
echo "║  " . date('Y-m-d H:i:s') . "                                      ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";

if ($dryRun) {
    echo "  ** MODO DRY-RUN: No se ejecutará nada **\n";
}
echo "\n";

// ============================================================================
// LOCK: Evitar ejecuciones concurrentes
// ============================================================================

$lockFile = APP_ROOT . '/storage/cache/.cron-plugins.lock';
$lockFp = fopen($lockFile, 'c');

if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    echo "⚠️  Otra instancia del cron de plugins ya está ejecutándose. Saliendo.\n";
    exit(0);
}

// Escribir PID en el lock
ftruncate($lockFp, 0);
fwrite($lockFp, (string) getmypid());

// ============================================================================
// REGISTRO DE PLUGINS CON SOPORTE DE CRON
// ============================================================================

/**
 * Cada plugin que soporte cron debe definir aquí:
 * - slug: nombre del plugin
 * - handler: función que recibe ($tenantId, $pluginDir) y ejecuta la lógica
 * - settings_check: función que verifica si el cron está activo para ese tenant
 */
$pluginCronHandlers = [];

// --- NEWS AGGREGATOR ---
$pluginCronHandlers['news-aggregator'] = [
    'name' => 'News Aggregator',
    'handler' => function (int $tenantId, string $pluginDir) {
        // Registrar autoloader del plugin
        registerPluginAutoloader('NewsAggregator', $pluginDir);

        $pipeline = new \NewsAggregator\Services\AutomationPipeline($tenantId);
        return $pipeline->run();
    },
    'settings_check' => function (int $tenantId) {
        // Verificar si el plugin está activo en settings
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT enabled FROM news_aggregator_settings WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        // Si no hay fila de settings, asumir activo por defecto
        if ($result === false) return true;
        return (bool) $result['enabled'];
    },
    'format_result' => function (array $results) {
        return "capturados={$results['fetched']}, reescritos={$results['rewritten']}, aprobados={$results['approved']}, publicados={$results['published']}";
    }
];

// --- CROSS-PUBLISHER ---
$pluginCronHandlers['cross-publisher'] = [
    'name' => 'Cross-Publisher',
    'handler' => function (int $tenantId, string $pluginDir) {
        registerPluginAutoloader('CrossPublisher', $pluginDir);

        // Procesar cola pendiente automáticamente
        try {
            $pdo = Database::connect();

            // Verificar si hay items pendientes en la cola
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM cross_publisher_queue
                WHERE source_tenant_id = ? AND status = 'pending'
            ");
            $stmt->execute([$tenantId]);
            $pendingCount = (int) $stmt->fetchColumn();

            if ($pendingCount === 0) {
                return ['processed' => 0, 'success' => 0, 'failed' => 0];
            }

            $service = new \CrossPublisher\Services\CrossPublishService($tenantId);
            $results = $service->processQueue(10);

            $success = count(array_filter($results, fn($r) => $r['success']));
            $failed = count($results) - $success;

            return ['processed' => count($results), 'success' => $success, 'failed' => $failed];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    },
    'settings_check' => function (int $tenantId) {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT enabled FROM cross_publisher_settings WHERE tenant_id = ?");
            $stmt->execute([$tenantId]);
            $result = $stmt->fetchColumn();
            return $result === null ? true : (bool) $result;
        } catch (\Exception $e) {
            return true; // Si no existe la tabla aún, asumir activo
        }
    },
    'format_result' => function (array $results) {
        if (isset($results['error'])) {
            return "error: {$results['error']}";
        }
        return "procesados={$results['processed']}, ok={$results['success']}, fallidos={$results['failed']}";
    }
];

// ============================================================================
// DESCUBRIR TENANTS CON PLUGINS ACTIVOS
// ============================================================================

echo "Descubriendo tenants con plugins activos...\n";

try {
    $pdo = Database::connect();

    $sql = "SELECT tenant_id, slug FROM tenant_plugins WHERE active = 1";
    $params = [];

    if ($filterPlugin) {
        $sql .= " AND slug = ?";
        $params[] = $filterPlugin;
    }

    if ($filterTenant) {
        $sql .= " AND tenant_id = ?";
        $params[] = $filterTenant;
    }

    $sql .= " ORDER BY tenant_id, slug";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(\PDO::FETCH_OBJ);

} catch (\Exception $e) {
    echo "❌ Error al consultar tenant_plugins: {$e->getMessage()}\n";

    // Fallback: buscar en filesystem
    echo "ℹ️  Usando fallback: escaneo del filesystem...\n";
    $rows = scanFilesystemForPlugins($filterPlugin, $filterTenant);
}

// Agrupar por tenant
$tenantPlugins = [];
foreach ($rows as $row) {
    $tenantId = is_object($row) ? $row->tenant_id : $row['tenant_id'];
    $slug = is_object($row) ? $row->slug : $row['slug'];

    // Solo procesar plugins que tengan handler registrado
    if (!isset($pluginCronHandlers[$slug])) {
        continue;
    }

    $tenantPlugins[$tenantId][] = $slug;
}

if (empty($tenantPlugins)) {
    echo "ℹ️  No hay tenants con plugins activos que tengan cron.\n";
    cleanup($lockFp, $lockFile);
    exit(0);
}

$totalTenants = count($tenantPlugins);
$totalPlugins = array_sum(array_map('count', $tenantPlugins));
echo "Encontrados: {$totalPlugins} plugins en {$totalTenants} tenants\n\n";

// ============================================================================
// EJECUTAR PLUGINS
// ============================================================================

$totalResults = [
    'executed' => 0,
    'skipped' => 0,
    'errors' => 0
];

foreach ($tenantPlugins as $tenantId => $plugins) {
    echo "━━━ Tenant #{$tenantId} ━━━\n";

    // Establecer contexto del tenant
    $GLOBALS['tenant'] = ['id' => $tenantId];
    $_SESSION['tenant_id'] = $tenantId;

    foreach ($plugins as $slug) {
        $handler = $pluginCronHandlers[$slug];
        $pluginDir = APP_ROOT . "/storage/tenants/{$tenantId}/plugins/{$slug}";

        // Verificar que existe en disco
        if (!is_dir($pluginDir)) {
            echo "  ⚠️  {$handler['name']}: No existe en disco, saltando\n";
            $totalResults['skipped']++;
            continue;
        }

        // Verificar si el cron está activo para este tenant
        try {
            $cronActive = $handler['settings_check']($tenantId);
        } catch (\Exception $e) {
            $cronActive = true; // Si falla el check, ejecutar de todas formas
        }

        if (!$cronActive) {
            if ($verbose) {
                echo "  ⏸️  {$handler['name']}: Desactivado en configuración\n";
            }
            $totalResults['skipped']++;
            continue;
        }

        // Ejecutar
        if ($dryRun) {
            echo "  ▸ {$handler['name']}: [DRY-RUN] Se ejecutaría\n";
            $totalResults['executed']++;
            continue;
        }

        $pluginStart = microtime(true);
        echo "  ▸ {$handler['name']}: Ejecutando... ";

        try {
            $result = $handler['handler']($tenantId, $pluginDir);
            $duration = round(microtime(true) - $pluginStart, 2);

            $formatted = isset($handler['format_result']) ? $handler['format_result']($result) : json_encode($result);
            echo "OK ({$duration}s) - {$formatted}\n";

            $totalResults['executed']++;
        } catch (\Throwable $e) {
            $duration = round(microtime(true) - $pluginStart, 2);
            echo "ERROR ({$duration}s) - {$e->getMessage()}\n";

            if ($verbose) {
                echo "    Stack: " . $e->getFile() . ":" . $e->getLine() . "\n";
            }

            $totalResults['errors']++;
        }
    }
    echo "\n";
}

// ============================================================================
// RESUMEN
// ============================================================================

$totalDuration = round(microtime(true) - $startTime, 2);

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║  RESUMEN                                                 ║\n";
echo "╠══════════════════════════════════════════════════════════╣\n";
echo "║  Ejecutados: {$totalResults['executed']}                                          ║\n";
echo "║  Saltados:   {$totalResults['skipped']}                                          ║\n";
echo "║  Errores:    {$totalResults['errors']}                                          ║\n";
echo "║  Duración:   {$totalDuration}s                                       ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";
echo "\n";

// Limpiar lock
cleanup($lockFp, $lockFile);
exit($totalResults['errors'] > 0 ? 1 : 0);

// ============================================================================
// FUNCIONES AUXILIARES
// ============================================================================

/**
 * Registrar autoloader PSR-4 para un plugin
 */
function registerPluginAutoloader(string $namespace, string $pluginDir): void
{
    $prefix = $namespace . '\\';

    // Verificar si ya está registrado
    static $registered = [];
    if (isset($registered[$prefix])) {
        return;
    }
    $registered[$prefix] = true;

    spl_autoload_register(function ($class) use ($prefix, $pluginDir) {
        if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $parts = explode('\\', $relativeClass);
        $className = array_pop($parts);
        $subDir = strtolower(implode('/', $parts));

        $file = $pluginDir . '/' . $subDir . '/' . $className . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    });

    // También intentar registrar vía ModuleAutoloader si está disponible
    if (class_exists('Screenart\\Musedock\\ModuleAutoloader')) {
        $dirs = ['controllers', 'models', 'services'];
        foreach ($dirs as $dir) {
            $path = $pluginDir . '/' . $dir;
            if (is_dir($path)) {
                \Screenart\Musedock\ModuleAutoloader::registerNamespace(
                    $namespace . '\\' . ucfirst($dir) . '\\',
                    $path
                );
            }
        }
    }
}

/**
 * Fallback: escanear filesystem para encontrar plugins
 */
function scanFilesystemForPlugins(?string $filterPlugin, ?int $filterTenant): array
{
    $rows = [];
    $tenantsDir = APP_ROOT . '/storage/tenants/';

    if (!is_dir($tenantsDir)) {
        return [];
    }

    foreach (scandir($tenantsDir) as $dir) {
        if ($dir === '.' || $dir === '..' || !is_numeric($dir)) continue;

        $tenantId = (int) $dir;
        if ($filterTenant && $tenantId !== $filterTenant) continue;

        $pluginsDir = $tenantsDir . $dir . '/plugins/';
        if (!is_dir($pluginsDir)) continue;

        foreach (scandir($pluginsDir) as $pluginDir) {
            if ($pluginDir === '.' || $pluginDir === '..') continue;
            if ($filterPlugin && $pluginDir !== $filterPlugin) continue;

            $pluginJson = $pluginsDir . $pluginDir . '/plugin.json';
            if (file_exists($pluginJson)) {
                $rows[] = ['tenant_id' => $tenantId, 'slug' => $pluginDir];
            }
        }
    }

    return $rows;
}

/**
 * Limpiar lock file
 */
function cleanup($lockFp, string $lockFile): void
{
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    @unlink($lockFile);
}
