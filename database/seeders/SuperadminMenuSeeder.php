<?php

namespace Screenart\Musedock\Database\Seeders;

use Screenart\Musedock\Database;

/**
 * Seeder para menús del panel de superadmin (/musedock/)
 * Estos son los menús que aparecen en el sidebar del panel de administración global
 */
class SuperadminMenuSeeder
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function run(): void
    {
        // Menús principales (sin parent_id)
        $mainMenus = [
            [
                'title' => 'Páginas',
                'slug' => 'pages',
                'url' => '{admin_path}/pages',
                'icon' => 'bi-file-text',
                'icon_type' => 'bi',
                'order_position' => 1,
                'is_active' => 1
            ],
            [
                'title' => 'Blog',
                'slug' => 'blog',
                'url' => '{admin_path}/blog',
                'icon' => 'bi-book',
                'icon_type' => 'bi',
                'order_position' => 2,
                'is_active' => 1
            ],
            [
                'title' => 'Medios',
                'slug' => 'media-manager',
                'url' => '{admin_path}/media',
                'icon' => 'bi-images',
                'icon_type' => 'bi',
                'order_position' => 3,
                'is_active' => 1
            ],
            [
                'title' => 'Apariencia',
                'slug' => 'appearance',
                'url' => '#',
                'icon' => 'bi-palette',
                'icon_type' => 'bi',
                'order_position' => 4,
                'is_active' => 1
            ],
            [
                'title' => 'Gestión de Usuarios',
                'slug' => 'user_management',
                'url' => '#',
                'icon' => 'bi-people',
                'icon_type' => 'bi',
                'order_position' => 5,
                'is_active' => 1
            ],
            [
                'title' => 'Ajustes',
                'slug' => 'settings',
                'url' => '#',
                'icon' => 'bi-sliders',
                'icon_type' => 'bi',
                'order_position' => 6,
                'is_active' => 1
            ],
            [
                'title' => 'Módulos',
                'slug' => 'modules',
                'url' => '{admin_path}/modules',
                'icon' => 'bi-puzzle',
                'icon_type' => 'bi',
                'order_position' => 7,
                'is_active' => 1
            ],
            [
                'title' => 'Plugins',
                'slug' => 'plugins',
                'url' => '{admin_path}/plugins',
                'icon' => 'bi-plug',
                'icon_type' => 'bi',
                'order_position' => 8,
                'is_active' => 1
            ],
            [
                'title' => 'IA',
                'slug' => 'ai',
                'url' => '#',
                'icon' => 'bi-cpu',
                'icon_type' => 'bi',
                'order_position' => 9,
                'is_active' => 1
            ],
        ];

        // Insertar menús principales y guardar sus IDs
        $menuIds = [];
        foreach ($mainMenus as $menu) {
            $menuIds[$menu['slug']] = $this->insertMenu($menu);
        }

        // Submenús de Apariencia
        $appearanceId = $menuIds['appearance'] ?? null;
        if ($appearanceId) {
            $this->insertMenu([
                'parent_id' => $appearanceId,
                'title' => 'Temas',
                'slug' => 'themes',
                'url' => '{admin_path}/themes',
                'icon' => 'bi-palette',
                'icon_type' => 'bi',
                'order_position' => 0,
                'is_active' => 1
            ]);
            $this->insertMenu([
                'parent_id' => $appearanceId,
                'title' => 'Menús',
                'slug' => 'menus',
                'url' => '{admin_path}/menus',
                'icon' => 'bi-list',
                'icon_type' => 'bi',
                'order_position' => 1,
                'is_active' => 1
            ]);
            $this->insertMenu([
                'parent_id' => $appearanceId,
                'title' => 'Widgets',
                'slug' => 'widgets',
                'url' => '{admin_path}/widgets/default',
                'icon' => 'bi-box',
                'icon_type' => 'bi',
                'order_position' => 2,
                'is_active' => 1
            ]);
            $this->insertMenu([
                'parent_id' => $appearanceId,
                'title' => 'Sliders',
                'slug' => 'sliders',
                'url' => '{admin_path}/sliders',
                'icon' => 'bi-image',
                'icon_type' => 'bi',
                'order_position' => 3,
                'is_active' => 1
            ]);
        }

        // Submenús de Blog
        $blogId = $menuIds['blog'] ?? null;
        if ($blogId) {
            $this->insertMenu([
                'parent_id' => $blogId,
                'title' => 'Posts',
                'slug' => 'blog-posts',
                'url' => '{admin_path}/blog/posts',
                'icon' => 'bi-file-text',
                'icon_type' => 'bi',
                'order_position' => 0,
                'is_active' => 1
            ]);
            $this->insertMenu([
                'parent_id' => $blogId,
                'title' => 'Categorías',
                'slug' => 'blog-categories',
                'url' => '{admin_path}/blog/categories',
                'icon' => 'bi-folder',
                'icon_type' => 'bi',
                'order_position' => 1,
                'is_active' => 1
            ]);
            $this->insertMenu([
                'parent_id' => $blogId,
                'title' => 'Etiquetas',
                'slug' => 'blog-tags',
                'url' => '{admin_path}/blog/tags',
                'icon' => 'bi-tag',
                'icon_type' => 'bi',
                'order_position' => 2,
                'is_active' => 1
            ]);
        }

        // Submenús de Gestión de Usuarios
        $usersId = $menuIds['user_management'] ?? null;
        if ($usersId) {
            $this->insertMenu([
                'parent_id' => $usersId,
                'title' => 'Usuarios',
                'slug' => 'users',
                'url' => '{admin_path}/users',
                'icon' => 'bi-people',
                'icon_type' => 'bi',
                'order_position' => 0,
                'is_active' => 1
            ]);
            $this->insertMenu([
                'parent_id' => $usersId,
                'title' => 'Roles',
                'slug' => 'roles',
                'url' => '{admin_path}/roles',
                'icon' => 'bi-shield',
                'icon_type' => 'bi',
                'order_position' => 1,
                'is_active' => 1
            ]);
            $this->insertMenu([
                'parent_id' => $usersId,
                'title' => 'Permisos',
                'slug' => 'permissions',
                'url' => '{admin_path}/permissions',
                'icon' => 'bi-lock',
                'icon_type' => 'bi',
                'order_position' => 2,
                'is_active' => 1
            ]);
            $this->insertMenu([
                'parent_id' => $usersId,
                'title' => 'Sesiones',
                'slug' => 'sessions',
                'url' => '{admin_path}/sessions',
                'icon' => 'bi-briefcase',
                'icon_type' => 'bi',
                'order_position' => 4,
                'is_active' => 1
            ]);
        }

        // Submenús de Ajustes
        $settingsId = $menuIds['settings'] ?? null;
        if ($settingsId) {
            $this->insertMenu([
                'parent_id' => $settingsId,
                'title' => 'Site Config',
                'slug' => 'config',
                'url' => '{admin_path}/settings',
                'icon' => 'bi-sliders',
                'icon_type' => 'bi',
                'order_position' => 0,
                'is_active' => 1
            ]);
            $this->insertMenu([
                'parent_id' => $settingsId,
                'title' => 'SEO y Social',
                'slug' => 'seo_social',
                'url' => '{admin_path}/settings/seo',
                'icon' => 'bi-globe',
                'icon_type' => 'bi',
                'order_position' => 1,
                'is_active' => 1
            ]);
            $this->insertMenu([
                'parent_id' => $settingsId,
                'title' => 'Idiomas',
                'slug' => 'languages',
                'url' => '{admin_path}/languages',
                'icon' => 'bi-globe',
                'icon_type' => 'bi',
                'order_position' => 2,
                'is_active' => 1
            ]);
            $this->insertMenu([
                'parent_id' => $settingsId,
                'title' => 'Avanzado',
                'slug' => 'advanced',
                'url' => '{admin_path}/settings/advanced',
                'icon' => 'bi-sliders',
                'icon_type' => 'bi',
                'order_position' => 3,
                'is_active' => 1
            ]);
            $this->insertMenu([
                'parent_id' => $settingsId,
                'title' => 'Cookies',
                'slug' => 'cookies',
                'url' => '{admin_path}/settings/cookies',
                'icon' => 'bi-cookie',
                'icon_type' => 'bi',
                'order_position' => 4,
                'is_active' => 1
            ]);
            $this->insertMenu([
                'parent_id' => $settingsId,
                'title' => 'Tenants',
                'slug' => 'tenants',
                'url' => '{admin_path}/tenants',
                'icon' => 'bi-building',
                'icon_type' => 'bi',
                'order_position' => 5,
                'is_active' => 1
            ]);
            $this->insertMenu([
                'parent_id' => $settingsId,
                'title' => 'Logs',
                'slug' => 'logs',
                'url' => '{admin_path}/logs',
                'icon' => 'bi-file-text',
                'icon_type' => 'bi',
                'order_position' => 6,
                'is_active' => 1
            ]);
            $this->insertMenu([
                'parent_id' => $settingsId,
                'title' => 'Audit Logs',
                'slug' => 'audit-logs',
                'url' => '{admin_path}/audit-logs',
                'icon' => 'bi-speedometer2',
                'icon_type' => 'bi',
                'order_position' => 7,
                'is_active' => 1
            ]);
            $this->insertMenu([
                'parent_id' => $settingsId,
                'title' => 'Admin Menus',
                'slug' => 'admin-menus',
                'url' => '{admin_path}/admin-menus',
                'icon' => 'bi-menu-app',
                'icon_type' => 'bi',
                'order_position' => 9,
                'is_active' => 1
            ]);
            $this->insertMenu([
                'parent_id' => $settingsId,
                'title' => 'Email',
                'slug' => 'email-settings',
                'url' => '{admin_path}/settings/email',
                'icon' => 'bi-envelope',
                'icon_type' => 'bi',
                'order_position' => 10,
                'is_active' => 1
            ]);
            $this->insertMenu([
                'parent_id' => $settingsId,
                'title' => 'Storage',
                'slug' => 'storage-settings',
                'url' => '{admin_path}/settings/storage',
                'icon' => 'bi-hdd',
                'icon_type' => 'bi',
                'order_position' => 11,
                'is_active' => 1
            ]);
        }

        // Submenús de IA
        $aiId = $menuIds['ai'] ?? null;
        if ($aiId) {
            $this->insertMenu([
                'parent_id' => $aiId,
                'title' => 'Dashboard IA',
                'slug' => 'ai_dashboard',
                'url' => '{admin_path}/ai',
                'icon' => 'bi-activity',
                'icon_type' => 'bi',
                'order_position' => 0,
                'is_active' => 1
            ]);
            $this->insertMenu([
                'parent_id' => $aiId,
                'title' => 'Ajustes IA',
                'slug' => 'ai_settings',
                'url' => '{admin_path}/ai/settings',
                'icon' => 'bi-gear',
                'icon_type' => 'bi',
                'order_position' => 1,
                'is_active' => 1
            ]);
            $this->insertMenu([
                'parent_id' => $aiId,
                'title' => 'Proveedores',
                'slug' => 'ai_providers',
                'url' => '{admin_path}/ai/providers',
                'icon' => 'bi-server',
                'icon_type' => 'bi',
                'order_position' => 2,
                'is_active' => 1
            ]);
            $this->insertMenu([
                'parent_id' => $aiId,
                'title' => 'Logs IA',
                'slug' => 'ai_logs',
                'url' => '{admin_path}/ai/logs',
                'icon' => 'bi-file-text',
                'icon_type' => 'bi',
                'order_position' => 3,
                'is_active' => 1
            ]);
        }

        echo "  ✓ SuperAdmin menus created\n";
    }

    /**
     * Insertar menú si no existe, retorna el ID
     */
    private function insertMenu(array $data): ?int
    {
        $stmt = $this->db->prepare("SELECT id FROM admin_menus WHERE slug = ?");
        $stmt->execute([$data['slug']]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($existing) {
            return (int)$existing['id'];
        }

        // Preparar columnas y valores
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($data), '?');

        $sql = "INSERT INTO admin_menus (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($data));

        return (int)$this->db->lastInsertId();
    }
}
