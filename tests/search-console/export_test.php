<?php
/**
 * Exportación a CSV: celdas protegidas contra inyección de fórmulas en Excel y formato de números.
 *   php tests/search-console/export_test.php
 */
define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/core/Env.php';
\Screenart\Musedock\Env::load();
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/core/helpers.php';
\Screenart\Musedock\ModuleAutoloader::init();
\Screenart\Musedock\ModuleAutoloader::registerNamespace('Modules\\SearchConsole\\Controllers\\', APP_ROOT . '/modules/search-console/controllers');
\Screenart\Musedock\ModuleAutoloader::registerNamespace('Modules\\SearchConsole\\Services\\', APP_ROOT . '/modules/search-console/services');

$fails = 0;
function ok(bool $c, string $m): void { global $fails; if (!$c) { $fails++; echo "  FALLA: $m\n"; } }

$cls = new ReflectionClass(\Modules\SearchConsole\Controllers\BaseController::class);
$cell = $cls->getMethod('csvCell'); $cell->setAccessible(true);
$num = $cls->getMethod('num'); $num->setAccessible(true);

ok($cell->invoke(null, 'hola') === '"hola"', 'texto normal entre comillas');
ok($cell->invoke(null, 'di "hola"') === '"di ""hola"""', 'comillas dobles escapadas');
ok($cell->invoke(null, '=HYPERLINK("http://x")') === '"\'=HYPERLINK(""http://x"")"', 'una consulta que empieza por = no se ejecuta como fórmula');
ok($cell->invoke(null, '+34 600') === '"\'+34 600"' && $cell->invoke(null, '@cmd') === '"\'@cmd"' && $cell->invoke(null, '-1+1') === '"\'-1+1"', 'también +, @ y -');
ok($cell->invoke(null, '-5') === '"-5"' && $cell->invoke(null, 12) === '"12"', 'los números (incluso negativos) no se alteran');
ok($num->invoke(null, 1234.567, 2) === '1234,57' && $num->invoke(null, 0.5, 1) === '0,5', 'decimales con coma, sin separador de miles (Excel en español)');

echo $fails ? "RESULTADO: $fails comprobaciones FALLAN\n" : "RESULTADO: las comprobaciones pasan\n";
exit($fails ? 1 : 0);
