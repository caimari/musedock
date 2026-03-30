<?php

use Screenart\Musedock\Database;

/**
 * Migration: Create Elements Tables
 *
 * Creates the necessary tables for the Elements module
 * Compatible with MySQL and PostgreSQL
 */
class CreateElementsTables_2025_12_17_000000
{
    /**
     * Run the migration
     */
    public function up(): void
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'pgsql') {
            $this->upPostgreSQL($pdo);
        } else {
            $this->upMySQL($pdo);
        }

        error_log("Elements: Tables created successfully ({$driver})");
    }

    /**
     * MySQL migration
     */
    private function upMySQL(\PDO $pdo): void
    {
        // Create elements table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `elements` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `tenant_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL = global element, available to all tenants',
                `name` VARCHAR(255) NOT NULL COMMENT 'Display name',
                `slug` VARCHAR(255) NOT NULL COMMENT 'URL-friendly identifier',
                `description` TEXT DEFAULT NULL COMMENT 'Optional description',
                `type` VARCHAR(50) NOT NULL COMMENT 'Element type: hero, faq, cta, features, testimonials, stats, timeline',
                `layout_type` VARCHAR(50) DEFAULT NULL COMMENT 'Layout variant for the type',
                `data` JSON NOT NULL COMMENT 'Element content and configuration',
                `settings` JSON DEFAULT NULL COMMENT 'Additional display settings',
                `is_active` TINYINT(1) DEFAULT 1 COMMENT 'Element is published/visible',
                `featured` TINYINT(1) DEFAULT 0 COMMENT 'Featured element',
                `sort_order` INT DEFAULT 0 COMMENT 'Display order',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX `idx_tenant_id` (`tenant_id`),
                INDEX `idx_slug` (`slug`),
                INDEX `idx_type` (`type`),
                INDEX `idx_active` (`is_active`),
                INDEX `idx_tenant_slug` (`tenant_id`, `slug`),
                INDEX `idx_tenant_type` (`tenant_id`, `type`),

                UNIQUE KEY `unique_tenant_slug` (`tenant_id`, `slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Reusable content elements'
        ");

        // Create element_settings table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `element_settings` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `tenant_id` INT UNSIGNED DEFAULT NULL,
                `setting_key` VARCHAR(100) NOT NULL,
                `setting_value` TEXT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX `idx_tenant_id` (`tenant_id`),
                UNIQUE KEY `unique_tenant_key` (`tenant_id`, `setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Element module settings'
        ");
    }

    /**
     * PostgreSQL migration
     */
    private function upPostgreSQL(\PDO $pdo): void
    {
        // Create elements table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS elements (
                id SERIAL PRIMARY KEY,
                tenant_id INTEGER DEFAULT NULL,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                description TEXT DEFAULT NULL,
                type VARCHAR(50) NOT NULL,
                layout_type VARCHAR(50) DEFAULT NULL,
                data JSONB NOT NULL,
                settings JSONB DEFAULT NULL,
                is_active BOOLEAN DEFAULT true,
                featured BOOLEAN DEFAULT false,
                sort_order INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Create indexes
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_elements_tenant_id ON elements(tenant_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_elements_slug ON elements(slug)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_elements_type ON elements(type)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_elements_active ON elements(is_active)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_elements_tenant_slug ON elements(tenant_id, slug)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_elements_tenant_type ON elements(tenant_id, type)");
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS unique_elements_tenant_slug ON elements(tenant_id, slug)");

        // Create trigger for updated_at
        $pdo->exec("
            CREATE OR REPLACE FUNCTION update_elements_updated_at()
            RETURNS TRIGGER AS \$\$
            BEGIN
                NEW.updated_at = CURRENT_TIMESTAMP;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        $pdo->exec("
            DROP TRIGGER IF EXISTS elements_updated_at_trigger ON elements;
            CREATE TRIGGER elements_updated_at_trigger
                BEFORE UPDATE ON elements
                FOR EACH ROW
                EXECUTE FUNCTION update_elements_updated_at();
        ");

        // Create element_settings table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS element_settings (
                id SERIAL PRIMARY KEY,
                tenant_id INTEGER DEFAULT NULL,
                setting_key VARCHAR(100) NOT NULL,
                setting_value TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Create indexes for element_settings
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_element_settings_tenant_id ON element_settings(tenant_id)");
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS unique_element_settings_tenant_key ON element_settings(tenant_id, setting_key)");

        // Create trigger for element_settings updated_at
        $pdo->exec("
            CREATE OR REPLACE FUNCTION update_element_settings_updated_at()
            RETURNS TRIGGER AS \$\$
            BEGIN
                NEW.updated_at = CURRENT_TIMESTAMP;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        $pdo->exec("
            DROP TRIGGER IF EXISTS element_settings_updated_at_trigger ON element_settings;
            CREATE TRIGGER element_settings_updated_at_trigger
                BEFORE UPDATE ON element_settings
                FOR EACH ROW
                EXECUTE FUNCTION update_element_settings_updated_at();
        ");
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'pgsql') {
            // Drop triggers and functions first
            $pdo->exec("DROP TRIGGER IF EXISTS element_settings_updated_at_trigger ON element_settings");
            $pdo->exec("DROP TRIGGER IF EXISTS elements_updated_at_trigger ON elements");
            $pdo->exec("DROP FUNCTION IF EXISTS update_element_settings_updated_at()");
            $pdo->exec("DROP FUNCTION IF EXISTS update_elements_updated_at()");

            // Drop tables
            $pdo->exec("DROP TABLE IF EXISTS element_settings");
            $pdo->exec("DROP TABLE IF EXISTS elements");
        } else {
            // MySQL - use backticks
            $pdo->exec("DROP TABLE IF EXISTS `element_settings`");
            $pdo->exec("DROP TABLE IF EXISTS `elements`");
        }

        error_log("Elements: Tables dropped successfully ({$driver})");
    }
}
