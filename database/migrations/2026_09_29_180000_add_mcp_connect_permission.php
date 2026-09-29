<?php
/**
 * Migration: permiso "Conectar asistentes de IA (MCP)" — mcp.connect
 *
 * Permite a un rol sin acceso a Ajustes autorizar conexiones OAuth (Claude.ai,
 * ChatGPT...). La conexión nunca recibe más permisos que la persona que la autoriza.
 * Se crea la plantilla global y una copia por tenant (como el resto de permisos).
 * Generated at: 2026_09_29_180000
 * Compatible with: MySQL/MariaDB + PostgreSQL
 */

use Screenart\Musedock\Database;

class AddMcpConnectPermission_2026_09_29_180000
{
    private const SLUG = 'mcp.connect';
    private const NAME = 'Conectar asistentes de IA (MCP)';
    private const DESCRIPTION = 'Autorizar conexiones de Claude, ChatGPT y otros asistentes de IA, limitadas a los permisos propios del usuario.';
    private const CATEGORY = 'IA';

    public function up()
    {
        $pdo = Database::connect();

        $exists = $pdo->prepare("SELECT id FROM permissions WHERE slug = ? AND tenant_id IS NULL LIMIT 1");
        $exists->execute([self::SLUG]);
        if (!$exists->fetchColumn()) {
            $pdo->prepare("
                INSERT INTO permissions (slug, name, description, category, tenant_id, scope, created_at, updated_at)
                VALUES (?, ?, ?, ?, NULL, 'tenant', NOW(), NOW())
            ")->execute([self::SLUG, self::NAME, self::DESCRIPTION, self::CATEGORY]);
            echo "✓ Global permission '" . self::SLUG . "' created\n";
        }

        // Copias por tenant (mismos tenants que ya tienen permisos propios)
        $tenantIds = $pdo->query("SELECT DISTINCT tenant_id FROM permissions WHERE tenant_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
        $check = $pdo->prepare("SELECT id FROM permissions WHERE slug = ? AND tenant_id = ? LIMIT 1");
        $insert = $pdo->prepare("
            INSERT INTO permissions (slug, name, description, category, tenant_id, scope, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'tenant', NOW(), NOW())
        ");

        $added = 0;
        foreach ($tenantIds as $tenantId) {
            $check->execute([self::SLUG, (int) $tenantId]);
            if ($check->fetchColumn()) {
                continue;
            }
            $insert->execute([self::SLUG, self::NAME, self::DESCRIPTION, self::CATEGORY, (int) $tenantId]);
            $added++;
        }

        echo "✓ Permission '" . self::SLUG . "' ensured for {$added} tenant(s)\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $pdo->prepare("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE slug = ?)")->execute([self::SLUG]);
        $pdo->prepare("DELETE FROM user_permissions WHERE permission_slug = ?")->execute([self::SLUG]);
        $pdo->prepare("DELETE FROM permissions WHERE slug = ?")->execute([self::SLUG]);
        echo "✓ Permission '" . self::SLUG . "' removed\n";
    }
}
