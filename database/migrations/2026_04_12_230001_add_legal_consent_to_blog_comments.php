<?php

use Screenart\Musedock\Database;

class AddLegalConsentToBlogComments_2026_04_12_230001
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $columns = $this->listColumns($pdo, $driver, 'blog_comments');

        if (!in_array('legal_consent', $columns, true)) {
            if ($driver === 'mysql') {
                $pdo->exec("ALTER TABLE `blog_comments` ADD COLUMN `legal_consent` TINYINT(1) NOT NULL DEFAULT 0");
            } else {
                $pdo->exec("ALTER TABLE blog_comments ADD COLUMN IF NOT EXISTS legal_consent BOOLEAN NOT NULL DEFAULT FALSE");
            }
        }

        if (!in_array('legal_consent_at', $columns, true)) {
            if ($driver === 'mysql') {
                $pdo->exec("ALTER TABLE `blog_comments` ADD COLUMN `legal_consent_at` DATETIME NULL");
            } else {
                $pdo->exec("ALTER TABLE blog_comments ADD COLUMN IF NOT EXISTS legal_consent_at TIMESTAMP NULL");
            }
        }
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $columns = $this->listColumns($pdo, $driver, 'blog_comments');
            if (in_array('legal_consent_at', $columns, true)) {
                $pdo->exec("ALTER TABLE `blog_comments` DROP COLUMN `legal_consent_at`");
            }
            if (in_array('legal_consent', $columns, true)) {
                $pdo->exec("ALTER TABLE `blog_comments` DROP COLUMN `legal_consent`");
            }
        } else {
            $pdo->exec("ALTER TABLE blog_comments DROP COLUMN IF EXISTS legal_consent_at");
            $pdo->exec("ALTER TABLE blog_comments DROP COLUMN IF EXISTS legal_consent");
        }
    }

    private function listColumns(\PDO $pdo, string $driver, string $table): array
    {
        if ($driver === 'mysql') {
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
            return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'Field');
        }

        $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name = ?");
        $stmt->execute([$table]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}

