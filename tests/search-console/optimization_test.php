<?php
/**
 * Seguimiento de optimizaciones: ventanas equivalentes antes/después, veredictos prudentes («coincide con…»),
 * contraste con el sitio entero y variantes de URL agrupadas. SQLite en memoria, sin red.
 *   php tests/search-console/optimization_test.php
 */
define('APP_ROOT', dirname(__DIR__, 2));
foreach (['PropertyService', 'UrlNormalizer', 'OptimizationService'] as $c) {
    require_once APP_ROOT . "/modules/search-console/services/{$c}.php";
}

use Modules\SearchConsole\Services\OptimizationService;

$fails = 0;
function ok(bool $c, string $m): void { global $fails; if (!$c) { $fails++; echo "  FALLA: $m\n"; } }

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE gsc_optimizations (id INTEGER PRIMARY KEY AUTOINCREMENT, property_id INT, domain TEXT, url TEXT, url_key TEXT, kind TEXT, note TEXT, done_on TEXT, created_by TEXT, created_at TEXT)');
$pdo->exec('CREATE TABLE gsc_page_daily (property_id INT, date TEXT, page TEXT, clicks INT, impressions INT, ctr REAL, position REAL)');
$pdo->exec('CREATE TABLE gsc_site_daily (property_id INT, date TEXT, clicks INT, impressions INT, ctr REAL, position REAL)');
$day = fn (string $base, int $n) => date('Y-m-d', strtotime($base . ' ' . ($n >= 0 ? '+' : '') . $n . ' days'));
$done = '2026-09-01';
$last = '2026-10-14';                     // 43 días de datos tras el cambio → ventana completa de 28
$prop = ['id' => 1, 'domain' => 'x.test'];
$page = $pdo->prepare('INSERT INTO gsc_page_daily VALUES (?, ?, ?, ?, ?, 0, ?)');
$site = $pdo->prepare('INSERT INTO gsc_site_daily VALUES (?, ?, ?, ?, 0, 5)');
$fill = function (int $pid, string $url, array $before, array $after, ?array $siteB = null, ?array $siteA = null) use ($pdo, $page, $site, $done, $day) {
    for ($i = 1; $i <= 28; $i++) {
        $page->execute([$pid, $day($done, -$i), $url, $before[0], $before[1], $before[2]]);
        $site->execute([$pid, $day($done, -$i), ($siteB ?? [100, 1000])[0], ($siteB ?? [100, 1000])[1]]);
    }
    for ($i = 0; $i < 28; $i++) {
        $page->execute([$pid, $day($done, $i), $url, $after[0], $after[1], $after[2]]);
        $site->execute([$pid, $day($done, $i), ($siteA ?? [100, 1000])[0], ($siteA ?? [100, 1000])[1]]);
    }
};
$svc = new OptimizationService($pdo);

// Página que sube: posición 12 → 7 y más clics, con el sitio estable
$fill(1, 'https://x.test/mejora', [0, 10, 12.0], [1, 20, 7.0]);
$id = $svc->add($prop, 'https://x.test/mejora#precio', 'title', 'Nuevo título', $done, 'test');
$e = $svc->evaluate($pdo->query("SELECT * FROM gsc_optimizations WHERE id = $id")->fetch(PDO::FETCH_ASSOC), $last);
ok($e['days'] === 28 && $e['state'] === 'better' && str_contains($e['verdict'], 'Coincide con una mejora'), 'subir de la posición 12 a la 7 con más clics y el sitio estable coincide con una mejora');
ok($e['windows']['before'] === [$day($done, -28), $day($done, -1)] && $e['windows']['after'] === [$done, $day($done, 27)], 'compara dos ventanas equivalentes de 28 días');
ok($e['before']['impressions'] === 280 && $e['after']['impressions'] === 560, 'suma las impresiones de la página de cada ventana');
ok(str_contains($e['note'], 'no prueba de causa'), 'dice que es una coincidencia, no una causa');

// El sitio entero sube igual: no se atribuye a la optimización
$fill(2, 'https://x.test/todo-sube', [1, 20, 9.0], [2, 40, 9.0], [100, 1000], [200, 2000]);
$id2 = $svc->add(['id' => 2, 'domain' => 'y.test'], 'https://x.test/todo-sube', 'content', '', $done, 'test');
$e2 = $svc->evaluate($pdo->query("SELECT * FROM gsc_optimizations WHERE id = $id2")->fetch(PDO::FETCH_ASSOC), $last);
ok($e2['state'] === 'flat', 'si todo el sitio sube lo mismo (+100 %), la página no se presenta como mejora propia: sin cambio claro');

// Empeora
$fill(3, 'https://x.test/peor', [3, 100, 5.0], [0, 40, 9.0]);
$id3 = $svc->add(['id' => 3, 'domain' => 'z.test'], 'https://x.test/peor', 'links', '', $done, 'test');
$e3 = $svc->evaluate($pdo->query("SELECT * FROM gsc_optimizations WHERE id = $id3")->fetch(PDO::FETCH_ASSOC), $last);
ok($e3['state'] === 'worse' && str_contains($e3['verdict'], 'empeoramiento'), 'perder posición y clics con el sitio estable coincide con un empeoramiento');

// Pocos datos antes del cambio
$fill(4, 'https://x.test/pequena', [0, 0, 20.0], [0, 1, 20.0]);
$id4 = $svc->add(['id' => 4, 'domain' => 'w.test'], 'https://x.test/pequena', 'other', '', $done, 'test');
$e4 = $svc->evaluate($pdo->query("SELECT * FROM gsc_optimizations WHERE id = $id4")->fetch(PDO::FETCH_ASSOC), $last);
ok($e4['state'] === 'few_data' && $e4['verdict'] === 'Pocos datos', 'con menos de 20 impresiones antes del cambio no se valora');

// Todavía pronto
$recent = ['property_id' => 1, 'url_key' => 'https://x.test/mejora', 'done_on' => '2026-10-12'];
$e5 = $svc->evaluate($recent, $last);
ok($e5['state'] === 'tracking' && $e5['days'] === 2 && str_contains($e5['note'], 'retraso'), 'dos días después del cambio: recién registrada');
$mid = $svc->evaluate(['property_id' => 1, 'url_key' => 'https://x.test/mejora', 'done_on' => '2026-10-02'], $last);
ok($mid['state'] === 'tracking' && $mid['days'] === 12 && str_contains($mid['note'], '12 de 28'), 'a 12 días: en seguimiento, con los mismos días de antes y de después');
$ok14 = $svc->evaluate(['property_id' => 1, 'url_key' => 'https://x.test/mejora', 'done_on' => '2026-09-30'], $last);
ok($ok14['days'] === 14 && in_array($ok14['state'], ['better', 'worse', 'flat', 'few_data'], true), 'a partir de 14 días ya hay una primera valoración');
$fut = $svc->evaluate(['property_id' => 1, 'url_key' => 'https://x.test/mejora', 'done_on' => '2026-12-01'], $last);
ok($fut['state'] === 'tracking' && str_contains($fut['note'], 'posterior'), 'una fecha futura no rompe nada');

// Variantes: el registro con #ancla y la medición con www / utm
$page->execute([1, $day($done, 3), 'https://www.x.test/mejora?utm_source=a', 0, 50, 7.0]);
$m = $svc->pageMetrics(1, 'https://x.test/mejora', $done, $day($done, 27));
ok($m['impressions'] === 560 + 50, 'las variantes de la URL (www, utm_*) cuentan como la misma página');
ok(count($svc->forProperty($prop)) === 1 && isset($svc->forProperty($prop)[0]['evaluation']['verdict']), 'lista las optimizaciones del dominio con su valoración');
ok(count($svc->recent([1, 2, 3, 4], 2)) === 2 && $svc->recent([]) === [], 'resumen reciente con límite y sin dominios');
$svc->delete($id, 99);
ok(count($svc->forProperty($prop)) === 1, 'no se puede borrar una optimización de otro dominio');
$svc->delete($id, 1);
ok(count($svc->forProperty($prop)) === 0, 'sí la del propio');
ok($svc->add($prop, 'https://x.test/a', 'tipo-raro', str_repeat('x', 900), $done, 'test') > 0 && $pdo->query("SELECT kind FROM gsc_optimizations ORDER BY id DESC LIMIT 1")->fetchColumn() === 'other' && strlen($pdo->query("SELECT note FROM gsc_optimizations ORDER BY id DESC LIMIT 1")->fetchColumn()) === 500, 'un tipo desconocido pasa a «otro» y la nota se recorta a 500');

echo $fails ? "RESULTADO: $fails comprobaciones FALLAN\n" : "RESULTADO: las comprobaciones pasan\n";
exit($fails ? 1 : 0);
