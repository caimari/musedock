<?php
/**
 * Migration: Create theme_skins table
 * Stores visual skins (enhanced presets) for the default theme.
 * Skins are safe (JSON only, no executable code) and can be uploaded by tenants.
 */

use Screenart\Musedock\Database;

class CreateThemeSkinsTable_2026_03_27_000001
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `theme_skins` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `slug` VARCHAR(100) NOT NULL,
                    `name` VARCHAR(255) NOT NULL,
                    `description` TEXT NULL,
                    `author` VARCHAR(255) NULL DEFAULT 'MuseDock',
                    `version` VARCHAR(20) NOT NULL DEFAULT '1.0',
                    `theme_slug` VARCHAR(100) NOT NULL DEFAULT 'default',
                    `screenshot` LONGTEXT NULL COMMENT 'Base64 encoded image or relative path',
                    `options` JSON NOT NULL COMMENT 'Theme options JSON (same structure as theme_options.value)',
                    `is_global` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Available to all tenants',
                    `tenant_id` INT(11) NULL DEFAULT NULL COMMENT 'NULL for system skins, tenant_id for user-uploaded',
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Whether skin is visible in the catalog',
                    `install_count` INT(11) NOT NULL DEFAULT 0 COMMENT 'How many tenants have applied this skin',
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_skin_slug` (`slug`, `tenant_id`),
                    KEY `idx_theme_slug` (`theme_slug`),
                    KEY `idx_tenant_id` (`tenant_id`),
                    KEY `idx_is_global` (`is_global`),
                    KEY `idx_is_active` (`is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            // PostgreSQL
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS theme_skins (
                    id SERIAL PRIMARY KEY,
                    slug VARCHAR(100) NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    description TEXT,
                    author VARCHAR(255) DEFAULT 'MuseDock',
                    version VARCHAR(20) NOT NULL DEFAULT '1.0',
                    theme_slug VARCHAR(100) NOT NULL DEFAULT 'default',
                    screenshot TEXT,
                    options JSONB NOT NULL,
                    is_global BOOLEAN NOT NULL DEFAULT TRUE,
                    tenant_id INTEGER,
                    is_active BOOLEAN NOT NULL DEFAULT TRUE,
                    install_count INTEGER NOT NULL DEFAULT 0,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP,
                    UNIQUE (slug, tenant_id)
                )
            ");

            $pdo->exec("CREATE INDEX IF NOT EXISTS theme_skins_idx_theme_slug ON theme_skins(theme_slug)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS theme_skins_idx_tenant_id ON theme_skins(tenant_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS theme_skins_idx_is_global ON theme_skins(is_global)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS theme_skins_idx_is_active ON theme_skins(is_active)");
        }

        echo "✓ Table theme_skins created\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("DROP TABLE IF EXISTS `theme_skins`");
        } else {
            $pdo->exec("DROP TABLE IF EXISTS theme_skins");
        }

        echo "✓ Table theme_skins dropped\n";
    }
}
