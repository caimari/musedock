<?php

use Screenart\Musedock\Database;
use Screenart\Musedock\Database\Migration;

return new class extends Migration
{
    /**
     * Ejecutar la migración: crear tabla superadmin_trusted_ips
     */
    public function up(): void
    {
        $db = Database::connect();
        $driver = $db->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $this->upMySQL($db);
        } else {
            $this->upPostgreSQL($db);
        }
    }

    private function upMySQL(\PDO $db): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS `superadmin_trusted_ips` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID único',
              `super_admin_id` int(11) unsigned NOT NULL COMMENT 'ID del superadmin',
              `ip_address` varchar(45) NOT NULL COMMENT 'Dirección IP (IPv4 o IPv6)',
              `description` varchar(255) DEFAULT NULL COMMENT 'Descripción de la IP (ej: Oficina, Casa)',
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de actualización',
              PRIMARY KEY (`id`),
              UNIQUE KEY `idx_super_admin_ip` (`super_admin_id`, `ip_address`),
              KEY `idx_super_admin_id` (`super_admin_id`),
              KEY `idx_ip_address` (`ip_address`),
              CONSTRAINT `fk_trusted_ips_super_admin`
                FOREIGN KEY (`super_admin_id`)
                REFERENCES `super_admins` (`id`)
                ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='IPs de confianza para superadmins (whitelist para evitar rate limiting)'
        ";

        $db->exec($sql);
        echo "✓ Tabla superadmin_trusted_ips creada (MySQL)\n";
    }

    private function upPostgreSQL(\PDO $db): void
    {
        // Crear tabla
        $sql = "
            CREATE TABLE IF NOT EXISTS superadmin_trusted_ips (
              id SERIAL PRIMARY KEY,
              super_admin_id INTEGER NOT NULL,
              ip_address VARCHAR(45) NOT NULL,
              description VARCHAR(255) DEFAULT NULL,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              CONSTRAINT fk_trusted_ips_super_admin
                FOREIGN KEY (super_admin_id)
                REFERENCES super_admins (id)
                ON DELETE CASCADE
            )
        ";
        $db->exec($sql);

        // Crear índices
        $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_super_admin_ip ON superadmin_trusted_ips (super_admin_id, ip_address)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_super_admin_id ON superadmin_trusted_ips (super_admin_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_ip_address ON superadmin_trusted_ips (ip_address)");

        // Crear trigger para updated_at
        $db->exec("
            CREATE OR REPLACE FUNCTION update_superadmin_trusted_ips_updated_at()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = CURRENT_TIMESTAMP;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        ");

        $db->exec("
            DROP TRIGGER IF EXISTS trigger_update_superadmin_trusted_ips_updated_at ON superadmin_trusted_ips
        ");

        $db->exec("
            CREATE TRIGGER trigger_update_superadmin_trusted_ips_updated_at
            BEFORE UPDATE ON superadmin_trusted_ips
            FOR EACH ROW
            EXECUTE FUNCTION update_superadmin_trusted_ips_updated_at()
        ");

        echo "✓ Tabla superadmin_trusted_ips creada (PostgreSQL)\n";
    }

    /**
     * Revertir la migración: eliminar tabla superadmin_trusted_ips
     */
    public function down(): void
    {
        $db = Database::connect();
        $driver = $db->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $db->exec("DROP TABLE IF EXISTS `superadmin_trusted_ips`");
        } else {
            $db->exec("DROP TRIGGER IF EXISTS trigger_update_superadmin_trusted_ips_updated_at ON superadmin_trusted_ips");
            $db->exec("DROP FUNCTION IF EXISTS update_superadmin_trusted_ips_updated_at()");
            $db->exec("DROP TABLE IF EXISTS superadmin_trusted_ips");
        }

        echo "✓ Tabla superadmin_trusted_ips eliminada\n";
    }
};
