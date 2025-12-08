<?php

namespace Screenart\Musedock\Database\Seeders;

use Screenart\Musedock\Database;

/**
 * Seeder para menús del panel de administración de tenants
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
                'title' => 'Dashboard',
                'slug' => 'dashboard',
                'icon' => 'bi-speedometer2',
                'url' => '/admin/dashboard',
                'parent_id' => null,
                'order_position' => 1,
                'permission' => null,
                'is_active' => 1
            ],
            // Contenido
            [
                'title' => 'Contenido',
                'slug' => 'content',
                'icon' => 'bi-file-earmark-text',
                'url' => '#',
                'parent_id' => null,
                'order_position' => 2,
                'permission' => null,
                'is_active' => 1
            ],
            [
                'title' => 'Páginas',
                'slug' => 'pages',
                'icon' => 'bi-file-text',
                'url' => '/admin/pages',
                'parent_id' => 'content',
                'order_position' => 1,
                'permission' => 'pages.view',
                'is_active' => 1
            ],
            [
                'title' => 'Blog',
                'slug' => 'blog',
                'icon' => 'bi-journal-richtext',
                'url' => '/admin/blog',
                'parent_id' => 'content',
                'order_position' => 2,
                'permission' => 'blog.view',
                'is_active' => 1
            ],
            // Medios
            [
                'title' => 'Medios',
                'slug' => 'media',
                'icon' => 'bi-images',
                'url' => '/admin/media',
                'parent_id' => null,
                'order_position' => 3,
                'permission' => 'media.manage',
                'is_active' => 1
            ],
            // Apariencia
            [
                'title' => 'Apariencia',
                'slug' => 'appearance',
                'icon' => 'bi-palette',
                'url' => '#',
                'parent_id' => null,
                'order_position' => 4,
                'permission' => null,
                'is_active' => 1
            ],
            [
                'title' => 'Temas',
                'slug' => 'themes',
                'icon' => 'bi-brush',
                'url' => '/admin/themes',
                'parent_id' => 'appearance',
                'order_position' => 1,
                'permission' => 'appearance.themes',
                'is_active' => 1
            ],
            [
                'title' => 'Menús',
                'slug' => 'menus',
                'icon' => 'bi-list',
                'url' => '/admin/menus',
                'parent_id' => 'appearance',
                'order_position' => 2,
                'permission' => 'appearance.menus',
                'is_active' => 1
            ],
            // Sistema
            [
                'title' => 'Sistema',
                'slug' => 'system',
                'icon' => 'bi-gear',
                'url' => '#',
                'parent_id' => null,
                'order_position' => 10,
                'permission' => null,
                'is_active' => 1
            ],
            [
                'title' => 'Módulos',
                'slug' => 'modules',
                'icon' => 'bi-puzzle',
                'url' => '/admin/modules',
                'parent_id' => 'system',
                'order_position' => 1,
                'permission' => 'modules.manage',
                'is_active' => 1
            ],
            [
                'title' => 'Configuración',
                'slug' => 'settings',
                'icon' => 'bi-sliders',
                'url' => '/admin/settings',
                'parent_id' => 'system',
                'order_position' => 2,
                'permission' => 'settings.view',
                'is_active' => 1
            ],
        ];

        // Insert menus in order (parents first)
        $insertedIds = [];

        foreach ($menus as $menu) {
            $parentSlug = $menu['parent_id'];

            // If parent_id is a slug reference, resolve it
            if (is_string($parentSlug) && isset($insertedIds[$parentSlug])) {
                $menu['parent_id'] = $insertedIds[$parentSlug];
            } elseif (is_string($parentSlug)) {
                // Try to find parent by slug
                $stmt = $this->db->prepare("SELECT id FROM admin_menus WHERE slug = ?");
                $stmt->execute([$parentSlug]);
                $parentId = $stmt->fetchColumn();
                $menu['parent_id'] = $parentId ?: null;
            }

            $id = $this->insertIfNotExists($menu);
            if ($id) {
                $insertedIds[$menu['slug']] = $id;
            }
        }

        echo "    + Menús de administración creados\n";
    }

    private function insertIfNotExists(array $data): ?int
    {
        $stmt = $this->db->prepare("SELECT id FROM admin_menus WHERE slug = ?");
        $stmt->execute([$data['slug']]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            return (int)$existing;
        }

        // Escape column names with backticks
        $columns = implode(', ', array_map(fn($col) => "`{$col}`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $stmt = $this->db->prepare("INSERT INTO admin_menus ({$columns}) VALUES ({$placeholders})");
        $stmt->execute(array_values($data));

        return (int)$this->db->lastInsertId();
    }
}
