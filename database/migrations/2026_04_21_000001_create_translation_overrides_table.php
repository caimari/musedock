<?php

use Screenart\Musedock\Database;

class CreateTranslationOverridesTable_2026_04_21_000001
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `translation_overrides` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `tenant_id` INT(11) NOT NULL DEFAULT 0,
                    `context` VARCHAR(30) NOT NULL DEFAULT 'tenant',
                    `locale` VARCHAR(10) NOT NULL,
                    `translation_key` VARCHAR(255) NOT NULL,
                    `translation_value` TEXT NOT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_translation_overrides_scope` (`context`, `locale`, `tenant_id`, `translation_key`),
                    KEY `idx_translation_overrides_lookup` (`context`, `locale`, `tenant_id`),
                    KEY `idx_translation_overrides_key` (`translation_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS translation_overrides (
                    id SERIAL PRIMARY KEY,
                    tenant_id INTEGER NOT NULL DEFAULT 0,
                    context VARCHAR(30) NOT NULL DEFAULT 'tenant',
                    locale VARCHAR(10) NOT NULL,
                    translation_key VARCHAR(255) NOT NULL,
                    translation_value TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE (context, locale, tenant_id, translation_key)
                )
            ");

            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_translation_overrides_lookup ON translation_overrides(context, locale, tenant_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_translation_overrides_key ON translation_overrides(translation_key)");
        }

        echo "✓ Table translation_overrides created\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("DROP TABLE IF EXISTS `translation_overrides`");
        } else {
            $pdo->exec("DROP TABLE IF EXISTS translation_overrides");
        }

        echo "✓ Table translation_overrides dropped\n";
    }
}
