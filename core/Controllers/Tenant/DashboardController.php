<?php
namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Security\SessionCleaner;

class DashboardController
{
    public function index()
    {
        SessionSecurity::startSession();
        
        // Verificar si hay una sesión de admin o de usuario
        $admin = $_SESSION['admin'] ?? null;
        $user = $_SESSION['user'] ?? null;
        $adminPath = '/' . admin_path();
        
        if (!$admin && !$user) {
            header("Location: {$adminPath}/login");
            exit;
        }
        
        // Ejecutar limpieza sólo si ha pasado más de 1 hora desde la última
        // Asegúrate de que esta llamada se hace DESPUÉS de verificar la autenticación
        // y nunca inmediatamente después de un login reciente
        if (!isset($_SESSION['last_cleanup']) || (time() - $_SESSION['last_cleanup']) > 3600) {
            // Para mayor seguridad, verificamos si estamos en una sesión recién creada
            $sessionAge = time() - ($_SESSION['last_active'] ?? time());
            
            if ($sessionAge > 5) { // Si la sesión tiene más de 5 segundos de antigüedad
                SessionCleaner::runAllCleanupTasks();
                $_SESSION['last_cleanup'] = time();
                error_log("DashboardController - Limpieza de sesiones/tokens ejecutada");
            } else {
                error_log("DashboardController - Limpieza omitida (sesión muy reciente)");
                $_SESSION['last_cleanup'] = time(); // Aun así actualizamos para no intentarlo pronto
            }
        } else {
            error_log("DashboardController - Limpieza omitida (última hace menos de 1 hora)");
        }
        
        // Depuración
        error_log("DashboardController - Sesión: " . json_encode($_SESSION));
        error_log("DashboardController - URL: " . $_SERVER['REQUEST_URI']);
        
        // Determinar los datos del usuario para la vista
        if ($admin) {
            $userData = [
                'email' => $admin['email'],
                'name' => $admin['name'] ?? 'Admin',
                'role' => $admin['role'] ?? 'admin'
            ];
        } else {
            $userData = [
                'email' => $user['email'],
                'name' => $user['name'] ?? 'Usuario',
                'role' => $user['role'] ?? 'user'
            ];
        }
        
        error_log("Acceso al dashboard: " . $userData['email'] . " (Rol: " . $userData['role'] . ")");

        $tenantId = tenant_id();
        $stats = [
            'pages' => 0,
            'menus' => 0,
            'modules_enabled' => 0,
            'modules_available' => 0,
            'plugins' => 0,
        ];

        if ($tenantId) {
            try {
                $pdo = Database::connect();

                $stmt = $pdo->prepare("SELECT COUNT(*) FROM pages WHERE tenant_id = ? AND status <> 'trash'");
                $stmt->execute([$tenantId]);
                $stats['pages'] = (int) $stmt->fetchColumn();

                $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenant_menus WHERE tenant_id = ? AND is_active = 1");
                $stmt->execute([$tenantId]);
                $stats['menus'] = (int) $stmt->fetchColumn();

                $stmt = $pdo->prepare("SELECT COUNT(*) FROM modules WHERE active = 1");
                $stmt->execute();
                $stats['modules_available'] = (int) $stmt->fetchColumn();

                $stmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM modules m
                    LEFT JOIN tenant_modules tm ON tm.module_id = m.id AND tm.tenant_id = ?
                    WHERE m.active = 1 AND COALESCE(tm.enabled, 0) = 1
                ");
                $stmt->execute([$tenantId]);
                $stats['modules_enabled'] = (int) $stmt->fetchColumn();

                $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenant_plugins WHERE tenant_id = ?");
                $stmt->execute([$tenantId]);
                $stats['plugins'] = (int) $stmt->fetchColumn();
            } catch (\Throwable $e) {
                error_log("DashboardController - Error cargando contadores: " . $e->getMessage());
            }
        }

        return View::renderTenantAdmin('dashboard', [
            'title' => __('dashboard'),
            'email' => $userData['email'],
            'name' => $userData['name'],
            'role' => $userData['role'],
            'stats' => $stats,
        ]);
    }
}
