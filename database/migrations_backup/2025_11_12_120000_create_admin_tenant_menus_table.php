<?php

use Screenart\Musedock\Database;

class CreateAdminTenantMenusTable_2025_11_12_120000
{
    public function up()
    {
        $pdo = Database::connect();

        // Crear tabla admin_tenant_menus para menús personalizados de tenants
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admin_tenant_menus (
                id INT PRIMARY KEY AUTO_INCREMENT,
                tenant_id INT NOT NULL,
                parent_id INT NULL,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                url VARCHAR(500) NOT NULL,
                icon VARCHAR(100),
                icon_type ENUM('bi', 'fas', 'far', 'fal') DEFAULT 'bi',
                order_position INT DEFAULT 0,
                permission VARCHAR(255),
                is_active BOOLEAN DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (parent_id) REFERENCES admin_tenant_menus(id) ON DELETE CASCADE,
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                INDEX idx_tenant_id (tenant_id),
                INDEX idx_parent_id (parent_id),
                INDEX idx_is_active (is_active),
                UNIQUE KEY unique_tenant_slug (tenant_id, slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Crear tabla para almacenar configuraciones personalizadas de menús del sistema por tenant
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admin_menu_customizations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                tenant_id INT NULL,
                admin_menu_id INT NOT NULL,
                custom_title VARCHAR(255) NULL,
                custom_icon VARCHAR(100) NULL,
                custom_icon_type ENUM('bi', 'fas', 'far', 'fal') NULL,
                custom_order_position INT NULL,
                is_hidden BOOLEAN DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_tenant_menu (tenant_id, admin_menu_id),
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                FOREIGN KEY (admin_menu_id) REFERENCES admin_menus(id) ON DELETE CASCADE,
                INDEX idx_tenant_id (tenant_id),
                INDEX idx_admin_menu_id (admin_menu_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        echo "✓ Tablas admin_tenant_menus y admin_menu_customizations creadas\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $pdo->exec("DROP TABLE IF EXISTS admin_menu_customizations");
        $pdo->exec("DROP TABLE IF EXISTS admin_tenant_menus");
        echo "✓ Tablas eliminadas\n";
    }
}
