<?php
use Screenart\Musedock\Database;

/** Only navigation. This migration does NOT activate a public MCP resource. */
class AddCmsMcpMenu_2026_10_11_120000
{
    public function up(): void
    {
        $db=Database::connect();
        $parent=$db->query("SELECT id FROM admin_menus WHERE slug='settings' AND parent_id IS NULL LIMIT 1")->fetchColumn();
        if (!$parent) return;
        $s=$db->prepare('SELECT id FROM admin_menus WHERE slug=?'); $s->execute(['cms-mcp-direct']);
        if (!$s->fetchColumn()) $db->prepare("INSERT INTO admin_menus(parent_id,title,slug,url,icon,icon_type,order_position,permission,is_active,show_in_superadmin,show_in_tenant,created_at,updated_at) VALUES(?,?,'cms-mcp-direct','{musedock_path}/settings/mcp','bi-robot','bi',12,'settings.view',1,1,0,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute([$parent,'Conexiones IA (MCP)']);
    }
    public function down(): void { Database::connect()->prepare('DELETE FROM admin_menus WHERE slug=?')->execute(['cms-mcp-direct']); }
}
