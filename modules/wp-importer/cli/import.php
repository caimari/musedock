#!/usr/bin/env php
<?php
/**
 * WP Importer por línea de comandos: clona un WordPress (API REST pública) en un tenant ya creado.
 *
 * Uso:
 *   php modules/wp-importer/cli/import.php --site=https://nycinfest.com --tenant=60            # simulación (no escribe nada)
 *   php modules/wp-importer/cli/import.php --site=https://nycinfest.com --tenant=60 --run      # importa de verdad
 *
 * Opciones: --policy=skip|overwrite|rename (páginas ya existentes), --no-media, --no-styles, --no-sliders (por defecto se importa todo: media, categorías, tags, entradas,
 * páginas, menús, sliders y estilos). Las direcciones (slugs) de las páginas se conservan.
 */
if (php_sapi_name() !== 'cli') {
    die('Solo CLI');
}
define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/core/Env.php';
\Screenart\Musedock\Env::load();
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/core/helpers.php';
\Screenart\Musedock\ModuleAutoloader::init();
foreach (['Blog\\Models\\' => '/../blog/models', 'WpImporter\\Services\\' => '/services', 'WpImporter\\Controllers\\' => '/controllers', 'MediaManager\\Controllers\\' => '/../media-manager/controllers', 'MediaManager\\Models\\' => '/../media-manager/models'] as $ns => $dir) {
    \Screenart\Musedock\ModuleAutoloader::registerNamespace($ns, dirname(__DIR__) . $dir);
}

use Screenart\Musedock\Database;
use WpImporter\Services\{WpApiClient, WpContentImporter, WpMediaImporter, WpStyleExtractor};

$o = getopt('', ['site:', 'tenant:', 'policy:', 'run', 'no-media', 'no-styles', 'no-sliders']);
$site = rtrim((string) ($o['site'] ?? ''), '/');
$tenantId = (int) ($o['tenant'] ?? 0);
$run = isset($o['run']);
if (!preg_match('#^https://[a-z0-9.-]+$#i', $site) || $tenantId <= 0) {
    fwrite(STDERR, "Uso: php cli/import.php --site=https://dominio --tenant=ID [--run]\n");
    exit(1);
}
$pdo = Database::connect();
$t = $pdo->prepare('SELECT id, name, domain, is_client_domain FROM tenants WHERE id = ?');
$t->execute([$tenantId]);
$tenant = $t->fetch(PDO::FETCH_ASSOC);
if (!$tenant) {
    fwrite(STDERR, "No existe el tenant $tenantId\n");
    exit(1);
}
if ((int) $tenant['is_client_domain'] === 1) {
    fwrite(STDERR, "El tenant es de un cliente: no se importa desde la línea de comandos.\n");
    exit(1);
}
// el contexto de tenant que usan los importadores y el Media Manager
$GLOBALS['tenant'] = ['id' => $tenantId, 'domain' => $tenant['domain']];
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
$_SESSION['admin'] = ['id' => 1, 'tenant_id' => $tenantId, 'role' => 'admin'];
@set_time_limit(0);
ini_set('memory_limit', '1024M');
if (function_exists('proc_nice')) {
    @proc_nice(10);
}

echo "Origen: $site → tenant #{$tenantId} {$tenant['domain']} · " . ($run ? 'IMPORTACIÓN REAL' : 'simulación') . "\n";
$client = new WpApiClient($site);
$test = $client->testConnection();
if (empty($test['success'])) {
    fwrite(STDERR, 'No se pudo conectar con la API de WordPress: ' . ($test['error'] ?? json_encode($test)) . "\n");
    exit(1);
}
$pages = $client->getAllPages();
$posts = $client->getAllPosts();
$cats = $client->getAllCategories();
$tags = $client->getAllTags();
$menus = $client->getMenus();
$extract = function (string $html): array {
    preg_match_all('#<img[^>]+src=["\']([^"\']+)["\']#i', $html, $m);
    return array_values(array_filter($m[1], fn ($u) => preg_match('#^https?://#', $u)));
};
$guessMime = function (string $url): string {
    $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    return ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml'][$ext] ?? 'image/jpeg';
};
$media = [];
foreach (array_merge($pages, $posts) as $p) {
    if (!empty($p['featured_media'])) {
        $media[(int) $p['featured_media']] = true;
    }
    foreach ($extract((string) ($p['content']['rendered'] ?? '')) as $u) {
        $media['url:' . $u] = $u;
    }
}
$carousels = $client->getCarouselSlidersFromPages($pages);
$hero = $client->getHeroSliderFromHtml();
$sliders = array_merge($client->getSliders(), $hero, $carousels);
printf("WordPress: %d páginas, %d entradas, %d categorías, %d etiquetas, %d menús, %d imágenes distintas referenciadas, %d sliders/carruseles\n",
    count($pages), count($posts), count($cats), count($tags), count($menus), count($media), count($sliders));
foreach ($pages as $p) {
    printf("  · %-40s /%s  (%s KB)\n", mb_substr(html_entity_decode(strip_tags((string) ($p['title']['rendered'] ?? ''))), 0, 40), $p['slug'] ?? '', round(strlen((string) ($p['content']['rendered'] ?? '')) / 1000, 1));
}
if (!$run) {
    echo "\nSimulación terminada: no se ha escrito nada. Añade --run para importar.\n";
    exit(0);
}

// ---- importación real (mismo orden que el importador del panel)
$mediaImporter = new WpMediaImporter($client, $tenantId);
$items = [];
if (!isset($o['no-media'])) {
    foreach ($media as $key => $value) {
        if ($value === true) {
            if ($md = $client->getMedia((int) $key)) {
                $items[] = $md;
            }
        } else {
            $items[] = ['id' => null, 'source_url' => $value, 'mime_type' => $guessMime($value), 'title' => ['rendered' => pathinfo(parse_url($value, PHP_URL_PATH) ?: '', PATHINFO_FILENAME)], 'alt_text' => '', 'caption' => ['rendered' => '']];
        }
    }
    $seen = [];
    $items = array_values(array_filter($items, function ($i) use (&$seen) {
        $u = $i['source_url'] ?? '';
        return $u !== '' && !isset($seen[$u]) && ($seen[$u] = true);
    }));
    echo 'Importando ' . count($items) . " imágenes…\n";
    $mr = $mediaImporter->importAll($items, function ($done, $total) {
        if ($done % 25 === 0 || $done === $total) {
            echo "  media $done/$total\n";
        }
    });
    printf("  imágenes: %d importadas, %d omitidas, %d errores\n", $mr['imported'], $mr['skipped'], count($mr['errors']));
}
$ci = new WpContentImporter($client, $mediaImporter, $tenantId);
$ci->setDuplicatePolicy(in_array($o['policy'] ?? '', ['skip', 'overwrite', 'rename'], true) ? $o['policy'] : 'skip');
$structure = $ci->detectAndApplyUrlStructure($posts, $pages, $site);
$ci->importCategories($cats);
$ci->importTags($tags);
$ci->importPosts($posts, null, false);
$ci->importPages($pages);
$ci->configureHomepage($client->getSiteSettings(), $pages);
$ci->importMenus($menus);
if (!isset($o['no-sliders'])) {
    $ci->importSliders($sliders);
}
$s = $ci->getStats();
printf("Contenido: %d páginas, %d entradas, %d categorías, %d etiquetas, %d menús (%d elementos), %d sliders (%d diapositivas)\n",
    $s['pages_imported'], $s['posts_imported'], $s['categories_imported'], $s['tags_imported'], $s['menus_imported'], $s['menu_items_imported'], $s['sliders_imported'], $s['slides_imported']);
if (!isset($o['no-styles'])) {
    $themeSlug = (string) ($pdo->query('SELECT theme FROM tenants WHERE id = ' . $tenantId)->fetchColumn() ?: 'default');
    $se = new WpStyleExtractor($client, $mediaImporter, $tenantId, $themeSlug);
    $ex = $se->extract();
    if (!empty($ex['theme_options'])) {
        $se->applyThemeOptions($ex['theme_options']);
    }
    if (!empty($ex['site_settings'])) {
        $se->applySiteSettings($ex['site_settings']);
    }
    echo 'Estilos aplicados (' . count($ex['theme_options'] ?? [], COUNT_RECURSIVE) . " opciones; fuentes: " . implode(', ', (array) ($ex['google_fonts'] ?? [])) . ")\n";
}
$errs = array_merge($ci->getErrors(), $mediaImporter->getErrors());
echo $errs ? count($errs) . " avisos/errores:\n  - " . implode("\n  - ", array_slice(array_map(fn ($e) => is_string($e) ? $e : json_encode($e, JSON_UNESCAPED_UNICODE), $errs), 0, 15)) . "\n" : "Sin errores.\n";
try {
    \Screenart\Musedock\Cache\HtmlCache::purge($tenantId);
} catch (\Throwable $e) {
}
echo "Hecho. Recuerda dejar el tenant sin indexar hasta el cambio de dominio.\n";
