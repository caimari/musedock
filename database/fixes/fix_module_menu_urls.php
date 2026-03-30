<?php
/**
 * FIX: Module Menu URLs in admin_menus table
 *
 * Problem: Modules like Instagram were registering menus with hardcoded /musedock/ URLs
 * instead of using {admin_path} placeholder. This causes tenant menus to have wrong URLs.
 *
 * Solution: Update all module menus in admin_menus to use {admin_path} placeholder.
 *
 * Usage:
 *   php database/fixes/fix_module_menu_urls.php
 */

// Load bootstrap
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/vendor/autoload.php';

use Screenart\Musedock\Database;

// Prevent browser execution unless explicitly enabled
if (php_sapi_name() !== 'cli') {
    $token = $_GET['token'] ?? '';
    if ($token !== 'fix-module-urls-2024') {
        die('This script should be run from CLI. Add ?token=fix-module-urls-2024 to run from browser (then delete this file).');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

echo "======================================\n";
echo "FIX: Module Menu URLs\n";
echo "======================================\n\n";

try {
    $pdo = Database::connect();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    echo "Database driver: {$driver}\n\n";

    // Get admin path from .env
    $adminPathMusedock = '/' . trim(\Screenart\Musedock\Env::get('ADMIN_PATH_MUSEDOCK', 'musedock'), '/');
    $adminPathTenant = '/' . trim(\Screenart\Musedock\Env::get('ADMIN_PATH_TENANT', 'admin'), '/');

    echo "Admin paths:\n";
    echo "  - Superadmin: {$adminPathMusedock}\n";
    echo "  - Tenant: {$adminPathTenant}\n\n";

    // Find all menus from modules with hardcoded /musedock/ URLs
    $stmt = $pdo->prepare("
        SELECT id, slug, title, url, module_id
        FROM admin_menus
        WHERE module_id IS NOT NULL
          AND (url LIKE ? OR url LIKE '/musedock/%')
        ORDER BY id
    ");
    $stmt->execute(["{$adminPathMusedock}/%"]);
    $moduleMenus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($moduleMenus)) {
        echo "✅ No module menus found with hardcoded URLs. Everything looks good!\n";
        exit(0);
    }

    echo "Found " . count($moduleMenus) . " module menus with hardcoded URLs:\n";
    echo str_repeat('-', 80) . "\n";

    $updated = 0;
    $updateStmt = $pdo->prepare("
        UPDATE admin_menus
        SET url = ?, show_in_superadmin = 1, show_in_tenant = 1, updated_at = NOW()
        WHERE id = ?
    ");

    foreach ($moduleMenus as $menu) {
        $oldUrl = $menu['url'];

        // Replace /musedock/ or /{admin_path_musedock}/ with {admin_path}
        $newUrl = preg_replace('#^/musedock/#', '{admin_path}/', $oldUrl);
        $newUrl = preg_replace('#^' . preg_quote($adminPathMusedock, '#') . '/#', '{admin_path}/', $newUrl);

        if ($newUrl !== $oldUrl) {
            echo sprintf(
                "  [%3d] %-30s: %s → %s\n",
                $menu['id'],
                $menu['slug'],
                $oldUrl,
                $newUrl
            );

            $updateStmt->execute([$newUrl, $menu['id']]);
            $updated++;
        }
    }

    echo str_repeat('-', 80) . "\n";
    echo "Updated: {$updated} menus\n\n";

    // Now update tenant_menus for all tenants
    echo "Updating tenant_menus for all tenants...\n";
    echo str_repeat('-', 80) . "\n";

    $tenantsStmt = $pdo->query("SELECT id, name, domain FROM tenants WHERE status = 'active'");
    $tenants = $tenantsStmt->fetchAll(PDO::FETCH_ASSOC);

    $tenantUpdateStmt = $pdo->prepare("
        UPDATE tenant_menus
        SET url = ?, updated_at = NOW()
        WHERE tenant_id = ? AND slug = ?
    ");

    $totalTenantUpdates = 0;

    foreach ($tenants as $tenant) {
        $tenantId = $tenant['id'];
        $tenantName = $tenant['name'];

        // Get menus for this tenant that need fixing
        $tenantMenusStmt = $pdo->prepare("
            SELECT id, slug, url
            FROM tenant_menus
            WHERE tenant_id = ?
              AND (url LIKE '/musedock/%' OR url LIKE ?)
        ");
        $tenantMenusStmt->execute([$tenantId, "{$adminPathMusedock}/%"]);
        $tenantMenus = $tenantMenusStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($tenantMenus)) {
            echo "  Tenant #{$tenantId} ({$tenantName}): " . count($tenantMenus) . " menus to fix\n";

            foreach ($tenantMenus as $tMenu) {
                $oldUrl = $tMenu['url'];

                // Replace /musedock/ with /admin/ (or tenant admin path)
                $newUrl = preg_replace('#^/musedock/#', "{$adminPathTenant}/", $oldUrl);
                $newUrl = preg_replace('#^' . preg_quote($adminPathMusedock, '#') . '/#', "{$adminPathTenant}/", $newUrl);

                if ($newUrl !== $oldUrl) {
                    $tenantUpdateStmt->execute([$newUrl, $tenantId, $tMenu['slug']]);
                    $totalTenantUpdates++;
                }
            }
        }
    }

    echo str_repeat('-', 80) . "\n";
    echo "Total tenant menu updates: {$totalTenantUpdates}\n\n";

    echo "======================================\n";
    echo "✅ FIX COMPLETED SUCCESSFULLY\n";
    echo "======================================\n\n";

    echo "Summary:\n";
    echo "  - Updated {$updated} module menus in admin_menus\n";
    echo "  - Updated {$totalTenantUpdates} menus across " . count($tenants) . " tenants\n\n";

    echo "⚠️  IMPORTANT:\n";
    echo "  1. Test the menu URLs in both superadmin and tenant panels\n";
    echo "  2. If everything works, delete this file: rm database/fixes/fix_module_menu_urls.php\n";
    echo "  3. For new tenants, use the 'Regenerate Menus' button in Domain Manager\n\n";

} catch (PDOException $e) {
    echo "\n❌ DATABASE ERROR: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
