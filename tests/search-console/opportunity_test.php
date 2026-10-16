<?php
/**
 * Detector de oportunidades SEO con datos sintéticos (SQLite en memoria).
 *   php tests/search-console/opportunity_test.php
 */
define('APP_ROOT', dirname(__DIR__, 2));
foreach (['PropertyService', 'ReportService', 'UrlNormalizer', 'QueryLabelService', 'BrandService', 'OpportunityService'] as $c) {
    require_once APP_ROOT . "/modules/search-console/services/{$c}.php";
}

use Modules\SearchConsole\Services\OpportunityService;

$fails = 0;
$n = 0;
function ok(bool $c, string $m): void
{
    global $fails, $n;
    $n++;
    if (!$c) {
        $fails++;
        echo "  FALLA: $m\n";
    }
}

function db(): PDO
{
    $p = new PDO('sqlite::memory:');
    $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $m = 'clicks INT NOT NULL, impressions INT NOT NULL, ctr REAL NOT NULL, position REAL NOT NULL';
    $p->exec("CREATE TABLE gsc_site_daily (property_id INT, date TEXT, $m)");
    $p->exec("CREATE TABLE gsc_query_daily (property_id INT, date TEXT, query TEXT, page TEXT NOT NULL DEFAULT '', $m)");
    $p->exec("CREATE TABLE gsc_page_daily (property_id INT, date TEXT, page TEXT, $m)");
    // último día con datos: los periodos terminan aquí
    $p->exec("INSERT INTO gsc_site_daily VALUES (1, '2026-10-14', 0, 0, 0, 0)");

    return $p;
}
// periodo actual (28 días): 2026-09-17 → 2026-10-14 · anterior: 2026-08-20 → 2026-09-16
function put(PDO $p, string $table, string $date, ?string $name, int $clicks, int $imp, float $pos): void
{
    $cols = $table === 'gsc_site_daily' ? 'property_id, date' : 'property_id, date, ' . ($table === 'gsc_query_daily' ? 'query' : 'page');
    $vals = $table === 'gsc_site_daily' ? '1, ?' : '1, ?, ?';
    $args = $table === 'gsc_site_daily' ? [$date] : [$date, $name];
    $p->prepare("INSERT INTO $table ($cols, clicks, impressions, ctr, position) VALUES ($vals, ?, ?, ?, ?)")->execute(array_merge($args, [$clicks, $imp, $imp ? $clicks / $imp : 0, $pos]));
}
$cur = '2026-10-01';
$prev = '2026-09-01';
$prop = ['id' => 1, 'domain' => 'x.test'];

$pdo = db();
put($pdo, 'gsc_site_daily', $cur, null, 100, 600, 8.0); // total bajo: umbral mínimo de 3 impresiones
put($pdo, 'gsc_site_daily', $prev, null, 100, 400, 8.0);
foreach ([
    ['a punto de despegar', $cur, 2, 100, 7.0],
    ['sale arriba sin clics', $cur, 2, 200, 3.0],
    ['muy lejos', $cur, 0, 30, 60.0],
    ['cae', $cur, 5, 50, 2.0], ['cae', $prev, 20, 80, 2.0],
    ['nueva y creciendo', $cur, 5, 120, 3.0],
    ['desaparece', $prev, 10, 40, 3.0],
    ['demasiado poco', $cur, 0, 2, 8.0],
    ['va bien', $cur, 14, 50, 1.0], ['va bien', $prev, 14, 50, 1.0], // igual que antes: no hay nada que avisar
] as [$q, $d, $c, $i, $pos]) { put($pdo, 'gsc_query_daily', $d, $q, $c, $i, $pos); }
put($pdo, 'gsc_page_daily', $cur, 'https://x.test/cms', 0, 9, 4.0);
put($pdo, 'gsc_page_daily', $cur, 'https://otro.x.test/blog', 0, 9, 4.0);

echo "== 1. Reglas\n";
$r = (new OpportunityService($pdo))->detect($prop, 28);
$by = [];
foreach ($r['items'] as $it) { $by[$it['type']][$it['kind'] . ':' . $it['name']] = $it; }
ok(!$r['empty'] && $r['from'] === '2026-09-17' && $r['to'] === '2026-10-14' && $r['min_impressions'] === 3, 'periodo y umbral mínimo de un sitio pequeño: ' . json_encode([$r['from'], $r['to'], $r['min_impressions']]));
ok(isset($by['striking']['query:a punto de despegar']) && $by['striking']['query:a punto de despegar']['score'] > 8, 'posición 7 con impresiones → «a punto de despegar»');
ok(isset($by['low_ctr']['query:sale arriba sin clics']) && $by['low_ctr']['query:sale arriba sin clics']['score'] > 19, 'posición 3 casi sin clics → «CTR bajo»');
ok(isset($by['far']['query:muy lejos']), 'posición 60 con demanda → «estás lejos»');
ok(isset($by['drop']['query:cae']) && isset($by['drop']['query:desaparece']), 'caída de clics y consulta que desaparece → «caída»');
ok($by['drop']['query:desaparece']['clicks'] === 0 && $by['drop']['query:desaparece']['prev_clicks'] === 10, 'la consulta desaparecida muestra clics 0 frente a 10');
ok(isset($by['rising']['query:nueva y creciendo']) && str_contains($by['rising']['query:nueva y creciendo']['advice'], 'primera vez'), 'consulta nueva → «en ascenso», con su consejo');
ok(!isset($by['striking']['query:demasiado poco']) && !isset($by['far']['query:demasiado poco']) && !isset($by['low_ctr']['query:demasiado poco']), 'por debajo del umbral no se avisa');
$all = [];
foreach ($by as $list) { foreach (array_keys($list) as $k) { $all[] = $k; } }
ok(!in_array('query:va bien', $all, true), 'lo que va bien no se marca');
ok(!isset($by['rising']['query:muy lejos']), 'una consulta nueva muy lejos no es «en ascenso» (ya sale como «estás lejos»)');
ok(isset($by['low_ctr']['page:https://x.test/cms']) && $by['low_ctr']['page:https://x.test/cms']['label'] === '/cms', 'páginas: se acorta la URL del propio dominio');
ok($by['low_ctr']['page:https://otro.x.test/blog']['label'] === 'https://otro.x.test/blog', 'una URL de otro subdominio se muestra completa');
$scores = array_column($r['items'], 'score');
$sorted = $scores; rsort($sorted);
ok($scores === $sorted, 'ordenadas de más a menos potencial');
ok(array_sum($r['counts']) === count($r['items']) && $r['counts']['drop'] === 2, 'contadores por tipo');
ok(count(array_filter($r['items'], fn ($i) => $i['advice'] === '')) === 0, 'cada oportunidad trae qué hacer');
ok($r['notes'] === [], 'con histórico suficiente y sin páginas excluidas no hay notas');

$young = (new OpportunityService($pdo))->detect($prop + ['data_from' => '2026-09-10'], 28);
ok(!array_filter($young['items'], fn ($i) => in_array($i['type'], ['drop', 'rising'], true)) && count($young['notes']) === 1 && str_contains($young['notes'][0], '10/09/2026'), 'sin histórico del periodo anterior no hay caídas ni subidas, y se explica por qué');
ok(OpportunityService::shortPage('https://www.x.test/', 'x.test') === '[www] /' && OpportunityService::shortPage('https://x.test/a', 'x.test') === '/a' && OpportunityService::shortPage('https://x.test', 'x.test') === '/', 'las páginas con www se distinguen de las que no');

echo "== 2. El umbral se adapta al volumen\n";
$pdo = db();
put($pdo, 'gsc_site_daily', $cur, null, 5000, 20000, 8.0); // sitio grande → umbral 50
put($pdo, 'gsc_query_daily', $cur, 'poca demanda', 0, 30, 60.0);
put($pdo, 'gsc_query_daily', $cur, 'mucha demanda', 0, 300, 60.0);
$r = (new OpportunityService($pdo))->detect($prop, 28);
$names = array_column($r['items'], 'name');
ok($r['min_impressions'] === 50 && in_array('mucha demanda', $names, true) && !in_array('poca demanda', $names, true), 'en un sitio grande se ignoran las consultas con pocas impresiones');

echo "== 3. Límites y casos vacíos\n";
$pdo = db();
put($pdo, 'gsc_site_daily', $cur, null, 10, 600, 8.0);
for ($i = 0; $i < 25; $i++) { put($pdo, 'gsc_query_daily', $cur, "lejos $i", 0, 10 + $i, 70.0); }
$r = (new OpportunityService($pdo))->detect($prop, 28);
ok($r['counts']['far'] === 15, 'máximo 15 oportunidades por tipo');
ok(($r['items'][0]['impressions'] ?? 0) === 34, 'se quedan las de más potencial');
$none = db();
$none->exec('DELETE FROM gsc_site_daily');
ok((new OpportunityService($none))->detect($prop, 28)['empty'] === true, 'sin datos → vacío, sin errores');
ok((new OpportunityService($pdo))->detect($prop, 13)['days'] === 28, 'un periodo no permitido vuelve a 28 días');
ok(OpportunityService::expectedCtr(1) === 0.28 && OpportunityService::expectedCtr(4.4) === 0.08 && OpportunityService::expectedCtr(55) === 0.01, 'tabla de CTR esperado por posición');

echo "== 4. Variantes de una página, páginas legales y prioridades\n";
$pdo = db();
put($pdo, 'gsc_site_daily', $cur, null, 20, 2000, 8.0);
foreach (['https://x.test/grok', 'https://x.test/grok#gratis', 'https://x.test/grok#precio', 'https://www.x.test/grok', 'https://x.test/grok/?utm_source=copilot.com'] as $v) {
    put($pdo, 'gsc_page_daily', $cur, $v, 0, 120, 9.0); // 5 variantes × 120 = 600 impresiones
}
put($pdo, 'gsc_page_daily', $cur, 'https://x.test/cookie-policy', 0, 400, 5.0);
put($pdo, 'gsc_page_daily', $cur, 'https://x.test/p/privacy', 0, 300, 5.0);
put($pdo, 'gsc_page_daily', $cur, 'https://x.test/pequena', 0, 12, 7.0);
put($pdo, 'gsc_page_daily', $cur, 'https://x.test/mediana', 0, 150, 7.0);
$r = (new OpportunityService($pdo))->detect($prop, 28);
$pages = array_values(array_filter($r['items'], fn ($i) => $i['kind'] === 'page'));
$names = array_column($pages, 'name');
ok(count(array_unique(array_filter($names, fn ($n) => str_contains($n, 'grok')))) === 1, 'las 5 variantes de /grok (www, #ancla, utm_*, barra final) salen como UNA sola página');
$grok = array_values(array_filter($pages, fn ($i) => str_contains($i['name'], 'grok')))[0];
ok($grok['impressions'] === 600 && $grok['variants'] === 5, 'suman sus impresiones: 600 en 5 variantes');
ok(!array_filter($pages, fn ($i) => str_contains($i['name'], 'cookie') || str_contains($i['name'], 'privacy')), 'las páginas legales no se proponen como oportunidad');
ok($r['notes'] !== [] && str_contains($r['notes'][0], '2 página'), 'se avisa de cuántas páginas se han excluido');
ok($grok['tier'] === 'high', 'una página con mucho peso en el sitio es prioridad alta');
$med = array_values(array_filter($pages, fn ($i) => str_contains($i['name'], 'mediana')));
$small = array_values(array_filter($pages, fn ($i) => str_contains($i['name'], 'pequena')));
ok($med && $med[0]['tier'] === 'medium', 'volumen razonable: prioridad media');
ok($small && $small[0]['tier'] === 'explore' && str_contains($small[0]['advice'], 'Pocos datos') && !str_contains($small[0]['advice'], 'top 3'), 'pocas impresiones: exploración, sin consejo concreto');
ok(array_sum($r['tiers']) === count($r['cards']), 'los contadores de prioridad cuadran (una tarjeta por página o consulta)');
$grokCtr = array_values(array_filter($pages, fn ($i) => str_contains($i['name'], 'grok') && $i['type'] === 'low_ctr'));
ok($grokCtr && str_contains($grokCtr[0]['detail'], 'estimación'), 'las cifras de potencial se presentan como estimación');

echo "== 5. Contexto: ediciones pasadas y edición vigente\n";
$cy = (int) date('Y');
$pdo = db();
put($pdo, 'gsc_site_daily', $cur, null, 20, 2000, 8.0);
put($pdo, 'gsc_page_daily', $cur, 'https://x.test/seleccion-oficial-' . ($cy - 6), 0, 99, 7.0);   // edición antigua con 99 impresiones
put($pdo, 'gsc_page_daily', $cur, 'https://x.test/ganadores-' . ($cy - 3) . '-' . substr((string) ($cy - 2), 2), 2, 96, 7.0);
put($pdo, 'gsc_page_daily', $cur, 'https://x.test/seleccion-oficial-' . $cy, 0, 150, 7.0);          // edición vigente, volumen medio
put($pdo, 'gsc_page_daily', $cur, 'https://x.test/seleccion-oficial-' . ($cy - 1), 0, 150, 7.0);    // edición del año pasado: sin cambio de trato
put($pdo, 'gsc_page_daily', $cur, 'https://x.test/nosotros', 0, 150, 7.0);                           // sin año: trato normal
$r = (new OpportunityService($pdo))->detect($prop, 28);
$by = fn (string $needle) => array_values(array_filter($r['items'], fn ($i) => $i['kind'] === 'page' && str_contains($i['name'], $needle)));
$old6 = $by('seleccion-oficial-' . ($cy - 6));
ok($old6 && $old6[0]['tier'] === 'archive' && str_contains($old6[0]['advice'], 'histórica') && str_contains($old6[0]['advice'], 'sin urgencia'), 'una edición de hace años (99 impresiones) es archivo, no prioridad alta');
$win = $by('ganadores');
ok($win && $win[0]['tier'] === 'archive' && $win[0]['year'] === $cy - 3, 'una página «ganadores» de una edición pasada también (se toma el año más reciente de la URL)');
$now = $by('seleccion-oficial-' . $cy);
ok($now && $now[0]['tier'] === 'high' && $now[0]['context'] === 'current', 'la edición vigente sube de prioridad media a alta');
$last = $by('seleccion-oficial-' . ($cy - 1));
ok($last && $last[0]['tier'] === 'medium' && $last[0]['context'] === null, 'la edición del año pasado se trata con normalidad');
$plain = $by('nosotros');
ok($plain && $plain[0]['tier'] === 'medium' && $plain[0]['year'] === null, 'una página sin año no cambia');
ok($r['tiers']['archive'] === count(array_filter($r['cards'], fn ($i) => $i['tier'] === 'archive')) && array_sum($r['tiers']) === count($r['cards']), 'los contadores incluyen el archivo');
ok(OpportunityService::yearOf('https://x.test/winners-2020-21/') === 2020 && OpportunityService::yearOf('https://x.test/blog/mi-post') === null && OpportunityService::yearOf('https://x.test/p/12345') === null, 'el año se toma de la ruta; un número cualquiera no cuenta');

echo "== 6. Umbrales por volumen, métrica exacta y una tarjeta por URL\n";
$pdo = db();
put($pdo, 'gsc_site_daily', $cur, null, 60, 5000, 8.0);
put($pdo, 'gsc_site_daily', $prev, null, 60, 5000, 8.0);
// sin comparar con histórico: 33 y 61 impresiones bien posicionadas NO son prioridad alta; 800 sí; 150 media
foreach ([['https://x.test/pocas33', 33], ['https://x.test/pocas61', 61], ['https://x.test/medias150', 150], ['https://x.test/muchas800', 800]] as [$u, $i]) {
    put($pdo, 'gsc_page_daily', $cur, $u, 0, $i, 7.0);
}
// caída de IMPRESIONES (167 → 83, sin perder clics): el texto debe decir impresiones, no clics
put($pdo, 'gsc_page_daily', $prev, 'https://x.test/seedance', 1, 167, 8.0);
put($pdo, 'gsc_page_daily', $cur, 'https://x.test/seedance', 1, 83, 8.0);
// una página con CTR bajo Y caída de clics: debe salir UNA sola tarjeta con los dos motivos
put($pdo, 'gsc_page_daily', $prev, 'https://x.test/doble', 14, 700, 7.0);
put($pdo, 'gsc_page_daily', $cur, 'https://x.test/doble', 3, 700, 7.0);
$r = (new OpportunityService($pdo))->detect($prop, 28);
$card = fn (string $needle) => array_values(array_filter($r['cards'], fn ($c) => $c['kind'] === 'page' && str_contains($c['name'], $needle)));
ok($card('pocas33') && $card('pocas33')[0]['tier'] === 'explore' && $card('pocas61')[0]['tier'] === 'explore', 'con 33 o 61 impresiones no hay prioridad alta (ni media): exploración');
ok($card('medias150')[0]['tier'] === 'medium' && $card('muchas800')[0]['tier'] === 'high', '150 impresiones: media; 800: alta');
$sd = $card('seedance');
ok($sd && $sd[0]['basis'] === 'impressions' && str_contains($sd[0]['advice'], 'Ha perdido impresiones') && str_contains($sd[0]['advice'], '167 → 83') && !str_contains($sd[0]['advice'], 'perdido clics'), 'una caída de 167 a 83 impresiones lo dice con esas palabras y esas cifras (no «clics»)');
$dbl = $card('doble');
ok(count($dbl) === 1, 'una página con varios motivos sale UNA sola vez en las tarjetas');
$types = $dbl ? $dbl[0]['types'] : [];
ok(in_array('low_ctr', $types, true) && in_array('drop', $types, true) && count($dbl[0]['reasons']) >= 1, 'agrupa sus motivos (CTR bajo y caída de clics) en una tarjeta');
$dropReason = array_values(array_filter(array_merge([['type' => $dbl[0]['type'], 'basis' => $dbl[0]['basis']]], $dbl[0]['reasons']), fn ($x) => $x['type'] === 'drop'));
ok($dropReason && $dropReason[0]['basis'] === 'clicks', 'y la caída se identifica como de clics');
ok(count($r['items']) > count($r['cards']) && array_sum($r['card_counts']) >= count($r['cards']), 'las tarjetas son menos que los avisos sueltos; los filtros cuentan cada motivo');
$ids = array_map(fn ($c) => $c['kind'] . '|' . $c['name'], $r['cards']);
ok(count($ids) === count(array_unique($ids)), 'ninguna URL ni consulta se repite entre las tarjetas');

echo "== 7. Coincidencias inesperadas (otra marca o error de escritura)\n";
$pdo = db();
put($pdo, 'gsc_site_daily', $cur, null, 30, 3000, 8.0);
put($pdo, 'gsc_site_daily', $prev, null, 30, 3000, 8.0);
foreach ([['screen ai', 14, 101, 4.3], ['screenai', 10, 86, 2.5], ['creen ai', 3, 249, 8.4], ['screen ia', 5, 45, 4.4]] as [$q, $c, $i, $ps]) { put($pdo, 'gsc_query_daily', $cur, $q, $c, $i, $ps); }
put($pdo, 'gsc_query_daily', $prev, 'creen ai', 12, 1191, 8.0);   // antes tenía mucho más: sin la regla sería una «caída» de prioridad alta
$r = (new OpportunityService($pdo))->detect(['id' => 1, 'domain' => 'screenai.es'], 28);
$names = array_column(array_filter($r['items'], fn ($i) => $i['kind'] === 'query'), 'name');
ok(!in_array('creen ai', $names, true), '«creen ai» (parecida a la marca, CTR 1,2 % frente al 12,8 % de la marca) no sale como oportunidad ni como caída propia');
$brandItems = array_filter($r['items'], fn ($i) => $i['kind'] === 'query' && $i['name'] === 'screenai');
ok(!$brandItems || $brandItems && array_values($brandItems)[0]['brand'] === true, 'las búsquedas de marca siguen su curso normal (marcadas como marca)');
$tail = (new OpportunityService($pdo))->detect(['id' => 1, 'domain' => 'x.test'], 28);
ok(in_array('creen ai', array_column(array_filter($tail['items'], fn ($i) => $i['kind'] === 'query'), 'name'), true), 'sin marca conocida (dominio corto) no se hace esta distinción y se trata como cualquier consulta');

echo "== 8. Una sola clasificación: dominios escritos, marcas ajenas etiquetadas y dudosas\n";
$pdo = db();
$pdo->exec('CREATE TABLE gsc_query_label (domain TEXT, query_key TEXT, query TEXT, label TEXT, updated_at TEXT, PRIMARY KEY (domain, query_key))');
put($pdo, 'gsc_site_daily', $cur, null, 30, 3000, 8.0);
put($pdo, 'gsc_site_daily', $prev, null, 30, 3000, 8.0);
foreach (['8i8gent.com', 'marca ajena', 'dudosa', 'normal'] as $q) {
    put($pdo, 'gsc_query_daily', $prev, $q, 0, 995, 6.0);
    put($pdo, 'gsc_query_daily', $cur, $q, 0, 23, 6.0);
}
foreach ([['marca ajena', 'third'], ['dudosa', 'review']] as [$q, $l]) { $pdo->prepare('INSERT INTO gsc_query_label VALUES (?, ?, ?, ?, ?)')->execute(['x.test', $q, $q, $l, '2026-10-01']); }
$r = (new OpportunityService($pdo))->detect($prop, 28);
$q = fn (string $n) => array_values(array_filter($r['items'], fn ($i) => $i['kind'] === 'query' && $i['name'] === $n));
ok(!$q('8i8gent.com'), '«8i8gent.com» (un dominio escrito en el buscador, 995 → 23) ya no sale como caída');
ok(!$q('marca ajena'), 'una consulta etiquetada como marca de otra empresa deja de recomendarse como tráfico propio');
ok($q('dudosa') && $q('dudosa')[0]['pending'] === true, 'una consulta «pendiente de investigar» sigue visible pero marcada (no se decide sola)');
ok($q('normal') && $q('normal')[0]['pending'] === false, 'una consulta normal con la misma caída sí sale');
ok($r['notes'] !== [] && str_contains(implode(' ', $r['notes']), '1 dominios escritos en el buscador') && str_contains(implode(' ', $r['notes']), 'etiquetadas'), 'el informe explica cuántas consultas se han apartado y por qué');
ok(array_keys(\Modules\SearchConsole\Services\QueryLabelService::LABELS) === ['own', 'third', 'review', 'ignore'], 'tres estados de marca (propia, ajena, dudosa) más «no relevante»');

echo "\n" . ($fails ? "RESULTADO: $fails de $n comprobaciones FALLAN\n" : "RESULTADO: las $n comprobaciones pasan\n");
exit($fails ? 1 : 0);
