<?php
/**
 * Migration: Create festival_submission_links table
 * Plugin: Festival Directory — Dynamic submission platforms (unlimited per festival)
 */

use Screenart\Musedock\Database;

class CreateFestivalSubmissionLinksTable_2026_04_07_100008
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `festival_submission_links` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `festival_id` INT UNSIGNED NOT NULL,
                    `platform` VARCHAR(100) NOT NULL,
                    `url` VARCHAR(500) NOT NULL,
                    `label` VARCHAR(255) DEFAULT NULL,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_festival_platform` (`festival_id`, `platform`),
                    KEY `idx_fsl_festival` (`festival_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS festival_submission_links (
                    id SERIAL PRIMARY KEY,
                    festival_id INTEGER NOT NULL,
                    platform VARCHAR(100) NOT NULL,
                    url VARCHAR(500) NOT NULL,
                    label VARCHAR(255),
                    sort_order INTEGER NOT NULL DEFAULT 0,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE (festival_id, platform)
                )
            ");
            $pdo->exec("CREATE INDEX IF NOT EXISTS fsl_idx_festival ON festival_submission_links(festival_id)");
        }

        // Migrate existing fixed fields to the new table
        $rows = $pdo->query("
            SELECT id, submission_filmfreeway_url, submission_festhome_url,
                   submission_festgate_url, submission_other_url
            FROM festivals
            WHERE deleted_at IS NULL
              AND (submission_filmfreeway_url IS NOT NULL
                OR submission_festhome_url IS NOT NULL
                OR submission_festgate_url IS NOT NULL
                OR submission_other_url IS NOT NULL)
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $insert = $pdo->prepare("INSERT INTO festival_submission_links (festival_id, platform, url, label, sort_order) VALUES (?, ?, ?, ?, ?) ON CONFLICT DO NOTHING");

        foreach ($rows as $row) {
            if (!empty($row['submission_filmfreeway_url'])) {
                $insert->execute([$row['id'], 'filmfreeway', $row['submission_filmfreeway_url'], 'FilmFreeway', 1]);
            }
            if (!empty($row['submission_festhome_url'])) {
                $insert->execute([$row['id'], 'festhome', $row['submission_festhome_url'], 'Festhome', 2]);
            }
            if (!empty($row['submission_festgate_url'])) {
                $insert->execute([$row['id'], 'festgate', $row['submission_festgate_url'], 'FestGate', 3]);
            }
            if (!empty($row['submission_other_url'])) {
                $insert->execute([$row['id'], 'other', $row['submission_other_url'], 'Otra plataforma', 10]);
            }
        }
    }

    public function down()
    {
        $pdo = Database::connect();
        $pdo->exec("DROP TABLE IF EXISTS festival_submission_links");
    }
}
