<?php
/**
 * Migration: Create theme_presets table
 * Stores saved theme customization presets per tenant
 */

use Screenart\Musedock\Database;

class CreateThemePresetsTable_2025_12_13_000002
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `theme_presets` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `tenant_id` INT(11) NOT NULL,
                    `theme_slug` VARCHAR(100) NOT NULL,
                    `preset_slug` VARCHAR(100) NOT NULL,
                    `preset_name` VARCHAR(255) NOT NULL,
                    `options` JSON NOT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_tenant_theme_preset` (`tenant_id`, `theme_slug`, `preset_slug`),
                    KEY `idx_tenant_id` (`tenant_id`),
                    KEY `idx_theme_slug` (`theme_slug`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            // PostgreSQL
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS theme_presets (
                    id SERIAL PRIMARY KEY,
                    tenant_id INTEGER NOT NULL,
                    theme_slug VARCHAR(100) NOT NULL,
                    preset_slug VARCHAR(100) NOT NULL,
                    preset_name VARCHAR(255) NOT NULL,
                    options JSONB NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP,
                    UNIQUE (tenant_id, theme_slug, preset_slug)
                )
            ");

            $pdo->exec("CREATE INDEX IF NOT EXISTS theme_presets_idx_tenant_id ON theme_presets(tenant_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS theme_presets_idx_theme_slug ON theme_presets(theme_slug)");
        }

        echo "✓ Table theme_presets created\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("DROP TABLE IF EXISTS `theme_presets`");
        } else {
            $pdo->exec("DROP TABLE IF EXISTS theme_presets");
        }

        echo "✓ Table theme_presets dropped\n";
    }
}
