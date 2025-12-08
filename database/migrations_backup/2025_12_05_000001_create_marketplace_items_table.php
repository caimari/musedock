<?php

/**
 * Migración: Crear tabla marketplace_items
 *
 * Esta migración crea la tabla para registrar los items
 * instalados desde el marketplace (módulos, plugins, temas)
 */

use Screenart\Musedock\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        echo "════════════════════════════════════════════════════════════\n";
        echo " MIGRACIÓN: Crear tabla marketplace_items\n";
        echo "════════════════════════════════════════════════════════════\n\n";

        // Verificar si la tabla ya existe
        $tableExists = $this->db->query("SHOW TABLES LIKE 'marketplace_items'")->rowCount() > 0;

        if ($tableExists) {
            echo "✓ La tabla marketplace_items ya existe, omitiendo creación.\n";
            return;
        }

        // Crear tabla marketplace_items
        echo "Creando tabla marketplace_items...\n";

        $this->db->exec("
            CREATE TABLE marketplace_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(100) NOT NULL COMMENT 'Identificador único del item',
                type ENUM('module', 'plugin', 'theme') NOT NULL COMMENT 'Tipo de item',
                name VARCHAR(255) NULL COMMENT 'Nombre del item',
                version VARCHAR(20) DEFAULT '1.0.0' COMMENT 'Versión instalada',
                author VARCHAR(255) NULL COMMENT 'Autor del item',
                description TEXT NULL COMMENT 'Descripción del item',
                marketplace_id VARCHAR(100) NULL COMMENT 'ID del item en el marketplace remoto',
                installed_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de instalación',
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última actualización',
                UNIQUE KEY unique_item (slug, type),
                KEY idx_type (type),
                KEY idx_installed_at (installed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Items instalados desde el Marketplace'
        ");

        echo "✓ Tabla marketplace_items creada correctamente.\n";

        // Agregar permiso para marketplace si no existe
        echo "\nVerificando permiso para marketplace...\n";

        $permExists = $this->db->query("SELECT COUNT(*) FROM permissions WHERE slug = 'marketplace.manage'")->fetchColumn();

        if (!$permExists) {
            $stmt = $this->db->prepare("
                INSERT INTO permissions (slug, name, description, category, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                'marketplace.manage',
                'Gestionar Marketplace',
                'Acceso al marketplace para instalar módulos, plugins y temas',
                'Sistema'
            ]);
            echo "✓ Permiso marketplace.manage creado.\n";
        } else {
            echo "✓ Permiso marketplace.manage ya existe.\n";
        }

        // Agregar entrada al menú de admin si no existe
        echo "\nVerificando menú del Marketplace...\n";

        $menuExists = $this->db->query("SELECT COUNT(*) FROM admin_menus WHERE url = '/musedock/marketplace'")->fetchColumn();

        if (!$menuExists) {
            // Obtener el orden máximo en la categoría de Ajustes
            $maxOrder = $this->db->query("
                SELECT MAX(order_position) FROM admin_menus WHERE parent_id = (
                    SELECT id FROM admin_menus WHERE title = 'Ajustes' AND parent_id IS NULL LIMIT 1
                )
            ")->fetchColumn() ?: 0;

            // Buscar el ID del menú padre "Ajustes"
            $parentId = $this->db->query("
                SELECT id FROM admin_menus WHERE title = 'Ajustes' AND parent_id IS NULL LIMIT 1
            ")->fetchColumn();

            if ($parentId) {
                $stmt = $this->db->prepare("
                    INSERT INTO admin_menus (title, slug, url, icon, parent_id, order_position, is_active, permission, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW())
                ");
                $stmt->execute([
                    'Marketplace',
                    'marketplace',
                    '/musedock/marketplace',
                    'bi-shop',
                    $parentId,
                    $maxOrder + 1,
                    'marketplace.manage'
                ]);
                echo "✓ Menú Marketplace añadido bajo Ajustes.\n";
            } else {
                echo "⚠ No se encontró el menú 'Ajustes', el menú del Marketplace no se añadió.\n";
            }
        } else {
            echo "✓ Menú Marketplace ya existe.\n";
        }

        echo "\n════════════════════════════════════════════════════════════\n";
        echo " ✓ Migración completada exitosamente\n";
        echo "════════════════════════════════════════════════════════════\n";
    }

    public function down(): void
    {
        echo "Eliminando tabla marketplace_items...\n";
        $this->db->exec("DROP TABLE IF EXISTS marketplace_items");

        echo "Eliminando permiso marketplace.manage...\n";
        $this->db->exec("DELETE FROM permissions WHERE slug = 'marketplace.manage'");

        echo "Eliminando menú Marketplace...\n";
        $this->db->exec("DELETE FROM admin_menus WHERE url = '/musedock/marketplace'");

        echo "✓ Rollback completado.\n";
    }
};
