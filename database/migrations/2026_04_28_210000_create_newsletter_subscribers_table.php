<?php

use Screenart\Musedock\Database;

class CreateNewsletterSubscribersTable_2026_04_28_210000
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id` INT UNSIGNED NULL,
                `email` VARCHAR(255) NOT NULL,
                `name` VARCHAR(255) NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
                `confirm_token` VARCHAR(64) NOT NULL,
                `unsubscribe_token` VARCHAR(64) NOT NULL,
                `consent_text` TEXT NULL,
                `consent_version` VARCHAR(30) NULL,
                `consent_at` DATETIME NULL,
                `consent_ip` VARCHAR(45) NULL,
                `consent_user_agent` VARCHAR(500) NULL,
                `source_url` VARCHAR(500) NULL,
                `confirmed_at` DATETIME NULL,
                `unsubscribed_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_tenant_email` (`tenant_id`, `email`),
                UNIQUE KEY `uniq_confirm_token` (`confirm_token`),
                UNIQUE KEY `uniq_unsubscribe_token` (`unsubscribe_token`),
                KEY `idx_tenant_status` (`tenant_id`, `status`),
                KEY `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS newsletter_subscribers (
                id SERIAL PRIMARY KEY,
                tenant_id INTEGER NULL,
                email VARCHAR(255) NOT NULL,
                name VARCHAR(255) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                confirm_token VARCHAR(64) NOT NULL UNIQUE,
                unsubscribe_token VARCHAR(64) NOT NULL UNIQUE,
                consent_text TEXT NULL,
                consent_version VARCHAR(30) NULL,
                consent_at TIMESTAMP NULL,
                consent_ip VARCHAR(45) NULL,
                consent_user_agent VARCHAR(500) NULL,
                source_url VARCHAR(500) NULL,
                confirmed_at TIMESTAMP NULL,
                unsubscribed_at TIMESTAMP NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (tenant_id, email)
            )");
            $pdo->exec("CREATE INDEX IF NOT EXISTS newsletter_subscribers_idx_tenant_status ON newsletter_subscribers(tenant_id, status)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS newsletter_subscribers_idx_created_at ON newsletter_subscribers(created_at)");
        }

        echo "✓ Table newsletter_subscribers created\n";

        // Menús admin / tenant
        $driverNow = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowExpr = $driverNow === 'pgsql' ? 'NOW()' : 'NOW()';

        $appearanceId = $pdo->query("SELECT id FROM admin_menus WHERE slug = 'appearance' LIMIT 1")->fetchColumn();
        if ($appearanceId) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_menus WHERE slug = 'newsletter'");
            $stmt->execute();
            if ((int)$stmt->fetchColumn() === 0) {
                $pdo->prepare("INSERT INTO admin_menus (parent_id, module_id, title, slug, url, icon, icon_type, order_position, permission, is_active, created_at, updated_at)
                    VALUES (?, NULL, 'Newsletter', 'newsletter', '/musedock/newsletter', 'bi-envelope-paper', 'bi', 91, 'settings.view', 1, {$nowExpr}, {$nowExpr})")
                    ->execute([$appearanceId]);
            }
        }

        $tenants = $pdo->query("SELECT id FROM tenants")->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($tenants)) {
            foreach ($tenants as $tenantId) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenant_menus WHERE tenant_id = ? AND slug = 'newsletter'");
                $stmt->execute([$tenantId]);
                if ((int)$stmt->fetchColumn() > 0) {
                    continue;
                }

                $parentStmt = $pdo->prepare("SELECT id FROM tenant_menus WHERE tenant_id = ? AND slug = 'appearance' LIMIT 1");
                $parentStmt->execute([$tenantId]);
                $parentId = $parentStmt->fetchColumn() ?: null;

                $pdo->prepare("INSERT INTO tenant_menus (tenant_id, parent_id, module_id, title, slug, url, icon, icon_type, order_position, permission, is_active, created_at, updated_at)
                    VALUES (?, ?, NULL, 'Newsletter', 'newsletter', '{admin_path}/newsletter', 'bi-envelope-paper', 'bi', 91, 'settings.view', 1, {$nowExpr}, {$nowExpr})")
                    ->execute([$tenantId, $parentId]);
            }
        }

        echo "✓ Newsletter menus inserted\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("DROP TABLE IF EXISTS `newsletter_subscribers`");
        } else {
            $pdo->exec("DROP TABLE IF EXISTS newsletter_subscribers");
        }

        $pdo->exec("DELETE FROM admin_menus WHERE slug = 'newsletter'");
        $pdo->exec("DELETE FROM tenant_menus WHERE slug = 'newsletter'");

        echo "✓ Newsletter table and menus removed\n";
    }
}
