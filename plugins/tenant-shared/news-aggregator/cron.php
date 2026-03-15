#!/usr/bin/env php
<?php

/**
 * News Aggregator - Cron Job
 *
 * Ejecuta el pipeline automatizado de captura, reescritura, aprobación y publicación.
 *
 * USO desde crontab (cada 15 minutos):
 * */15 * * * * /usr/bin/php /var/www/vhosts/musedock.com/httpdocs/storage/tenants/19/plugins/news-aggregator/cron.php >> /var/log/news-aggregator.log 2>&1
 *
 * O llamar desde el cron principal del sistema.
 */

// Verificar que se ejecuta desde CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Solo ejecutable desde CLI');
}

// Determinar la ruta raíz del proyecto
$appRoot = dirname(__DIR__, 4); // Subir desde storage/tenants/19/plugins/
define('APP_ROOT', $appRoot);

// Cargar autoloader y bootstrap
require_once APP_ROOT . '/vendor/autoload.php';

if (file_exists(APP_ROOT . '/core/Env.php')) {
    require_once APP_ROOT . '/core/Env.php';
    \Screenart\Musedock\Env::load();
}

if (file_exists(APP_ROOT . '/config/config.php')) {
    require_once APP_ROOT . '/config/config.php';
}

// Cargar helpers del framework
if (file_exists(APP_ROOT . '/core/helpers.php')) {
    require_once APP_ROOT . '/core/helpers.php';
}

// Registrar autoloader del plugin
spl_autoload_register(function ($class) {
    $prefix = 'NewsAggregator\\';
    $baseDir = __DIR__ . '/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $parts = explode('\\', $relativeClass);
    $className = array_pop($parts);
    $namespace = strtolower(implode('/', $parts));

    $file = $baseDir . $namespace . '/' . $className . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use NewsAggregator\Services\AutomationPipeline;
use Screenart\Musedock\Database;

// Banner
echo "\n";
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║  NEWS AGGREGATOR - Pipeline Automatizado                ║\n";
echo "║  " . date('Y-m-d H:i:s') . "                                      ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";
echo "\n";

try {
    // Determinar tenant ID
    // Si se pasa como argumento, usar ese; si no, buscar tenants con el plugin activo
    $tenantId = isset($argv[1]) ? (int) $argv[1] : null;

    if ($tenantId) {
        // Ejecutar para un tenant específico
        echo "▸ Ejecutando para tenant #{$tenantId}...\n";
        $results = runPipeline($tenantId);
        printResults($tenantId, $results);
    } else {
        // Buscar todos los tenants con el plugin news-aggregator activo
        $tenantIds = getActiveTenants();

        if (empty($tenantIds)) {
            echo "ℹ️  No hay tenants con News Aggregator activo.\n";
            exit(0);
        }

        echo "▸ Tenants con News Aggregator activo: " . count($tenantIds) . "\n\n";

        foreach ($tenantIds as $tid) {
            echo "━━━ Tenant #{$tid} ━━━\n";
            $results = runPipeline($tid);
            printResults($tid, $results);
            echo "\n";
        }
    }

    echo "✅ Pipeline completado\n\n";
    exit(0);

} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

/**
 * Ejecutar pipeline para un tenant
 */
function runPipeline(int $tenantId): array
{
    $pipeline = new AutomationPipeline($tenantId);
    return $pipeline->run();
}

/**
 * Mostrar resultados
 */
function printResults(int $tenantId, array $results): void
{
    echo "  Capturados: {$results['fetched']}\n";
    echo "  Reescritos: {$results['rewritten']}\n";
    echo "  Aprobados:  {$results['approved']}\n";
    echo "  Publicados: {$results['published']}\n";

    if (!empty($results['errors'])) {
        echo "  Log:\n";
        foreach ($results['errors'] as $msg) {
            echo "    {$msg}\n";
        }
    }
}

/**
 * Obtener tenants con el plugin news-aggregator activo
 */
function getActiveTenants(): array
{
    try {
        $pdo = Database::connect();
        $stmt = $pdo->query("
            SELECT DISTINCT tenant_id
            FROM tenant_plugins
            WHERE slug = 'news-aggregator' AND active = 1
        ");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    } catch (\Exception $e) {
        // Fallback: buscar en el filesystem
        $tenantsDir = APP_ROOT . '/storage/tenants/';
        $tenantIds = [];

        if (is_dir($tenantsDir)) {
            foreach (scandir($tenantsDir) as $dir) {
                if ($dir === '.' || $dir === '..') continue;
                $pluginJson = $tenantsDir . $dir . '/plugins/news-aggregator/plugin.json';
                if (file_exists($pluginJson)) {
                    $tenantIds[] = (int) $dir;
                }
            }
        }

        return $tenantIds;
    }
}
