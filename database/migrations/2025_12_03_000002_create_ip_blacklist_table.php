<?php

/**
 * Migración: Crear tabla ip_blacklist para WAF
 */

use Screenart\Musedock\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $driver = $this->getDriverName();

        if ($driver === 'pgsql') {
            $this->execute("
                CREATE TABLE IF NOT EXISTS ip_blacklist (
                    id SERIAL PRIMARY KEY,
                    ip_address VARCHAR(45) NOT NULL,
                    reason VARCHAR(255) NULL,
                    blocked_by VARCHAR(50) DEFAULT 'system',
                    expires_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(ip_address)
                )
            ");

            $this->execute("CREATE INDEX IF NOT EXISTS idx_ip_blacklist_expires ON ip_blacklist (expires_at)");
        } else {
            $this->execute("
                CREATE TABLE IF NOT EXISTS ip_blacklist (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    ip_address VARCHAR(45) NOT NULL,
                    reason VARCHAR(255) NULL,
                    blocked_by VARCHAR(50) DEFAULT 'system',
                    expires_at DATETIME NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_ip (ip_address),
                    INDEX idx_expires (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        echo "  ✓ Tabla ip_blacklist creada\n";
    }

    public function down(): void
    {
        $this->execute("DROP TABLE IF EXISTS ip_blacklist");
        echo "  ✓ Tabla ip_blacklist eliminada\n";
    }
};
