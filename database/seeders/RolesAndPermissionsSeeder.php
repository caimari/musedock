<?php

namespace Screenart\Musedock\Database\Seeders;

use Screenart\Musedock\Database;

/**
 * Seeder para roles y permisos iniciales
 */
class RolesAndPermissionsSeeder
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function run(): void
    {
        // Crear permisos base
        $permissions = [
            // Dashboard
            ['slug' => 'dashboard.view', 'name' => 'Ver Dashboard', 'module' => 'core', 'description' => 'Acceso al panel principal'],

            // Usuarios
            ['slug' => 'users.view', 'name' => 'Ver Usuarios', 'module' => 'core', 'description' => 'Ver lista de usuarios'],
            ['slug' => 'users.create', 'name' => 'Crear Usuarios', 'module' => 'core', 'description' => 'Crear nuevos usuarios'],
            ['slug' => 'users.edit', 'name' => 'Editar Usuarios', 'module' => 'core', 'description' => 'Editar usuarios existentes'],
            ['slug' => 'users.delete', 'name' => 'Eliminar Usuarios', 'module' => 'core', 'description' => 'Eliminar usuarios'],

            // Páginas
            ['slug' => 'pages.view', 'name' => 'Ver Páginas', 'module' => 'core', 'description' => 'Ver lista de páginas'],
            ['slug' => 'pages.create', 'name' => 'Crear Páginas', 'module' => 'core', 'description' => 'Crear nuevas páginas'],
            ['slug' => 'pages.edit', 'name' => 'Editar Páginas', 'module' => 'core', 'description' => 'Editar páginas existentes'],
            ['slug' => 'pages.delete', 'name' => 'Eliminar Páginas', 'module' => 'core', 'description' => 'Eliminar páginas'],
            ['slug' => 'pages.publish', 'name' => 'Publicar Páginas', 'module' => 'core', 'description' => 'Publicar/despublicar páginas'],

            // Medios
            ['slug' => 'media.view', 'name' => 'Ver Medios', 'module' => 'media-manager', 'description' => 'Ver archivos multimedia'],
            ['slug' => 'media.upload', 'name' => 'Subir Medios', 'module' => 'media-manager', 'description' => 'Subir archivos multimedia'],
            ['slug' => 'media.delete', 'name' => 'Eliminar Medios', 'module' => 'media-manager', 'description' => 'Eliminar archivos multimedia'],

            // Blog
            ['slug' => 'blog.view', 'name' => 'Ver Blog', 'module' => 'blog', 'description' => 'Ver posts del blog'],
            ['slug' => 'blog.create', 'name' => 'Crear Posts', 'module' => 'blog', 'description' => 'Crear nuevos posts'],
            ['slug' => 'blog.edit', 'name' => 'Editar Posts', 'module' => 'blog', 'description' => 'Editar posts existentes'],
            ['slug' => 'blog.delete', 'name' => 'Eliminar Posts', 'module' => 'blog', 'description' => 'Eliminar posts'],
            ['slug' => 'blog.publish', 'name' => 'Publicar Posts', 'module' => 'blog', 'description' => 'Publicar/despublicar posts'],

            // Menús
            ['slug' => 'menus.view', 'name' => 'Ver Menús', 'module' => 'core', 'description' => 'Ver menús de navegación'],
            ['slug' => 'menus.edit', 'name' => 'Editar Menús', 'module' => 'core', 'description' => 'Editar menús de navegación'],

            // Configuración
            ['slug' => 'settings.view', 'name' => 'Ver Configuración', 'module' => 'core', 'description' => 'Ver configuración del sitio'],
            ['slug' => 'settings.edit', 'name' => 'Editar Configuración', 'module' => 'core', 'description' => 'Modificar configuración del sitio'],

            // Módulos
            ['slug' => 'modules.view', 'name' => 'Ver Módulos', 'module' => 'core', 'description' => 'Ver módulos instalados'],
            ['slug' => 'modules.manage', 'name' => 'Gestionar Módulos', 'module' => 'core', 'description' => 'Instalar/desinstalar módulos'],

            // Temas
            ['slug' => 'themes.view', 'name' => 'Ver Temas', 'module' => 'core', 'description' => 'Ver temas disponibles'],
            ['slug' => 'themes.manage', 'name' => 'Gestionar Temas', 'module' => 'core', 'description' => 'Cambiar/configurar temas'],
        ];

        foreach ($permissions as $permission) {
            $this->insertIfNotExists('permissions', $permission, 'slug');
        }

        // Crear roles base
        $roles = [
            [
                'name' => 'superadmin',
                'display_name' => 'Super Administrador',
                'description' => 'Acceso completo a todo el sistema',
                'is_system' => 1
            ],
            [
                'name' => 'admin',
                'display_name' => 'Administrador',
                'description' => 'Administrador del sitio con acceso completo',
                'is_system' => 1
            ],
            [
                'name' => 'editor',
                'display_name' => 'Editor',
                'description' => 'Puede crear y editar contenido',
                'is_system' => 0
            ],
            [
                'name' => 'author',
                'display_name' => 'Autor',
                'description' => 'Puede crear contenido propio',
                'is_system' => 0
            ],
            [
                'name' => 'viewer',
                'display_name' => 'Visualizador',
                'description' => 'Solo puede ver contenido',
                'is_system' => 0
            ],
        ];

        foreach ($roles as $role) {
            $this->insertIfNotExists('roles', $role, 'name');
        }
    }

    private function insertIfNotExists(string $table, array $data, string $uniqueKey): void
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$uniqueKey} = ?");
        $stmt->execute([$data[$uniqueKey]]);

        if ($stmt->fetchColumn() == 0) {
            $columns = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $stmt = $this->db->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})");
            $stmt->execute(array_values($data));
        }
    }
}
