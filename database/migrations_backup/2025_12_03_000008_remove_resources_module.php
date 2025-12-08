<?php

/**
 * Migración: Eliminar módulo Resources
 *
 * Esta migración limpia el sistema eliminando:
 * 1. La tabla resource_permissions (ya no se usa)
 * 2. El menú "resources" del sidebar
 *
 * El sistema ahora usa:
 * - Permissions + Roles para control de acceso
 * - admin_menus.permission para filtrar sidebar
 * - checkPermission() en controladores como verificación final
 */

use Screenart\Musedock\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        echo "════════════════════════════════════════════════════════════\n";
        echo " MIGRACIÓN: Eliminar módulo Resources\n";
        echo "════════════════════════════════════════════════════════════\n\n";

        // 1. Eliminar menú "resources" del sidebar
        echo "Eliminando menú 'resources' del sidebar...\n";
        $this->execute("DELETE FROM admin_menus WHERE slug = 'resources'");
        echo "  ✓ Menú eliminado\n\n";

        // 2. Eliminar tabla resource_permissions
        if ($this->tableExists('resource_permissions')) {
            echo "Eliminando tabla resource_permissions...\n";
            $this->execute("DROP TABLE IF EXISTS resource_permissions");
            echo "  ✓ Tabla eliminada\n\n";
        } else {
            echo "  ✓ Tabla resource_permissions no existe (ya fue eliminada)\n\n";
        }

        echo "════════════════════════════════════════════════════════════\n";
        echo " ✓ Módulo Resources eliminado correctamente\n";
        echo "════════════════════════════════════════════════════════════\n\n";
        echo "El sistema ahora usa:\n";
        echo "  • Permissions + Roles para control de acceso\n";
        echo "  • admin_menus.permission para filtrar sidebar\n";
        echo "  • checkPermission() en controladores\n";
    }

    public function down(): void
    {
        echo "Recreando tabla resource_permissions...\n";

        $driver = $this->getDriverName();

        if ($driver === 'pgsql') {
            $this->execute("
                CREATE TABLE IF NOT EXISTS resource_permissions (
                    id SERIAL PRIMARY KEY,
                    resource_type VARCHAR(50) NOT NULL,
                    resource_identifier VARCHAR(255) NOT NULL,
                    permission_slug VARCHAR(100) NOT NULL,
                    description TEXT,
                    tenant_id INT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
        } else {
            $this->execute("
                CREATE TABLE IF NOT EXISTS resource_permissions (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    resource_type VARCHAR(50) NOT NULL COMMENT 'Tipo: route, component, api',
                    resource_identifier VARCHAR(255) NOT NULL COMMENT 'Identificador del recurso',
                    permission_slug VARCHAR(100) NOT NULL COMMENT 'Slug del permiso requerido',
                    description TEXT,
                    tenant_id INT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_resource (resource_type, resource_identifier),
                    INDEX idx_permission (permission_slug),
                    INDEX idx_tenant (tenant_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        echo "  ✓ Tabla resource_permissions recreada\n";
        echo "  ⚠ Nota: El menú del sidebar debe recrearse manualmente\n";
    }
};
