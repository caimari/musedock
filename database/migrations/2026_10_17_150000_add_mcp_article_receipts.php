<?php
use Screenart\Musedock\Database;
class AddMcpArticleReceipts_2026_10_17_150000
{
    public function up(): void
    {
        Database::connect()->exec('CREATE TABLE IF NOT EXISTS mcp_article_receipts (job_id VARCHAR(64) PRIMARY KEY, post_id BIGINT NOT NULL, site_id BIGINT NOT NULL)');
    }
    public function down(): void { /* Preserve idempotency receipts. */ }
}
