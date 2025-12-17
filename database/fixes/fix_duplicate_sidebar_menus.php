<?php
/**
 * FIX: Duplicate Sidebar Menus in Superadmin Panel
 *
 * Run this script ONCE on existing installations that have duplicate sidebar items.
 *
 * Usage:
 *   php database/fixes/fix_duplicate_sidebar_menus.php
 *
 * Or from browser (remove after use):
 *   https://your-domain.com/database/fixes/fix_duplicate_sidebar_menus.php
 */

// Load bootstrap
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/vendor/autoload.php';

use Screenart\Musedock\Database;

// Prevent browser execution unless explicitly enabled
if (php_sapi_name() !== 'cli') {
    // Check for secret token to allow browser execution
    $token = $_GET['token'] ?? '';
    if ($token !== 'run-fix-menus-2024') {
        die('This script should be run from CLI. Add ?token=run-fix-menus-2024 to run from browser (then delete this file).');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

echo "======================================\n";
echo "FIX: Duplicate Sidebar Menus\n";
echo "======================================\n\n";

try {
    $pdo = Database::connect();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    echo "Database driver: {$driver}\n\n";

    // Step 1: Count affected menus before fix
    $stmt = $pdo->query("SELECT COUNT(*) FROM admin_menus WHERE url LIKE '/admin/%' AND show_in_superadmin = 1");
    $affectedCount = $stmt->fetchColumn();
    echo "Step 1: Found {$affectedCount} menus with /admin/ URLs visible in superadmin panel\n";

    // Step 2: Hide menus with literal /admin/ URLs from superadmin panel
    $stmt = $pdo->prepare("
        UPDATE admin_menus
        SET show_in_superadmin = 0
        WHERE url LIKE '/admin/%'
          AND show_in_superadmin = 1
    ");
    $stmt->execute();
    $updated1 = $stmt->rowCount();
    echo "   - Updated {$updated1} menus: set show_in_superadmin = 0\n\n";

    // Step 3: Hide old tenant menus by slug pattern (before tenant- prefix)
    $conflictingSlugs = ['dashboard', 'media', 'content', 'system'];
    $placeholders = str_repeat('?,', count($conflictingSlugs) - 1) . '?';

    $stmt = $pdo->prepare("
        UPDATE admin_menus
        SET show_in_superadmin = 0
        WHERE slug IN ({$placeholders})
          AND url NOT LIKE '{admin_path}%'
          AND url NOT LIKE '{musedock_path}%'
          AND show_in_superadmin = 1
    ");
    $stmt->execute($conflictingSlugs);
    $updated2 = $stmt->rowCount();
    echo "Step 2: Updated {$updated2} conflicting slug menus\n\n";

    // Step 4: Ensure superadmin menus with placeholders are visible
    $stmt = $pdo->prepare("
        UPDATE admin_menus
        SET show_in_superadmin = 1
        WHERE (url LIKE '{admin_path}%' OR url LIKE '{musedock_path}%' OR url = '#')
          AND show_in_superadmin = 0
          AND slug NOT LIKE 'tenant-%'
    ");
    $stmt->execute();
    $updated3 = $stmt->rowCount();
    echo "Step 3: Ensured {$updated3} superadmin menus are visible\n\n";

    // Step 5: Mark tenant menus for tenant panel
    $stmt = $pdo->prepare("
        UPDATE admin_menus
        SET show_in_tenant = 1
        WHERE slug LIKE 'tenant-%'
    ");
    $stmt->execute();
    $updated4 = $stmt->rowCount();
    echo "Step 4: Updated {$updated4} tenant menus\n\n";

    // Verification: Show current state
    echo "======================================\n";
    echo "VERIFICATION\n";
    echo "======================================\n\n";

    // Menus visible in superadmin
    $stmt = $pdo->query("
        SELECT id, slug, title, url, show_in_superadmin, show_in_tenant
        FROM admin_menus
        WHERE show_in_superadmin = 1 AND is_active = 1
        ORDER BY order_position
    ");
    $superadminMenus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Menus visible in Superadmin Panel (/musedock/):\n";
    echo str_repeat('-', 80) . "\n";
    printf("%-5s %-25s %-30s %-10s\n", "ID", "Slug", "Title", "URL");
    echo str_repeat('-', 80) . "\n";
    foreach ($superadminMenus as $menu) {
        $url = strlen($menu['url']) > 25 ? substr($menu['url'], 0, 25) . '...' : $menu['url'];
        printf("%-5s %-25s %-30s %-10s\n", $menu['id'], $menu['slug'], $menu['title'], $url);
    }
    echo "\nTotal: " . count($superadminMenus) . " menus\n\n";

    // Check for potential duplicates by title
    $stmt = $pdo->query("
        SELECT title, COUNT(*) as count
        FROM admin_menus
        WHERE is_active = 1 AND show_in_superadmin = 1
        GROUP BY title
        HAVING COUNT(*) > 1
    ");
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($duplicates)) {
        echo "⚠️  WARNING: Found potential duplicate titles in superadmin:\n";
        foreach ($duplicates as $dup) {
            echo "   - '{$dup['title']}' appears {$dup['count']} times\n";
        }
        echo "\nYou may need to manually delete or rename these.\n";
    } else {
        echo "✅ No duplicate titles found in superadmin panel.\n";
    }

    echo "\n======================================\n";
    echo "FIX COMPLETED SUCCESSFULLY\n";
    echo "======================================\n";
    echo "\n⚠️  IMPORTANT: Delete this file after running!\n";
    echo "   rm database/fixes/fix_duplicate_sidebar_menus.php\n\n";

} catch (PDOException $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
