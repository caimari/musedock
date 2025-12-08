<?php

/**
 * Migración: Sistema de Plugins para Superadmin
 * Fecha: 2025-01-18
 * Descripción: Plugins independientes para el dominio base, aislados de tenants
 *
 * CARACTERÍSTICAS:
 * ✅ Plugins exclusivos del dominio base
 * ✅ Aislamiento total de plugins de tenants
 * ✅ Sistema de dependencias
 * ✅ Verificación de requisitos (PHP, MuseDock)
 * ✅ Hooks, rutas, menús y assets
 * ✅ Scripts de ciclo de vida (install, activate, deactivate, uninstall)
 */

use Screenart\Musedock\Database;

class CreateSuperadminPluginsTables_2025_01_18_000000
{
    public function up()
    {
        $pdo = Database::connect();

        echo "════════════════════════════════════════════════════════════\n";
        echo " MIGRACIÓN: Sistema de Plugins para Superadmin\n";
        echo "════════════════════════════════════════════════════════════\n\n";

        try {
            // ========== TABLA: superadmin_plugins ==========
            echo "📝 Creando tabla 'superadmin_plugins'...\n";

            $stmt = $pdo->query("SHOW TABLES LIKE 'superadmin_plugins'");
            if ($stmt->fetch()) {
                echo "⚠ Tabla 'superadmin_plugins' ya existe\n";
            } else {
                $pdo->exec("
                    CREATE TABLE `superadmin_plugins` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `slug` varchar(100) NOT NULL COMMENT 'Identificador único del plugin',
                        `name` varchar(255) NOT NULL COMMENT 'Nombre del plugin',
                        `description` text DEFAULT NULL COMMENT 'Descripción del plugin',
                        `version` varchar(50) DEFAULT '1.0.0' COMMENT 'Versión del plugin',
                        `author` varchar(255) DEFAULT NULL COMMENT 'Autor del plugin',
                        `author_url` varchar(500) DEFAULT NULL COMMENT 'URL del autor',
                        `plugin_url` varchar(500) DEFAULT NULL COMMENT 'URL del plugin',
                        `path` varchar(500) NOT NULL COMMENT 'Ruta del directorio del plugin',
                        `main_file` varchar(255) NOT NULL COMMENT 'Archivo principal del plugin',
                        `namespace` varchar(255) DEFAULT NULL COMMENT 'Namespace del plugin',
                        `is_active` tinyint(1) DEFAULT 0 COMMENT '1 = Activo, 0 = Inactivo',
                        `is_installed` tinyint(1) DEFAULT 0 COMMENT '1 = Instalado, 0 = No instalado',
                        `auto_activate` tinyint(1) DEFAULT 0 COMMENT 'Activar automáticamente al instalar',
                        `requires_php` varchar(50) DEFAULT NULL COMMENT 'Versión mínima de PHP requerida',
                        `requires_musedock` varchar(50) DEFAULT NULL COMMENT 'Versión mínima de MuseDock',
                        `dependencies` text DEFAULT NULL COMMENT 'Dependencias del plugin (JSON)',
                        `settings` longtext DEFAULT NULL COMMENT 'Configuración del plugin (JSON)',
                        `installed_at` datetime DEFAULT NULL COMMENT 'Fecha de instalación',
                        `activated_at` datetime DEFAULT NULL COMMENT 'Fecha de última activación',
                        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `slug` (`slug`),
                        KEY `is_active` (`is_active`),
                        KEY `is_installed` (`is_installed`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Plugins instalados en el dominio base (superadmin)'
                ");
                echo "✓ Tabla 'superadmin_plugins' creada\n";
            }

            // ========== TABLA: superadmin_plugin_hooks ==========
            echo "📝 Creando tabla 'superadmin_plugin_hooks'...\n";

            $stmt = $pdo->query("SHOW TABLES LIKE 'superadmin_plugin_hooks'");
            if ($stmt->fetch()) {
                echo "⚠ Tabla 'superadmin_plugin_hooks' ya existe\n";
            } else {
                $pdo->exec("
                    CREATE TABLE `superadmin_plugin_hooks` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `plugin_id` int(11) NOT NULL COMMENT 'ID del plugin',
                        `hook_name` varchar(255) NOT NULL COMMENT 'Nombre del hook/acción',
                        `callback` varchar(500) NOT NULL COMMENT 'Función callback',
                        `priority` int(11) DEFAULT 10 COMMENT 'Prioridad de ejecución (menor = primero)',
                        `is_active` tinyint(1) DEFAULT 1 COMMENT '1 = Activo, 0 = Inactivo',
                        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `plugin_id` (`plugin_id`),
                        KEY `hook_name` (`hook_name`),
                        KEY `is_active` (`is_active`),
                        CONSTRAINT `fk_plugin_hooks_plugin`
                            FOREIGN KEY (`plugin_id`)
                            REFERENCES `superadmin_plugins` (`id`)
                            ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Hooks y acciones registradas por plugins'
                ");
                echo "✓ Tabla 'superadmin_plugin_hooks' creada\n";
            }

            // ========== TABLA: superadmin_plugin_routes ==========
            echo "📝 Creando tabla 'superadmin_plugin_routes'...\n";

            $stmt = $pdo->query("SHOW TABLES LIKE 'superadmin_plugin_routes'");
            if ($stmt->fetch()) {
                echo "⚠ Tabla 'superadmin_plugin_routes' ya existe\n";
            } else {
                $pdo->exec("
                    CREATE TABLE `superadmin_plugin_routes` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `plugin_id` int(11) NOT NULL COMMENT 'ID del plugin',
                        `method` varchar(10) NOT NULL COMMENT 'GET, POST, PUT, DELETE, etc.',
                        `path` varchar(500) NOT NULL COMMENT 'Ruta del endpoint',
                        `controller` varchar(500) NOT NULL COMMENT 'Controlador',
                        `action` varchar(255) NOT NULL COMMENT 'Método del controlador',
                        `middleware` text DEFAULT NULL COMMENT 'Middlewares (JSON array)',
                        `name` varchar(255) DEFAULT NULL COMMENT 'Nombre de la ruta',
                        `is_active` tinyint(1) DEFAULT 1 COMMENT '1 = Activa, 0 = Inactiva',
                        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `plugin_id` (`plugin_id`),
                        KEY `is_active` (`is_active`),
                        CONSTRAINT `fk_plugin_routes_plugin`
                            FOREIGN KEY (`plugin_id`)
                            REFERENCES `superadmin_plugins` (`id`)
                            ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Rutas registradas por plugins'
                ");
                echo "✓ Tabla 'superadmin_plugin_routes' creada\n";
            }

            // ========== TABLA: superadmin_plugin_menus ==========
            echo "📝 Creando tabla 'superadmin_plugin_menus'...\n";

            $stmt = $pdo->query("SHOW TABLES LIKE 'superadmin_plugin_menus'");
            if ($stmt->fetch()) {
                echo "⚠ Tabla 'superadmin_plugin_menus' ya existe\n";
            } else {
                $pdo->exec("
                    CREATE TABLE `superadmin_plugin_menus` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `plugin_id` int(11) NOT NULL COMMENT 'ID del plugin',
                        `parent_id` int(11) DEFAULT NULL COMMENT 'ID del menú padre (para submenús)',
                        `title` varchar(255) NOT NULL COMMENT 'Título del menú',
                        `icon` varchar(100) DEFAULT NULL COMMENT 'Icono del menú',
                        `icon_type` varchar(20) DEFAULT 'bi' COMMENT 'Tipo de icono (bi, fas, etc.)',
                        `url` varchar(500) DEFAULT NULL COMMENT 'URL del menú',
                        `permission` varchar(255) DEFAULT NULL COMMENT 'Permiso requerido',
                        `order` int(11) DEFAULT 0 COMMENT 'Orden de visualización',
                        `is_active` tinyint(1) DEFAULT 1 COMMENT '1 = Activo, 0 = Inactivo',
                        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `plugin_id` (`plugin_id`),
                        KEY `parent_id` (`parent_id`),
                        KEY `is_active` (`is_active`),
                        CONSTRAINT `fk_plugin_menus_plugin`
                            FOREIGN KEY (`plugin_id`)
                            REFERENCES `superadmin_plugins` (`id`)
                            ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Menús agregados al panel por plugins'
                ");
                echo "✓ Tabla 'superadmin_plugin_menus' creada\n";
            }

            // ========== TABLA: superadmin_plugin_assets ==========
            echo "📝 Creando tabla 'superadmin_plugin_assets'...\n";

            $stmt = $pdo->query("SHOW TABLES LIKE 'superadmin_plugin_assets'");
            if ($stmt->fetch()) {
                echo "⚠ Tabla 'superadmin_plugin_assets' ya existe\n";
            } else {
                $pdo->exec("
                    CREATE TABLE `superadmin_plugin_assets` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `plugin_id` int(11) NOT NULL COMMENT 'ID del plugin',
                        `type` enum('css','js') NOT NULL COMMENT 'Tipo de asset',
                        `path` varchar(500) NOT NULL COMMENT 'Ruta del archivo',
                        `location` enum('header','footer') DEFAULT 'footer' COMMENT 'Dónde cargar el asset',
                        `priority` int(11) DEFAULT 10 COMMENT 'Prioridad de carga',
                        `is_active` tinyint(1) DEFAULT 1 COMMENT '1 = Activo, 0 = Inactivo',
                        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `plugin_id` (`plugin_id`),
                        KEY `type` (`type`),
                        KEY `is_active` (`is_active`),
                        CONSTRAINT `fk_plugin_assets_plugin`
                            FOREIGN KEY (`plugin_id`)
                            REFERENCES `superadmin_plugins` (`id`)
                            ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Assets CSS/JS de plugins'
                ");
                echo "✓ Tabla 'superadmin_plugin_assets' creada\n";
            }

            // ========== TABLA: superadmin_plugin_logs ==========
            echo "📝 Creando tabla 'superadmin_plugin_logs'...\n";

            $stmt = $pdo->query("SHOW TABLES LIKE 'superadmin_plugin_logs'");
            if ($stmt->fetch()) {
                echo "⚠ Tabla 'superadmin_plugin_logs' ya existe\n";
            } else {
                $pdo->exec("
                    CREATE TABLE `superadmin_plugin_logs` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `plugin_id` int(11) DEFAULT NULL COMMENT 'ID del plugin (NULL si es del sistema)',
                        `action` varchar(100) NOT NULL COMMENT 'Acción realizada',
                        `message` text NOT NULL COMMENT 'Mensaje del log',
                        `level` enum('info','warning','error','debug') DEFAULT 'info' COMMENT 'Nivel del log',
                        `data` text DEFAULT NULL COMMENT 'Datos adicionales (JSON)',
                        `user_id` int(11) DEFAULT NULL COMMENT 'ID del usuario que realizó la acción',
                        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `plugin_id` (`plugin_id`),
                        KEY `level` (`level`),
                        KEY `created_at` (`created_at`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Registro de actividad de plugins'
                ");
                echo "✓ Tabla 'superadmin_plugin_logs' creada\n";
            }

            // Crear índices adicionales
            echo "📝 Creando índices adicionales...\n";

            try {
                $pdo->exec("CREATE INDEX idx_active_plugins ON superadmin_plugins(is_active, is_installed)");
                $pdo->exec("CREATE INDEX idx_plugin_slug ON superadmin_plugins(slug, is_active)");
                echo "✓ Índices adicionales creados\n";
            } catch (Exception $e) {
                // Índices pueden ya existir
                echo "⚠ Algunos índices ya existen\n";
            }

            echo "\n";
            echo "════════════════════════════════════════════════════════════\n";
            echo " ✓ Migración completada exitosamente\n";
            echo "════════════════════════════════════════════════════════════\n\n";

            echo "📋 PRÓXIMOS PASOS:\n";
            echo "1. Crear directorio de plugins:\n";
            echo "   mkdir -p plugins/superadmin\n\n";
            echo "2. Acceder al gestor de plugins:\n";
            echo "   /musedock/plugins\n\n";
            echo "3. Documentación completa:\n";
            echo "   docs/SISTEMA_PLUGINS_SUPERADMIN.md\n\n";
            echo "4. Ejemplo de plugin:\n";
            echo "   plugins/superadmin/README.md\n\n";

        } catch (Exception $e) {
            echo "\n";
            echo "✗ Error en migración: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function down()
    {
        $pdo = Database::connect();

        echo "════════════════════════════════════════════════════════════\n";
        echo " ROLLBACK: Sistema de Plugins para Superadmin\n";
        echo "════════════════════════════════════════════════════════════\n\n";

        try {
            // Eliminar en orden inverso por foreign keys
            $tables = [
                'superadmin_plugin_logs',
                'superadmin_plugin_assets',
                'superadmin_plugin_menus',
                'superadmin_plugin_routes',
                'superadmin_plugin_hooks',
                'superadmin_plugins'
            ];

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

        } catch (Exception $e) {
            echo "\n";
            echo "✗ Error al revertir migración: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}
