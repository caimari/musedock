<?php
namespace Screenart\Musedock\Middlewares;

use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Database;

class AuthMiddleware {
   public function handle() {
    // 🔒 SECURITY: Logging reducido - NO loguear datos sensibles (cookies, sesiones, headers completos)
    $requestUri = $_SERVER['REQUEST_URI'] ?? 'desconocida';
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'desconocido';

    // Solo logging en modo debug
    if (defined('APP_DEBUG') && APP_DEBUG === true) {
        error_log("AuthMiddleware - {$requestMethod} {$requestUri}");
    }

    SessionSecurity::startSession();
    
    $adminPath = '/' . admin_path();

    if (isset($_SESSION['admin'])) {
        $tenantId = $_SESSION['admin']['tenant_id'] ?? null;
        $adminId = $_SESSION['admin']['id'] ?? null;

        try {
            $db = Database::connect();
            $stmt = $db->prepare("SELECT * FROM admins WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $adminId]);
            $adminData = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($adminData) {
                // 🔒 SECURITY: Verificar tenant_id sin loguear datos sensibles
                if (($adminData['tenant_id'] ?? null) !== ($tenantId ?? null)) {
                    // Solo loguear ID parcial para seguridad
                    $maskedId = substr($adminId, 0, 3) . '***';
                    error_log("⚠️ SECURITY ALERT: Cross-tenant access attempt - Admin ID: {$maskedId}");
                    SessionSecurity::destroy();
                    flash('error', 'Acceso no autorizado. Por favor inicia sesión nuevamente.');
                    header("Location: {$adminPath}/login");
                    exit;
                }
            } else {
                error_log("⚠️ SECURITY ALERT: Admin not found in database");
            }
        } catch (\Exception $e) {
            error_log("AuthMiddleware - Database error: " . $e->getMessage());
        }
    }
    
    $isSuperAdminSession = isset($_SESSION['super_admin']);
    $isAdminSession = isset($_SESSION['admin']);
    $isUserSession = isset($_SESSION['user']);

    // IMPORTANTE: Solo admins pueden acceder al panel de administración del tenant
    // Los usuarios de la tabla 'users' NO deben acceder al panel admin
    if ($isAdminSession) {
        $userType = 'admin';
        $userId = $_SESSION['admin']['id'];

        error_log("AuthMiddleware - Tipo de usuario detectado: $userType, ID: $userId");
        
        try {
            $db = Database::connect();

            // Solo verificamos admins aquí
            $userStmt = $db->prepare("SELECT COUNT(*) FROM admins WHERE id = :id");
            $userStmt->execute(['id' => $userId]);
            $userExists = $userStmt->fetchColumn() > 0;

            error_log("AuthMiddleware - ¿Admin existe en BD? " . ($userExists ? 'Sí' : 'No'));

            if ($userExists) {
                if (isset($_COOKIE['remember_token'])) {
                    $token = $_COOKIE['remember_token'];
                    $tokenHash = hash('sha256', $token);

                    $stmt = $db->prepare("
                        SELECT COUNT(*) FROM admin_session_tokens
                        WHERE admin_id = :user_id AND token = :token
                    ");

                    $stmt->execute([
                        'user_id' => $userId,
                        'token' => $tokenHash
                    ]);

                    $rememberExists = $stmt->fetchColumn() > 0;
                    error_log("AuthMiddleware - ¿Existe token remember? " . ($rememberExists ? 'Sí' : 'No'));
                }

                error_log("AuthMiddleware - Admin accediendo: {$_SESSION['admin']['email']}");
                error_log("AuthMiddleware - Acceso permitido para admin");
                error_log("==================== FIN AUTHMIDDLEWARE (ACCESO PERMITIDO) ====================");
                return true;
            } else {
                error_log("AuthMiddleware - Usuario no existe en la base de datos. Forzando logout.");
                $this->forceLogout();
                error_log("AuthMiddleware - Redirigiendo a: {$adminPath}/login?error=invalid_user");
                error_log("==================== FIN AUTHMIDDLEWARE (USUARIO NO EXISTE) ====================");
                header("Location: {$adminPath}/login?error=invalid_user");
                exit;
            }
            
        } catch (\Exception $e) {
            error_log("AuthMiddleware - Error verificando sesión: " . $e->getMessage());
            error_log("AuthMiddleware - Traza: " . $e->getTraceAsString());
            error_log("==================== FIN AUTHMIDDLEWARE (ERROR DB) ====================");
            return true;
        }
    }
    
    // Si hay sesión de usuario normal (tabla users), rechazar acceso al panel admin
    if ($isUserSession) {
        error_log("AuthMiddleware - Usuario normal intentando acceder al panel admin. Acceso denegado.");
        $this->forceLogout();
        error_log("AuthMiddleware - Redirigiendo a: {$adminPath}/login?error=access_denied");
        error_log("==================== FIN AUTHMIDDLEWARE (USUARIO NORMAL RECHAZADO) ====================");
        header("Location: {$adminPath}/login?error=access_denied");
        exit;
    }

    if ($isSuperAdminSession) {
        error_log("AuthMiddleware - Super Admin detectado, acceso permitido");
        error_log("==================== FIN AUTHMIDDLEWARE (SUPER ADMIN) ====================");
        return true;
    }
    
    if (!$isSuperAdminSession && !$isAdminSession && !$isUserSession && isset($_COOKIE['remember_token'])) {
        error_log("AuthMiddleware - No hay sesión pero sí token remember. Intentando restaurar...");

        $token = $_COOKIE['remember_token'];
        $tokenHash = hash('sha256', $token);
        error_log("AuthMiddleware - Token hash: " . substr($tokenHash, 0, 10) . "...");

        try {
            $db = Database::connect();
            $superAdminToken = $db->prepare("SELECT COUNT(*) FROM super_admin_session_tokens WHERE token = ?");
            $superAdminToken->execute([$tokenHash]);
            $hasSuperAdminToken = $superAdminToken->fetchColumn() > 0;

            $adminToken = $db->prepare("SELECT COUNT(*) FROM admin_session_tokens WHERE token = ?");
            $adminToken->execute([$tokenHash]);
            $hasAdminToken = $adminToken->fetchColumn() > 0;

            // NO verificamos user_session_tokens porque los usuarios normales no deben acceder al panel admin

            error_log("AuthMiddleware - Token encontrado en: " .
                ($hasSuperAdminToken ? "super_admin " : "") .
                ($hasAdminToken ? "admin " : "") .
                "| NO SE PERMITE user_session_tokens"
            );
        } catch (\Exception $e) {
            error_log("AuthMiddleware - Error verificando token en BD: " . $e->getMessage());
        }

        if (SessionSecurity::checkRemembered()) {
            // Verificar que la sesión restaurada sea de admin o super_admin, NO de user
            if (isset($_SESSION['user'])) {
                error_log("AuthMiddleware - Token remember restauró sesión de usuario normal. Rechazando acceso.");
                $this->forceLogout();
                error_log("==================== FIN AUTHMIDDLEWARE (USER TOKEN RECHAZADO) ====================");
                header("Location: {$adminPath}/login?error=access_denied");
                exit;
            }

            error_log("AuthMiddleware - Sesión restaurada con token remember");
            error_log("AuthMiddleware - Nueva sesión: " . json_encode($_SESSION));
            error_log("==================== FIN AUTHMIDDLEWARE (SESIÓN RESTAURADA) ====================");
            return true;
        }

        error_log("AuthMiddleware - No se pudo restaurar la sesión con token remember");
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }
    
    error_log("AuthMiddleware - No hay sesión activa. Redirigiendo al login.");
    error_log("==================== FIN AUTHMIDDLEWARE (REDIRIGIENDO A LOGIN) ====================");
    header("Location: {$adminPath}/login");
    exit;
}

    
    /**
     * Fuerza el cierre de sesión
     */
    private function forceLogout() {
        error_log("AuthMiddleware - Ejecutando forceLogout()");
        
        // Capturar información de sesión antes de destruirla para diagnosticar
        $sessionInfo = json_encode($_SESSION);
        error_log("AuthMiddleware - Información de sesión antes de destruir: " . $sessionInfo);
        
        // Destruir la sesión
        SessionSecurity::destroy();
        
        // Verificar si la sesión se destruyó correctamente
        error_log("AuthMiddleware - Sesión después de destroy(): " . json_encode($_SESSION));
        
        // Asegurarse de que la cookie de remember me también se elimina
        if (isset($_COOKIE['remember_token'])) {
            error_log("AuthMiddleware - Eliminando cookie remember_token");
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
        
        error_log("AuthMiddleware - Sesión destruida completamente");
    }
    
    /**
     * Limpia los registros de actividad inactivos
     * 
     * @param int $hoursInactive Horas de inactividad para considerar una sesión expirada
     * @return array Resultado de la limpieza
     */
    public static function cleanInactiveUsers($hoursInactive = 24)
    {
        try {
            $db = Database::connect();
            $results = [
                'admin_activities' => 0,
                'user_activities' => 0,
                'super_admin_activities' => 0
            ];
            
            // Limpiar actividad de admins
            $stmt = $db->prepare("
                DELETE FROM admin_activity 
                WHERE last_active < DATE_SUB(NOW(), INTERVAL :hours HOUR)
            ");
            $stmt->execute(['hours' => $hoursInactive]);
            $results['admin_activities'] = $stmt->rowCount();
            
            // Limpiar actividad de usuarios
            $stmt = $db->prepare("
                DELETE FROM user_activity 
                WHERE last_active < DATE_SUB(NOW(), INTERVAL :hours HOUR)
            ");
            $stmt->execute(['hours' => $hoursInactive]);
            $results['user_activities'] = $stmt->rowCount();
            
            // Limpiar actividad de superadmins
            $stmt = $db->prepare("
                DELETE FROM super_admin_activity 
                WHERE last_active < DATE_SUB(NOW(), INTERVAL :hours HOUR)
            ");
            $stmt->execute(['hours' => $hoursInactive]);
            $results['super_admin_activities'] = $stmt->rowCount();
            
            $totalCount = $results['admin_activities'] + $results['user_activities'] + $results['super_admin_activities'];
            if ($totalCount > 0) {
                error_log("Limpieza: Se eliminaron {$totalCount} registros de actividad inactivos");
            }
            
            return $results;
        } catch (\Exception $e) {
            error_log("Error al limpiar usuarios inactivos: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtiene los usuarios activos actualmente en el sistema
     * 
     * @param int $minutes Minutos para considerar un usuario activo
     * @return array Lista de usuarios activos
     */
    public static function getActiveUsers($minutes = 15) 
    {
        try {
            $db = Database::connect();
            $result = [];
            
            // Obtener superadmins activos
            $stmt = $db->prepare("
                SELECT 
                    sa.id as user_id, 
                    'super_admin' as user_type, 
                    a.last_active, 
                    a.ip, 
                    a.user_agent,
                    sa.name,
                    sa.email
                FROM 
                    super_admin_activity a
                JOIN 
                    super_admins sa ON a.super_admin_id = sa.id
                WHERE 
                    a.last_active > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
            ");
            $stmt->execute(['minutes' => $minutes]);
            $result = array_merge($result, $stmt->fetchAll(\PDO::FETCH_ASSOC));
            
            // Obtener admins activos
            $stmt = $db->prepare("
                SELECT 
                    a.admin_id as user_id, 
                    'admin' as user_type, 
                    a.last_active, 
                    a.ip, 
                    a.user_agent,
                    adm.name,
                    adm.email
                FROM 
                    admin_activity a
                JOIN 
                    admins adm ON a.admin_id = adm.id
                WHERE 
                    a.last_active > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
            ");
            $stmt->execute(['minutes' => $minutes]);
            $result = array_merge($result, $stmt->fetchAll(\PDO::FETCH_ASSOC));
            
            // Obtener usuarios activos
            $stmt = $db->prepare("
                SELECT 
                    a.user_id, 
                    'user' as user_type, 
                    a.last_active, 
                    a.ip, 
                    a.user_agent,
                    u.name,
                    u.email
                FROM 
                    user_activity a
                JOIN 
                    users u ON a.user_id = u.id
                WHERE 
                    a.last_active > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
            ");
            $stmt->execute(['minutes' => $minutes]);
            $result = array_merge($result, $stmt->fetchAll(\PDO::FETCH_ASSOC));
            
            // Ordenar por última actividad
            usort($result, function($a, $b) {
                return strtotime($b['last_active']) - strtotime($a['last_active']);
            });
            
            return $result;
        } catch (\Exception $e) {
            error_log("Error al obtener usuarios activos: " . $e->getMessage());
            return [];
        }
    }
}