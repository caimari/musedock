<?php
namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\View;
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
        return View::renderTenantAdmin('dashboard', [
            'title' => __('dashboard'),
            'email' => $userData['email'],
            'name' => $userData['name'],
            'role' => $userData['role']
        ]);
    }
}