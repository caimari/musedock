<?php

namespace Screenart\Musedock\Migrations;

use Screenart\Musedock\Database;

class CreateTenantMenusTable
{
    public function up()
    {
        $pdo = Database::connect();

        // Crear tabla tenant_menus - menús independientes para cada tenant
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tenant_menus (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                parent_id INT NULL,
                module_id INT NULL,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                url VARCHAR(500) NOT NULL,
                icon VARCHAR(100) NULL,
                icon_type ENUM('bi', 'fas', 'far', 'fal') DEFAULT 'bi',
                order_position INT DEFAULT 0,
                permission VARCHAR(255) NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_tenant_id (tenant_id),
                INDEX idx_parent_id (parent_id),
                INDEX idx_module_id (module_id),
                INDEX idx_slug (slug),
                INDEX idx_order (order_position),
                UNIQUE KEY unique_tenant_slug (tenant_id, slug),
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                FOREIGN KEY (parent_id) REFERENCES tenant_menus(id) ON DELETE CASCADE,
                FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        echo "✓ Tabla tenant_menus creada correctamente\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $pdo->exec("DROP TABLE IF EXISTS tenant_menus");
        echo "✓ Tabla tenant_menus eliminada\n";
    }
}
