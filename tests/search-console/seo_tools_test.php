<?php
/**
 * Enviar sitemap a Google, inspección de URL e IndexNow. Sin red: Google y los HTTP están simulados;
 * la BD real va dentro de una transacción que se deshace.
 *   php tests/search-console/seo_tools_test.php
 */
define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/core/Env.php';
\Screenart\Musedock\Env::load();
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/core/helpers.php';
foreach (['Crypto', 'GoogleApiException', 'GoogleClient', 'PropertyService', 'SitemapService', 'UrlInspectionService', 'IndexNowService'] as $c) {
    require_once APP_ROOT . "/modules/search-console/services/{$c}.php";
}

use Modules\SearchConsole\Services\{GoogleApiException, GoogleClient, IndexNowService, SitemapService, UrlInspectionService};

$fails = 0;
function ok(bool $c, string $m): void { global $fails; if (!$c) { $fails++; echo "  FALLA: $m\n"; } }

class FakeG extends GoogleClient
{
    public array $calls = [];
    public ?array $reply = [];
    public bool $fail = false;
    public function __construct() {}
    public function request(string $method, string $url, ?array $body = null): array
    {
        $this->calls[] = [$method, $url, $body];
        if ($this->fail) { throw new GoogleApiException('denegado', 403); }
        return $this->reply ?? [];
    }
}

echo "== Sitemap\n";
$g = new FakeG();
$sm = new SitemapService(fn ($u) => 200);
$r = $sm->submit($g, 'sc-domain:alexapost.com', 'alexapost.com');
ok($r['ok'] && $g->calls[0][0] === 'PUT' && str_contains($g->calls[0][1], rawurlencode('sc-domain:alexapost.com') . '/sitemaps/' . rawurlencode('https://alexapost.com/sitemap.xml')), 'PUT a sitemaps con la propiedad y el sitemap codificados');
$g2 = new FakeG();
$r = (new SitemapService(fn ($u) => 404))->submit($g2, 'sc-domain:x.com', 'x.com');
ok(!$r['ok'] && $g2->calls === [], 'si el sitemap no responde, no se envía nada a Google');
$g3 = new FakeG(); $g3->fail = true;
ok(!$sm->submit($g3, 'sc-domain:x.com', 'x.com')['ok'], 'si Google lo rechaza, se informa');
$g4 = new FakeG(); $g4->reply = ['lastSubmitted' => '2026-10-16T10:00:00Z', 'isPending' => true, 'errors' => '0'];
$st = $sm->status($g4, 'sc-domain:x.com', 'www.x.com');
ok($st && $st['pending'] === true && $st['sitemap'] === 'https://x.com/sitemap.xml', 'estado del sitemap en Google (www se normaliza)');

echo "== Inspección de URL\n";
$redir = UrlInspectionService::parse(['inspectionResult' => ['inspectionResultLink' => 'https://sc/x', 'indexStatusResult' => ['verdict' => 'NEUTRAL', 'coverageState' => 'Page with redirect', 'lastCrawlTime' => '2026-10-15T08:00:00Z', 'googleCanonical' => 'https://a.com/x', 'userCanonical' => 'https://a.com/x', 'pageFetchState' => 'SUCCESSFUL']]], 'https://a.com/x');
ok($redir['ok'] && !$redir['indexed'] && $redir['tone'] === 'info' && str_contains($redir['coverage_es'], 'redirección'), 'una redirección se explica en español');
$dup = UrlInspectionService::parse(['inspectionResult' => ['indexStatusResult' => ['verdict' => 'NEUTRAL', 'coverageState' => 'Duplicate, Google chose different canonical than user', 'googleCanonical' => 'https://b.com/', 'userCanonical' => 'https://a.com/']]], 'https://a.com/');
ok($dup['canonical_differs'] === true, 'detecta que Google eligió otro canonical');
$good = UrlInspectionService::parse(['inspectionResult' => ['indexStatusResult' => ['verdict' => 'PASS', 'coverageState' => 'Submitted and indexed', 'googleCanonical' => 'https://a.com/', 'userCanonical' => 'https://a.com']]], 'https://a.com/');
ok($good['indexed'] && !$good['canonical_differs'], 'indexada y sin canonical distinto (la barra final no cuenta)');
$es = UrlInspectionService::parse(['inspectionResult' => ['indexStatusResult' => ['verdict' => 'PASS', 'coverageState' => 'Enviada e indexada']]], 'https://a.com/');
$esRedir = UrlInspectionService::parse(['inspectionResult' => ['indexStatusResult' => ['verdict' => 'NEUTRAL', 'coverageState' => 'Página con redirección']]], 'https://a.com/');
ok($es['tone'] === 'success' && $esRedir['tone'] === 'info', 'Google responde en español (languageCode=es): los colores no dependen del texto en inglés');
ok(UrlInspectionService::parse([], 'x')['ok'] === false, 'sin datos de Google, error controlado');
ok(UrlInspectionService::belongsTo('https://www.alexapost.com/blog', 'alexapost.com') && UrlInspectionService::belongsTo('https://sub.alexapost.com/', 'alexapost.com'), 'URL del dominio (con www o subdominio)');
ok(!UrlInspectionService::belongsTo('https://otro.com/', 'alexapost.com') && !UrlInspectionService::belongsTo('https://evilalexapost.com/', 'alexapost.com') && !UrlInspectionService::belongsTo('javascript:alert(1)', 'alexapost.com'), 'URL de otro dominio o con esquema raro: no');
ok(UrlInspectionService::belongsTo('https://s.musedock.com/a', 'musedock.com', 'https://s.musedock.com/') && !UrlInspectionService::belongsTo('https://t.musedock.com/a', 'musedock.com', 'https://s.musedock.com/'), 'un subdominio virtual solo inspecciona URL de su prefijo (no las de otros tenants)');
$g5 = new FakeG(); $g5->reply = ['inspectionResult' => ['indexStatusResult' => ['verdict' => 'PASS', 'coverageState' => 'Submitted and indexed']]];
(new UrlInspectionService())->inspect($g5, 'sc-domain:a.com', 'https://a.com/');
ok($g5->calls[0][0] === 'POST' && str_contains($g5->calls[0][1], 'urlInspection/index:inspect') && $g5->calls[0][2]['siteUrl'] === 'sc-domain:a.com', 'llama a urlInspection.index.inspect con la propiedad');

echo "== IndexNow\n";
$xml = '<?xml version="1.0"?><urlset><url><loc>https://x.test/</loc><lastmod>2026-10-16</lastmod></url><url><loc>https://x.test/a?b=1&amp;c=2</loc><lastmod>2026-10-01</lastmod></url><url><loc>https://otro.test/z</loc></url></urlset>';
$p = IndexNowService::parseSitemap($xml, 'x.test');
ok(count($p) === 2 && isset($p['https://x.test/a?b=1&c=2']) && $p['https://x.test/'] === '2026-10-16', 'lee el sitemap, decodifica y solo conserva URL del propio host');

$pdo = \Screenart\Musedock\Database::connect();
$pdo->beginTransaction();
$tmp = sys_get_temp_dir() . '/gscin_' . bin2hex(random_bytes(4));
mkdir($tmp . '/storage', 0777, true); mkdir($tmp . '/public', 0777, true);
try {
    $sitemap = $xml;
    $posts = [];
    $svc = new IndexNowService($pdo, function ($u) use (&$sitemap) { return $sitemap; }, function ($u, $d) use (&$posts) { $posts[] = $d; return 200; }, $tmp . '/storage/indexnow.json');
    $key = $svc->ensureKey();
    ok(preg_match('/^[a-f0-9]{32}$/', $key) && is_file($tmp . "/public/{$key}.txt") && file_get_contents($tmp . "/public/{$key}.txt") === $key, 'crea la clave y su fichero público');
    $r = $svc->scanHost('x.test');
    ok($r['baseline'] && $r['notified'] === 0 && $posts === [], 'la primera lectura solo toma nota: no avisa de lo que ya existía');
    $r = $svc->scanHost('x.test');
    ok($r['notified'] === 0 && $posts === [], 'sin cambios, no avisa');
    $sitemap = str_replace('2026-10-01', '2026-10-16', $xml) . '';
    $sitemap = str_replace('</urlset>', '<url><loc>https://x.test/nueva</loc><lastmod>2026-10-16</lastmod></url></urlset>', $sitemap);
    $r = $svc->scanHost('x.test');
    ok($r['notified'] === 2 && count($posts) === 1 && sort($posts[0]['urlList']) !== null && in_array('https://x.test/nueva', $posts[0]['urlList'], true) && $posts[0]['key'] === $key && $posts[0]['host'] === 'x.test', 'avisa solo de la URL nueva y la que cambió de lastmod');
    $r = $svc->scanHost('x.test');
    ok($r['notified'] === 0 && count($posts) === 1, 'lo ya avisado no se repite');
    $svc->setEnabled(false);
    $sitemap = str_replace('/nueva', '/otra', $sitemap);
    $r = $svc->scanHost('x.test');
    ok($r['notified'] === 0 && count($posts) === 1 && str_contains($r['status'], 'desactivado'), 'desactivado: no avisa');
    $svc->setEnabled(true);
    $r = $svc->scanHost('x.test');
    ok($r['notified'] === 1, 'al activarlo, avisa de lo pendiente');
    $bad = new IndexNowService($pdo, function ($u) use (&$sitemap) { return $sitemap; }, fn ($u, $d) => 500, $tmp . '/storage/indexnow.json');
    $sitemap = str_replace('/otra', '/otra2', $sitemap);
    $r = $bad->scanHost('x.test');
    ok($r['notified'] === 0 && str_contains($r['status'], '500'), 'si IndexNow falla, no da por avisado');
    $r = $svc->scanHost('x.test');
    ok($r['notified'] === 1, 'y se reintenta en la siguiente lectura');
    ok((new IndexNowService($pdo, fn ($u) => null, fn ($u, $d) => 200, $tmp . '/storage/indexnow.json'))->scanHost('sin-sitemap.test')['status'] !== '', 'un dominio sin sitemap no rompe nada');

    // privacidad: solo dominios de tenants propios
    $client = $pdo->query('SELECT id, domain FROM tenants WHERE is_client_domain = 0 ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $pdo->prepare('UPDATE tenants SET domain = ?, is_client_domain = 1 WHERE id = ?')->execute(['cliente-in-test.example', $client['id']]);
    ok(!in_array('cliente-in-test.example', $svc->ownHosts(), true), 'los dominios de clientes no se vigilan');
} finally {
    $pdo->rollBack();
    array_map('unlink', glob($tmp . '/public/*') ?: []); array_map('unlink', glob($tmp . '/storage/*') ?: []); @rmdir($tmp . '/public'); @rmdir($tmp . '/storage'); @rmdir($tmp);
}

echo $fails ? "RESULTADO: $fails comprobaciones FALLAN\n" : "RESULTADO: las comprobaciones pasan\n";
exit($fails ? 1 : 0);
