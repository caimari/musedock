<?php
/**
 * Migration: Add tenant_id to sliders and slider_slides tables
 * Generated at: 2026_02_25_000001
 * Compatible with: MySQL/MariaDB + PostgreSQL
 *
 * Existing rows with tenant_id = NULL remain as superadmin-owned global sliders.
 */

use Screenart\Musedock\Database;

class AddTenantIdToSliders_2026_02_25_000001
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
            // Ignore: column/index may already exist or driver-specific edge case
        }
    }

    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        echo "🔄 Adding tenant_id to sliders and slider_slides tables...\n";

        // ── sliders table ──────────────────────────────────────────────
        if (!$this->hasColumn($pdo, $driver, 'sliders', 'tenant_id')) {
            if ($driver === 'mysql') {
                $pdo->exec("ALTER TABLE `sliders` ADD COLUMN `tenant_id` INT UNSIGNED DEFAULT NULL AFTER `id`");
            } else {
                $pdo->exec("ALTER TABLE sliders ADD COLUMN tenant_id INTEGER DEFAULT NULL");
            }
            echo "  ✓ Added column: sliders.tenant_id\n";
        } else {
            echo "  - Column sliders.tenant_id already exists\n";
        }

        // Index on sliders.tenant_id
        $this->tryExec($pdo, $driver === 'mysql'
            ? "CREATE INDEX idx_sliders_tenant_id ON `sliders` (`tenant_id`)"
            : "CREATE INDEX idx_sliders_tenant_id ON sliders (tenant_id)"
        );

        // ── slider_slides table ────────────────────────────────────────
        if (!$this->hasColumn($pdo, $driver, 'slider_slides', 'tenant_id')) {
            if ($driver === 'mysql') {
                $pdo->exec("ALTER TABLE `slider_slides` ADD COLUMN `tenant_id` INT UNSIGNED DEFAULT NULL AFTER `id`");
            } else {
                $pdo->exec("ALTER TABLE slider_slides ADD COLUMN tenant_id INTEGER DEFAULT NULL");
            }
            echo "  ✓ Added column: slider_slides.tenant_id\n";
        } else {
            echo "  - Column slider_slides.tenant_id already exists\n";
        }

        // Index on slider_slides.tenant_id
        $this->tryExec($pdo, $driver === 'mysql'
            ? "CREATE INDEX idx_slider_slides_tenant_id ON `slider_slides` (`tenant_id`)"
            : "CREATE INDEX idx_slider_slides_tenant_id ON slider_slides (tenant_id)"
        );

        echo "✅ Added tenant_id to sliders and slider_slides tables\n";
        echo "   Note: Existing rows with tenant_id = NULL are superadmin-owned global sliders.\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        echo "⚠️  Reverting migration: removing tenant_id from sliders and slider_slides...\n";

        // Drop index + column from sliders
        $this->tryExec($pdo, $driver === 'mysql'
            ? "DROP INDEX idx_sliders_tenant_id ON `sliders`"
            : "DROP INDEX IF EXISTS idx_sliders_tenant_id"
        );

        if ($driver === 'mysql') {
            $this->tryExec($pdo, "ALTER TABLE `sliders` DROP COLUMN `tenant_id`");
        } else {
            $this->tryExec($pdo, "ALTER TABLE sliders DROP COLUMN IF EXISTS tenant_id");
        }
        echo "  ✓ Removed column: sliders.tenant_id\n";

        // Drop index + column from slider_slides
        $this->tryExec($pdo, $driver === 'mysql'
            ? "DROP INDEX idx_slider_slides_tenant_id ON `slider_slides`"
            : "DROP INDEX IF EXISTS idx_slider_slides_tenant_id"
        );

        if ($driver === 'mysql') {
            $this->tryExec($pdo, "ALTER TABLE `slider_slides` DROP COLUMN `tenant_id`");
        } else {
            $this->tryExec($pdo, "ALTER TABLE slider_slides DROP COLUMN IF EXISTS tenant_id");
        }
        echo "  ✓ Removed column: slider_slides.tenant_id\n";

        echo "✅ Rollback completed\n";
    }
}
