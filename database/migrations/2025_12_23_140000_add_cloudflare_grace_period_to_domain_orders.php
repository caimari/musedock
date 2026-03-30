<?php
/**
 * Migration: Add cloudflare_grace_period_until to domain_orders
 *
 * Adds a grace period column to allow users 48 hours to restore
 * Cloudflare nameservers before their DNS configuration is deleted.
 *
 * Compatible with both PostgreSQL and MySQL/MariaDB
 */

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;

class AddCloudflareGracePeriodToDomainOrders_2025_12_23_140000
{
    /**
     * Run the migration
     */
    public function up(): void
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $isPostgres = ($driver === 'pgsql');

        echo "Adding cloudflare_grace_period_until column to domain_orders table...\n";

        try {
            // Check if column already exists
            if ($isPostgres) {
                $stmt = $pdo->query("
                    SELECT column_name
                    FROM information_schema.columns
                    WHERE table_name = 'domain_orders'
                      AND column_name = 'cloudflare_grace_period_until'
                ");
            } else {
                $stmt = $pdo->query("
                    SELECT COLUMN_NAME
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'domain_orders'
                      AND COLUMN_NAME = 'cloudflare_grace_period_until'
                ");
            }

            if ($stmt && $stmt->fetch()) {
                echo "✓ Column cloudflare_grace_period_until already exists in domain_orders table\n";
                return;
            }

            // Add column based on database driver
            if ($isPostgres) {
                // PostgreSQL syntax
                $pdo->exec("
                    ALTER TABLE domain_orders
                    ADD COLUMN cloudflare_grace_period_until TIMESTAMP NULL
                ");

                $pdo->exec("
                    COMMENT ON COLUMN domain_orders.cloudflare_grace_period_until
                    IS 'Fecha límite para restaurar Cloudflare NS sin perder configuración DNS'
                ");

                echo "✓ Added column cloudflare_grace_period_until (TIMESTAMP) to domain_orders\n";

            } else {
                // MySQL/MariaDB syntax
                $pdo->exec("
                    ALTER TABLE domain_orders
                    ADD COLUMN cloudflare_grace_period_until DATETIME NULL
                    COMMENT 'Fecha límite para restaurar Cloudflare NS sin perder configuración DNS'
                    AFTER use_cloudflare_ns
                ");

                echo "✓ Added column cloudflare_grace_period_until (DATETIME) to domain_orders\n";
            }

            // Check if index already exists
            if ($isPostgres) {
                $stmt = $pdo->query("
                    SELECT indexname
                    FROM pg_indexes
                    WHERE tablename = 'domain_orders'
                      AND indexname = 'idx_grace_period_cleanup'
                ");
            } else {
                $stmt = $pdo->query("
                    SHOW INDEX FROM domain_orders
                    WHERE Key_name = 'idx_grace_period_cleanup'
                ");
            }

            if ($stmt && $stmt->fetch()) {
                echo "✓ Index idx_grace_period_cleanup already exists\n";
            } else {
                // Add index for cron job performance
                if ($isPostgres) {
                    $pdo->exec("
                        CREATE INDEX idx_grace_period_cleanup
                        ON domain_orders(use_cloudflare_ns, cloudflare_zone_id, cloudflare_grace_period_until)
                        WHERE use_cloudflare_ns = FALSE AND cloudflare_zone_id IS NOT NULL
                    ");
                } else {
                    $pdo->exec("
                        CREATE INDEX idx_grace_period_cleanup
                        ON domain_orders(use_cloudflare_ns, cloudflare_zone_id, cloudflare_grace_period_until)
                    ");
                }

                echo "✓ Created index idx_grace_period_cleanup for performance\n";
            }

            Logger::info("[Migration] Added cloudflare_grace_period_until column to domain_orders");

        } catch (Exception $e) {
            Logger::error("[Migration] Error adding cloudflare_grace_period_until: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $isPostgres = ($driver === 'pgsql');

        echo "Removing cloudflare_grace_period_until column from domain_orders table...\n";

        try {
            // Drop index first
            if ($isPostgres) {
                $pdo->exec("DROP INDEX IF EXISTS idx_grace_period_cleanup");
            } else {
                $stmt = $pdo->query("
                    SHOW INDEX FROM domain_orders
                    WHERE Key_name = 'idx_grace_period_cleanup'
                ");
                if ($stmt && $stmt->fetch()) {
                    $pdo->exec("DROP INDEX idx_grace_period_cleanup ON domain_orders");
                }
            }

            echo "✓ Dropped index idx_grace_period_cleanup\n";

            // Drop column
            $pdo->exec("ALTER TABLE domain_orders DROP COLUMN cloudflare_grace_period_until");

            echo "✓ Removed column cloudflare_grace_period_until from domain_orders\n";

            Logger::info("[Migration] Removed cloudflare_grace_period_until column from domain_orders");

        } catch (Exception $e) {
            Logger::error("[Migration] Error removing cloudflare_grace_period_until: " . $e->getMessage());
            throw $e;
        }
    }
}
