<?php
/**
 * Recalcula en segundo plano la caché del cuadro de mando de Web Analytics (/musedock/analytics),
 * para que casi nadie espere los varios segundos que tardan las consultas.
 *
 * Uso: php cli/cron-analytics-cache.php [--periods=30,7] [--tenant=ID]
 * Cron recomendado: cada 10 minutos (la caché dura 10).
 */
if (php_sapi_name() !== 'cli') {
    exit('CLI only');
}
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/core/Env.php';
\Screenart\Musedock\Env::load();
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/core/helpers.php';

$opts = getopt('', ['periods::', 'tenant::']);
$periods = array_filter(array_map('trim', explode(',', $opts['periods'] ?? '30')));
$tenant = isset($opts['tenant']) ? (int) $opts['tenant'] : null;

$lock = fopen(APP_ROOT . '/storage/cache/.analytics-cache.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0); // ya hay otro recalculo en curso
}
$controller = new \Screenart\Musedock\Controllers\Superadmin\AnalyticsController();
foreach ($periods as $p) {
    printf("[%s] periodo %s días%s: %.1f s\n", date('H:i:s'), $p, $tenant ? " tenant $tenant" : '', $controller->warmCache($p, $tenant));
}
