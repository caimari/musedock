<?php
/**
 * Repair Caddy Routes — Recreates missing routes for all tenants with custom domains.
 *
 * Usage: php cli/repair-caddy-routes.php [--dry-run]
 */

define('APP_ROOT', realpath(__DIR__ . '/..'));

require_once APP_ROOT . '/vendor/autoload.php';

// Bootstrap env
$envFile = APP_ROOT . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

$dryRun = in_array('--dry-run', $argv ?? []);

echo "=== Caddy Routes Repair Tool ===\n";
echo $dryRun ? "[DRY-RUN MODE]\n\n" : "\n";

// Database connection
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbPort = $_ENV['DB_PORT'] ?? '5432';
$dbName = $_ENV['DB_NAME'] ?? '';
$dbUser = $_ENV['DB_USER'] ?? '';
$dbPass = $_ENV['DB_PASS'] ?? '';

try {
    $pdo = new PDO("pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "ERROR: No se pudo conectar a la BD: {$e->getMessage()}\n";
    exit(1);
}

// Caddy API
$caddyApi = $_ENV['CADDY_API_URL'] ?? 'http://localhost:2019';

// Check Caddy API
$ch = curl_init("{$caddyApi}/config/");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
$configJson = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode < 200 || $httpCode >= 400) {
    echo "ERROR: Caddy API no disponible en {$caddyApi} (HTTP {$httpCode})\n";
    exit(1);
}

echo "Caddy API: OK ({$caddyApi})\n";

// Get existing routes
$ch = curl_init("{$caddyApi}/config/apps/http/servers/srv0/routes");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
$routesJson = curl_exec($ch);
curl_close($ch);
$existingRoutes = json_decode($routesJson, true) ?: [];

$existingRouteIds = [];
foreach ($existingRoutes as $route) {
    if (isset($route['@id'])) {
        $existingRouteIds[] = $route['@id'];
    }
}
echo "Rutas existentes en Caddy: " . count($existingRoutes) . " (" . count($existingRouteIds) . " con @id)\n";

// Get tenants with custom domains
$stmt = $pdo->query("SELECT id, name, domain, caddy_route_id, caddy_status, include_www FROM tenants WHERE status = 'active' AND is_subdomain = 0 ORDER BY id");
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Tenants con dominio propio: " . count($tenants) . "\n\n";

// Initialize CaddyService
require_once APP_ROOT . '/plugins/superadmin/caddy-domain-manager/Services/CaddyService.php';
$caddyService = new \CaddyDomainManager\Services\CaddyService();

$repaired = 0;
$skipped = 0;
$errors = 0;

foreach ($tenants as $tenant) {
    $routeId = $tenant['caddy_route_id'];
    $domain = $tenant['domain'];

    // Check if route exists in Caddy
    if (in_array($routeId, $existingRouteIds)) {
        echo "  [OK]  {$domain} — ruta '{$routeId}' existe\n";
        $skipped++;
        continue;
    }

    echo "  [MISS] {$domain} — ruta '{$routeId}' NO existe en Caddy\n";

    // Get aliases for this tenant
    $aliasStmt = $pdo->prepare("SELECT domain, include_www FROM domain_aliases WHERE tenant_id = :tid AND status IN ('active', 'pending')");
    $aliasStmt->execute(['tid' => $tenant['id']]);
    $aliases = $aliasStmt->fetchAll(PDO::FETCH_ASSOC);

    $aliasArray = [];
    foreach ($aliases as $alias) {
        $aliasArray[] = [
            'domain' => $alias['domain'],
            'include_www' => (bool) $alias['include_www'],
        ];
    }

    if ($dryRun) {
        echo "         [DRY-RUN] Se recrearia la ruta para {$domain}" . (count($aliasArray) ? " + " . count($aliasArray) . " alias" : "") . "\n";
        $repaired++;
        continue;
    }

    // Recreate route
    $includeWww = (bool) $tenant['include_www'];
    $result = $caddyService->upsertDomainWithAliases($domain, $includeWww, $aliasArray);

    if ($result['success']) {
        echo "         [FIXED] Ruta recreada: {$result['route_id']}\n";

        // Update DB if route_id changed
        if ($result['route_id'] && $result['route_id'] !== $routeId) {
            $update = $pdo->prepare("UPDATE tenants SET caddy_route_id = :rid, caddy_configured_at = NOW() WHERE id = :id");
            $update->execute(['rid' => $result['route_id'], 'id' => $tenant['id']]);
            echo "         [DB] caddy_route_id actualizado a {$result['route_id']}\n";
        }

        $repaired++;
    } else {
        echo "         [ERROR] {$result['error']}\n";
        $errors++;
    }
}

// Also check hosting panel accounts (from MuseDock Panel DB)
$panelRepaired = 0;
$panelDbFile = '/opt/musedock-panel/config/panel.php';
if (file_exists($panelDbFile)) {
    // Load the panel's autoloader if available so its Env class is found
    $panelAutoload = '/opt/musedock-panel/vendor/autoload.php';
    if (file_exists($panelAutoload)) {
        require_once $panelAutoload;
    }
    try {
        $panelConfig = require $panelDbFile;
    } catch (\Throwable $e) {
        echo "  [WARN] Could not load panel config: {$e->getMessage()}\n";
        $panelConfig = null;
    }
    if (!$panelConfig) { goto skipPanel; }
    try {
        $panelPdo = new PDO(
            "pgsql:host={$panelConfig['db']['host']};port={$panelConfig['db']['port']};dbname={$panelConfig['db']['database']}",
            $panelConfig['db']['username'],
            $panelConfig['db']['password']
        );
        $panelPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $panelPdo->query("SELECT id, domain, username, document_root, php_version, caddy_route_id FROM hosting_accounts WHERE status = 'active'");
        $hostings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($hostings as $hosting) {
            $hRouteId = $hosting['caddy_route_id'] ?? "hosting-{$hosting['username']}";

            // Refresh existing route IDs from Caddy
            $ch = curl_init("{$caddyApi}/id/{$hRouteId}");
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3]);
            curl_exec($ch);
            $hCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($hCode >= 200 && $hCode < 300) {
                echo "  [OK]  hosting:{$hosting['domain']} — ruta '{$hRouteId}' existe\n";
                continue;
            }

            echo "  [MISS] hosting:{$hosting['domain']} — ruta '{$hRouteId}' NO existe\n";

            if (!$dryRun) {
                // Recreate via shell — call SystemService addCaddyRoute
                $cmd = sprintf(
                    'php -r "define(\'PANEL_ROOT\',\'/opt/musedock-panel\');require PANEL_ROOT.\'/app/Services/SystemService.php\';require PANEL_ROOT.\'/app/Services/LogService.php\';\$r=\\MuseDockPanel\\Services\\SystemService::addCaddyRoute(%s,%s,%s,%s);echo \$r?:\'FAIL\';" 2>&1',
                    var_export($hosting['domain'], true),
                    var_export($hosting['document_root'], true),
                    var_export($hosting['username'], true),
                    var_export($hosting['php_version'], true)
                );
                $result = trim(shell_exec($cmd) ?? '');
                if ($result && $result !== 'FAIL') {
                    echo "         [FIXED] Ruta recreada: {$result}\n";
                    $panelRepaired++;
                } else {
                    echo "         [ERROR] No se pudo recrear\n";
                    $errors++;
                }
            } else {
                echo "         [DRY-RUN] Se recrearia\n";
                $panelRepaired++;
            }
        }
    } catch (\Throwable $e) {
        echo "  [WARN] No se pudo conectar a la BD del panel: {$e->getMessage()}\n";
    }
}
skipPanel:

// Update autosave if anything was repaired
if ($repaired > 0 || $panelRepaired > 0) {
    if (!$dryRun) {
        $autosavePath = '/var/lib/caddy/.config/caddy/autosave.json';
        $currentConfig = @file_get_contents("{$caddyApi}/config/");
        if ($currentConfig && is_writable(dirname($autosavePath))) {
            file_put_contents($autosavePath, $currentConfig);
            echo "\n[AUTOSAVE] Config guardada en {$autosavePath}\n";
        }
    }
}

echo "\n=== Resumen ===\n";
echo "Tenants OK: {$skipped}\n";
echo "Tenants reparados: {$repaired}\n";
echo "Hostings reparados: {$panelRepaired}\n";
echo "Errores: {$errors}\n";

if ($dryRun) {
    echo "\n[DRY-RUN] Ningun cambio realizado. Ejecuta sin --dry-run para aplicar.\n";
}
