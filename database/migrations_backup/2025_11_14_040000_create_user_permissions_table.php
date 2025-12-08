<?php

/**
 * Migración: Sistema de permisos directos por usuario
 * Fecha: 2025-11-14
 *
 * Crea tabla para asignar permisos directamente a usuarios
 * sin necesidad de roles intermedios.
 *
 * Los permisos directos SOBRESCRIBEN los permisos heredados de roles.
 */

use Screenart\Musedock\Database;

class CreateUserPermissionsTable_2025_11_14_040000
{
    public function up()
    {
        $pdo = Database::connect();

        echo "════════════════════════════════════════════════════════════\n";
        echo " MIGRACIÓN: Sistema de Permisos Directos\n";
        echo "════════════════════════════════════════════════════════════\n\n";

        try {
            // PASO 1: Agregar columna 'slug' a la tabla permissions si no existe
            echo "Verificando columna 'slug' en tabla permissions...\n";

            $stmt = $pdo->query("SHOW COLUMNS FROM permissions LIKE 'slug'");
            $slugExists = $stmt->fetch();

            if (!$slugExists) {
                echo "  → Agregando columna 'slug'...\n";
                $pdo->exec("ALTER TABLE permissions ADD COLUMN slug VARCHAR(100) AFTER id");

                // Poblar slug basándose en name (convertir a slug format)
                echo "  → Poblando valores de slug...\n";
                $permissions = $pdo->query("SELECT id, name FROM permissions")->fetchAll(PDO::FETCH_ASSOC);
                $updateStmt = $pdo->prepare("UPDATE permissions SET slug = ? WHERE id = ?");

                foreach ($permissions as $permission) {
                    $slug = strtolower(str_replace(' ', '.', trim($permission['name'])));
                    $slug = preg_replace('/[^a-z0-9.]/', '', $slug);
                    $updateStmt->execute([$slug, $permission['id']]);
                }

                echo "  ✓ Columna 'slug' agregada y poblada\n\n";
            } else {
                echo "  ✓ Columna 'slug' ya existe\n\n";
            }

            // PASO 2: Crear tabla user_permissions (permisos directos por usuario)
            echo "Creando tabla user_permissions...\n";

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS user_permissions (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    permission_slug VARCHAR(100) NOT NULL,
                    tenant_id INT NULL,
                    granted_by INT NULL COMMENT 'ID del admin que otorgó el permiso',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                    UNIQUE KEY unique_user_permission (user_id, permission_slug, tenant_id),
                    INDEX idx_user_id (user_id),
                    INDEX idx_permission_slug (permission_slug),
                    INDEX idx_tenant_id (tenant_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            echo "✓ Tabla 'user_permissions' creada exitosamente\n\n";

            // PASO 3: Insertar permisos base del sistema
            echo "Insertando permisos base del sistema...\n";

            $basePermissions = [
                // PÁGINAS
                ['slug' => 'pages.view', 'name' => 'Ver páginas', 'description' => 'Ver listado y detalle de páginas', 'category' => 'Páginas'],
                ['slug' => 'pages.create', 'name' => 'Crear páginas', 'description' => 'Crear nuevas páginas', 'category' => 'Páginas'],
                ['slug' => 'pages.edit', 'name' => 'Editar páginas', 'description' => 'Editar cualquier página', 'category' => 'Páginas'],
                ['slug' => 'pages.delete', 'name' => 'Eliminar páginas', 'description' => 'Mover páginas a la papelera', 'category' => 'Páginas'],

                // BLOG
                ['slug' => 'blog.view', 'name' => 'Ver posts', 'description' => 'Ver listado y detalle de posts del blog', 'category' => 'Blog'],
                ['slug' => 'blog.create', 'name' => 'Crear posts', 'description' => 'Crear nuevos posts', 'category' => 'Blog'],
                ['slug' => 'blog.edit.all', 'name' => 'Editar todos los posts', 'description' => 'Editar cualquier post del blog', 'category' => 'Blog'],
                ['slug' => 'blog.edit.own', 'name' => 'Editar solo sus posts', 'description' => 'Editar solo los posts propios', 'category' => 'Blog'],
                ['slug' => 'blog.delete', 'name' => 'Eliminar posts', 'description' => 'Mover posts a la papelera', 'category' => 'Blog'],

                // MEDIA
                ['slug' => 'media.view', 'name' => 'Ver media', 'description' => 'Ver biblioteca de medios', 'category' => 'Media'],
                ['slug' => 'media.upload', 'name' => 'Subir archivos', 'description' => 'Subir imágenes y archivos', 'category' => 'Media'],
                ['slug' => 'media.delete', 'name' => 'Eliminar archivos', 'description' => 'Eliminar archivos de la biblioteca', 'category' => 'Media'],

                // USUARIOS (solo para tenant admin)
                ['slug' => 'users.view', 'name' => 'Ver usuarios', 'description' => 'Ver listado de usuarios del tenant', 'category' => 'Usuarios'],
                ['slug' => 'users.create', 'name' => 'Crear usuarios', 'description' => 'Invitar nuevos usuarios', 'category' => 'Usuarios'],
                ['slug' => 'users.edit', 'name' => 'Editar usuarios', 'description' => 'Editar información de usuarios', 'category' => 'Usuarios'],
                ['slug' => 'users.delete', 'name' => 'Eliminar usuarios', 'description' => 'Eliminar usuarios del sistema', 'category' => 'Usuarios'],

                // CONFIGURACIÓN
                ['slug' => 'settings.view', 'name' => 'Ver configuración', 'description' => 'Ver configuración del sitio', 'category' => 'Configuración'],
                ['slug' => 'settings.edit', 'name' => 'Editar configuración', 'description' => 'Modificar configuración del sitio', 'category' => 'Configuración'],

                // APARIENCIA
                ['slug' => 'appearance.themes', 'name' => 'Gestionar temas', 'description' => 'Cambiar y personalizar temas', 'category' => 'Apariencia'],
                ['slug' => 'appearance.menus', 'name' => 'Gestionar menús', 'description' => 'Crear y editar menús de navegación', 'category' => 'Apariencia'],
            ];

            $stmt = $pdo->prepare("
                INSERT IGNORE INTO permissions (slug, name, description, category, tenant_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, NULL, NOW(), NOW())
            ");

            $inserted = 0;
            foreach ($basePermissions as $perm) {
                try {
                    $result = $stmt->execute([
                        $perm['slug'],
                        $perm['name'],
                        $perm['description'],
                        $perm['category']
                    ]);
                    if ($result && $stmt->rowCount() > 0) {
                        $inserted++;
                    }
                } catch (\Exception $e) {
                    // Ignorar duplicados
                    if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                        throw $e;
                    }
                }
            }

            echo "✓ {$inserted} permisos base insertados (algunos pueden haber existido ya)\n\n";

            echo "════════════════════════════════════════════════════════════\n";
            echo " ✓ Migración completada exitosamente\n";
            echo "════════════════════════════════════════════════════════════\n\n";

            echo "📋 PERMISOS DISPONIBLES:\n";
            echo "   • Páginas: view, create, edit, delete\n";
            echo "   • Blog: view, create, edit.all, edit.own, delete\n";
            echo "   • Media: view, upload, delete\n";
            echo "   • Usuarios: view, create, edit, delete\n";
            echo "   • Configuración: view, edit\n";
            echo "   • Apariencia: themes, menus\n\n";

        } catch (Exception $e) {
            echo "\n✗ Error: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function down()
    {
        $pdo = Database::connect();

        echo "════════════════════════════════════════════════════════════\n";
        echo " ROLLBACK: Eliminar sistema de permisos directos\n";
        echo "════════════════════════════════════════════════════════════\n\n";

        try {
            $pdo->exec("DROP TABLE IF EXISTS user_permissions");
            echo "✓ Tabla 'user_permissions' eliminada\n\n";

            echo "════════════════════════════════════════════════════════════\n";
            echo " ✓ Rollback completado\n";
            echo "════════════════════════════════════════════════════════════\n\n";

        } catch (Exception $e) {
            echo "\n✗ Error: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}
