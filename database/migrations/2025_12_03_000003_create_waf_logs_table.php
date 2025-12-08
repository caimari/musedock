<?php

/**
 * Migración: Crear tabla waf_logs para registrar ataques bloqueados
 */

use Screenart\Musedock\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $driver = $this->getDriverName();

        if ($driver === 'pgsql') {
            $this->execute("
                CREATE TABLE IF NOT EXISTS waf_logs (
                    id SERIAL PRIMARY KEY,
                    type VARCHAR(50) NOT NULL,
                    source VARCHAR(100) NOT NULL,
                    ip_address VARCHAR(45) NOT NULL,
                    uri TEXT NULL,
                    method VARCHAR(10) NULL,
                    user_agent TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");

            $this->execute("CREATE INDEX IF NOT EXISTS idx_waf_logs_type ON waf_logs (type)");
            $this->execute("CREATE INDEX IF NOT EXISTS idx_waf_logs_ip ON waf_logs (ip_address)");
            $this->execute("CREATE INDEX IF NOT EXISTS idx_waf_logs_date ON waf_logs (created_at)");
        } else {
            $this->execute("
                CREATE TABLE IF NOT EXISTS waf_logs (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    type VARCHAR(50) NOT NULL,
                    source VARCHAR(100) NOT NULL,
                    ip_address VARCHAR(45) NOT NULL,
                    uri TEXT NULL,
                    method VARCHAR(10) NULL,
                    user_agent TEXT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_type (type),
                    INDEX idx_ip (ip_address),
                    INDEX idx_date (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        echo "  ✓ Tabla waf_logs creada\n";
    }

    public function down(): void
    {
        $this->execute("DROP TABLE IF EXISTS waf_logs");
        echo "  ✓ Tabla waf_logs eliminada\n";
    }
};
