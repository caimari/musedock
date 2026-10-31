<?php
/**
 * Traducciones de categorías del blog (nombre, descripción y SEO por idioma).
 * El slug de la categoría es el mismo en todos los idiomas: /category/x y /en/category/x.
 * Compatible con MySQL/MariaDB y PostgreSQL.
 */

use Screenart\Musedock\Database;

class CreateBlogCategoryTranslationsTable_2026_10_23_000001
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `blog_category_translations` (
                    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `category_id` INT(10) UNSIGNED NOT NULL,
                    `tenant_id` INT(11) DEFAULT NULL,
                    `locale` VARCHAR(10) NOT NULL,
                    `name` VARCHAR(255) NOT NULL,
                    `description` TEXT DEFAULT NULL,
                    `seo_title` VARCHAR(255) DEFAULT NULL,
                    `seo_description` TEXT DEFAULT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_bct_category_locale` (`category_id`, `locale`),
                    KEY `idx_bct_locale` (`locale`),
                    KEY `idx_bct_tenant_id` (`tenant_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS blog_category_translations (
                    id SERIAL PRIMARY KEY,
                    category_id INTEGER NOT NULL,
                    tenant_id INTEGER,
                    locale VARCHAR(10) NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    description TEXT,
                    seo_title VARCHAR(255),
                    seo_description TEXT,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP,
                    UNIQUE (category_id, locale)
                )
            ");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_bct_locale ON blog_category_translations(locale)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_bct_tenant_id ON blog_category_translations(tenant_id)");
        }

        echo "✓ Table blog_category_translations created\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("DROP TABLE IF EXISTS `blog_category_translations`");
        } else {
            $pdo->exec("DROP TABLE IF EXISTS blog_category_translations");
        }

        echo "✓ Table blog_category_translations dropped\n";
    }
}
