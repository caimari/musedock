<?php

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;

/**
 * Migration: Add cloudflare_grace_period_until to domain_orders
 *
 * Adds a grace period column to allow users 48 hours to restore
 * Cloudflare nameservers before their DNS configuration is deleted.
 *
 * Compatible with MySQL and PostgreSQL
 */
return new class {
    /**
     * Run the migration
     */
    public function up(): void
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        try {
            if ($driver === 'mysql') {
                // MySQL syntax
                $pdo->exec("
                    ALTER TABLE domain_orders
                    ADD COLUMN cloudflare_grace_period_until DATETIME NULL
                    COMMENT 'Fecha límite para restaurar Cloudflare NS sin perder configuración DNS'
                    AFTER use_cloudflare_ns
                ");

                // Add index for cron job performance
                $pdo->exec("
                    CREATE INDEX idx_grace_period_cleanup
                    ON domain_orders(use_cloudflare_ns, cloudflare_zone_id, cloudflare_grace_period_until)
                ");

            } else if ($driver === 'pgsql') {
                // PostgreSQL syntax
                $pdo->exec("
                    ALTER TABLE domain_orders
                    ADD COLUMN cloudflare_grace_period_until TIMESTAMP NULL
                ");

                $pdo->exec("
                    COMMENT ON COLUMN domain_orders.cloudflare_grace_period_until
                    IS 'Fecha límite para restaurar Cloudflare NS sin perder configuración DNS'
                ");

                // Add index for cron job performance
                $pdo->exec("
                    CREATE INDEX idx_grace_period_cleanup
                    ON domain_orders(use_cloudflare_ns, cloudflare_zone_id, cloudflare_grace_period_until)
                    WHERE use_cloudflare_ns = 0 AND cloudflare_zone_id IS NOT NULL
                ");
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

        try {
            // Drop index first
            if ($driver === 'mysql') {
                $pdo->exec("DROP INDEX idx_grace_period_cleanup ON domain_orders");
            } else if ($driver === 'pgsql') {
                $pdo->exec("DROP INDEX IF EXISTS idx_grace_period_cleanup");
            }

            // Drop column
            $pdo->exec("ALTER TABLE domain_orders DROP COLUMN cloudflare_grace_period_until");

            Logger::info("[Migration] Removed cloudflare_grace_period_until column from domain_orders");

        } catch (Exception $e) {
            Logger::error("[Migration] Error removing cloudflare_grace_period_until: " . $e->getMessage());
            throw $e;
        }
    }
};
