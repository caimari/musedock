<?php
/**
 * Migration: Add custom domain fields to tenants table
 *
 * Adds fields required for custom domain management with Cloudflare:
 * - cloudflare_zone_id: Zone ID in Cloudflare Account 2
 * - cloudflare_nameservers: Cloudflare nameservers (JSON)
 * - email_routing_enabled: Whether email routing is active
 * - Updates status ENUM to include 'waiting_ns_change'
 */

use Screenart\Musedock\Database;

class AddCustomDomainFieldsToTenants_2025_12_15
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            // MySQL implementation

            // Check and add cloudflare_zone_id
            $stmt = $pdo->query("SHOW COLUMNS FROM tenants LIKE 'cloudflare_zone_id'");
            if (!$stmt->fetch()) {
                $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `cloudflare_zone_id` VARCHAR(255) NULL AFTER `cloudflare_record_id`");
                echo "  ✓ Added cloudflare_zone_id column\n";
            }

            // Check and add cloudflare_nameservers
            $stmt = $pdo->query("SHOW COLUMNS FROM tenants LIKE 'cloudflare_nameservers'");
            if (!$stmt->fetch()) {
                $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `cloudflare_nameservers` JSON NULL AFTER `cloudflare_zone_id`");
                echo "  ✓ Added cloudflare_nameservers column\n";
            }

            // Check and add email_routing_enabled
            $stmt = $pdo->query("SHOW COLUMNS FROM tenants LIKE 'email_routing_enabled'");
            if (!$stmt->fetch()) {
                $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `email_routing_enabled` TINYINT(1) DEFAULT 0 AFTER `cloudflare_nameservers`");
                echo "  ✓ Added email_routing_enabled column\n";
            }

            // Update status ENUM to include 'waiting_ns_change'
            // First, check current ENUM values
            $stmt = $pdo->query("SHOW COLUMNS FROM tenants LIKE 'status'");
            $statusColumn = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($statusColumn && strpos($statusColumn['Type'], 'waiting_ns_change') === false) {
                $pdo->exec("ALTER TABLE `tenants` MODIFY COLUMN `status` ENUM('active', 'suspended', 'pending', 'waiting_ns_change', 'error') DEFAULT 'pending'");
                echo "  ✓ Updated status ENUM to include 'waiting_ns_change'\n";
            }

        } else {
            // PostgreSQL implementation

            // Check and add cloudflare_zone_id
            $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'tenants' AND column_name = 'cloudflare_zone_id'");
            if (!$stmt->fetch()) {
                $pdo->exec("ALTER TABLE tenants ADD COLUMN cloudflare_zone_id VARCHAR(255) NULL");
                echo "  ✓ Added cloudflare_zone_id column\n";
            }

            // Check and add cloudflare_nameservers
            $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'tenants' AND column_name = 'cloudflare_nameservers'");
            if (!$stmt->fetch()) {
                $pdo->exec("ALTER TABLE tenants ADD COLUMN cloudflare_nameservers JSONB NULL");
                echo "  ✓ Added cloudflare_nameservers column\n";
            }

            // Check and add email_routing_enabled
            $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'tenants' AND column_name = 'email_routing_enabled'");
            if (!$stmt->fetch()) {
                $pdo->exec("ALTER TABLE tenants ADD COLUMN email_routing_enabled BOOLEAN DEFAULT FALSE");
                echo "  ✓ Added email_routing_enabled column\n";
            }

            // Update status type to include 'waiting_ns_change'
            // Check if the enum value already exists
            $stmt = $pdo->query("
                SELECT EXISTS (
                    SELECT 1 FROM pg_type t
                    JOIN pg_enum e ON t.oid = e.enumtypid
                    WHERE t.typname = 'tenant_status_type'
                    AND e.enumlabel = 'waiting_ns_change'
                ) as exists
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result['exists']) {
                // Try to add the new enum value
                try {
                    $pdo->exec("ALTER TYPE tenant_status_type ADD VALUE 'waiting_ns_change'");
                    echo "  ✓ Added 'waiting_ns_change' to status enum\n";
                } catch (PDOException $e) {
                    // If type doesn't exist, alter the column directly
                    $pdo->exec("
                        ALTER TABLE tenants
                        ALTER COLUMN status TYPE VARCHAR(20),
                        ALTER COLUMN status SET DEFAULT 'pending'
                    ");
                    echo "  ✓ Changed status to VARCHAR to support 'waiting_ns_change'\n";
                }
            }
        }

        echo "✓ Custom domain fields added to tenants table\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            // MySQL rollback
            $pdo->exec("ALTER TABLE `tenants` DROP COLUMN IF EXISTS `cloudflare_zone_id`");
            $pdo->exec("ALTER TABLE `tenants` DROP COLUMN IF EXISTS `cloudflare_nameservers`");
            $pdo->exec("ALTER TABLE `tenants` DROP COLUMN IF EXISTS `email_routing_enabled`");

            // Revert status ENUM (remove waiting_ns_change)
            $pdo->exec("ALTER TABLE `tenants` MODIFY COLUMN `status` ENUM('active', 'suspended', 'pending', 'error') DEFAULT 'pending'");

            echo "  ✓ Removed custom domain fields from tenants\n";

        } else {
            // PostgreSQL rollback
            $pdo->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS cloudflare_zone_id");
            $pdo->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS cloudflare_nameservers");
            $pdo->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS email_routing_enabled");

            echo "  ✓ Removed custom domain fields from tenants\n";
            echo "  ⚠ Note: PostgreSQL ENUM types cannot be easily modified. Status column unchanged.\n";
        }

        echo "✓ Migration rolled back\n";
    }
}
