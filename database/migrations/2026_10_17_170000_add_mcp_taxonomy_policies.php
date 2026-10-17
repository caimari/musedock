<?php
use Screenart\Musedock\Database;
class AddMcpTaxonomyPolicies_2026_10_17_170000
{
    public function up(): void { Database::connect()->exec('CREATE TABLE IF NOT EXISTS mcp_taxonomy_policies(site_id BIGINT PRIMARY KEY,data TEXT NOT NULL)'); }
    public function down(): void { /* Preserve editorial policies. */ }
}
