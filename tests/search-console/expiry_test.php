<?php
/**
 * Caducidad de dominios: dominio registrable, lectura de RDAP, niveles de aviso y privacidad (sin clientes).
 *   php tests/search-console/expiry_test.php
 */
define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/core/Env.php';
\Screenart\Musedock\Env::load();
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/core/helpers.php';
foreach (['PropertyService', 'DomainExpiryService'] as $c) {
    require_once APP_ROOT . "/modules/search-console/services/{$c}.php";
}

use Modules\SearchConsole\Services\DomainExpiryService as D;

$fails = 0;
function ok(bool $c, string $m): void { global $fails; if (!$c) { $fails++; echo "  FALLA: $m\n"; } }

ok(D::registrable('alexapost.musedock.com') === 'musedock.com' && D::registrable('www.x.co.uk') === 'x.co.uk' && D::registrable('spain.filmconnect.org') === 'filmconnect.org' && D::registrable('shortsinfest.com') === 'shortsinfest.com', 'dominio registrable');

$redemption = ['status' => ['client transfer prohibited', 'redemption period'], 'events' => [['eventAction' => 'registration', 'eventDate' => '2015-08-10T18:54:04Z'], ['eventAction' => 'expiration', 'eventDate' => '2026-08-10T18:54:04Z']],
    'entities' => [['roles' => ['registrar'], 'vcardArray' => ['vcard', [['version', [], 'text', '4.0'], ['fn', [], 'text', 'Domain.com, LLC']]]]]];
$r = D::parseRdap($redemption);
ok($r['expires_at'] === '2026-08-10' && $r['status'] === 'redemption' && $r['registrar'] === 'Domain.com, LLC', 'RDAP: caducidad, redención y registrador');
$future = date('Y-m-d', strtotime('+200 days'));
ok(D::parseRdap(['status' => ['active'], 'events' => [['eventAction' => 'expiration', 'eventDate' => $future . 'T00:00:00Z']]])['status'] === 'ok', 'RDAP: dominio al día');
ok(D::parseRdap(['status' => ['pending delete']])['status'] === 'pending_delete' && D::parseRdap([]) === null, 'RDAP: pendiente de borrado / sin datos');
ok(D::classify('2020-01-01', ['active']) === 'expired', 'fecha pasada sin estado → caducado');

ok(D::levelFor(date('Y-m-d', strtotime('+90 days')), 'ok') === 0, 'a 90 días no hay aviso');
ok(D::levelFor(date('Y-m-d', strtotime('+45 days')), 'ok') === 60 && D::levelFor(date('Y-m-d', strtotime('+20 days')), 'ok') === 30 && D::levelFor(date('Y-m-d', strtotime('+10 days')), 'ok') === 15 && D::levelFor(date('Y-m-d', strtotime('+3 days')), 'ok') === 7, 'escalones 60/30/15/7');
ok(D::levelFor(date('Y-m-d', strtotime('+400 days')), 'redemption') === 1, 'en redención es siempre crítico');

$pdo = \Screenart\Musedock\Database::connect();
$pdo->beginTransaction();
try {
    $svc = new D($pdo, fn ($url) => json_encode(['status' => ['active'], 'events' => [['eventAction' => 'expiration', 'eventDate' => date('Y-m-d', strtotime('+5 days')) . 'T00:00:00Z']]]));
    [$own, $client] = $pdo->query('SELECT id, domain FROM tenants WHERE is_client_domain = 0 ORDER BY id LIMIT 2')->fetchAll(PDO::FETCH_ASSOC);
    $pdo->prepare('UPDATE tenants SET domain = ? WHERE id = ?')->execute(['sub.propio-exp-test.com', $own['id']]);
    $pdo->prepare('UPDATE tenants SET domain = ?, is_client_domain = 1 WHERE id = ?')->execute(['cliente-exp-test.com', $client['id']]);
    $pdo->prepare("INSERT INTO domain_aliases (tenant_id, domain, status) VALUES (?, 'alias-exp-test.org', 'active'), (?, 'alias-cliente-exp-test.org', 'active')")->execute([$own['id'], $client['id']]);
    $domains = array_keys($svc->ownDomains());
    ok(in_array('propio-exp-test.com', $domains, true) && in_array('alias-exp-test.org', $domains, true), 'se vigilan el dominio de un tenant propio y su alias');
    ok(!in_array('cliente-exp-test.com', $domains, true) && !in_array('alias-cliente-exp-test.org', $domains, true), 'los dominios de clientes NO se vigilan');
    $svc->check('propio-exp-test.com');
    $row = null;
    foreach ($svc->listing() as $x) { if ($x['domain'] === 'propio-exp-test.com') { $row = $x; } }
    ok($row && $row['days'] === 5 && $row['level'] === 7, 'el dominio guardado sale con sus días y nivel');
    $due = array_column($svc->dueAlerts(), 'domain');
    ok(in_array('propio-exp-test.com', $due, true), 'a 5 días toca avisar');
    $svc->markAlerted('propio-exp-test.com', 7);
    ok(!in_array('propio-exp-test.com', array_column($svc->dueAlerts(), 'domain'), true), 'una vez avisado, no se repite el aviso');
    $svc2 = new D($pdo, fn ($url) => json_encode(['status' => ['active'], 'events' => [['eventAction' => 'expiration', 'eventDate' => date('Y-m-d', strtotime('+1 days')) . 'T00:00:00Z']]]));
    $svc2->check('propio-exp-test.com');
    ok(in_array('propio-exp-test.com', array_column($svc2->dueAlerts(), 'domain'), true) === false, 'mismo nivel (7 días): no se repite');
    $svc3 = new D($pdo, fn ($url) => json_encode(['status' => ['redemption period'], 'events' => [['eventAction' => 'expiration', 'eventDate' => '2026-01-01T00:00:00Z']]]));
    $svc3->check('propio-exp-test.com');
    ok(in_array('propio-exp-test.com', array_column($svc3->dueAlerts(), 'domain'), true), 'si empeora (redención), vuelve a avisar');
    $svc4 = new D($pdo, fn ($url) => json_encode(['status' => ['active'], 'events' => [['eventAction' => 'expiration', 'eventDate' => date('Y-m-d', strtotime('+365 days')) . 'T00:00:00Z']]]));
    $svc4->check('propio-exp-test.com');
    $lvl = (int) $pdo->query("SELECT last_alert_level FROM gsc_domain_expiry WHERE domain = 'propio-exp-test.com'")->fetchColumn();
    ok($lvl === 0, 'al renovarse, los avisos se reinician');
} finally {
    $pdo->rollBack();
}
echo $fails ? "RESULTADO: $fails comprobaciones FALLAN\n" : "RESULTADO: las comprobaciones pasan\n";
exit($fails ? 1 : 0);
