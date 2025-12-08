<?php

namespace Screenart\Musedock\Database\Seeders;

use Screenart\Musedock\Database;

/**
 * Seeder para menús del panel de administración
 */
class AdminMenuSeeder
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function run(): void
    {
        $menus = [
            // Dashboard
            [
                'name' => 'Dashboard',
                'slug' => 'dashboard',
                'icon' => 'bi-speedometer2',
                'route' => '/admin/dashboard',
                'parent_id' => null,
                'order' => 1,
                'permission' => 'dashboard.view',
                'is_active' => 1
            ],
            // Contenido
            [
                'name' => 'Contenido',
                'slug' => 'content',
                'icon' => 'bi-file-earmark-text',
                'route' => null,
                'parent_id' => null,
                'order' => 2,
                'permission' => null,
                'is_active' => 1
            ],
            [
                'name' => 'Páginas',
                'slug' => 'pages',
                'icon' => 'bi-file-text',
                'route' => '/admin/pages',
                'parent_id' => 2, // Contenido
                'order' => 1,
                'permission' => 'pages.view',
                'is_active' => 1
            ],
            [
                'name' => 'Blog',
                'slug' => 'blog',
                'icon' => 'bi-journal-richtext',
                'route' => '/admin/blog',
                'parent_id' => 2, // Contenido
                'order' => 2,
                'permission' => 'blog.view',
                'is_active' => 1
            ],
            // Medios
            [
                'name' => 'Medios',
                'slug' => 'media',
                'icon' => 'bi-images',
                'route' => '/admin/media',
                'parent_id' => null,
                'order' => 3,
                'permission' => 'media.view',
                'is_active' => 1
            ],
            // Apariencia
            [
                'name' => 'Apariencia',
                'slug' => 'appearance',
                'icon' => 'bi-palette',
                'route' => null,
                'parent_id' => null,
                'order' => 4,
                'permission' => null,
                'is_active' => 1
            ],
            [
                'name' => 'Temas',
                'slug' => 'themes',
                'icon' => 'bi-brush',
                'route' => '/admin/themes',
                'parent_id' => 6, // Apariencia
                'order' => 1,
                'permission' => 'themes.view',
                'is_active' => 1
            ],
            [
                'name' => 'Menús',
                'slug' => 'menus',
                'icon' => 'bi-list',
                'route' => '/admin/menus',
                'parent_id' => 6, // Apariencia
                'order' => 2,
                'permission' => 'menus.view',
                'is_active' => 1
            ],
            // Sistema
            [
                'name' => 'Sistema',
                'slug' => 'system',
                'icon' => 'bi-gear',
                'route' => null,
                'parent_id' => null,
                'order' => 10,
                'permission' => null,
                'is_active' => 1
            ],
            [
                'name' => 'Módulos',
                'slug' => 'modules',
                'icon' => 'bi-puzzle',
                'route' => '/admin/modules',
                'parent_id' => 9, // Sistema
                'order' => 1,
                'permission' => 'modules.view',
                'is_active' => 1
            ],
            [
                'name' => 'Configuración',
                'slug' => 'settings',
                'icon' => 'bi-sliders',
                'route' => '/admin/settings',
                'parent_id' => 9, // Sistema
                'order' => 2,
                'permission' => 'settings.view',
                'is_active' => 1
            ],
        ];

        // Limpiar menús existentes primero si se desea un fresh seed
        // $this->db->exec("DELETE FROM admin_menus");

        foreach ($menus as $menu) {
            $this->insertIfNotExists($menu);
        }
    }

    private function insertIfNotExists(array $data): void
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM admin_menus WHERE slug = ?");
        $stmt->execute([$data['slug']]);

        if ($stmt->fetchColumn() == 0) {
            $columns = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $stmt = $this->db->prepare("INSERT INTO admin_menus ({$columns}) VALUES ({$placeholders})");
            $stmt->execute(array_values($data));
        }
    }
}
