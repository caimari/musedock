<?php
/**
 * Migration: Add presentation fields to slider_slides table
 * Generated at: 2025_12_14_120000
 * Compatible with: MySQL/MariaDB + PostgreSQL
 *
 * Adds per-slide fields for:
 * - Button texts/urls (2 CTAs) + target behavior
 * - Typography, colors, bold toggle
 * - Button styling + shape
 */
 
use Screenart\Musedock\Database;

class AddSliderSlidePresentationFields_2025_12_14_120000
{
    private function hasColumn(\PDO $pdo, string $driver, string $table, string $column): bool
    {
        if ($driver === 'mysql') {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE :col");
            $stmt->execute(['col' => $column]);
            return (bool) $stmt->fetch();
        }

        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_name = :table AND column_name = :col");
        $stmt->execute(['table' => $table, 'col' => $column]);
        return (bool) $stmt->fetch();
    }

    private function tryExec(\PDO $pdo, string $sql): void
    {
        try {
            $pdo->exec($sql);
        } catch (\Throwable $e) {
            // Ignorar: normalmente significa que ya existe o no aplica al driver
        }
    }

    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        // Si la tabla aún no existe, no hacemos nada aquí (se creará en otra migración)
        try {
            $pdo->query($driver === 'mysql' ? "SELECT 1 FROM `slider_slides` LIMIT 1" : "SELECT 1 FROM slider_slides LIMIT 1");
        } catch (\Throwable $e) {
            echo "⚠ Table slider_slides does not exist yet, skipping...\n";
            return;
        }

        if ($driver === 'mysql') {
            $adds = [
                'link_text' => "ALTER TABLE `slider_slides` ADD COLUMN `link_text` VARCHAR(120) NULL AFTER `link_target`",
                'link_target' => "ALTER TABLE `slider_slides` ADD COLUMN `link_target` VARCHAR(16) NOT NULL DEFAULT '_self' AFTER `link_url`",
                'title_bold' => "ALTER TABLE `slider_slides` ADD COLUMN `title_bold` TINYINT(1) NOT NULL DEFAULT 1 AFTER `link_text`",
                'title_font' => "ALTER TABLE `slider_slides` ADD COLUMN `title_font` VARCHAR(255) NULL AFTER `title_bold`",
                'title_color' => "ALTER TABLE `slider_slides` ADD COLUMN `title_color` VARCHAR(32) NULL AFTER `title_font`",
                'description_font' => "ALTER TABLE `slider_slides` ADD COLUMN `description_font` VARCHAR(255) NULL AFTER `title_color`",
                'description_color' => "ALTER TABLE `slider_slides` ADD COLUMN `description_color` VARCHAR(32) NULL AFTER `description_font`",
                'button_custom' => "ALTER TABLE `slider_slides` ADD COLUMN `button_custom` TINYINT(1) NOT NULL DEFAULT 0 AFTER `title_bold`",
                'button_bg_color' => "ALTER TABLE `slider_slides` ADD COLUMN `button_bg_color` VARCHAR(32) NULL AFTER `button_custom`",
                'button_text_color' => "ALTER TABLE `slider_slides` ADD COLUMN `button_text_color` VARCHAR(32) NULL AFTER `button_bg_color`",
                'button_border_color' => "ALTER TABLE `slider_slides` ADD COLUMN `button_border_color` VARCHAR(32) NULL AFTER `button_text_color`",
                'link2_url' => "ALTER TABLE `slider_slides` ADD COLUMN `link2_url` VARCHAR(255) NULL AFTER `link_text`",
                'link2_target' => "ALTER TABLE `slider_slides` ADD COLUMN `link2_target` VARCHAR(16) NOT NULL DEFAULT '_self' AFTER `link2_url`",
                'link2_text' => "ALTER TABLE `slider_slides` ADD COLUMN `link2_text` VARCHAR(120) NULL AFTER `link2_target`",
                'button2_custom' => "ALTER TABLE `slider_slides` ADD COLUMN `button2_custom` TINYINT(1) NOT NULL DEFAULT 0 AFTER `button_border_color`",
                'button2_bg_color' => "ALTER TABLE `slider_slides` ADD COLUMN `button2_bg_color` VARCHAR(32) NULL AFTER `button2_custom`",
                'button2_text_color' => "ALTER TABLE `slider_slides` ADD COLUMN `button2_text_color` VARCHAR(32) NULL AFTER `button2_bg_color`",
                'button2_border_color' => "ALTER TABLE `slider_slides` ADD COLUMN `button2_border_color` VARCHAR(32) NULL AFTER `button2_text_color`",
                'button_shape' => "ALTER TABLE `slider_slides` ADD COLUMN `button_shape` VARCHAR(16) NOT NULL DEFAULT 'rounded' AFTER `button2_border_color`",
            ];

            foreach ($adds as $col => $sql) {
                if (!$this->hasColumn($pdo, $driver, 'slider_slides', $col)) {
                    $this->tryExec($pdo, $sql);
                }
            }

            // Asegurar defaults consistentes
            if ($this->hasColumn($pdo, $driver, 'slider_slides', 'title_bold')) {
                $this->tryExec($pdo, "ALTER TABLE `slider_slides` MODIFY `title_bold` TINYINT(1) NOT NULL DEFAULT 1");
                $this->tryExec($pdo, "UPDATE `slider_slides` SET `title_bold` = 1 WHERE `title_bold` = 0");
            }
            if ($this->hasColumn($pdo, $driver, 'slider_slides', 'link_target')) {
                $this->tryExec($pdo, "UPDATE `slider_slides` SET `link_target` = '_self' WHERE `link_target` IS NULL OR `link_target` = ''");
            }
            if ($this->hasColumn($pdo, $driver, 'slider_slides', 'link2_target')) {
                $this->tryExec($pdo, "UPDATE `slider_slides` SET `link2_target` = '_self' WHERE `link2_target` IS NULL OR `link2_target` = ''");
            }
            if ($this->hasColumn($pdo, $driver, 'slider_slides', 'button_shape')) {
                $this->tryExec($pdo, "UPDATE `slider_slides` SET `button_shape` = 'rounded' WHERE `button_shape` IS NULL OR `button_shape` = ''");
            }
        } else {
            // PostgreSQL
            $this->tryExec($pdo, "ALTER TABLE slider_slides ADD COLUMN IF NOT EXISTS link_target VARCHAR(16) NOT NULL DEFAULT '_self'");
            $this->tryExec($pdo, "ALTER TABLE slider_slides ADD COLUMN IF NOT EXISTS link_text VARCHAR(120) NULL");

            $this->tryExec($pdo, "ALTER TABLE slider_slides ADD COLUMN IF NOT EXISTS title_bold SMALLINT NOT NULL DEFAULT 1");
            $this->tryExec($pdo, "ALTER TABLE slider_slides ADD COLUMN IF NOT EXISTS title_font VARCHAR(255) NULL");
            $this->tryExec($pdo, "ALTER TABLE slider_slides ADD COLUMN IF NOT EXISTS title_color VARCHAR(32) NULL");
            $this->tryExec($pdo, "ALTER TABLE slider_slides ADD COLUMN IF NOT EXISTS description_font VARCHAR(255) NULL");
            $this->tryExec($pdo, "ALTER TABLE slider_slides ADD COLUMN IF NOT EXISTS description_color VARCHAR(32) NULL");

            $this->tryExec($pdo, "ALTER TABLE slider_slides ADD COLUMN IF NOT EXISTS button_custom SMALLINT NOT NULL DEFAULT 0");
            $this->tryExec($pdo, "ALTER TABLE slider_slides ADD COLUMN IF NOT EXISTS button_bg_color VARCHAR(32) NULL");
            $this->tryExec($pdo, "ALTER TABLE slider_slides ADD COLUMN IF NOT EXISTS button_text_color VARCHAR(32) NULL");
            $this->tryExec($pdo, "ALTER TABLE slider_slides ADD COLUMN IF NOT EXISTS button_border_color VARCHAR(32) NULL");

            $this->tryExec($pdo, "ALTER TABLE slider_slides ADD COLUMN IF NOT EXISTS link2_url VARCHAR(255) NULL");
            $this->tryExec($pdo, "ALTER TABLE slider_slides ADD COLUMN IF NOT EXISTS link2_target VARCHAR(16) NOT NULL DEFAULT '_self'");
            $this->tryExec($pdo, "ALTER TABLE slider_slides ADD COLUMN IF NOT EXISTS link2_text VARCHAR(120) NULL");

            $this->tryExec($pdo, "ALTER TABLE slider_slides ADD COLUMN IF NOT EXISTS button2_custom SMALLINT NOT NULL DEFAULT 0");
            $this->tryExec($pdo, "ALTER TABLE slider_slides ADD COLUMN IF NOT EXISTS button2_bg_color VARCHAR(32) NULL");
            $this->tryExec($pdo, "ALTER TABLE slider_slides ADD COLUMN IF NOT EXISTS button2_text_color VARCHAR(32) NULL");
            $this->tryExec($pdo, "ALTER TABLE slider_slides ADD COLUMN IF NOT EXISTS button2_border_color VARCHAR(32) NULL");

            $this->tryExec($pdo, "ALTER TABLE slider_slides ADD COLUMN IF NOT EXISTS button_shape VARCHAR(16) NOT NULL DEFAULT 'rounded'");

            // Defaults/backfill
            $this->tryExec($pdo, "UPDATE slider_slides SET title_bold = 1 WHERE title_bold = 0");
            $this->tryExec($pdo, "UPDATE slider_slides SET link_target = '_self' WHERE link_target IS NULL OR link_target = ''");
            $this->tryExec($pdo, "UPDATE slider_slides SET link2_target = '_self' WHERE link2_target IS NULL OR link2_target = ''");
            $this->tryExec($pdo, "UPDATE slider_slides SET button_shape = 'rounded' WHERE button_shape IS NULL OR button_shape = ''");
        }

        echo "✓ Added presentation fields to slider_slides\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $drops = [
                'button_shape', 'button2_border_color', 'button2_text_color', 'button2_bg_color', 'button2_custom',
                'link2_text', 'link2_target', 'link2_url',
                'button_border_color', 'button_text_color', 'button_bg_color', 'button_custom',
                'description_color', 'description_font',
                'title_color', 'title_font', 'title_bold',
                'link_text', 'link_target',
            ];
            foreach ($drops as $col) {
                $this->tryExec($pdo, "ALTER TABLE `slider_slides` DROP COLUMN `{$col}`");
            }
        } else {
            $this->tryExec($pdo, "ALTER TABLE slider_slides DROP COLUMN IF EXISTS button_shape");
            $this->tryExec($pdo, "ALTER TABLE slider_slides DROP COLUMN IF EXISTS button2_border_color");
            $this->tryExec($pdo, "ALTER TABLE slider_slides DROP COLUMN IF EXISTS button2_text_color");
            $this->tryExec($pdo, "ALTER TABLE slider_slides DROP COLUMN IF EXISTS button2_bg_color");
            $this->tryExec($pdo, "ALTER TABLE slider_slides DROP COLUMN IF EXISTS button2_custom");
            $this->tryExec($pdo, "ALTER TABLE slider_slides DROP COLUMN IF EXISTS link2_text");
            $this->tryExec($pdo, "ALTER TABLE slider_slides DROP COLUMN IF EXISTS link2_target");
            $this->tryExec($pdo, "ALTER TABLE slider_slides DROP COLUMN IF EXISTS link2_url");
            $this->tryExec($pdo, "ALTER TABLE slider_slides DROP COLUMN IF EXISTS button_border_color");
            $this->tryExec($pdo, "ALTER TABLE slider_slides DROP COLUMN IF EXISTS button_text_color");
            $this->tryExec($pdo, "ALTER TABLE slider_slides DROP COLUMN IF EXISTS button_bg_color");
            $this->tryExec($pdo, "ALTER TABLE slider_slides DROP COLUMN IF EXISTS button_custom");
            $this->tryExec($pdo, "ALTER TABLE slider_slides DROP COLUMN IF EXISTS description_color");
            $this->tryExec($pdo, "ALTER TABLE slider_slides DROP COLUMN IF EXISTS description_font");
            $this->tryExec($pdo, "ALTER TABLE slider_slides DROP COLUMN IF EXISTS title_color");
            $this->tryExec($pdo, "ALTER TABLE slider_slides DROP COLUMN IF EXISTS title_font");
            $this->tryExec($pdo, "ALTER TABLE slider_slides DROP COLUMN IF EXISTS title_bold");
            $this->tryExec($pdo, "ALTER TABLE slider_slides DROP COLUMN IF EXISTS link_text");
            $this->tryExec($pdo, "ALTER TABLE slider_slides DROP COLUMN IF EXISTS link_target");
        }

        echo "✓ Removed presentation fields from slider_slides\n";
    }
}

