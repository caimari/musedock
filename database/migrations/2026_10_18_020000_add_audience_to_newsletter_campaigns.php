<?php
use Screenart\Musedock\Database;

/** Público de cada campaña: todos, solo la lista de espera del alta o solo el newsletter. */
class AddAudienceToNewsletterCampaigns_2026_10_18_020000
{
    public function up(): void
    {
        $pdo = Database::connect();
        if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $pdo->exec("ALTER TABLE newsletter_campaigns ADD COLUMN IF NOT EXISTS audience VARCHAR(30) NOT NULL DEFAULT 'all'");
            return;
        }
        if (!$pdo->query("SHOW COLUMNS FROM newsletter_campaigns LIKE 'audience'")->fetch()) {
            $pdo->exec("ALTER TABLE newsletter_campaigns ADD COLUMN audience VARCHAR(30) NOT NULL DEFAULT 'all'");
        }
    }

    public function down(): void { /* Se conserva la columna. */ }
}
