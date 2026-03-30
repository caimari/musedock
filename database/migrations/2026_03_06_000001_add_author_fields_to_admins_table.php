<?php
/**
 * Migration: Add author profile fields to admins table
 * Enables public author pages with bio, social links, and toggle
 */

use Screenart\Musedock\Database;

class AddAuthorFieldsToAdminsTable_2026_03_06_000001
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $this->upMySQL($pdo);
        } else {
            $this->upPostgreSQL($pdo);
        }
    }

    private function upMySQL($pdo)
    {
        $columns = [
            'author_slug'        => "ALTER TABLE `admins` ADD COLUMN `author_slug` VARCHAR(255) DEFAULT NULL AFTER `avatar`",
            'bio'                => "ALTER TABLE `admins` ADD COLUMN `bio` TEXT DEFAULT NULL AFTER `author_slug`",
            'social_twitter'     => "ALTER TABLE `admins` ADD COLUMN `social_twitter` VARCHAR(255) DEFAULT NULL AFTER `bio`",
            'social_linkedin'    => "ALTER TABLE `admins` ADD COLUMN `social_linkedin` VARCHAR(255) DEFAULT NULL AFTER `social_twitter`",
            'social_github'      => "ALTER TABLE `admins` ADD COLUMN `social_github` VARCHAR(255) DEFAULT NULL AFTER `social_linkedin`",
            'social_website'     => "ALTER TABLE `admins` ADD COLUMN `social_website` VARCHAR(255) DEFAULT NULL AFTER `social_github`",
            'author_page_enabled'=> "ALTER TABLE `admins` ADD COLUMN `author_page_enabled` TINYINT(1) DEFAULT 0 AFTER `social_website`",
        ];

        foreach ($columns as $col => $sql) {
            $check = $pdo->query("SHOW COLUMNS FROM `admins` LIKE '{$col}'");
            if ($check->rowCount() === 0) {
                $pdo->exec($sql);
                echo "  + Column {$col} added\n";
            } else {
                echo "  ~ Column {$col} already exists\n";
            }
        }

        // Unique index on tenant_id + author_slug
        try {
            $pdo->exec("CREATE UNIQUE INDEX idx_admins_tenant_author_slug ON `admins` (`tenant_id`, `author_slug`)");
            echo "  + Index idx_admins_tenant_author_slug created\n";
        } catch (\Exception $e) {
            echo "  ~ Index idx_admins_tenant_author_slug already exists\n";
        }

        echo "✓ Author fields added to admins (MySQL)\n";
    }

    private function upPostgreSQL($pdo)
    {
        $columns = [
            'author_slug'         => "ALTER TABLE admins ADD COLUMN IF NOT EXISTS author_slug VARCHAR(255) DEFAULT NULL",
            'bio'                 => "ALTER TABLE admins ADD COLUMN IF NOT EXISTS bio TEXT DEFAULT NULL",
            'social_twitter'      => "ALTER TABLE admins ADD COLUMN IF NOT EXISTS social_twitter VARCHAR(255) DEFAULT NULL",
            'social_linkedin'     => "ALTER TABLE admins ADD COLUMN IF NOT EXISTS social_linkedin VARCHAR(255) DEFAULT NULL",
            'social_github'       => "ALTER TABLE admins ADD COLUMN IF NOT EXISTS social_github VARCHAR(255) DEFAULT NULL",
            'social_website'      => "ALTER TABLE admins ADD COLUMN IF NOT EXISTS social_website VARCHAR(255) DEFAULT NULL",
            'author_page_enabled' => "ALTER TABLE admins ADD COLUMN IF NOT EXISTS author_page_enabled SMALLINT DEFAULT 0",
        ];

        foreach ($columns as $col => $sql) {
            $pdo->exec($sql);
            echo "  + Column {$col} ensured\n";
        }

        // Unique index on tenant_id + author_slug
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_admins_tenant_author_slug ON admins (tenant_id, author_slug)");
        echo "  + Index idx_admins_tenant_author_slug ensured\n";

        echo "✓ Author fields added to admins (PostgreSQL)\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $columns = ['author_slug', 'bio', 'social_twitter', 'social_linkedin', 'social_github', 'social_website', 'author_page_enabled'];

        if ($driver === 'mysql') {
            try { $pdo->exec("DROP INDEX idx_admins_tenant_author_slug ON `admins`"); } catch (\Exception $e) {}
            foreach ($columns as $col) {
                try { $pdo->exec("ALTER TABLE `admins` DROP COLUMN `{$col}`"); } catch (\Exception $e) {}
            }
        } else {
            $pdo->exec("DROP INDEX IF EXISTS idx_admins_tenant_author_slug");
            foreach ($columns as $col) {
                try { $pdo->exec("ALTER TABLE admins DROP COLUMN IF EXISTS {$col}"); } catch (\Exception $e) {}
            }
        }

        echo "✓ Author fields removed from admins\n";
    }
}
