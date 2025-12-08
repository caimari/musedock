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
        // Limpiar TODOS los buffers de salida existentes
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Iniciar nuevo buffer limpio
        ob_start();

        // Establecer JSON como tipo de respuesta
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, no-store, must-revalidate');
        }

        try {
            SessionSecurity::startSession();

            // Verificar que sea superadmin
            $auth = SessionSecurity::getAuthenticatedUser();
            if (!$auth || $auth['type'] !== 'super_admin' || ($_SESSION['super_admin']['role'] ?? '') !== 'superadmin') {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'No autorizado']);
                exit;
            }

            $seederKey = $_POST['seeder'] ?? 'all';
            $results = [];

            // Cargar seeders - usar ruta absoluta
            $seederPath = realpath(dirname(__DIR__, 2) . '/../database/seeders/');

            if (!$seederPath || !is_dir($seederPath)) {
                throw new \Exception('Directorio de seeders no encontrado: ' . dirname(__DIR__, 2) . '/../database/seeders/');
            }

            $seederPath .= '/';

            if ($seederKey === 'all' || $seederKey === 'roles_permissions') {
                $file = $seederPath . 'RolesAndPermissionsSeeder.php';
                if (!file_exists($file)) {
                    throw new \Exception("Archivo no encontrado: $file");
                }
                require_once $file;
                $seeder = new \Screenart\Musedock\Database\Seeders\RolesAndPermissionsSeeder();
                $seeder->run();
                $results[] = 'RolesAndPermissionsSeeder ejecutado';
            }

            if ($seederKey === 'all' || $seederKey === 'modules') {
                $file = $seederPath . 'ModulesSeeder.php';
                if (!file_exists($file)) {
                    throw new \Exception("Archivo no encontrado: $file");
                }
                require_once $file;
                $seeder = new \Screenart\Musedock\Database\Seeders\ModulesSeeder();
                $seeder->run();
                $results[] = 'ModulesSeeder ejecutado';
            }

            if ($seederKey === 'all' || $seederKey === 'themes') {
                $file = $seederPath . 'ThemesSeeder.php';
                if (!file_exists($file)) {
                    throw new \Exception("Archivo no encontrado: $file");
                }
                require_once $file;
                $seeder = new \Screenart\Musedock\Database\Seeders\ThemesSeeder();
                $seeder->run();
                $results[] = 'ThemesSeeder ejecutado';
            }

            if ($seederKey === 'all' || $seederKey === 'superadmin_menus') {
                $file = $seederPath . 'SuperadminMenuSeeder.php';
                if (!file_exists($file)) {
                    throw new \Exception("Archivo no encontrado: $file");
                }
                require_once $file;
                $seeder = new \Screenart\Musedock\Database\Seeders\SuperadminMenuSeeder();
                $seeder->run();
                $results[] = 'SuperadminMenuSeeder ejecutado';
            }

            // Limpiar cualquier output capturado
            ob_end_clean();

            echo json_encode([
                'success' => true,
                'message' => 'Seeders ejecutados correctamente',
                'results' => $results
            ]);

        } catch (\Throwable $e) {
            // Capturar cualquier error o excepción
            $output = ob_get_clean();
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'output' => $output ? substr($output, 0, 500) : null
            ]);
        }

        exit;
    }
}