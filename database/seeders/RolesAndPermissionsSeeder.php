<?php

namespace Screenart\Musedock\Database\Seeders;

use Screenart\Musedock\Database;

/**
 * Seeder para roles y permisos del sistema
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
        $this->seedPermissions();
        echo "    + Permisos base creados\n";
    }

    /**
     * Seed base permissions (global permissions without tenant_id)
     */
    private function seedPermissions(): void
    {
        $permissions = [
            // Apariencia
            ['slug' => 'appearance.themes', 'name' => 'Gestionar temas', 'description' => 'Cambiar y personalizar temas', 'category' => 'Apariencia', 'scope' => 'tenant'],
            ['slug' => 'appearance.menus', 'name' => 'Gestionar menús', 'description' => 'Crear y editar menús de navegación', 'category' => 'Apariencia', 'scope' => 'tenant'],

            // Avanzado
            ['slug' => 'advanced.ai', 'name' => 'Usar AI', 'description' => 'Acceder a funcionalidades de inteligencia artificial', 'category' => 'Avanzado', 'scope' => 'tenant'],
            ['slug' => 'advanced.cron', 'name' => 'Gestionar tareas programadas', 'description' => 'Ver y gestionar tareas cron del sistema', 'category' => 'Avanzado', 'scope' => 'tenant'],

            // Media
            ['slug' => 'media.manage', 'name' => 'Gestionar media avanzada', 'description' => 'Gestionar sliders, galerías y media compleja', 'category' => 'Media', 'scope' => 'tenant'],

            // Usuarios
            ['slug' => 'users.manage', 'name' => 'Gestionar usuarios', 'description' => 'Gestión completa de usuarios y roles', 'category' => 'Usuarios', 'scope' => 'tenant'],

            // Contenido - Páginas
            ['slug' => 'pages.view', 'name' => 'Ver páginas', 'description' => 'Ver listado de páginas', 'category' => 'Contenido', 'scope' => 'tenant'],
            ['slug' => 'pages.create', 'name' => 'Crear páginas', 'description' => 'Crear nuevas páginas', 'category' => 'Contenido', 'scope' => 'tenant'],
            ['slug' => 'pages.edit', 'name' => 'Editar páginas', 'description' => 'Editar páginas existentes', 'category' => 'Contenido', 'scope' => 'tenant'],
            ['slug' => 'pages.delete', 'name' => 'Eliminar páginas', 'description' => 'Eliminar páginas', 'category' => 'Contenido', 'scope' => 'tenant'],

            // Sistema
            ['slug' => 'modules.manage', 'name' => 'Gestionar módulos', 'description' => 'Instalar/desinstalar módulos', 'category' => 'Sistema', 'scope' => 'tenant'],
            ['slug' => 'logs.view', 'name' => 'Ver logs', 'description' => 'Ver registros del sistema', 'category' => 'Sistema', 'scope' => 'tenant'],

            // Configuración
            ['slug' => 'settings.view', 'name' => 'Ver configuración', 'description' => 'Ver configuración del sistema', 'category' => 'Configuración', 'scope' => 'tenant'],
            ['slug' => 'settings.edit', 'name' => 'Editar configuración', 'description' => 'Modificar configuración del sistema', 'category' => 'Configuración', 'scope' => 'tenant'],
            ['slug' => 'languages.manage', 'name' => 'Gestionar idiomas', 'description' => 'Agregar y configurar idiomas', 'category' => 'Configuración', 'scope' => 'tenant'],

            // Blog
            ['slug' => 'blog.view', 'name' => 'Ver Blog', 'description' => 'Ver listado de posts del blog', 'category' => 'Blog', 'scope' => 'global'],
            ['slug' => 'blog.create', 'name' => 'Crear Blog', 'description' => 'Crear nuevos posts del blog', 'category' => 'Blog', 'scope' => 'global'],
            ['slug' => 'blog.edit', 'name' => 'Editar Blog', 'description' => 'Editar posts del blog propios', 'category' => 'Blog', 'scope' => 'global'],
            ['slug' => 'blog.edit.all', 'name' => 'Editar todos los posts', 'description' => 'Editar cualquier post del blog', 'category' => 'Blog', 'scope' => 'global'],
            ['slug' => 'blog.delete', 'name' => 'Eliminar Blog', 'description' => 'Eliminar posts del blog', 'category' => 'Blog', 'scope' => 'global'],

            // Custom Forms
            ['slug' => 'custom_forms.view', 'name' => 'Ver formularios', 'description' => 'Ver listado de formularios', 'category' => 'Formularios', 'scope' => 'global'],
            ['slug' => 'custom_forms.create', 'name' => 'Crear formularios', 'description' => 'Crear nuevos formularios', 'category' => 'Formularios', 'scope' => 'global'],
            ['slug' => 'custom_forms.edit', 'name' => 'Editar formularios', 'description' => 'Editar formularios existentes', 'category' => 'Formularios', 'scope' => 'global'],
            ['slug' => 'custom_forms.delete', 'name' => 'Eliminar formularios', 'description' => 'Eliminar formularios', 'category' => 'Formularios', 'scope' => 'global'],
            ['slug' => 'custom_forms.submissions.view', 'name' => 'Ver envíos', 'description' => 'Ver envíos de formularios', 'category' => 'Formularios', 'scope' => 'global'],
            ['slug' => 'custom_forms.submissions.delete', 'name' => 'Eliminar envíos', 'description' => 'Eliminar envíos de formularios', 'category' => 'Formularios', 'scope' => 'global'],
            ['slug' => 'custom_forms.submissions.export', 'name' => 'Exportar envíos', 'description' => 'Exportar envíos de formularios', 'category' => 'Formularios', 'scope' => 'global'],

            // Image Gallery
            ['slug' => 'image_gallery.view', 'name' => 'Ver galerías', 'description' => 'Ver listado de galerías de imágenes', 'category' => 'Galerías', 'scope' => 'global'],
            ['slug' => 'image_gallery.create', 'name' => 'Crear galerías', 'description' => 'Crear nuevas galerías de imágenes', 'category' => 'Galerías', 'scope' => 'global'],
            ['slug' => 'image_gallery.edit', 'name' => 'Editar galerías', 'description' => 'Editar galerías de imágenes', 'category' => 'Galerías', 'scope' => 'global'],
            ['slug' => 'image_gallery.delete', 'name' => 'Eliminar galerías', 'description' => 'Eliminar galerías de imágenes', 'category' => 'Galerías', 'scope' => 'global'],

            // React Sliders
            ['slug' => 'react_sliders.view', 'name' => 'Ver sliders', 'description' => 'Ver listado de sliders', 'category' => 'Sliders', 'scope' => 'global'],
            ['slug' => 'react_sliders.create', 'name' => 'Crear sliders', 'description' => 'Crear nuevos sliders', 'category' => 'Sliders', 'scope' => 'global'],
            ['slug' => 'react_sliders.edit', 'name' => 'Editar sliders', 'description' => 'Editar sliders existentes', 'category' => 'Sliders', 'scope' => 'global'],
            ['slug' => 'react_sliders.delete', 'name' => 'Eliminar sliders', 'description' => 'Eliminar sliders', 'category' => 'Sliders', 'scope' => 'global'],

            // Tenants (Superadmin)
            ['slug' => 'tenants.manage', 'name' => 'Gestionar Tenants', 'description' => 'Gestión completa de tenants/sitios', 'category' => 'Superadmin', 'scope' => 'global'],

            // Soporte
            ['slug' => 'tickets.manage', 'name' => 'Gestionar tickets', 'description' => 'Gestión de tickets de soporte', 'category' => 'Soporte', 'scope' => 'global'],
        ];

        foreach ($permissions as $permission) {
            $this->insertPermissionIfNotExists($permission);
        }
    }

    /**
     * Insert permission if not exists (by slug)
     */
    private function insertPermissionIfNotExists(array $data): void
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM permissions WHERE slug = ? AND tenant_id IS NULL");
        $stmt->execute([$data['slug']]);

        if ($stmt->fetchColumn() == 0) {
            $stmt = $this->db->prepare("
                INSERT INTO permissions (slug, name, description, category, tenant_id, scope, created_at, updated_at)
                VALUES (?, ?, ?, ?, NULL, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $data['slug'],
                $data['name'],
                $data['description'],
                $data['category'],
                $data['scope']
            ]);
        }
    }

    /**
     * Create default roles for a tenant
     * This is called when a new tenant is created
     */
    public static function createRolesForTenant(int $tenantId): void
    {
        $db = Database::connect();

        $roles = [
            [
                'name' => 'admin',
                'description' => 'Administrador con acceso completo',
                'is_system' => 1
            ],
            [
                'name' => 'editor',
                'description' => 'Editor de contenido con permisos limitados',
                'is_system' => 1
            ],
            [
                'name' => 'viewer',
                'description' => 'Solo puede ver contenido',
                'is_system' => 1
            ]
        ];

        foreach ($roles as $role) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM roles WHERE name = ? AND tenant_id = ?");
            $stmt->execute([$role['name'], $tenantId]);

            if ($stmt->fetchColumn() == 0) {
                $stmt = $db->prepare("
                    INSERT INTO roles (name, description, tenant_id, is_system, created_at, updated_at)
                    VALUES (?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $role['name'],
                    $role['description'],
                    $tenantId,
                    $role['is_system']
                ]);
            }
        }
    }
}
