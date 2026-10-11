<?php
use Screenart\Musedock\Database;
class RenameMcpConnections_2026_10_11_160000
{
    public function up(): void
    {
        foreach (['admin_menus','tenant_menus'] as $table) Database::connect()->prepare("UPDATE {$table} SET title=?,updated_at=CURRENT_TIMESTAMP WHERE slug IN ('cms-mcp-direct','mcp-connections') AND title=?")->execute(['(MCP) Conexiones IA','Conexiones IA (MCP)']);
    }
    public function down(): void { }
}
