<?php

/**
 * Migración: Crear tablas para React Sliders
 *
 * Esta clase sigue el formato esperado por MigrationManager:
 * - Nombre: CreateReactSlidersTables_2025_11_18_000000
 * - Sin namespace (para compatibilidad con el sistema de migraciones)
 */
class CreateReactSlidersTables_2025_11_18_000000
{
    public function up()
    {
        $pdo = \Screenart\Musedock\Database::connect();

        echo "════════════════════════════════════════════════════════════\n";
        echo " MIGRACIÓN: React Sliders Module\n";
        echo "════════════════════════════════════════════════════════════\n\n";

        try {
            // ========== TABLA: react_sliders ==========
            echo "📝 Creando tabla 'react_sliders'...\n";

            $stmt = $pdo->query("SHOW TABLES LIKE 'react_sliders'");
            if ($stmt->fetch()) {
                echo "⚠ Tabla 'react_sliders' ya existe\n";
            } else {
                $pdo->exec("
                    CREATE TABLE `react_sliders` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `name` varchar(255) NOT NULL COMMENT 'Nombre del slider',
                        `identifier` varchar(100) NOT NULL COMMENT 'Identificador único',
                        `engine` enum('swiper','slick','keen','embla') DEFAULT 'swiper' COMMENT 'Motor del slider',
                        `settings` text DEFAULT NULL COMMENT 'Configuración JSON',
                        `tenant_id` int(11) DEFAULT NULL COMMENT 'ID del tenant (NULL = global)',
                        `is_active` tinyint(1) DEFAULT 1 COMMENT '1 = Activo, 0 = Inactivo',
                        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `unique_identifier_tenant` (`identifier`, `tenant_id`),
                        KEY `tenant_id` (`tenant_id`),
                        KEY `is_active` (`is_active`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Sliders React modernos'
                ");
                echo "✓ Tabla 'react_sliders' creada\n";
            }

            // ========== TABLA: react_slides ==========
            echo "📝 Creando tabla 'react_slides'...\n";

            $stmt = $pdo->query("SHOW TABLES LIKE 'react_slides'");
            if ($stmt->fetch()) {
                echo "⚠ Tabla 'react_slides' ya existe\n";
            } else {
                $pdo->exec("
                    CREATE TABLE `react_slides` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `slider_id` int(11) NOT NULL COMMENT 'ID del slider',
                        `title` varchar(255) DEFAULT NULL COMMENT 'Título de la diapositiva',
                        `subtitle` varchar(255) DEFAULT NULL COMMENT 'Subtítulo',
                        `description` text DEFAULT NULL COMMENT 'Descripción',
                        `image_url` varchar(500) NOT NULL COMMENT 'URL de la imagen',
                        `button_text` varchar(100) DEFAULT NULL COMMENT 'Texto del botón',
                        `button_link` varchar(500) DEFAULT NULL COMMENT 'Enlace del botón',
                        `button_target` enum('_self','_blank') DEFAULT '_self' COMMENT 'Target del enlace',
                        `background_color` varchar(20) DEFAULT NULL COMMENT 'Color de fondo',
                        `text_color` varchar(20) DEFAULT '#ffffff' COMMENT 'Color del texto',
                        `overlay_opacity` decimal(3,2) DEFAULT 0.30 COMMENT 'Opacidad del overlay (0.00-1.00)',
                        `sort_order` int(11) DEFAULT 0 COMMENT 'Orden de visualización',
                        `is_active` tinyint(1) DEFAULT 1 COMMENT '1 = Activo, 0 = Inactivo',
                        `custom_css` text DEFAULT NULL COMMENT 'CSS personalizado',
                        `custom_data` text DEFAULT NULL COMMENT 'Datos personalizados (JSON)',
                        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `slider_id` (`slider_id`),
                        KEY `sort_order` (`sort_order`),
                        KEY `is_active` (`is_active`),
                        CONSTRAINT `fk_react_slides_slider`
                            FOREIGN KEY (`slider_id`)
                            REFERENCES `react_sliders` (`id`)
                            ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Diapositivas de los sliders React'
                ");
                echo "✓ Tabla 'react_slides' creada\n";
            }

            echo "\n";
            echo "════════════════════════════════════════════════════════════\n";
            echo " ✓ Migración completada exitosamente\n";
            echo "════════════════════════════════════════════════════════════\n\n";

            echo "📋 PRÓXIMOS PASOS:\n";
            echo "1. Habilitar el módulo en la base de datos si no está activo\n";
            echo "2. Acceder al panel de sliders:\n";
            echo "   Superadmin: /musedock/react-sliders\n";
            echo "   Tenant: /admin/react-sliders\n\n";

        } catch (\Exception $e) {
            echo "\n";
            echo "✗ Error en migración: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function down()
    {
        $pdo = \Screenart\Musedock\Database::connect();

        echo "════════════════════════════════════════════════════════════\n";
        echo " ROLLBACK: React Sliders Module\n";
        echo "════════════════════════════════════════════════════════════\n\n";

        try {
            $tables = ['react_slides', 'react_sliders'];

            foreach ($tables as $table) {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                if ($stmt->fetch()) {
                    $pdo->exec("DROP TABLE `$table`");
                    echo "✓ Tabla '$table' eliminada\n";
                } else {
                    echo "⚠ Tabla '$table' no existe\n";
                }
            }

            echo "\n";
            echo "════════════════════════════════════════════════════════════\n";
            echo " ✓ Rollback completado exitosamente\n";
            echo "════════════════════════════════════════════════════════════\n\n";

        } catch (\Exception $e) {
            echo "\n";
            echo "✗ Error al revertir migración: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}

// Ejecutar si se llama directamente
if (php_sapi_name() === 'cli' && isset($argv) && basename($argv[0]) === basename(__FILE__)) {
    // Definir constante APP_ROOT si no existe
    if (!defined('APP_ROOT')) {
        define('APP_ROOT', realpath(__DIR__ . '/../../..'));
    }

    // Cargar dependencias core necesarias
    require_once APP_ROOT . '/core/Env.php';
    require_once APP_ROOT . '/core/Database.php';
    require_once APP_ROOT . '/core/Database/DatabaseDriver.php';
    require_once APP_ROOT . '/core/Database/QueryBuilder.php';
    require_once APP_ROOT . '/core/Database/Drivers/MySQLDriver.php';
    require_once APP_ROOT . '/core/Database/Drivers/PostgreSQLDriver.php';

    // Cargar configuración
    $config = require APP_ROOT . '/config/config.php';

    $migration = new CreateReactSlidersTables_2025_11_18_000000();

    if (isset($argv[1]) && $argv[1] === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}
