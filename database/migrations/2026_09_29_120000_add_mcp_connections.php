<?php
/**
 * Migration: Conexiones MCP por tenant
 * - api_keys.allowed_ips: lista opcional de IPs/CIDR permitidas
 * - api_tool_logs.source: 'rest' | 'mcp'
 * - Menú "Ajustes → Conexiones IA (MCP)" en admin_menus y tenant_menus
 * Generated at: 2026_09_29_120000
 * Compatible with: MySQL/MariaDB + PostgreSQL
 */

use Screenart\Musedock\Database;

class AddMcpConnections_2026_09_29_120000
{
    private const SLUG = 'mcp-connections';
    private const TITLE = 'Conexiones IA (MCP)';
    private const URL = '{admin_path}/mcp';
    private const ICON = 'bi-robot';
    private const ORDER = 11;

    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if (!$this->columnExists($pdo, 'api_keys', 'allowed_ips')) {
            $pdo->exec($driver === 'mysql'
                ? "ALTER TABLE `api_keys` ADD COLUMN `allowed_ips` TEXT DEFAULT NULL COMMENT 'IPs/CIDR permitidas (vacío = cualquiera)'"
                : "ALTER TABLE api_keys ADD COLUMN allowed_ips TEXT DEFAULT NULL");
            echo "✓ api_keys.allowed_ips added\n";
        }

        if (!$this->columnExists($pdo, 'api_tool_logs', 'source')) {
            $pdo->exec($driver === 'mysql'
                ? "ALTER TABLE `api_tool_logs` ADD COLUMN `source` VARCHAR(10) NOT NULL DEFAULT 'rest'"
                : "ALTER TABLE api_tool_logs ADD COLUMN source VARCHAR(10) NOT NULL DEFAULT 'rest'");
            echo "✓ api_tool_logs.source added\n";
        }

        $this->seedMenus($pdo);
    }

    private function seedMenus(PDO $pdo): void
    {
        // 1) admin_menus (plantilla para tenants nuevos)
        $parentId = $this->fetchOneInt($pdo, "SELECT id FROM admin_menus WHERE slug = 'settings' AND parent_id IS NULL LIMIT 1");
        if ($parentId !== null && $this->fetchOneInt($pdo, "SELECT id FROM admin_menus WHERE slug = ? LIMIT 1", [self::SLUG]) === null) {
            $pdo->prepare("
                INSERT INTO admin_menus (parent_id, title, slug, url, icon, icon_type, order_position, permission, is_active, show_in_superadmin, show_in_tenant, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'bi', ?, 'settings.view', 1, 0, 1, NOW(), NOW())
            ")->execute([$parentId, self::TITLE, self::SLUG, self::URL, self::ICON, self::ORDER]);
            echo "✓ Menu '" . self::SLUG . "' added to admin_menus\n";
        }

        // 2) tenant_menus (tenants existentes)
        $parents = $pdo->query("SELECT tenant_id, id FROM tenant_menus WHERE slug = 'settings' AND parent_id IS NULL")->fetchAll(PDO::FETCH_ASSOC);
        $check = $pdo->prepare("SELECT id FROM tenant_menus WHERE tenant_id = ? AND slug = ? LIMIT 1");
        $insert = $pdo->prepare("
            INSERT INTO tenant_menus (tenant_id, parent_id, title, slug, url, icon, icon_type, order_position, permission, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 'bi', ?, 'settings.view', 1, NOW(), NOW())
        ");

        $added = 0;
        foreach ($parents as $row) {
            $check->execute([(int) $row['tenant_id'], self::SLUG]);
            if ($check->fetchColumn()) {
                continue;
            }
            $insert->execute([(int) $row['tenant_id'], (int) $row['id'], self::TITLE, self::SLUG, self::URL, self::ICON, self::ORDER]);
            $added++;
        }

        echo "✓ Menu '" . self::SLUG . "' ensured in tenant_menus ({$added} tenant(s) added)\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $pdo->prepare("DELETE FROM tenant_menus WHERE slug = ?")->execute([self::SLUG]);
        $pdo->prepare("DELETE FROM admin_menus WHERE slug = ?")->execute([self::SLUG]);

        if ($this->columnExists($pdo, 'api_tool_logs', 'source')) {
            $pdo->exec($driver === 'mysql' ? "ALTER TABLE `api_tool_logs` DROP COLUMN `source`" : "ALTER TABLE api_tool_logs DROP COLUMN source");
        }
        if ($this->columnExists($pdo, 'api_keys', 'allowed_ips')) {
            $pdo->exec($driver === 'mysql' ? "ALTER TABLE `api_keys` DROP COLUMN `allowed_ips`" : "ALTER TABLE api_keys DROP COLUMN allowed_ips");
        }

        echo "✓ MCP connections rolled back\n";
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?");
        }
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function fetchOneInt(PDO $pdo, string $sql, array $params = []): ?int
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return ($value === false || $value === null || $value === '') ? null : (int) $value;
    }
}
