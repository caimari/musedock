<?php

use Screenart\Musedock\Database;

/**
 * Migration: Añadir campos para dominios personalizados en tenants
 *
 * Campos nuevos:
 * - cloudflare_zone_id: ID de la zona en Cloudflare Account 2
 * - cloudflare_nameservers: NSs de Cloudflare (JSON)
 * - email_routing_enabled: Si tiene email routing activo
 * - status: Actualizar ENUM para incluir 'waiting_ns_change'
 *
 * @return string
 */
return function(): string {
    try {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            // MySQL
            $pdo->exec("
                ALTER TABLE tenants
                ADD COLUMN IF NOT EXISTS cloudflare_zone_id VARCHAR(255) NULL AFTER cloudflare_record_id,
                ADD COLUMN IF NOT EXISTS cloudflare_nameservers JSON NULL AFTER cloudflare_zone_id,
                ADD COLUMN IF NOT EXISTS email_routing_enabled BOOLEAN DEFAULT FALSE AFTER cloudflare_nameservers
            ");

            // Actualizar ENUM de status
            $pdo->exec("
                ALTER TABLE tenants
                MODIFY COLUMN status ENUM('active', 'suspended', 'pending', 'waiting_ns_change', 'error') DEFAULT 'pending'
            ");

            return "✅ [MySQL] Custom domain fields added to tenants table";

        } else {
            // PostgreSQL
            $pdo->exec("
                ALTER TABLE tenants
                ADD COLUMN IF NOT EXISTS cloudflare_zone_id VARCHAR(255) NULL,
                ADD COLUMN IF NOT EXISTS cloudflare_nameservers JSONB NULL,
                ADD COLUMN IF NOT EXISTS email_routing_enabled BOOLEAN DEFAULT FALSE
            ");

            // Actualizar tipo status si no existe 'waiting_ns_change'
            $pdo->exec("
                DO $$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_type t
                        JOIN pg_enum e ON t.oid = e.enumtypid
                        WHERE t.typname = 'tenant_status' AND e.enumlabel = 'waiting_ns_change'
                    ) THEN
                        ALTER TYPE tenant_status ADD VALUE 'waiting_ns_change';
                    END IF;
                END$$;
            ");

            return "✅ [PostgreSQL] Custom domain fields added to tenants table";
        }

    } catch (PDOException $e) {
        return "❌ Error in migration: " . $e->getMessage();
    }
};
