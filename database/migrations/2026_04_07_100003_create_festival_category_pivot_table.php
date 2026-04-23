<?php
/**
 * Migration: Create festival_category_pivot table
 * Plugin: Festival Directory
 */

use Screenart\Musedock\Database;

class CreateFestivalCategoryPivotTable_2026_04_07_100003
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `festival_category_pivot` (
                    `festival_id` INT UNSIGNED NOT NULL,
                    `category_id` INT UNSIGNED NOT NULL,
                    PRIMARY KEY (`festival_id`, `category_id`),
                    KEY `idx_fcp_category` (`category_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS festival_category_pivot (
                    festival_id INTEGER NOT NULL,
                    category_id INTEGER NOT NULL,
                    PRIMARY KEY (festival_id, category_id)
                )
            ");
            $pdo->exec("CREATE INDEX IF NOT EXISTS fcp_idx_category ON festival_category_pivot(category_id)");
        }
    }

    public function down()
    {
        $pdo = Database::connect();
        $pdo->exec("DROP TABLE IF EXISTS festival_category_pivot");
    }
}
