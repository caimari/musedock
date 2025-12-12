<?php
/**
 * Script de debug para permisos - ELIMINAR después de usar
 */
require __DIR__ . '/vendor/autoload.php';

use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Helpers\PermissionHelper;
use Screenart\Musedock\Database;

SessionSecurity::startSession();

echo "<h2>Debug de Permisos</h2>";

// 1. Info del usuario autenticado
echo "<h3>1. Usuario Autenticado</h3>";
$auth = SessionSecurity::getAuthenticatedUser();
if ($auth) {
    echo "<pre>";
    print_r($auth);
    echo "</pre>";
} else {
    echo "<p style='color:red'>No hay usuario autenticado. Inicia sesión primero.</p>";
    exit;
}

// 2. Tenant ID global
echo "<h3>2. Tenant ID Global (de GLOBALS)</h3>";
echo "tenant_id() = " . (tenant_id() ?? 'NULL') . "<br>";
echo "\$GLOBALS['tenant'] = <pre>" . print_r($GLOBALS['tenant'] ?? 'NO DEFINIDO', true) . "</pre>";

// 3. Consulta directa a user_permissions
echo "<h3>3. Permisos directos del usuario (user_permissions)</h3>";
try {
    $pdo = Database::connect();
    $userId = $auth['id'];
    $tenantId = $auth['tenant_id'];

    $stmt = $pdo->prepare("SELECT * FROM user_permissions WHERE user_id = ?");
    $stmt->execute([$userId]);
    $userPerms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Usuario ID: {$userId}, Tenant ID del usuario: " . ($tenantId ?? 'NULL') . "<br>";
    echo "<table border='1' style='border-collapse:collapse'>";
    echo "<tr><th>ID</th><th>permission_slug</th><th>tenant_id</th><th>granted_by</th></tr>";
    foreach ($userPerms as $perm) {
        echo "<tr>";
        echo "<td>{$perm['id']}</td>";
        echo "<td>{$perm['permission_slug']}</td>";
        echo "<td>" . ($perm['tenant_id'] ?? 'NULL') . "</td>";
        echo "<td>" . ($perm['granted_by'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    if (empty($userPerms)) {
        echo "<p style='color:orange'>No tiene permisos directos asignados en user_permissions</p>";
    }

} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

// 4. Consulta a user_roles
echo "<h3>4. Roles del usuario (user_roles)</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT ur.*, r.name as role_name, r.tenant_id as role_tenant_id
        FROM user_roles ur
        LEFT JOIN roles r ON r.id = ur.role_id
        WHERE ur.user_id = ?
    ");
    $stmt->execute([$userId]);
    $userRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' style='border-collapse:collapse'>";
    echo "<tr><th>role_id</th><th>role_name</th><th>role_tenant_id</th><th>user_type</th></tr>";
    foreach ($userRoles as $role) {
        echo "<tr>";
        echo "<td>{$role['role_id']}</td>";
        echo "<td>" . ($role['role_name'] ?? 'N/A') . "</td>";
        echo "<td>" . ($role['role_tenant_id'] ?? 'NULL') . "</td>";
        echo "<td>" . ($role['user_type'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    if (empty($userRoles)) {
        echo "<p style='color:orange'>No tiene roles asignados en user_roles</p>";
    }

} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

// 5. Test específico de settings.view
echo "<h3>5. Test de userCan('settings.view')</h3>";
$permSlug = 'settings.view';
$result = PermissionHelper::userCan($userId, $permSlug, $tenantId);
echo "PermissionHelper::userCan({$userId}, '{$permSlug}', " . ($tenantId ?? 'NULL') . ") = " . ($result ? 'TRUE' : 'FALSE') . "<br>";

$result2 = PermissionHelper::currentUserCan($permSlug);
echo "PermissionHelper::currentUserCan('{$permSlug}') = " . ($result2 ? 'TRUE' : 'FALSE') . "<br>";

// 6. Simulación de la consulta que hace PermissionHelper
echo "<h3>6. Simulación de consultas de PermissionHelper</h3>";
try {
    // Consulta para permisos directos
    if ($tenantId === null) {
        echo "<p>Modo: tenantId es NULL</p>";
        $stmt = $pdo->prepare("
            SELECT id FROM user_permissions
            WHERE user_id = ?
            AND permission_slug = ?
            AND tenant_id IS NULL
            LIMIT 1
        ");
        $stmt->execute([$userId, $permSlug]);
    } else {
        echo "<p>Modo: tenantId = {$tenantId}</p>";
        $stmt = $pdo->prepare("
            SELECT id FROM user_permissions
            WHERE user_id = ?
            AND permission_slug = ?
            AND (tenant_id = ? OR tenant_id IS NULL)
            LIMIT 1
        ");
        $stmt->execute([$userId, $permSlug, $tenantId]);
    }

    $found = $stmt->fetch();
    echo "Permiso directo encontrado: " . ($found ? 'SÍ (ID: ' . $found['id'] . ')' : 'NO') . "<br>";

    // Consulta para permisos de roles
    if ($tenantId === null) {
        $stmt = $pdo->prepare("
            SELECT p.id, p.slug, r.name as role_name
            FROM permissions p
            INNER JOIN role_permissions rp ON rp.permission_id = p.id
            INNER JOIN user_roles ur ON ur.role_id = rp.role_id
            WHERE ur.user_id = ?
            AND p.slug = ?
            AND p.tenant_id IS NULL
            LIMIT 1
        ");
        $stmt->execute([$userId, $permSlug]);
    } else {
        $stmt = $pdo->prepare("
            SELECT p.id, p.slug, r.name as role_name
            FROM permissions p
            INNER JOIN role_permissions rp ON rp.permission_id = p.id
            INNER JOIN roles r ON r.id = rp.role_id
            INNER JOIN user_roles ur ON ur.role_id = rp.role_id
            WHERE ur.user_id = ?
            AND p.slug = ?
            AND (p.tenant_id = ? OR p.tenant_id IS NULL)
            LIMIT 1
        ");
        $stmt->execute([$userId, $permSlug, $tenantId]);
    }

    $foundRole = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Permiso por rol encontrado: " . ($foundRole ? 'SÍ (Rol: ' . ($foundRole['role_name'] ?? 'N/A') . ')' : 'NO') . "<br>";

} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

// 7. Listar todos los permisos del usuario
echo "<h3>7. Todos los permisos del usuario</h3>";
$allPerms = PermissionHelper::getUserPermissions($userId, $tenantId);
echo "<pre>";
print_r($allPerms);
echo "</pre>";

echo "<hr><p style='color:red'><strong>IMPORTANTE: Eliminar este archivo después de usarlo</strong></p>";
