<?php
namespace Screenart\Musedock\Controllers\Superadmin;
use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Security\SessionCleaner;
use Screenart\Musedock\ModuleManager;

class DashboardController {
    public function index() {
        SessionSecurity::startSession();

        // Obtener usuario autenticado
        $auth = SessionSecurity::getAuthenticatedUser();

        // Si no hay usuario autenticado, redirigir al login
        if (!$auth) {
            header('Location: /musedock/login');
            exit;
        }

        // Verificar si es superadmin
        $isSuperAdmin = $auth['type'] === 'super_admin' &&
                        ($_SESSION['super_admin']['role'] ?? '') === 'superadmin';

        // Limpiar tokens expirados según tipo de usuario
        if ($isSuperAdmin) {
            SessionCleaner::cleanExpiredSuperAdminTokens();
            // Sincronizar módulos solo para superadmin
            ModuleManager::syncModulesWithDisk();
        }

        // Detectar seeders faltantes
        $missingSeeders = $this->detectMissingSeeders();

        // Obtener email y nombre del usuario
        $email = $auth['email'] ?? 'Usuario';
        $name = $auth['name'] ?? 'Usuario';

        // Renderizar dashboard con información del usuario
        return View::renderSuperadmin('dashboard', [
            'title' => 'Panel de control',
            'email' => $email,
            'name' => $name,
            'userType' => $auth['type'],
            'isSuperAdmin' => $isSuperAdmin,
            'missingSeeders' => $missingSeeders
        ]);
    }

    /**
     * Detectar qué seeders faltan por ejecutar
     */
    private function detectMissingSeeders(): array
    {
        $missing = [];
        $pdo = Database::connect();

        // Verificar admin_menus (SuperadminMenuSeeder)
        $stmt = $pdo->query("SELECT COUNT(*) FROM admin_menus");
        $menuCount = (int)$stmt->fetchColumn();
        if ($menuCount < 5) {
            $missing[] = [
                'name' => 'SuperadminMenuSeeder',
                'description' => 'Menús del panel de administración',
                'key' => 'superadmin_menus'
            ];
        }

        // Verificar roles (RolesAndPermissionsSeeder)
        $stmt = $pdo->query("SELECT COUNT(*) FROM roles");
        $rolesCount = (int)$stmt->fetchColumn();
        if ($rolesCount < 3) {
            $missing[] = [
                'name' => 'RolesAndPermissionsSeeder',
                'description' => 'Roles y permisos del sistema',
                'key' => 'roles_permissions'
            ];
        }

        // Verificar módulos (ModulesSeeder)
        $stmt = $pdo->query("SELECT COUNT(*) FROM modules");
        $modulesCount = (int)$stmt->fetchColumn();
        if ($modulesCount < 3) {
            $missing[] = [
                'name' => 'ModulesSeeder',
                'description' => 'Módulos base del sistema',
                'key' => 'modules'
            ];
        }

        // Verificar temas (ThemesSeeder)
        $stmt = $pdo->query("SELECT COUNT(*) FROM themes");
        $themesCount = (int)$stmt->fetchColumn();
        if ($themesCount < 1) {
            $missing[] = [
                'name' => 'ThemesSeeder',
                'description' => 'Temas del sistema',
                'key' => 'themes'
            ];
        }

        return $missing;
    }

    /**
     * Ejecutar seeders faltantes via AJAX
     */
    public function runMissingSeeders()
    {
        SessionSecurity::startSession();

        // Verificar que sea superadmin
        $auth = SessionSecurity::getAuthenticatedUser();
        if (!$auth || $auth['type'] !== 'super_admin' || ($_SESSION['super_admin']['role'] ?? '') !== 'superadmin') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            exit;
        }

        header('Content-Type: application/json');

        $seederKey = $_POST['seeder'] ?? 'all';

        try {
            $results = [];

            // Cargar seeders
            $seederPath = dirname(__DIR__, 2) . '/../database/seeders/';

            if ($seederKey === 'all' || $seederKey === 'roles_permissions') {
                require_once $seederPath . 'RolesAndPermissionsSeeder.php';
                $seeder = new \Screenart\Musedock\Database\Seeders\RolesAndPermissionsSeeder();
                $seeder->run();
                $results[] = 'RolesAndPermissionsSeeder ejecutado';
            }

            if ($seederKey === 'all' || $seederKey === 'modules') {
                require_once $seederPath . 'ModulesSeeder.php';
                $seeder = new \Screenart\Musedock\Database\Seeders\ModulesSeeder();
                $seeder->run();
                $results[] = 'ModulesSeeder ejecutado';
            }

            if ($seederKey === 'all' || $seederKey === 'themes') {
                require_once $seederPath . 'ThemesSeeder.php';
                $seeder = new \Screenart\Musedock\Database\Seeders\ThemesSeeder();
                $seeder->run();
                $results[] = 'ThemesSeeder ejecutado';
            }

            if ($seederKey === 'all' || $seederKey === 'superadmin_menus') {
                require_once $seederPath . 'SuperadminMenuSeeder.php';
                $seeder = new \Screenart\Musedock\Database\Seeders\SuperadminMenuSeeder();
                $seeder->run();
                $results[] = 'SuperadminMenuSeeder ejecutado';
            }

            echo json_encode([
                'success' => true,
                'message' => 'Seeders ejecutados correctamente',
                'results' => $results
            ]);

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }

        exit;
    }
}