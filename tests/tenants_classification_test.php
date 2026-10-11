<?php
require __DIR__.'/mcp/bootstrap.php';
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE tenants (id INTEGER PRIMARY KEY, label_color VARCHAR(16) NOT NULL DEFAULT '', is_client_domain INTEGER NOT NULL DEFAULT 0); INSERT INTO tenants VALUES (2, '', 1), (3, 'green', 0)");
$connection = new ReflectionProperty(Screenart\Musedock\Database::class, 'connection');
$connection->setValue(null, $db);
$controller = new Screenart\Musedock\Controllers\Superadmin\TenantsController();
$save = new ReflectionMethod($controller, 'saveClassification');
foreach (['blue', 'green', '', 'purple', 'red', '', 'orange', ''] as $color) {
    $save->invoke($controller, 2, ['label_color' => $color]);
    $row = $db->query('SELECT * FROM tenants WHERE id=2')->fetch(PDO::FETCH_ASSOC);
    if ($row['label_color'] !== $color || (int)$row['is_client_domain'] !== 1) throw new RuntimeException('Color not saved or client classification changed.');
}
$save->invoke($controller, 2, ['label_color' => '', 'is_client_domain' => 0]);
if ((int)$db->query('SELECT is_client_domain FROM tenants WHERE id=2')->fetchColumn() !== 0) throw new RuntimeException('Client flag not saved.');
if ($db->query('SELECT label_color FROM tenants WHERE id=3')->fetchColumn() !== 'green') throw new RuntimeException('Another tenant changed.');
echo "PASS: repeated color changes, empty color, client flag and tenant isolation.\n";
