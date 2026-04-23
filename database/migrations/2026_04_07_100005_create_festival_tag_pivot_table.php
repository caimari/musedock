<?php
/**
 * Migration: Create festival_tag_pivot table
 * Plugin: Festival Directory
 */

use Screenart\Musedock\Database;

class CreateFestivalTagPivotTable_2026_04_07_100005
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `festival_tag_pivot` (
                    `festival_id` INT UNSIGNED NOT NULL,
                    `tag_id` INT UNSIGNED NOT NULL,
                    PRIMARY KEY (`festival_id`, `tag_id`),
                    KEY `idx_ftp_tag` (`tag_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS festival_tag_pivot (
                    festival_id INTEGER NOT NULL,
                    tag_id INTEGER NOT NULL,
                    PRIMARY KEY (festival_id, tag_id)
                )
            ");
            $pdo->exec("CREATE INDEX IF NOT EXISTS ftp_idx_tag ON festival_tag_pivot(tag_id)");
        }
    }

    public function down()
    {
        $pdo = Database::connect();
        $pdo->exec("DROP TABLE IF EXISTS festival_tag_pivot");
    }
}
