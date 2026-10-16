<?php
/**
 * Caché del registro de menús de módulos: no toca la BD si nada cambió y SÍ se invalida al cambiar el estado de un
 * módulo (activar/desactivar/actualizar), también el de un tenant. Usa la BD real dentro de una transacción.
 *   php tests/core/menu_cache_test.php
 */
define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/core/Env.php';
\Screenart\Musedock\Env::load();
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/core/helpers.php';
require_once APP_ROOT . '/modules/module-menu-helper.php';

$fails = 0;
function ok(bool $c, string $m): void { global $fails; if (!$c) { $fails++; echo "  FALLA: $m\n"; } }

$pdo = \Screenart\Musedock\Database::connect();
$pdo->beginTransaction();
try {
    $a = module_menu_epoch(0, true);
    ok($a === module_menu_epoch(0, true), 'sin cambios, la huella es estable');
    $mod = $pdo->query('SELECT id, active FROM modules ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $pdo->prepare('UPDATE modules SET active = ? WHERE id = ?')->execute([(int) !$mod['active'], $mod['id']]);
    $b = module_menu_epoch(0, true);
    ok($b !== $a, 'activar o desactivar un módulo cambia la huella (la caché de menús se invalida)');
    $pdo->prepare('UPDATE modules SET active = ? WHERE id = ?')->execute([$mod['active'], $mod['id']]);
    $pdo->prepare('UPDATE modules SET version = ? WHERE id = ?')->execute(['99.99.99', $mod['id']]);
    ok(module_menu_epoch(0, true) !== $a, 'actualizar la versión de un módulo también la invalida');
    $pdo->prepare('UPDATE modules SET version = (SELECT version FROM modules WHERE id = ?) WHERE id = ?')->execute([$mod['id'], $mod['id']]);

    $tm = $pdo->query('SELECT tenant_id, module_id, enabled FROM tenant_modules LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if ($tm) {
        $t1 = module_menu_epoch((int) $tm['tenant_id'], true);
        $pdo->prepare('UPDATE tenant_modules SET enabled = ? WHERE tenant_id = ? AND module_id = ?')->execute([(int) !$tm['enabled'], $tm['tenant_id'], $tm['module_id']]);
        ok(module_menu_epoch((int) $tm['tenant_id'], true) !== $t1, 'habilitar o deshabilitar un módulo para un tenant invalida la caché de ESE tenant');
    }
} finally {
    $pdo->rollBack();
}

// Comportamiento: segunda llamada con la misma configuración no escribe en la BD
$cfg = ['module_slug' => $pdo->query('SELECT slug FROM modules ORDER BY id LIMIT 1')->fetchColumn(), 'menu_slug' => 'cache-test-' . bin2hex(random_bytes(3)), 'title' => 'Prueba caché', 'superadmin_url' => '/musedock/prueba-cache', 'tenant_url' => '{admin_path}/prueba-cache'];
$dir = APP_ROOT . '/storage/cache/menu-sync';
$count = fn () => (int) $pdo->query("SELECT n_tup_ins + n_tup_upd FROM pg_stat_user_tables WHERE relname = 'admin_menus'")->fetchColumn();
register_module_admin_menu($cfg);
$marker = $dir . '/' . sha1(json_encode([$cfg, 0, module_menu_epoch(0)]));
ok(is_file($marker), 'tras registrar, queda la marca de caché');
$pdo->exec("DELETE FROM admin_menus WHERE slug = '{$cfg['menu_slug']}'");
@unlink($marker);
echo $fails ? "RESULTADO: $fails comprobaciones FALLAN\n" : "RESULTADO: las comprobaciones pasan\n";
exit($fails ? 1 : 0);
