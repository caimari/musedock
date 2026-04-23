<?php
/**
 * Migration: Create festival_types table
 * Plugin: Festival Directory — Editable festival types
 */

use Screenart\Musedock\Database;

class CreateFestivalTypesTable_2026_04_07_100007
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `festival_types` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `tenant_id` INT NOT NULL,
                    `name` VARCHAR(255) NOT NULL,
                    `slug` VARCHAR(300) NOT NULL,
                    `description` TEXT DEFAULT NULL,
                    `icon` VARCHAR(100) DEFAULT NULL,
                    `color` VARCHAR(7) DEFAULT NULL,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    `festival_count` INT NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_tenant_slug` (`tenant_id`, `slug`),
                    KEY `idx_ftype_tenant` (`tenant_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS festival_types (
                    id SERIAL PRIMARY KEY,
                    tenant_id INTEGER NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    slug VARCHAR(300) NOT NULL,
                    description TEXT,
                    icon VARCHAR(100),
                    color VARCHAR(7),
                    sort_order INTEGER NOT NULL DEFAULT 0,
                    festival_count INTEGER NOT NULL DEFAULT 0,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP,
                    UNIQUE (tenant_id, slug)
                )
            ");
            $pdo->exec("CREATE INDEX IF NOT EXISTS ftype_idx_tenant ON festival_types(tenant_id)");
        }

        // Seed default types for tenant 53 (festivalnews)
        $defaults = [
            ['Festival de Cine', 'film_festival', 'bi-camera-reels', '#dc3545'],
            ['Festival de Cortometrajes', 'short_film_festival', 'bi-film', '#fd7e14'],
            ['Festival de Documentales', 'documentary_festival', 'bi-journal-richtext', '#6f42c1'],
            ['Festival de Animación', 'animation_festival', 'bi-stars', '#e83e8c'],
            ['Festival de Música', 'music_festival', 'bi-music-note-beamed', '#20c997'],
            ['Festival de Artes', 'arts_festival', 'bi-palette', '#0dcaf0'],
            ['Multidisciplinar', 'multidisciplinary', 'bi-grid-3x3', '#6c757d'],
            ['Festival de Teatro', 'theater_festival', 'bi-mask', '#ffc107'],
            ['Festival de Danza', 'dance_festival', 'bi-person-arms-up', '#198754'],
            ['Festival Literario', 'literary_festival', 'bi-book', '#0d6efd'],
            ['Festival Gastronómico', 'food_festival', 'bi-cup-hot', '#795548'],
            ['Festival de Tecnología', 'tech_festival', 'bi-cpu', '#607D8B'],
            ['Certamen', 'certamen', 'bi-trophy', '#ff9800'],
            ['Concurso', 'contest', 'bi-award', '#9c27b0'],
            ['Premio', 'award', 'bi-star', '#ffd700'],
            ['Otro', 'other', 'bi-three-dots', '#adb5bd'],
        ];

        $stmt = $pdo->prepare("INSERT INTO festival_types (tenant_id, name, slug, icon, color, sort_order) VALUES (53, ?, ?, ?, ?, ?)");
        foreach ($defaults as $i => $type) {
            try {
                $stmt->execute([$type[0], $type[1], $type[2], $type[3], $i]);
            } catch (\Exception $e) {
                // Skip if already exists
            }
        }
    }

    public function down()
    {
        $pdo = Database::connect();
        $pdo->exec("DROP TABLE IF EXISTS festival_types");
    }
}
