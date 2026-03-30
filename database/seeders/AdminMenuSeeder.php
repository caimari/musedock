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
        // NOTE: This seeder creates tenant-only menus.
        // These menus use show_in_superadmin=0 to prevent them from appearing in /musedock/
        // The SuperadminMenuSeeder creates the superadmin menus with {admin_path} placeholders.

        $menus = [
            // Dashboard - tenant only (superadmin has its own hardcoded Dashboard)
            [
                'title' => 'Dashboard',
                'slug' => 'tenant-dashboard',  // Different slug to avoid conflict with superadmin
                'icon' => 'bi-speedometer2',
                'url' => '{admin_path}/dashboard',
                'parent_id' => null,
                'order_position' => 1,
                'permission' => null,
                'is_active' => 1,
                'show_in_superadmin' => 0,
                'show_in_tenant' => 1
            ],
            // Contenido
            [
                'title' => 'Contenido',
                'slug' => 'tenant-content',
                'icon' => 'bi-file-earmark-text',
                'url' => '#',
                'parent_id' => null,
                'order_position' => 2,
                'permission' => null,
                'is_active' => 1,
                'show_in_superadmin' => 0,
                'show_in_tenant' => 1
            ],
            [
                'title' => 'Páginas',
                'slug' => 'tenant-pages',
                'icon' => 'bi-file-text',
                'url' => '{admin_path}/pages',
                'parent_id' => 'tenant-content',
                'order_position' => 1,
                'permission' => 'pages.view',
                'is_active' => 1,
                'show_in_superadmin' => 0,
                'show_in_tenant' => 1
            ],
            [
                'title' => 'Blog',
                'slug' => 'tenant-blog',
                'icon' => 'bi-journal-richtext',
                'url' => '{admin_path}/blog',
                'parent_id' => 'tenant-content',
                'order_position' => 2,
                'permission' => 'blog.view',
                'is_active' => 1,
                'show_in_superadmin' => 0,
                'show_in_tenant' => 1
            ],
            // Medios - tenant only (superadmin uses media-manager slug)
            [
                'title' => 'Medios',
                'slug' => 'tenant-media',
                'icon' => 'bi-images',
                'url' => '{admin_path}/media',
                'parent_id' => null,
                'order_position' => 3,
                'permission' => 'media.manage',
                'is_active' => 1,
                'show_in_superadmin' => 0,
                'show_in_tenant' => 1
            ],
            // Apariencia
            [
                'title' => 'Apariencia',
                'slug' => 'tenant-appearance',
                'icon' => 'bi-palette',
                'url' => '#',
                'parent_id' => null,
                'order_position' => 4,
                'permission' => null,
                'is_active' => 1,
                'show_in_superadmin' => 0,
                'show_in_tenant' => 1
            ],
            [
                'title' => 'Temas',
                'slug' => 'tenant-themes',
                'icon' => 'bi-brush',
                'url' => '{admin_path}/themes',
                'parent_id' => 'tenant-appearance',
                'order_position' => 1,
                'permission' => 'appearance.themes',
                'is_active' => 1,
                'show_in_superadmin' => 0,
                'show_in_tenant' => 1
            ],
            [
                'title' => 'Menús',
                'slug' => 'tenant-menus',
                'icon' => 'bi-list',
                'url' => '{admin_path}/menus',
                'parent_id' => 'tenant-appearance',
                'order_position' => 2,
                'permission' => 'appearance.menus',
                'is_active' => 1,
                'show_in_superadmin' => 0,
                'show_in_tenant' => 1
            ],
            // Sistema
            [
                'title' => 'Sistema',
                'slug' => 'tenant-system',
                'icon' => 'bi-gear',
                'url' => '#',
                'parent_id' => null,
                'order_position' => 10,
                'permission' => null,
                'is_active' => 1,
                'show_in_superadmin' => 0,
                'show_in_tenant' => 1
            ],
            [
                'title' => 'Módulos',
                'slug' => 'tenant-modules',
                'icon' => 'bi-puzzle',
                'url' => '{admin_path}/modules',
                'parent_id' => 'tenant-system',
                'order_position' => 1,
                'permission' => 'modules.manage',
                'is_active' => 1,
                'show_in_superadmin' => 0,
                'show_in_tenant' => 1
            ],
            [
                'title' => 'Configuración',
                'slug' => 'tenant-settings',
                'icon' => 'bi-sliders',
                'url' => '{admin_path}/settings',
                'parent_id' => 'tenant-system',
                'order_position' => 2,
                'permission' => 'settings.view',
                'is_active' => 1,
                'show_in_superadmin' => 0,
                'show_in_tenant' => 1
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

        // Column names without quotes (compatible with both MySQL and PostgreSQL)
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $stmt = $this->db->prepare("INSERT INTO admin_menus ({$columns}) VALUES ({$placeholders})");
        $stmt->execute(array_values($data));

        return (int)$this->db->lastInsertId();
    }
}
