<?php
require __DIR__ . '/../../modules/search-console/services/VisitSourcesService.php';
use Modules\SearchConsole\Services\VisitSourcesService as V;
$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE web_analytics (id INTEGER PRIMARY KEY, tenant_id INT, page_url TEXT, referrer_domain TEXT, referrer_type TEXT, is_bot BOOLEAN, created_at TEXT)');
$ins = $pdo->prepare('INSERT INTO web_analytics (tenant_id, page_url, referrer_domain, referrer_type, is_bot, created_at) VALUES (?,?,?,?,?,?)');
$d = '2026-10-10 10:00:00';
foreach ([[1, '/a', 'blog.example', 'referral', 0], [1, '/a', 'blog.example', 'referral', 0], [1, '/b', 'blog.example', 'referral', 0], [1, '/a', 'bot.example', 'referral', 1], [1, '/', null, 'direct', 0], [1, '/x', 'www.google.com', 'search', 0], [2, '/a', 'otro.example', 'referral', 0]] as $r) {
    $ins->execute([$r[0], $r[1], $r[2], $r[3], $r[4], $d]);
}
$f = 0;
$t = function ($ok, $m) use (&$f) { echo ($ok ? 'OK   ' : 'FAIL ') . $m . "\n"; if (!$ok) $f++; };
$r = V::forTenant($pdo, 1, '2026-10-01', '2026-10-31');
$t($r['total'] === 5 && $r['direct'] === 1 && $r['search'] === 1, 'cuenta solo visitas reales del tenant (sin robots ni de otros tenants)');
$t(count($r['sources']) === 1 && $r['sources'][0]['domain'] === 'blog.example' && $r['sources'][0]['visits'] === 3 && $r['sources'][0]['page'] === '/a', 'origen, visitas y página más visitada');
$t(V::forTenant($pdo, null, '2026-10-01', '2026-10-31') === null, 'sin tenant (sitio principal) no hay tarjeta');
$t(V::forTenant($pdo, 3, '2026-10-01', '2026-10-31') === null, 'sin visitas no hay tarjeta');
$t(V::forTenant(new PDO('sqlite::memory:'), 1, '2026-10-01', '2026-10-31') === null, 'sin tabla de analítica no falla');
exit($f ? 1 : 0);
