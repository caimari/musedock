<?php
/**
 * Migration: Add image_position column to slider_slides table
 * Allows per-slide focal point control via CSS object-position.
 * Stored as "X% Y%" (e.g., "50% 30%" = center horizontally, 30% from top).
 */

use Screenart\Musedock\Database;

class AddImagePositionToSliderSlides_2026_03_30_000001
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        // Check if column already exists
        if ($driver === 'pgsql') {
            $check = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'slider_slides' AND column_name = 'image_position'");
        } else {
            $check = $pdo->query("SHOW COLUMNS FROM `slider_slides` LIKE 'image_position'");
        }

        if ($check->rowCount() > 0) {
            return;
        }

        if ($driver === 'mysql') {
            $pdo->exec("ALTER TABLE `slider_slides` ADD COLUMN `image_position` VARCHAR(20) DEFAULT 'center center' AFTER `image_url`");
        } else {
            $pdo->exec("ALTER TABLE slider_slides ADD COLUMN image_position VARCHAR(20) DEFAULT 'center center'");
        }
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("ALTER TABLE `slider_slides` DROP COLUMN `image_position`");
        } else {
            $pdo->exec("ALTER TABLE slider_slides DROP COLUMN image_position");
        }
    }
}
