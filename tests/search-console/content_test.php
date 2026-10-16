<?php
/**
 * Relación URL → contenido del CMS: título y «Editar», sin cruzar tenants. Usa la BD real (solo lectura).
 *   php tests/search-console/content_test.php
 */
define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/core/Env.php';
\Screenart\Musedock\Env::load();
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/core/helpers.php';
require_once APP_ROOT . '/modules/search-console/services/ContentMapService.php';

use Modules\SearchConsole\Services\ContentMapService;

$fails = 0;
function ok(bool $c, string $m): void { global $fails; if (!$c) { $fails++; echo "  FALLA: $m\n"; } }

$pdo = \Screenart\Musedock\Database::connect();
$svc = new ContentMapService($pdo);

// Un artículo real de un tenant con slug propio
$post = $pdo->query("SELECT s.tenant_id, s.slug, s.prefix, b.title, b.id FROM slugs s JOIN blog_posts b ON b.id = s.reference_id AND b.tenant_id = s.tenant_id WHERE s.module = 'blog' AND s.tenant_id IS NOT NULL AND COALESCE(s.prefix, '') = '' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$post) { exit("sin artículos de prueba\n"); }
$url = 'https://ejemplo.test/' . $post['slug'];
$r = $svc->resolve((int) $post['tenant_id'], 'ejemplo.test', [$url, 'https://ejemplo.test/no-existe-xyz'], 'admin');
ok(isset($r[$url]) && $r[$url]['title'] === $post['title'] && str_ends_with($r[$url]['edit'], '/admin/blog/posts/' . $post['id'] . '/edit') && str_starts_with($r[$url]['edit'], 'https://ejemplo.test/'), 'el artículo se reconoce con su título y su enlace de edición');
ok(!isset($r['https://ejemplo.test/no-existe-xyz']), 'una URL que no es del CMS no se inventa');
$rel = $svc->resolve((int) $post['tenant_id'], 'ejemplo.test', [$url], 'admin', true);
ok(str_starts_with($rel[$url]['edit'], '/admin/blog/posts/'), 'en el panel del propio tenant, el enlace es relativo');
$otro = $pdo->query('SELECT id FROM tenants WHERE id <> ' . (int) $post['tenant_id'] . ' ORDER BY id LIMIT 1')->fetchColumn();
ok($svc->resolve((int) $otro, 'ejemplo.test', [$url], 'admin') === [], 'otro tenant NO ve el contenido de este (no se mezclan)');
ok($svc->resolve(null, 'ejemplo.test', [$url]) === [], 'el sitio principal tampoco ve contenido de un tenant');
$loc = $svc->resolve((int) $post['tenant_id'], 'ejemplo.test', ['https://ejemplo.test/en/' . $post['slug']], 'admin');
ok(count($loc) === 1, 'una URL con prefijo de idioma (/en/…) se reconoce');

echo $fails ? "RESULTADO: $fails comprobaciones FALLAN\n" : "RESULTADO: las comprobaciones pasan\n";
exit($fails ? 1 : 0);
