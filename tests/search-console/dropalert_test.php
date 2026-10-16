<?php
/**
 * Aviso de caídas de SEO: umbrales, ruido, no repetir y reintento si el correo falla. Datos sintéticos en una
 * transacción que se deshace; el correo y el fichero de estado están simulados.
 *   php tests/search-console/dropalert_test.php
 */
define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/core/Env.php';
\Screenart\Musedock\Env::load();
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/core/helpers.php';
foreach (['PropertyService', 'DropAlertService'] as $c) {
    require_once APP_ROOT . "/modules/search-console/services/{$c}.php";
}

use Modules\SearchConsole\Services\{DropAlertService, PropertyService};

$fails = 0;
function ok(bool $c, string $m): void { global $fails; if (!$c) { $fails++; echo "  FALLA: $m\n"; } }

$pdo = \Screenart\Musedock\Database::connect();
$pdo->beginTransaction();
$file = tempnam(sys_get_temp_dir(), 'gscdrop');
@unlink($file);
try {
    $to = date('Y-m-d', strtotime('-3 days'));
    $day = fn (int $n) => date('Y-m-d', strtotime($to . " -{$n} days"));
    $ins = $pdo->prepare('INSERT INTO gsc_site_daily (property_id, date, clicks, impressions, ctr, position) VALUES (?, ?, ?, ?, 0, 5)');
    $insP = $pdo->prepare('INSERT INTO gsc_page_daily (property_id, date, page, clicks, impressions, ctr, position) VALUES (?, ?, ?, ?, ?, 0, 5)');
    // A (id 990001): clics 100/día antes → 20/día ahora (caída fuerte). B (990002): estable. C (990003): sitio pequeño con ruido.
    foreach ([990001 => [100, 20], 990002 => [100, 95], 990003 => [0, 0]] as $id => [$before, $after]) {
        for ($n = 0; $n < 28; $n++) { $ins->execute([$id, $day($n), $after, $after * 10]); }
        for ($n = 28; $n < 56; $n++) { $ins->execute([$id, $day($n), $before, $before * 10]); }
    }
    for ($n = 0; $n < 28; $n++) { $insP->execute([990001, $day($n), 'https://a.test/vieja', 0, 1]); $insP->execute([990001, $day($n), 'https://a.test/estable', 1, 5]); }
    for ($n = 28; $n < 56; $n++) { $insP->execute([990001, $day($n), 'https://a.test/vieja', 1, 5]); $insP->execute([990001, $day($n), 'https://a.test/estable', 1, 5]); }

    $svc = new DropAlertService($pdo, $file);
    $A = ['id' => 990001, 'domain' => 'a.test', 'status' => 'active', 'page_filter' => ''];
    $B = ['id' => 990002, 'domain' => 'b.test', 'status' => 'active', 'page_filter' => ''];
    $C = ['id' => 990003, 'domain' => 'c.test', 'status' => 'active', 'page_filter' => ''];
    $da = $svc->dropsFor($A);
    ok(count($da) >= 1 && str_contains($da[0]['text'], 'clics bajan') && $da[0]['key'] === 'a.test|site-clicks', 'una caída del 80 % de clics se detecta');
    ok(array_filter($da, fn ($d) => str_contains($d['text'], '/vieja')) !== [] && array_filter($da, fn ($d) => str_contains($d['text'], '/estable')) === [], 'se señala la página que cae y no la estable');
    ok($svc->dropsFor($B) === [], 'un sitio estable no avisa');
    ok($svc->dropsFor($C) === [], 'un sitio con muy poco tráfico no avisa (ruido)');
    ok($svc->dropsFor($A + ['data_from' => date('Y-m-d', strtotime('-20 days'))]) === [], 'sin histórico del periodo anterior no se compara');

    $stub = new class($pdo) extends PropertyService {
        public array $list = [];
        public function visibleForSuperadmin(): array { return $this->list; }
    };
    $stub->list = [$A, $B, $C, ['id' => 990001, 'domain' => 'virtual.test', 'status' => 'active', 'page_filter' => 'https://v.test/']];
    $sent = [];
    $okMail = function ($to, $subj, $html) use (&$sent) { $sent[] = [$to, $subj, $html]; return true; };
    $now = strtotime('today 09:00');
    ok($svc->due($now), 'toca ejecutar a partir de las 8:00');
    ok(!$svc->due(strtotime('today 07:00')), 'no antes de las 8:00');
    $r = $svc->run($stub, $okMail, ['x@y.z'], $now);
    ok($r['checked'] === 3 && $r['alerts'] >= 1 && $r['sent'] && count($sent) === 1 && str_contains($sent[0][2], 'a.test') && !str_contains($sent[0][2], 'b.test') && !str_contains($sent[0][2], 'virtual.test'), 'un correo con los dominios que caen; los subdominios virtuales no se duplican');
    ok(!$svc->due($now + 3600), 'ya ejecutado hoy: no se repite');
    $sent = [];
    $svc->run($stub, $okMail, ['x@y.z'], $now + 86400);
    ok($sent === [], 'al día siguiente no se repite el mismo aviso');
    $sent = [];
    $svc->run($stub, $okMail, ['x@y.z'], $now + 8 * 86400);
    ok(count($sent) === 1, 'pasada una semana, el aviso se repite');
    $failMail = fn ($to, $s, $h) => false;
    @unlink($file);
    $svc2 = new DropAlertService($pdo, $file);
    $r = $svc2->run($stub, $failMail, ['x@y.z'], $now);
    ok(!$r['sent'] && $svc2->due($now + 600), 'si el correo falla, no se da por avisado y se reintenta');
} finally {
    $pdo->rollBack();
    @unlink($file);
}
echo $fails ? "RESULTADO: $fails comprobaciones FALLAN\n" : "RESULTADO: las comprobaciones pasan\n";
exit($fails ? 1 : 0);
