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
            ['slug' => 'appearance.themes', 'name' => 'Gestionar temas', 'description' => 'Cambiar, activar y personalizar temas visuales del sitio', 'category' => 'Apariencia', 'scope' => 'tenant'],
            ['slug' => 'appearance.menus', 'name' => 'Gestionar menús', 'description' => 'Crear, editar y organizar menús de navegación del sitio', 'category' => 'Apariencia', 'scope' => 'tenant'],

            // Avanzado
            ['slug' => 'advanced.ai', 'name' => 'Usar AI', 'description' => 'Acceder a funcionalidades de inteligencia artificial para generación de contenido', 'category' => 'Avanzado', 'scope' => 'tenant'],
            ['slug' => 'advanced.cron', 'name' => 'Gestionar tareas programadas', 'description' => 'Ver estado y gestionar tareas cron automáticas del sistema', 'category' => 'Avanzado', 'scope' => 'tenant'],

            // Media
            ['slug' => 'media.manage', 'name' => 'Gestionar media avanzada', 'description' => 'Gestión completa de archivos multimedia, sliders y galerías', 'category' => 'Media', 'scope' => 'tenant'],

            // Usuarios
            ['slug' => 'users.manage', 'name' => 'Gestionar usuarios', 'description' => 'Gestión completa de usuarios, roles y asignación de permisos', 'category' => 'Usuarios', 'scope' => 'tenant'],

            // Contenido - Páginas
            ['slug' => 'pages.view', 'name' => 'Ver páginas', 'description' => 'Ver y listar todas las páginas del sitio', 'category' => 'Contenido', 'scope' => 'tenant'],
            ['slug' => 'pages.create', 'name' => 'Crear páginas', 'description' => 'Crear nuevas páginas de contenido estático', 'category' => 'Contenido', 'scope' => 'tenant'],
            ['slug' => 'pages.edit', 'name' => 'Editar páginas', 'description' => 'Editar contenido y configuración de páginas existentes', 'category' => 'Contenido', 'scope' => 'tenant'],
            ['slug' => 'pages.delete', 'name' => 'Eliminar páginas', 'description' => 'Eliminar páginas del sitio (mover a papelera o eliminar permanentemente)', 'category' => 'Contenido', 'scope' => 'tenant'],

            // Sistema
            ['slug' => 'modules.manage', 'name' => 'Gestionar módulos', 'description' => 'Instalar, desinstalar y configurar módulos del sistema', 'category' => 'Sistema', 'scope' => 'tenant'],
            ['slug' => 'logs.view', 'name' => 'Ver logs', 'description' => 'Ver registros de actividad y errores del sistema', 'category' => 'Sistema', 'scope' => 'tenant'],

            // Configuración
            ['slug' => 'settings.view', 'name' => 'Ver configuración', 'description' => 'Ver ajustes y configuración general del sistema', 'category' => 'Configuración', 'scope' => 'tenant'],
            ['slug' => 'settings.edit', 'name' => 'Editar configuración', 'description' => 'Modificar ajustes generales, SEO, email y almacenamiento', 'category' => 'Configuración', 'scope' => 'tenant'],
            ['slug' => 'languages.manage', 'name' => 'Gestionar idiomas', 'description' => 'Agregar, editar y configurar idiomas disponibles en el sitio', 'category' => 'Configuración', 'scope' => 'tenant'],

            // Blog
            ['slug' => 'blog.view', 'name' => 'Ver Blog', 'description' => 'Ver y listar publicaciones del blog', 'category' => 'Blog', 'scope' => 'global'],
            ['slug' => 'blog.create', 'name' => 'Crear Blog', 'description' => 'Crear nuevas publicaciones en el blog', 'category' => 'Blog', 'scope' => 'global'],
            ['slug' => 'blog.edit', 'name' => 'Editar Blog', 'description' => 'Editar publicaciones propias del blog', 'category' => 'Blog', 'scope' => 'global'],
            ['slug' => 'blog.edit.all', 'name' => 'Editar todos los posts', 'description' => 'Editar cualquier publicación del blog, incluyendo las de otros autores', 'category' => 'Blog', 'scope' => 'global'],
            ['slug' => 'blog.delete', 'name' => 'Eliminar Blog', 'description' => 'Eliminar publicaciones del blog', 'category' => 'Blog', 'scope' => 'global'],

            // Custom Forms
            ['slug' => 'custom_forms.view', 'name' => 'Ver formularios', 'description' => 'Ver y listar formularios personalizados', 'category' => 'Formularios', 'scope' => 'global'],
            ['slug' => 'custom_forms.create', 'name' => 'Crear formularios', 'description' => 'Crear nuevos formularios personalizados con campos dinámicos', 'category' => 'Formularios', 'scope' => 'global'],
            ['slug' => 'custom_forms.edit', 'name' => 'Editar formularios', 'description' => 'Editar estructura y configuración de formularios existentes', 'category' => 'Formularios', 'scope' => 'global'],
            ['slug' => 'custom_forms.delete', 'name' => 'Eliminar formularios', 'description' => 'Eliminar formularios personalizados y sus datos', 'category' => 'Formularios', 'scope' => 'global'],
            ['slug' => 'custom_forms.submissions.view', 'name' => 'Ver envíos', 'description' => 'Ver los envíos recibidos de formularios personalizados', 'category' => 'Formularios', 'scope' => 'global'],
            ['slug' => 'custom_forms.submissions.delete', 'name' => 'Eliminar envíos', 'description' => 'Eliminar envíos de formularios personalizados', 'category' => 'Formularios', 'scope' => 'global'],
            ['slug' => 'custom_forms.submissions.export', 'name' => 'Exportar envíos', 'description' => 'Exportar envíos de formularios a CSV/Excel', 'category' => 'Formularios', 'scope' => 'global'],

            // Image Gallery
            ['slug' => 'image_gallery.view', 'name' => 'Ver galerías', 'description' => 'Ver y listar galerías de imágenes', 'category' => 'Galerías', 'scope' => 'global'],
            ['slug' => 'image_gallery.create', 'name' => 'Crear galerías', 'description' => 'Crear nuevas galerías de imágenes', 'category' => 'Galerías', 'scope' => 'global'],
            ['slug' => 'image_gallery.edit', 'name' => 'Editar galerías', 'description' => 'Editar galerías de imágenes y sus configuraciones', 'category' => 'Galerías', 'scope' => 'global'],
            ['slug' => 'image_gallery.delete', 'name' => 'Eliminar galerías', 'description' => 'Eliminar galerías de imágenes', 'category' => 'Galerías', 'scope' => 'global'],

            // React Sliders
            ['slug' => 'react_sliders.view', 'name' => 'Ver sliders', 'description' => 'Ver y listar sliders interactivos', 'category' => 'Sliders', 'scope' => 'global'],
            ['slug' => 'react_sliders.create', 'name' => 'Crear sliders', 'description' => 'Crear nuevos sliders interactivos', 'category' => 'Sliders', 'scope' => 'global'],
            ['slug' => 'react_sliders.edit', 'name' => 'Editar sliders', 'description' => 'Editar configuración y contenido de sliders existentes', 'category' => 'Sliders', 'scope' => 'global'],
            ['slug' => 'react_sliders.delete', 'name' => 'Eliminar sliders', 'description' => 'Eliminar sliders interactivos', 'category' => 'Sliders', 'scope' => 'global'],

            // Tenants (Superadmin)
            ['slug' => 'tenants.manage', 'name' => 'Gestionar Tenants', 'description' => 'Gestión completa de inquilinos/organizaciones del sistema', 'category' => 'Superadmin', 'scope' => 'global'],

            // Soporte
            ['slug' => 'tickets.manage', 'name' => 'Gestionar tickets', 'description' => 'Gestión completa de tickets de soporte', 'category' => 'Soporte', 'scope' => 'global'],
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
