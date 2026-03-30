<?php
/**
 * Migration: Seed default languages for existing tenants
 * Generated at: 2025-12-13
 * Compatible with: MySQL/MariaDB + PostgreSQL
 */

use Screenart\Musedock\Database;
use Screenart\Musedock\Seeders\TenantLanguagesSeeder;

class SeedTenantLanguages_2025_12_13
{
    public function up()
    {
        echo "Ejecutando seeder de idiomas para tenants existentes...\\n";

        // Ejecutar el seeder para todos los tenants activos
        require_once __DIR__ . '/../../core/Seeders/TenantLanguagesSeeder.php';
        $seeder = new TenantLanguagesSeeder();
        $seeder->run(); // Sin parámetro = todos los tenants

        echo "✓ Seeder de idiomas ejecutado correctamente\\n";
    }

    public function down()
    {
        // No hacemos nada en el down, ya que eliminar idiomas podría
        // romper la funcionalidad de tenants existentes
        echo "No se eliminan idiomas en rollback (por seguridad)\\n";
    }
}
