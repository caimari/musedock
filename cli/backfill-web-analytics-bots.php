<?php
/**
 * Marca como robot (is_bot, bot_kind, bot_name) las visitas ya guardadas, según su user-agent.
 * Va por lotes de ids y con prioridad baja para no cargar la base de datos. Se puede repetir sin problema.
 * Uso: php cli/backfill-web-analytics-bots.php [--dry-run]
 */
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/core/Env.php';
\Screenart\Musedock\Env::load();
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/core/helpers.php';

use Screenart\Musedock\Database;
use Screenart\Musedock\Services\BotClassifier;

if (function_exists('proc_nice')) {
    @proc_nice(19);
}
$dry = in_array('--dry-run', $argv, true);
$db = Database::connect();
$max = (int) $db->query('SELECT COALESCE(MAX(id), 0) FROM web_analytics')->fetchColumn();
$batch = 20000;
$select = $db->prepare('SELECT id, user_agent FROM web_analytics WHERE id > ? AND id <= ? AND bot_kind IS NULL ORDER BY id');
$update = $db->prepare('UPDATE web_analytics SET is_bot = TRUE, bot_kind = ?, bot_name = ? WHERE id = ?');
$cache = [];
$marked = $seen = 0;
for ($from = 0; $from < $max; $from += $batch) {
    $select->execute([$from, $from + $batch]);
    $db->beginTransaction();
    while ($r = $select->fetch(PDO::FETCH_ASSOC)) {
        $seen++;
        $ua = (string) $r['user_agent'];
        $c = $cache[$ua] ??= BotClassifier::classify($ua);
        if ($c['is_bot']) {
            $marked++;
            if (!$dry) {
                $update->execute([$c['kind'], $c['name'], $r['id']]);
            }
        }
        if (count($cache) > 5000) {
            $cache = [];
        }
    }
    $db->commit();
    usleep(100000); // respira entre lotes
}
// Referrers sospechosos (pages.dev / workers.dev: escáneres que simulan un origen) y visitas que vienen del propio sitio
if (!$dry) {
    $n1 = $db->exec("UPDATE web_analytics SET is_bot = TRUE, bot_kind = 'suspect', bot_name = 'Referrer sospechoso (pages.dev / workers.dev)'
        WHERE is_bot = FALSE AND referrer_domain ~ '\\.(pages|workers)\\.dev$'");
    $n2 = $db->exec("UPDATE web_analytics w SET referrer_type = 'internal' FROM tenants t
        WHERE w.tenant_id = t.id AND w.referrer_type = 'referral' AND (w.referrer_domain = t.domain OR w.referrer_domain = 'www.' || t.domain)");
    echo "referrers sospechosos marcados como robot: $n1 · visitas desde el propio sitio pasadas a internas: $n2\n";
}
echo ($dry ? '[simulación] ' : '') . "revisadas $seen visitas, $marked marcadas como robot\n";
