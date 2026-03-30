<?php
/**
 * Repair Caddy Routes — Recreates missing routes for all tenants with custom domains.
 *
 * IMPORTANT: This script collects ALL missing routes first, then applies them
 * in a SINGLE API call. This prevents Caddy from canceling certificate obtainment
 * due to repeated config changes (context canceled).
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

// Helper: Caddy API request
function caddyRequest(string $method, string $path, $body = null): array {
    global $caddyApi;
    $ch = curl_init("{$caddyApi}{$path}");
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30];
    if ($method !== 'GET') {
        $opts[CURLOPT_CUSTOMREQUEST] = $method;
    }
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = is_string($body) ? $body : json_encode($body);
        $opts[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $resp];
}

/**
 * Merge existing routes (raw JSON) with new routes (PHP arrays) preserving
 * empty JSON objects ({}) that json_decode($str, true) turns into PHP [].
 * Strategy: keep existing routes as raw JSON fragments, encode new routes,
 * and concatenate them into a valid JSON array string.
 */
function buildMergedRoutesJson(string $existingRoutesJson, array $newRoutes): string {
    $existingRoutesJson = trim($existingRoutesJson);
    // Strip outer brackets
    $inner = substr($existingRoutesJson, 1, -1);
    $inner = trim($inner);

    $newJson = json_encode($newRoutes);
    // Strip outer brackets from new routes
    $newInner = substr($newJson, 1, -1);
    $newInner = trim($newInner);

    if ($inner === '' && $newInner === '') return '[]';
    if ($inner === '') return "[{$newInner}]";
    if ($newInner === '') return "[{$inner}]";

    return "[{$inner},{$newInner}]";
}

// Check Caddy API
$check = caddyRequest('GET', '/config/');
if ($check['code'] < 200 || $check['code'] >= 400) {
    echo "ERROR: Caddy API no disponible en {$caddyApi} (HTTP {$check['code']})\n";
    exit(1);
}
echo "Caddy API: OK ({$caddyApi})\n";

// Get existing routes — keep raw JSON to preserve empty objects ({})
$routesResp = caddyRequest('GET', '/config/apps/http/servers/srv0/routes');
$existingRoutesRaw = $routesResp['body'] ?: '[]';
$existingRoutes = json_decode($existingRoutesRaw, true) ?: [];

$existingRouteIds = [];
foreach ($existingRoutes as $route) {
    if (isset($route['@id'])) {
        $existingRouteIds[] = $route['@id'];
    }
}
echo "Rutas existentes en Caddy: " . count($existingRoutes) . " (" . count($existingRouteIds) . " con @id)\n";

// ═══════════════════════════════════════════════════════
// PHASE 1: COLLECT all missing routes (no API calls yet)
// ═══════════════════════════════════════════════════════

$missingRoutes = []; // Routes to add in batch
$repaired = 0;
$skipped = 0;
$errors = 0;
$panelRepaired = 0;

// Document root for CMS tenants
$documentRoot = '/var/www/vhosts/musedock.com/httpdocs/public';
$phpFpmSocket = 'unix//run/php/php8.3-fpm-musedock.sock';

// --- CMS Tenants ---
$stmt = $pdo->query("SELECT id, name, domain, caddy_route_id, caddy_status, include_www FROM tenants WHERE status = 'active' AND is_subdomain = 0 ORDER BY id");
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Tenants con dominio propio: " . count($tenants) . "\n\n";

foreach ($tenants as $tenant) {
    $routeId = $tenant['caddy_route_id'];
    $domain = $tenant['domain'];

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

    // Build host list
    $hosts = [$domain];
    if ($tenant['include_www']) $hosts[] = 'www.' . $domain;
    foreach ($aliases as $alias) {
        $hosts[] = $alias['domain'];
        if ($alias['include_www']) $hosts[] = 'www.' . $alias['domain'];
    }

    // Build route config (same as CaddyService::generateCaddyConfig)
    $missingRoutes[] = [
        '@id' => $routeId,
        'match' => [['host' => $hosts]],
        'terminal' => true,
        'handle' => [[
            'handler' => 'subroute',
            'routes' => [
                ['handle' => [
                    ['handler' => 'vars', 'root' => $documentRoot],
                    ['handler' => 'headers', 'response' => [
                        'deferred' => true,
                        'delete' => ['Server', 'X-Powered-By'],
                        'set' => [
                            'Referrer-Policy' => ['strict-origin-when-cross-origin'],
                            'Strict-Transport-Security' => ['max-age=31536000; includeSubDomains; preload'],
                            'X-Content-Type-Options' => ['nosniff'],
                            'X-Frame-Options' => ['SAMEORIGIN'],
                            'X-Xss-Protection' => ['1; mode=block'],
                        ]
                    ]]
                ]],
                ['group' => 'group0', 'match' => [['file' => ['try_files' => ['{http.request.uri.path}', '{http.request.uri.path}/']]]], 'handle' => [['handler' => 'rewrite', 'uri' => '{http.matchers.file.relative}']]],
                ['group' => 'group0', 'match' => [['file' => ['try_files' => ['/index.php']]]], 'handle' => [['handler' => 'rewrite', 'uri' => '{http.matchers.file.relative}?{http.request.uri.query}']]],
                ['match' => [['path' => ['*.jpg','*.jpeg','*.png','*.gif','*.webp','*.svg','*.ico','*.woff','*.woff2','*.ttf','*.eot']]], 'handle' => [['handler' => 'headers', 'response' => ['set' => ['Cache-Control' => ['public, max-age=31536000, immutable']]]]]],
                ['match' => [['path' => ['*.css','*.js']]], 'handle' => [['handler' => 'headers', 'response' => ['set' => ['Cache-Control' => ['public, max-age=2592000']]]]]],
                ['handle' => [['handler' => 'encode', 'encodings' => ['gzip' => (object)[], 'zstd' => (object)[]], 'prefer' => ['gzip', 'zstd']]]],
                ['match' => [['path' => ['*.env','*.htaccess','*.htpasswd','*.log','*.ini','*.json','*.lock','*.sql','*.md','*.sh','*.bak','*.old','*.backup','*.swp','*.dist','*.yml','*.yaml','composer.json','composer.lock','package.json','package-lock.json'], 'path_regexp' => ['name' => 'git', 'pattern' => '/\\.git']]], 'handle' => [['handler' => 'static_response', 'status_code' => 403]]],
                ['match' => [['path_regexp' => ['name' => 'hidden', 'pattern' => '/\\..+']]], 'handle' => [['handler' => 'static_response', 'status_code' => 403]]],
                ['match' => [['file' => ['try_files' => ['{http.request.uri.path}/index.php']], 'not' => [['path' => ['*/']]]]], 'handle' => [['handler' => 'static_response', 'status_code' => 308, 'headers' => ['Location' => ['{http.request.orig_uri.path}/{http.request.orig_uri.prefixed_query}']]]]],
                ['match' => [['file' => ['split_path' => ['.php'], 'try_files' => ['{http.request.uri.path}', '{http.request.uri.path}/index.php', 'index.php'], 'try_policy' => 'first_exist_fallback']]], 'handle' => [['handler' => 'rewrite', 'uri' => '{http.matchers.file.relative}']]],
                ['match' => [['path' => ['*.php']]], 'handle' => [['handler' => 'reverse_proxy', 'transport' => ['protocol' => 'fastcgi', 'root' => $documentRoot, 'split_path' => ['.php'], 'env' => ['APP_ENV' => 'production']], 'upstreams' => [['dial' => $phpFpmSocket]]]]],
                ['handle' => [['handler' => 'file_server', 'hide' => ['.git', '.env', '.htaccess', '/etc/caddy/Caddyfile']]]],
            ],
            'errors' => ['routes' => [['handle' => [
                ['handler' => 'rewrite', 'uri' => '/index.php?_error={http.error.status_code}'],
                ['handler' => 'reverse_proxy', 'transport' => ['protocol' => 'fastcgi', 'root' => $documentRoot, 'split_path' => ['.php']], 'upstreams' => [['dial' => $phpFpmSocket]]]
            ]]]]
        ]],
    ];

    echo "         [QUEUED] Ruta preparada: {$routeId}" . (count($aliases) ? " + " . count($aliases) . " alias" : "") . "\n";
    $repaired++;
}

// --- CMS Subdomain tenants with custom domain aliases ---
// These are *.musedock.com tenants that have custom domain aliases (e.g. screenart.es → screenart.musedock.com)
// They get their own Caddy route when aliases are added via upsertDomainWithAliases()
echo "\n--- CMS Subdomain Tenants with Aliases ---\n";
try {
    $stmt = $pdo->query("
        SELECT t.id, t.name, t.domain AS tenant_domain, t.caddy_route_id, t.include_www
        FROM tenants t
        WHERE t.is_subdomain = 1 AND t.status = 'active'
        AND t.caddy_route_id IS NOT NULL
        AND EXISTS (SELECT 1 FROM domain_aliases da WHERE da.tenant_id = t.id AND da.status IN ('active','pending') AND da.is_subdomain = 0)
        ORDER BY t.id
    ");
    $subTenantsWithAliases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Subdomain tenants con aliases: " . count($subTenantsWithAliases) . "\n";

    foreach ($subTenantsWithAliases as $st) {
        $stRouteId = $st['caddy_route_id'];

        if (in_array($stRouteId, $existingRouteIds)) {
            echo "  [OK]  {$st['tenant_domain']} — ruta '{$stRouteId}' existe\n";
            $skipped++;
            continue;
        }

        // Get all aliases for this tenant
        $aliasStmt = $pdo->prepare("SELECT domain, include_www FROM domain_aliases WHERE tenant_id = :tid AND status IN ('active', 'pending')");
        $aliasStmt->execute(['tid' => $st['id']]);
        $aliases = $aliasStmt->fetchAll(PDO::FETCH_ASSOC);

        // Build host list: tenant domain + www + all aliases + www
        $hosts = [$st['tenant_domain']];
        if ($st['include_www']) $hosts[] = 'www.' . $st['tenant_domain'];
        foreach ($aliases as $alias) {
            $hosts[] = $alias['domain'];
            if ($alias['include_www']) $hosts[] = 'www.' . $alias['domain'];
        }

        $aliasNames = array_map(fn($a) => $a['domain'], $aliases);
        echo "  [MISS] {$st['tenant_domain']} + aliases: " . implode(', ', $aliasNames) . "\n";

        $missingRoutes[] = [
            '@id' => $stRouteId,
            'match' => [['host' => $hosts]],
            'terminal' => true,
            'handle' => [[
                'handler' => 'subroute',
                'routes' => [
                    ['handle' => [
                        ['handler' => 'vars', 'root' => $documentRoot],
                        ['handler' => 'headers', 'response' => [
                            'deferred' => true,
                            'delete' => ['Server', 'X-Powered-By'],
                            'set' => [
                                'Referrer-Policy' => ['strict-origin-when-cross-origin'],
                                'Strict-Transport-Security' => ['max-age=31536000; includeSubDomains; preload'],
                                'X-Content-Type-Options' => ['nosniff'],
                                'X-Frame-Options' => ['SAMEORIGIN'],
                                'X-Xss-Protection' => ['1; mode=block'],
                            ]
                        ]]
                    ]],
                    ['group' => 'group0', 'match' => [['file' => ['try_files' => ['{http.request.uri.path}', '{http.request.uri.path}/']]]], 'handle' => [['handler' => 'rewrite', 'uri' => '{http.matchers.file.relative}']]],
                    ['group' => 'group0', 'match' => [['file' => ['try_files' => ['/index.php']]]], 'handle' => [['handler' => 'rewrite', 'uri' => '{http.matchers.file.relative}?{http.request.uri.query}']]],
                    ['match' => [['path' => ['*.jpg','*.jpeg','*.png','*.gif','*.webp','*.svg','*.ico','*.woff','*.woff2','*.ttf','*.eot']]], 'handle' => [['handler' => 'headers', 'response' => ['set' => ['Cache-Control' => ['public, max-age=31536000, immutable']]]]]],
                    ['match' => [['path' => ['*.css','*.js']]], 'handle' => [['handler' => 'headers', 'response' => ['set' => ['Cache-Control' => ['public, max-age=2592000']]]]]],
                    ['handle' => [['handler' => 'encode', 'encodings' => ['gzip' => (object)[], 'zstd' => (object)[]], 'prefer' => ['gzip', 'zstd']]]],
                    ['match' => [['path' => ['*.env','*.htaccess','*.htpasswd','*.log','*.ini','*.json','*.lock','*.sql','*.md','*.sh','*.bak','*.old','*.backup','*.swp','*.dist','*.yml','*.yaml','composer.json','composer.lock','package.json','package-lock.json'], 'path_regexp' => ['name' => 'git', 'pattern' => '/\\.git']]], 'handle' => [['handler' => 'static_response', 'status_code' => 403]]],
                    ['match' => [['path_regexp' => ['name' => 'hidden', 'pattern' => '/\\..+']]], 'handle' => [['handler' => 'static_response', 'status_code' => 403]]],
                    ['match' => [['file' => ['try_files' => ['{http.request.uri.path}/index.php']], 'not' => [['path' => ['*/']]]]], 'handle' => [['handler' => 'static_response', 'status_code' => 308, 'headers' => ['Location' => ['{http.request.orig_uri.path}/{http.request.orig_uri.prefixed_query}']]]]],
                    ['match' => [['file' => ['split_path' => ['.php'], 'try_files' => ['{http.request.uri.path}', '{http.request.uri.path}/index.php', 'index.php'], 'try_policy' => 'first_exist_fallback']]], 'handle' => [['handler' => 'rewrite', 'uri' => '{http.matchers.file.relative}']]],
                    ['match' => [['path' => ['*.php']]], 'handle' => [['handler' => 'reverse_proxy', 'transport' => ['protocol' => 'fastcgi', 'root' => $documentRoot, 'split_path' => ['.php'], 'env' => ['APP_ENV' => 'production']], 'upstreams' => [['dial' => $phpFpmSocket]]]]],
                    ['handle' => [['handler' => 'file_server', 'hide' => ['.git', '.env', '.htaccess', '/etc/caddy/Caddyfile']]]],
                ],
                'errors' => ['routes' => [['handle' => [
                    ['handler' => 'rewrite', 'uri' => '/index.php?_error={http.error.status_code}'],
                    ['handler' => 'reverse_proxy', 'transport' => ['protocol' => 'fastcgi', 'root' => $documentRoot, 'split_path' => ['.php']], 'upstreams' => [['dial' => $phpFpmSocket]]]
                ]]]]
            ]],
        ];

        echo "         [QUEUED] Ruta preparada: {$stRouteId}\n";
        $repaired++;
    }
} catch (\Throwable $e) {
    echo "  [WARN] No se pudieron cargar subdomain tenants con aliases: {$e->getMessage()}\n";
}

// --- CMS Domain Redirects ---
echo "\n--- CMS Domain Redirects ---\n";
try {
    $stmt = $pdo->query("SELECT id, domain, redirect_to, redirect_type, include_www, preserve_path FROM domain_redirects WHERE status = 'active' AND caddy_configured = 1");
    $redirects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Redirects activos: " . count($redirects) . "\n";

    foreach ($redirects as $redir) {
        $rDomain = $redir['domain'];
        $rRouteId = 'redirect-' . str_replace('.', '-', $rDomain);

        if (in_array($rRouteId, $existingRouteIds)) {
            echo "  [OK]  {$rDomain} — ruta '{$rRouteId}' existe\n";
            continue;
        }

        echo "  [MISS] {$rDomain} → {$redir['redirect_to']}\n";

        $rHosts = [$rDomain];
        if ($redir['include_www']) $rHosts[] = 'www.' . $rDomain;

        $redirectTo = rtrim($redir['redirect_to'], '/');
        $redirectUri = $redir['preserve_path'] ? $redirectTo . '{http.request.uri}' : $redirectTo . '/';

        $missingRoutes[] = [
            '@id' => $rRouteId,
            'match' => [['host' => $rHosts]],
            'handle' => [['handler' => 'static_response', 'status_code' => (string)($redir['redirect_type'] ?: 301), 'headers' => ['Location' => [$redirectUri]]]],
            'terminal' => true,
        ];

        echo "         [QUEUED] Redirect preparado: {$rRouteId}\n";
        $repaired++;
    }
} catch (\Throwable $e) {
    echo "  [WARN] No se pudieron cargar redirects: {$e->getMessage()}\n";
}

// --- Panel hosting accounts ---
$panelDbFile = '/opt/musedock-panel/config/panel.php';
if (file_exists($panelDbFile)) {
    $panelEnv = '/opt/musedock-panel/app/Env.php';
    if (file_exists($panelEnv)) require_once $panelEnv;

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

        // Hosting accounts
        $stmt = $panelPdo->query("SELECT id, domain, username, document_root, php_version, caddy_route_id FROM hosting_accounts WHERE status = 'active'");
        $hostings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($hostings as $hosting) {
            $hRouteId = $hosting['caddy_route_id'] ?? "hosting-{$hosting['username']}";

            if (in_array($hRouteId, $existingRouteIds)) {
                echo "  [OK]  hosting:{$hosting['domain']} — ruta '{$hRouteId}' existe\n";
                continue;
            }

            echo "  [MISS] hosting:{$hosting['domain']} — ruta '{$hRouteId}' NO existe\n";

            $docRoot = $hosting['document_root'];
            $phpVer = $hosting['php_version'] ?? '8.3';
            $socketPath = "/run/php/php{$phpVer}-fpm-{$hosting['username']}.sock";

            $missingRoutes[] = [
                '@id' => $hRouteId,
                'match' => [['host' => [$hosting['domain'], 'www.' . $hosting['domain']]]],
                'handle' => [['handler' => 'subroute', 'routes' => [
                    ['handle' => [['handler' => 'vars', 'root' => $docRoot]]],
                    ['match' => [['path' => ['*.jpg','*.jpeg','*.png','*.gif','*.webp','*.svg','*.ico','*.css','*.js','*.woff','*.woff2']]], 'handle' => [['handler' => 'headers', 'response' => ['set' => ['Cache-Control' => ['public, max-age=2592000']]]]]],
                    ['match' => [['file' => ['try_files' => ['{http.request.uri.path}', '{http.request.uri.path}/index.php', '/index.php']]]], 'handle' => [['handler' => 'rewrite', 'uri' => '{http.matchers.file.relative}']]],
                    ['match' => [['path' => ['*.php']]], 'handle' => [['handler' => 'reverse_proxy', 'transport' => ['protocol' => 'fastcgi', 'root' => $docRoot, 'split_path' => ['.php']], 'upstreams' => [['dial' => "unix/{$socketPath}"]]]]],
                    ['handle' => [['handler' => 'file_server', 'root' => $docRoot, 'hide' => ['.git', '.env', '.htaccess']]]]
                ]]],
                'terminal' => true,
            ];

            echo "         [QUEUED] Hosting preparado: {$hRouteId}\n";
            $panelRepaired++;
        }

        // Panel domain aliases (redirects like musedock.org -> musedock.com)
        $aliasStmt = $panelPdo->query("SELECT hda.*, ha.domain AS parent_domain FROM hosting_domain_aliases hda JOIN hosting_accounts ha ON ha.id = hda.hosting_account_id");
        $panelAliases = $aliasStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($panelAliases as $pa) {
            $paRouteId = $pa['caddy_route_id'] ?? "redirect-" . str_replace('.', '-', $pa['domain']);

            if (in_array($paRouteId, $existingRouteIds)) {
                echo "  [OK]  redirect:{$pa['domain']} — ruta '{$paRouteId}' existe\n";
                continue;
            }

            echo "  [MISS] redirect:{$pa['domain']} → {$pa['parent_domain']}\n";

            $redirectCode = $pa['redirect_code'] ?? 301;
            $target = "https://{$pa['parent_domain']}{http.request.uri}";

            $missingRoutes[] = [
                '@id' => $paRouteId,
                'match' => [['host' => [$pa['domain'], 'www.' . $pa['domain']]]],
                'handle' => [['handler' => 'static_response', 'status_code' => (string)$redirectCode, 'headers' => ['Location' => [$target]]]],
                'terminal' => true,
            ];

            echo "         [QUEUED] Panel redirect preparado: {$paRouteId}\n";
            $panelRepaired++;
        }

    } catch (\Throwable $e) {
        echo "  [WARN] No se pudo conectar a la BD del panel: {$e->getMessage()}\n";
    }
}
skipPanel:

// ═══════════════════════════════════════════════════════
// PHASE 2: TLS catch-all policy (collect, don't apply yet)
// ═══════════════════════════════════════════════════════

$cfToken = trim(getenv('CLOUDFLARE_API_TOKEN') ?: '');
if (!$cfToken) {
    $caddyEnv = @file_get_contents('/etc/default/caddy');
    if ($caddyEnv && preg_match('/^CLOUDFLARE_API_TOKEN=(.+)$/m', $caddyEnv, $m)) {
        $cfToken = trim($m[1]);
    }
}

$tlsAction = null; // 'add', 'fix', or null
$tlsPolicies = json_decode(caddyRequest('GET', '/config/apps/tls/automation/policies')['body'] ?: '[]', true) ?: [];

if ($cfToken) {
    $hasCatchAll = false;
    $catchAllIdx = null;
    $needsTokenFix = false;

    foreach ($tlsPolicies as $idx => $p) {
        if (!isset($p['subjects']) || empty($p['subjects'])) {
            $hasCatchAll = true;
            $catchAllIdx = $idx;
            $existingToken = $p['issuers'][0]['challenges']['dns']['provider']['api_token'] ?? '';
            $existingResolvers = $p['issuers'][0]['challenges']['dns']['resolvers'] ?? [];
            if (str_contains($existingToken, '{env.') || $existingToken !== $cfToken || empty($existingResolvers)) {
                $needsTokenFix = true;
            }
            break;
        }
    }

    $catchAllPolicy = [
        'issuers' => [[
            'module' => 'acme',
            'email' => 'admin@musedock.com',
            'challenges' => ['dns' => [
                'provider' => ['name' => 'cloudflare', 'api_token' => $cfToken],
                'resolvers' => ['1.1.1.1:53', '8.8.8.8:53']
            ]]
        ]]
    ];

    if (!$hasCatchAll) {
        $tlsAction = 'add';
    } elseif ($needsTokenFix) {
        $tlsAction = 'fix';
    }
}

// ═══════════════════════════════════════════════════════
// PHASE 3: APPLY everything in minimal API calls
// ═══════════════════════════════════════════════════════

if ($dryRun) {
    echo "\n[DRY-RUN] " . count($missingRoutes) . " rutas se añadirían.\n";
    if ($tlsAction) echo "[DRY-RUN] TLS policy se " . ($tlsAction === 'add' ? 'añadiría' : 'corregiría') . ".\n";
} elseif (count($missingRoutes) > 0 || $tlsAction) {
    echo "\n--- Aplicando cambios ---\n";
    echo "Rutas a añadir: " . count($missingRoutes) . "\n";

    // Strategy: merge existing routes (raw JSON) with new routes (PHP arrays)
    // into a single JSON string, preserving empty objects ({}) from Caddy config.
    // This way Caddy only does ONE config reload, not N.
    $allRoutesJson = buildMergedRoutesJson($existingRoutesRaw, $missingRoutes);

    // Apply TLS policy FIRST (before routes, so certs can start immediately)
    if ($tlsAction === 'add') {
        $tlsPolicies[] = $catchAllPolicy;
        $r = caddyRequest('PATCH', '/config/apps/tls/automation/policies', $tlsPolicies);
        echo "[TLS] Catch-all DNS-01 policy añadida (HTTP {$r['code']})\n";
    } elseif ($tlsAction === 'fix') {
        $r = caddyRequest('PATCH', "/config/apps/tls/automation/policies/{$catchAllIdx}", $catchAllPolicy);
        echo "[TLS] Catch-all policy token corregido (HTTP {$r['code']})\n";
    } else {
        echo "[TLS] Catch-all policy OK\n";
    }

    // Apply ALL routes in a single PATCH (replaces entire routes array)
    // Caddy API: PUT = create new key (409 if exists), PATCH = replace existing key
    // Use raw JSON string to preserve empty objects ({}) from existing routes
    $r = caddyRequest('PATCH', '/config/apps/http/servers/srv0/routes', $allRoutesJson);
    if ($r['code'] >= 200 && $r['code'] < 300) {
        echo "[ROUTES] " . count($missingRoutes) . " rutas añadidas en un solo PATCH (HTTP {$r['code']})\n";
    } else {
        echo "[ROUTES] ERROR al aplicar rutas (HTTP {$r['code']}): {$r['body']}\n";
        $errors += count($missingRoutes);
        $repaired = 0;
        $panelRepaired = 0;
    }
} else {
    echo "\n[OK] Todo correcto, nada que reparar.\n";
    if (!$cfToken) {
        echo "[TLS] WARNING: No CLOUDFLARE_API_TOKEN found, cannot ensure catch-all TLS policy\n";
    } else {
        echo "[TLS] Catch-all policy OK\n";
    }
}

// Update autosave if anything was repaired
if (($repaired > 0 || $panelRepaired > 0) && !$dryRun && $errors === 0) {
    $autosavePath = '/var/lib/caddy/.config/caddy/autosave.json';
    $currentConfig = caddyRequest('GET', '/config/')['body'];
    if ($currentConfig && is_writable(dirname($autosavePath))) {
        file_put_contents($autosavePath, $currentConfig);
        echo "\n[AUTOSAVE] Config guardada en {$autosavePath}\n";
    }
}

echo "\n=== Resumen ===\n";
echo "Tenants OK: {$skipped}\n";
echo "Tenants/redirects reparados: {$repaired}\n";
echo "Hostings/panel reparados: {$panelRepaired}\n";
echo "Errores: {$errors}\n";

if ($dryRun) {
    echo "\n[DRY-RUN] Ningun cambio realizado. Ejecuta sin --dry-run para aplicar.\n";
}
