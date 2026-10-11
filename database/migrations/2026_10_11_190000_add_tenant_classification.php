<?php
use Screenart\Musedock\Database;
class AddTenantClassification_2026_10_11_190000
{
    public function up(): void
    {
        $db = Database::connect();
        $mysql = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        foreach (['is_client_domain' => 'SMALLINT NOT NULL DEFAULT 0', 'label_color' => "VARCHAR(16) NOT NULL DEFAULT ''"] as $column => $definition) {
            $statement = $db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=' . ($mysql ? 'DATABASE()' : 'current_schema()') . ' AND table_name=? AND column_name=?');
            $statement->execute(['tenants', $column]);
            if (!$statement->fetchColumn()) $db->exec('ALTER TABLE tenants ADD COLUMN ' . $column . ' ' . $definition);
        }
    }
    public function down(): void { /* Preserve tenant classification. */ }
}
