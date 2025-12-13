<?php
namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\Database;
use Screenart\Musedock\View;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\Services\TenantCreationService;
use PDO;

/**
 * TenantDefaultsController
 *
 * Controlador para gestionar la configuración por defecto de nuevos tenants.
 * Permite al superadmin definir qué permisos, roles y menús se crean automáticamente.
 */
class TenantDefaultsController
{
    use RequiresPermission;

    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.view');

        $pdo = Database::connect();

        // Obtener configuración actual
        $settings = $this->getSettings($pdo);

        // Obtener todos los permisos disponibles
        $allPermissions = TenantCreationService::getAllAvailablePermissions();

        // Permisos actualmente seleccionados
        $selectedPermissions = $settings['default_permissions'] ?? [];

        // Roles por defecto
        $defaultRoles = $settings['default_roles'] ?? [];

        // Obtener todos los menús de admin_menus con jerarquía
        $allMenus = $this->getAllAdminMenus($pdo);

        // Menús actualmente seleccionados (si no hay ninguno guardado, usar todos por defecto)
        $selectedMenus = $settings['default_menu_slugs'] ?? array_column($allMenus, 'slug');

        return View::renderSuperadmin('settings.tenant-defaults', [
            'title' => 'Configuración de Nuevos Tenants',
            'settings' => $settings,
            'allPermissions' => $allPermissions,
            'selectedPermissions' => $selectedPermissions,
            'defaultRoles' => $defaultRoles,
            'allMenus' => $allMenus,
            'selectedMenus' => $selectedMenus
        ]);
    }

    public function update()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();

            // Procesar permisos seleccionados
            $selectedPermissions = $_POST['permissions'] ?? [];
            $this->updateSetting($pdo, 'default_permissions', json_encode($selectedPermissions), 'json');

            // Procesar roles
            $roles = [];
            if (!empty($_POST['role_name'])) {
                foreach ($_POST['role_name'] as $index => $name) {
                    if (!empty($name)) {
                        $roles[] = [
                            'name' => $name,
                            'slug' => $_POST['role_slug'][$index] ?? strtolower(str_replace(' ', '_', $name)),
                            'description' => $_POST['role_description'][$index] ?? ''
                        ];
                    }
                }
            }
            $this->updateSetting($pdo, 'default_roles', json_encode($roles), 'json');

            // Opciones booleanas
            $copyMenus = isset($_POST['copy_menus_from_admin']) ? '1' : '0';
            $assignAdminRole = isset($_POST['assign_admin_role_to_creator']) ? '1' : '0';
            $this->updateSetting($pdo, 'copy_menus_from_admin', $copyMenus, 'boolean');
            $this->updateSetting($pdo, 'assign_admin_role_to_creator', $assignAdminRole, 'boolean');

            // Tema por defecto
            $defaultTheme = $_POST['default_theme'] ?? 'default';
            $this->updateSetting($pdo, 'default_theme', $defaultTheme, 'string');

            // Menús seleccionados para copiar a tenants
            $selectedMenus = $_POST['menus'] ?? [];
            $this->updateSetting($pdo, 'default_menu_slugs', json_encode($selectedMenus), 'json');

            $pdo->commit();

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Configuración guardada correctamente'
            ]);

        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al guardar: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * Obtener todas las configuraciones
     */
    private function getSettings(PDO $pdo): array
    {
        $settings = [];

        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value, setting_type FROM tenant_default_settings");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $value = $row['setting_value'];

                switch ($row['setting_type']) {
                    case 'json':
                        $value = json_decode($value, true) ?? [];
                        break;
                    case 'boolean':
                        $value = (bool) $value;
                        break;
                    case 'integer':
                        $value = (int) $value;
                        break;
                }

                $settings[$row['setting_key']] = $value;
            }
        } catch (\PDOException $e) {
            // Si la tabla no existe, retornar valores por defecto
            $settings = $this->getDefaultSettings();
        }

        return $settings;
    }

    /**
     * Valores por defecto
     */
    private function getDefaultSettings(): array
    {
        return [
            'default_permissions' => [
                'view_dashboard', 'manage_content', 'view_content', 'manage_media', 'view_media',
                'manage_users', 'view_users', 'manage_settings', 'view_settings', 'manage_pages',
                'view_pages', 'manage_posts', 'view_posts', 'manage_categories', 'view_categories',
                'manage_comments', 'view_comments', 'manage_menus', 'view_menus', 'manage_themes',
                'view_themes', 'manage_plugins'
            ],
            'default_roles' => [
                ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Administrador del tenant con acceso completo'],
                ['name' => 'Editor', 'slug' => 'editor', 'description' => 'Editor de contenido'],
                ['name' => 'Viewer', 'slug' => 'viewer', 'description' => 'Solo lectura']
            ],
            'copy_menus_from_admin' => true,
            'default_theme' => 'default',
            'assign_admin_role_to_creator' => true
        ];
    }

    /**
     * Actualizar o insertar una configuración
     */
    private function updateSetting(PDO $pdo, string $key, string $value, string $type): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $sql = "INSERT INTO tenant_default_settings (setting_key, setting_value, setting_type, updated_at)
                    VALUES (:key, :value, :type, NOW())
                    ON DUPLICATE KEY UPDATE setting_value = :value2, setting_type = :type2, updated_at = NOW()";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'key' => $key,
                'value' => $value,
                'type' => $type,
                'value2' => $value,
                'type2' => $type
            ]);
        } else {
            // PostgreSQL - usar INSERT ON CONFLICT
            $sql = "INSERT INTO tenant_default_settings (setting_key, setting_value, setting_type, updated_at)
                    VALUES (:key, :value, :type, NOW())
                    ON CONFLICT (setting_key)
                    DO UPDATE SET setting_value = :value2, setting_type = :type2, updated_at = NOW()";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'key' => $key,
                'value' => $value,
                'type' => $type,
                'value2' => $value,
                'type2' => $type
            ]);
        }
    }

    /**
     * Obtiene todos los menús de admin_menus con jerarquía
     * Solo incluye los menús marcados para tenant (show_in_tenant = 1)
     */
    private function getAllAdminMenus(PDO $pdo): array
    {
        $stmt = $pdo->query("
            SELECT id, parent_id, title, slug, url, icon, icon_type, order_position, permission, show_in_tenant
            FROM admin_menus
            WHERE is_active = 1 AND show_in_tenant = 1
            ORDER BY order_position ASC
        ");
        $allMenus = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Construir árbol de menús
        $menuTree = [];
        $menuById = [];

        foreach ($allMenus as $menu) {
            $menu['children'] = [];
            $menuById[$menu['id']] = $menu;
        }

        foreach ($menuById as $id => $menu) {
            if (empty($menu['parent_id'])) {
                $menuTree[] = &$menuById[$id];
            } else {
                if (isset($menuById[$menu['parent_id']])) {
                    $menuById[$menu['parent_id']]['children'][] = &$menuById[$id];
                }
            }
        }

        return $menuTree;
    }
}
