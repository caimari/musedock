<?php

use Screenart\Musedock\Database;

class CreateAdminMenusTable_2025_11_12_000000
{
    public function up()
    {
        $pdo = Database::connect();

        // Crear tabla admin_menus
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admin_menus (
                id INT PRIMARY KEY AUTO_INCREMENT,
                parent_id INT NULL,
                module_id INT NULL,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) UNIQUE NOT NULL,
                url VARCHAR(500) NOT NULL,
                icon VARCHAR(100),
                icon_type ENUM('bi', 'fas', 'far', 'fal') DEFAULT 'bi',
                order_position INT DEFAULT 0,
                permission VARCHAR(255),
                is_active BOOLEAN DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (parent_id) REFERENCES admin_menus(id),
                FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE SET NULL,
                INDEX idx_parent_id (parent_id),
                INDEX idx_module_id (module_id),
                INDEX idx_is_active (is_active),
                INDEX idx_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Crear tabla tenant_admin_menu_visibility
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tenant_admin_menu_visibility (
                id INT PRIMARY KEY AUTO_INCREMENT,
                tenant_id INT NOT NULL,
                admin_menu_id INT NOT NULL,
                is_visible BOOLEAN DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_tenant_menu (tenant_id, admin_menu_id),
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                FOREIGN KEY (admin_menu_id) REFERENCES admin_menus(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Insertar menús base del sistema
        $menus = [
            ['title' => 'Dashboard', 'slug' => 'dashboard', 'url' => '/musedock', 'icon' => 'house', 'order' => 0],
            ['title' => 'Páginas', 'slug' => 'pages', 'url' => '/musedock/pages', 'icon' => 'file-text', 'order' => 5],
            ['title' => 'Apariencia', 'slug' => 'appearance', 'url' => '/musedock/themes', 'icon' => 'palette', 'order' => 10],
            ['title' => 'Módulos', 'slug' => 'modules', 'url' => '/musedock/modules', 'icon' => 'puzzle', 'order' => 20],
            ['title' => 'Gestión de Usuarios', 'slug' => 'user_management', 'url' => '/musedock/users', 'icon' => 'people', 'order' => 30],
            ['title' => 'Ajustes', 'slug' => 'settings', 'url' => '/musedock/settings', 'icon' => 'sliders', 'order' => 40],
            ['title' => 'IA', 'slug' => 'ai', 'url' => '/musedock/ai', 'icon' => 'zap', 'order' => 50],
        ];

        $stmt = $pdo->prepare("INSERT IGNORE INTO admin_menus (title, slug, url, icon, order_position) VALUES (?, ?, ?, ?, ?)");
        foreach ($menus as $menu) {
            $stmt->execute([
                $menu['title'],
                $menu['slug'],
                $menu['url'],
                $menu['icon'],
                $menu['order']
            ]);
        }

        // Obtener IDs de padres para insertar hijos
        $appearance_id = $pdo->query("SELECT id FROM admin_menus WHERE slug = 'appearance'")->fetch()['id'] ?? null;
        $user_mgmt_id = $pdo->query("SELECT id FROM admin_menus WHERE slug = 'user_management'")->fetch()['id'] ?? null;
        $settings_id = $pdo->query("SELECT id FROM admin_menus WHERE slug = 'settings'")->fetch()['id'] ?? null;
        $ai_id = $pdo->query("SELECT id FROM admin_menus WHERE slug = 'ai'")->fetch()['id'] ?? null;

        // Menús hijos
        $children = [
            // Apariencia
            ['parent' => $appearance_id, 'title' => 'Temas', 'slug' => 'themes', 'url' => '/musedock/themes', 'icon' => 'palette', 'order' => 1],
            ['parent' => $appearance_id, 'title' => 'Menús', 'slug' => 'menus', 'url' => '/musedock/menus', 'icon' => 'list', 'order' => 2],
            ['parent' => $appearance_id, 'title' => 'Sliders', 'slug' => 'sliders', 'url' => '/musedock/sliders', 'icon' => 'image', 'order' => 3],
            ['parent' => $appearance_id, 'title' => 'Widgets', 'slug' => 'widgets', 'url' => '/musedock/widgets', 'icon' => 'box', 'order' => 4],
            // Gestión de Usuarios
            ['parent' => $user_mgmt_id, 'title' => 'Usuarios', 'slug' => 'users', 'url' => '/musedock/users', 'icon' => 'user', 'order' => 1],
            ['parent' => $user_mgmt_id, 'title' => 'Roles', 'slug' => 'roles', 'url' => '/musedock/roles', 'icon' => 'shield', 'order' => 2],
            ['parent' => $user_mgmt_id, 'title' => 'Permisos', 'slug' => 'permissions', 'url' => '/musedock/permissions', 'icon' => 'lock', 'order' => 3],
            ['parent' => $user_mgmt_id, 'title' => 'Recursos', 'slug' => 'resources', 'url' => '/musedock/resources', 'icon' => 'server', 'order' => 4],
            ['parent' => $user_mgmt_id, 'title' => 'Sesiones', 'slug' => 'sessions', 'url' => '/musedock/sessions', 'icon' => 'log-in', 'order' => 5],
            // Ajustes
            ['parent' => $settings_id, 'title' => 'Configuración', 'slug' => 'config', 'url' => '/musedock/settings', 'icon' => 'sliders', 'order' => 1],
            ['parent' => $settings_id, 'title' => 'Avanzado', 'slug' => 'advanced', 'url' => '/musedock/settings/advanced', 'icon' => 'sliders', 'order' => 2],
            ['parent' => $settings_id, 'title' => 'Idiomas', 'slug' => 'languages', 'url' => '/musedock/languages', 'icon' => 'globe', 'order' => 3],
            ['parent' => $settings_id, 'title' => 'SEO y Social', 'slug' => 'seo_social', 'url' => '/musedock/settings/seo', 'icon' => 'share-2', 'order' => 4],
            ['parent' => $settings_id, 'title' => 'Cookies', 'slug' => 'cookies', 'url' => '/musedock/settings/cookies', 'icon' => 'cookie', 'order' => 5],
            ['parent' => $settings_id, 'title' => 'Tenants', 'slug' => 'tenants', 'url' => '/musedock/tenants', 'icon' => 'building', 'order' => 6],
            ['parent' => $settings_id, 'title' => 'Logs', 'slug' => 'logs', 'url' => '/musedock/logs', 'icon' => 'file-text', 'order' => 7],
            // IA
            ['parent' => $ai_id, 'title' => 'Dashboard IA', 'slug' => 'ai_dashboard', 'url' => '/musedock/ai', 'icon' => 'activity', 'order' => 1],
            ['parent' => $ai_id, 'title' => 'Ajustes IA', 'slug' => 'ai_settings', 'url' => '/musedock/ai/settings', 'icon' => 'settings', 'order' => 2],
            ['parent' => $ai_id, 'title' => 'Proveedores', 'slug' => 'ai_providers', 'url' => '/musedock/ai/providers', 'icon' => 'server', 'order' => 3],
            ['parent' => $ai_id, 'title' => 'Logs IA', 'slug' => 'ai_logs', 'url' => '/musedock/ai/logs', 'icon' => 'file-text', 'order' => 4],
        ];

        $stmt = $pdo->prepare("INSERT IGNORE INTO admin_menus (parent_id, title, slug, url, icon, order_position) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($children as $child) {
            $stmt->execute([
                $child['parent'],
                $child['title'],
                $child['slug'],
                $child['url'],
                $child['icon'],
                $child['order']
            ]);
        }

        echo "✓ Tablas admin_menus y tenant_admin_menu_visibility creadas\n";
        echo "✓ Menús base insertados correctamente\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $pdo->exec("DROP TABLE IF EXISTS tenant_admin_menu_visibility");
        $pdo->exec("DROP TABLE IF EXISTS admin_menus");
        echo "✓ Tablas eliminadas\n";
    }
}
