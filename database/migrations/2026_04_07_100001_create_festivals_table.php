<?php
/**
 * Migration: Create festivals table
 * Plugin: Festival Directory (tenant: festivalnews.org)
 */

use Screenart\Musedock\Database;

class CreateFestivalsTable_2026_04_07_100001
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `festivals` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `tenant_id` INT NOT NULL,
                    `name` VARCHAR(500) NOT NULL,
                    `slug` VARCHAR(600) NOT NULL,
                    `short_description` TEXT DEFAULT NULL,
                    `description` TEXT DEFAULT NULL,
                    `logo` VARCHAR(500) DEFAULT NULL,
                    `cover_image` VARCHAR(500) DEFAULT NULL,
                    `featured_image` VARCHAR(500) DEFAULT NULL,
                    `type` VARCHAR(50) NOT NULL DEFAULT 'film_festival',
                    `country` VARCHAR(100) NOT NULL,
                    `city` VARCHAR(255) DEFAULT NULL,
                    `venue` VARCHAR(500) DEFAULT NULL,
                    `address` TEXT DEFAULT NULL,
                    `latitude` DECIMAL(10,8) DEFAULT NULL,
                    `longitude` DECIMAL(11,8) DEFAULT NULL,
                    `edition_number` INT DEFAULT NULL,
                    `edition_year` INT DEFAULT NULL,
                    `start_date` DATE DEFAULT NULL,
                    `end_date` DATE DEFAULT NULL,
                    `deadline_date` DATE DEFAULT NULL,
                    `frequency` VARCHAR(30) DEFAULT 'annual',
                    `website_url` VARCHAR(500) DEFAULT NULL,
                    `email` VARCHAR(255) DEFAULT NULL,
                    `phone` VARCHAR(50) DEFAULT NULL,
                    `social_facebook` VARCHAR(500) DEFAULT NULL,
                    `social_instagram` VARCHAR(500) DEFAULT NULL,
                    `social_twitter` VARCHAR(500) DEFAULT NULL,
                    `social_youtube` VARCHAR(500) DEFAULT NULL,
                    `social_vimeo` VARCHAR(500) DEFAULT NULL,
                    `social_linkedin` VARCHAR(500) DEFAULT NULL,
                    `submission_filmfreeway_url` VARCHAR(500) DEFAULT NULL,
                    `submission_festhome_url` VARCHAR(500) DEFAULT NULL,
                    `submission_festgate_url` VARCHAR(500) DEFAULT NULL,
                    `submission_other_url` VARCHAR(500) DEFAULT NULL,
                    `submission_status` VARCHAR(20) DEFAULT 'closed',
                    `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
                    `claimed_by` INT DEFAULT NULL,
                    `claimed_at` DATETIME DEFAULT NULL,
                    `claim_token` VARCHAR(100) DEFAULT NULL,
                    `contact_email` VARCHAR(255) DEFAULT NULL,
                    `seo_title` VARCHAR(500) DEFAULT NULL,
                    `seo_description` TEXT DEFAULT NULL,
                    `seo_keywords` TEXT DEFAULT NULL,
                    `seo_image` VARCHAR(500) DEFAULT NULL,
                    `noindex` TINYINT(1) NOT NULL DEFAULT 0,
                    `view_count` INT UNSIGNED NOT NULL DEFAULT 0,
                    `featured` TINYINT(1) NOT NULL DEFAULT 0,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    `base_locale` VARCHAR(10) NOT NULL DEFAULT 'es',
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT NULL,
                    `deleted_at` DATETIME DEFAULT NULL,
                    `deleted_by` INT DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_tenant_slug` (`tenant_id`, `slug`),
                    KEY `idx_festivals_tenant` (`tenant_id`),
                    KEY `idx_festivals_status` (`tenant_id`, `status`),
                    KEY `idx_festivals_type` (`tenant_id`, `type`),
                    KEY `idx_festivals_country` (`tenant_id`, `country`),
                    KEY `idx_festivals_featured` (`tenant_id`, `featured`),
                    KEY `idx_festivals_slug` (`slug`),
                    KEY `idx_festivals_deadline` (`deadline_date`),
                    KEY `idx_festivals_start_date` (`start_date`),
                    KEY `idx_festivals_claim_token` (`claim_token`),
                    KEY `idx_festivals_deleted` (`deleted_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            // PostgreSQL
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS festivals (
                    id SERIAL PRIMARY KEY,
                    tenant_id INTEGER NOT NULL,
                    name VARCHAR(500) NOT NULL,
                    slug VARCHAR(600) NOT NULL,
                    short_description TEXT,
                    description TEXT,
                    logo VARCHAR(500),
                    cover_image VARCHAR(500),
                    featured_image VARCHAR(500),
                    type VARCHAR(50) NOT NULL DEFAULT 'film_festival',
                    country VARCHAR(100) NOT NULL,
                    city VARCHAR(255),
                    venue VARCHAR(500),
                    address TEXT,
                    latitude DECIMAL(10,8),
                    longitude DECIMAL(11,8),
                    edition_number INTEGER,
                    edition_year INTEGER,
                    start_date DATE,
                    end_date DATE,
                    deadline_date DATE,
                    frequency VARCHAR(30) DEFAULT 'annual',
                    website_url VARCHAR(500),
                    email VARCHAR(255),
                    phone VARCHAR(50),
                    social_facebook VARCHAR(500),
                    social_instagram VARCHAR(500),
                    social_twitter VARCHAR(500),
                    social_youtube VARCHAR(500),
                    social_vimeo VARCHAR(500),
                    social_linkedin VARCHAR(500),
                    submission_filmfreeway_url VARCHAR(500),
                    submission_festhome_url VARCHAR(500),
                    submission_festgate_url VARCHAR(500),
                    submission_other_url VARCHAR(500),
                    submission_status VARCHAR(20) DEFAULT 'closed',
                    status VARCHAR(20) NOT NULL DEFAULT 'draft',
                    claimed_by INTEGER,
                    claimed_at TIMESTAMP,
                    claim_token VARCHAR(100),
                    contact_email VARCHAR(255),
                    seo_title VARCHAR(500),
                    seo_description TEXT,
                    seo_keywords TEXT,
                    seo_image VARCHAR(500),
                    noindex SMALLINT NOT NULL DEFAULT 0,
                    view_count INTEGER NOT NULL DEFAULT 0,
                    featured SMALLINT NOT NULL DEFAULT 0,
                    sort_order INTEGER NOT NULL DEFAULT 0,
                    base_locale VARCHAR(10) NOT NULL DEFAULT 'es',
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP,
                    deleted_at TIMESTAMP,
                    deleted_by INTEGER,
                    CHECK (type IN ('film_festival','music_festival','arts_festival','multidisciplinary','theater_festival','dance_festival','literary_festival','food_festival','tech_festival','other')),
                    CHECK (frequency IN ('annual','biannual','quarterly','monthly','biennial','irregular','one_time')),
                    CHECK (submission_status IN ('open','closed','upcoming')),
                    CHECK (status IN ('draft','published','verified','claimed','suspended')),
                    UNIQUE (tenant_id, slug)
                )
            ");

            $pdo->exec("CREATE INDEX IF NOT EXISTS festivals_idx_tenant ON festivals(tenant_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS festivals_idx_status ON festivals(tenant_id, status)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS festivals_idx_type ON festivals(tenant_id, type)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS festivals_idx_country ON festivals(tenant_id, country)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS festivals_idx_featured ON festivals(tenant_id, featured)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS festivals_idx_slug ON festivals(slug)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS festivals_idx_deadline ON festivals(deadline_date)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS festivals_idx_start_date ON festivals(start_date)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS festivals_idx_claim_token ON festivals(claim_token)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS festivals_idx_deleted ON festivals(deleted_at)");
        }
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("DROP TABLE IF EXISTS `festivals`");
        } else {
            $pdo->exec("DROP TABLE IF EXISTS festivals");
        }
    }
}
