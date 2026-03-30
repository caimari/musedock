<?php
/**
 * Migration: Add hide_title to blog_posts table
 * Compatible with: MySQL/MariaDB + PostgreSQL
 */

use Screenart\Musedock\Database;

class AddHideTitleToBlogPostsTable_2025_12_15_150000
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            try {
                $pdo->exec("ALTER TABLE `blog_posts` ADD COLUMN `hide_title` TINYINT(1) NOT NULL DEFAULT 0");
            } catch (\PDOException $e) {
                if (stripos($e->getMessage(), 'Duplicate column name') === false) {
                    throw $e;
                }
            }
        } else {
            // PostgreSQL
            try {
                $pdo->exec("ALTER TABLE blog_posts ADD COLUMN hide_title SMALLINT NOT NULL DEFAULT 0");
            } catch (\PDOException $e) {
                if (stripos($e->getMessage(), 'already exists') === false) {
                    throw $e;
                }
            }
        }

        echo "✓ Column hide_title added to blog_posts\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            try {
                $pdo->exec("ALTER TABLE `blog_posts` DROP COLUMN `hide_title`");
            } catch (\PDOException $e) {
            }
        } else {
            try {
                $pdo->exec("ALTER TABLE blog_posts DROP COLUMN hide_title");
            } catch (\PDOException $e) {
            }
        }

        echo "✓ Column hide_title dropped from blog_posts\n";
    }
}

