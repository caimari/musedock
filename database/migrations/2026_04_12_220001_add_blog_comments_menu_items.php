<?php
/**
 * Migration: Add Blog comments menu items to admin_menus and tenant_menus
 * Generated at: 2026_04_12_220001
 * Compatible with: MySQL/MariaDB + PostgreSQL
 */

use Screenart\Musedock\Database;

class AddBlogCommentsMenuItems_2026_04_12_220001
{
    public function up()
    {
        $pdo = Database::connect();

        $blogModuleId = $this->fetchOneInt($pdo, "SELECT id FROM modules WHERE slug = ? LIMIT 1", ['Blog']);

        // 1) admin_menus (panel principal /musedock)
        $adminBlogParentId = $this->fetchOneInt($pdo, "SELECT id FROM admin_menus WHERE slug = ? LIMIT 1", ['blog']);
        if ($adminBlogParentId !== null) {
            $adminMenuId = $this->fetchOneInt($pdo, "SELECT id FROM admin_menus WHERE slug = ? LIMIT 1", ['blog-comments']);

            if ($adminMenuId !== null) {
                $stmt = $pdo->prepare("
                    UPDATE admin_menus
                    SET parent_id = ?,
                        module_id = ?,
                        title = ?,
                        url = ?,
                        icon = ?,
                        icon_type = ?,
                        order_position = ?,
                        permission = NULL,
                        is_active = 1,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $adminBlogParentId,
                    $blogModuleId,
                    'Comentarios',
                    '{admin_path}/blog/comments',
                    'bi-chat-left-text',
                    'bi',
                    3,
                    $adminMenuId,
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO admin_menus (
                        parent_id, module_id, title, slug, url, icon, icon_type, order_position, permission, is_active, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, 1, NOW(), NOW())
                ");
                $stmt->execute([
                    $adminBlogParentId,
                    $blogModuleId,
                    'Comentarios',
                    'blog-comments',
                    '{admin_path}/blog/comments',
                    'bi-chat-left-text',
                    'bi',
                    3,
                ]);
            }
            echo "✓ Menu 'blog-comments' ensured in admin_menus\n";
        } else {
            echo "⚠ Parent menu 'blog' not found in admin_menus. Skipped admin menu insertion.\n";
        }

        // 2) tenant_menus (todos los tenants existentes)
        $stmt = $pdo->query("SELECT tenant_id, id AS parent_id, module_id FROM tenant_menus WHERE slug = 'blog'");
        $tenantBlogParents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($tenantBlogParents)) {
            echo "⚠ No tenant blog parent menus found. Skipped tenant menu insertion.\n";
            return;
        }

        $checkStmt = $pdo->prepare("SELECT id FROM tenant_menus WHERE tenant_id = ? AND slug = ? LIMIT 1");
        $updateStmt = $pdo->prepare("
            UPDATE tenant_menus
            SET parent_id = ?,
                module_id = ?,
                title = ?,
                url = ?,
                icon = ?,
                icon_type = ?,
                order_position = ?,
                permission = NULL,
                is_active = 1,
                updated_at = NOW()
            WHERE id = ?
        ");
        $insertStmt = $pdo->prepare("
            INSERT INTO tenant_menus (
                tenant_id, parent_id, module_id, title, slug, url, icon, icon_type, order_position, permission, is_active, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 1, NOW(), NOW())
        ");

        $affectedTenants = 0;

        foreach ($tenantBlogParents as $row) {
            $tenantId = (int)($row['tenant_id'] ?? 0);
            $parentId = (int)($row['parent_id'] ?? 0);
            $moduleId = isset($row['module_id']) ? (int)$row['module_id'] : $blogModuleId;

            if ($tenantId <= 0 || $parentId <= 0) {
                continue;
            }

            $checkStmt->execute([$tenantId, 'blog-comments']);
            $existingId = $checkStmt->fetchColumn();

            if ($existingId) {
                $updateStmt->execute([
                    $parentId,
                    $moduleId,
                    'Comentarios',
                    '{admin_path}/blog/comments',
                    'bi-chat-left-text',
                    'bi',
                    3,
                    (int)$existingId,
                ]);
            } else {
                $insertStmt->execute([
                    $tenantId,
                    $parentId,
                    $moduleId,
                    'Comentarios',
                    'blog-comments',
                    '{admin_path}/blog/comments',
                    'bi-chat-left-text',
                    'bi',
                    3,
                ]);
            }

            $affectedTenants++;
        }

        echo "✓ Menu 'blog-comments' ensured in tenant_menus for {$affectedTenants} tenant(s)\n";
    }

    public function down()
    {
        $pdo = Database::connect();

        $pdo->exec("DELETE FROM admin_menus WHERE slug = 'blog-comments'");
        $pdo->exec("DELETE FROM tenant_menus WHERE slug = 'blog-comments'");

        echo "✓ Menu 'blog-comments' removed from admin_menus and tenant_menus\n";
    }

    private function fetchOneInt(PDO $pdo, string $sql, array $params = []): ?int
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();

        if ($value === false || $value === null || $value === '') {
            return null;
        }

        return (int)$value;
    }
}

