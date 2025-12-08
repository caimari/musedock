<?php
namespace Screenart\Musedock\Security;

use Screenart\Musedock\Database;

class SessionSecurity
{
    const TIMEOUT = 1800; // 30 minutos (solo para sesiones normales)
    const TIMEOUT_PERSISTENT = 86400; // 24 horas (para sesiones "recordarme")
    const PERSISTENT_DAYS = 30;
    
    /**
     * Obtiene la conexión a la base de datos
     * 
     * @return \PDO
     */
    private static function getDatabase()
    {
        return Database::connect();
    }

    /**
     * Inicia o recupera la sesión y verifica la inactividad
     */
    public static function startSession()
    {
        static $sessionStarted = false;

        if (session_status() !== PHP_SESSION_ACTIVE) {
            // Cargar configuración de seguridad
            $config = require __DIR__ . '/../../config/config.php';
            $security = $config['security'] ?? [];

            // Configurar parámetros de sesión ANTES de session_start()
            ini_set('session.cookie_httponly', $security['session_httponly'] ?? 1);
            ini_set('session.cookie_secure', $security['session_secure'] ?? 1);
            ini_set('session.cookie_samesite', $security['session_samesite'] ?? 'Strict');
            ini_set('session.use_strict_mode', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.sid_length', 48);
            ini_set('session.sid_bits_per_character', 6);

            // Configurar parámetros de cookie de sesión
            session_set_cookie_params([
                'lifetime' => $security['session_lifetime'] ?? 7200,
                'path' => '/',
                'domain' => '',
                'secure' => $security['session_secure'] ?? true,
                'httponly' => $security['session_httponly'] ?? true,
                'samesite' => $security['session_samesite'] ?? 'Strict'
            ]);

            session_start();
            $sessionStarted = true;

            // Regenerar ID de sesión periódicamente
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
            } elseif (time() - $_SESSION['created'] > 1800) { // 30 minutos
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }

        // Expiración por inactividad
        // Usar timeout extendido si la sesión es persistente (remember me)
        $isPersistent = isset($_SESSION['persistent']) && $_SESSION['persistent'] === true;
        $timeout = $isPersistent ? self::TIMEOUT_PERSISTENT : self::TIMEOUT;

        if (isset($_SESSION['last_active']) && time() - $_SESSION['last_active'] > $timeout) {
            // Si la sesión expiró, intentar restaurar desde token "remember me" antes de destruir
            if (isset($_COOKIE['remember_token'])) {
                error_log("SessionSecurity - Sesión expirada, intentando restaurar desde token remember me");

                // NO destruir la sesión completamente, solo limpiar datos de usuario
                // Esto preserva el ID de sesión y el token CSRF
                $csrfToken = $_SESSION['_csrf_token'] ?? null;
                $created = $_SESSION['created'] ?? null;

                unset($_SESSION['super_admin']);
                unset($_SESSION['admin']);
                unset($_SESSION['user']);
                unset($_SESSION['persistent']);
                unset($_SESSION['last_active']);

                // Intentar restaurar (pasar true para no llamar startSession() de nuevo)
                if (self::checkRemembered(true)) {
                    error_log("SessionSecurity - Sesión restaurada exitosamente desde token");
                    $_SESSION['last_active'] = time();

                    // Restaurar CSRF token si existía
                    if ($csrfToken) {
                        $_SESSION['_csrf_token'] = $csrfToken;
                    }
                    if ($created) {
                        $_SESSION['created'] = $created;
                    }
                    return;
                }

                error_log("SessionSecurity - No se pudo restaurar sesión desde token, destruyendo completamente");
                self::destroy();
            } else {
                self::destroy();
            }
            return;
        }

        $_SESSION['last_active'] = time();
		
		
		 // Prueba directa para registrar actividad
    if (isset($_SESSION['super_admin'])) {
        try {
            $db = self::getDatabase();
            $superAdminId = (int)$_SESSION['super_admin']['id'];
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            $stmt = $db->prepare("
                INSERT INTO super_admin_activity 
                (super_admin_id, last_active, ip, user_agent) 
                VALUES (:id, NOW(), :ip, :user_agent)
                ON DUPLICATE KEY UPDATE 
                    last_active = NOW(),
                    ip = :ip,
                    user_agent = :user_agent
            ");
            
            $stmt->execute([
                'id' => $superAdminId,
                'ip' => $ip,
                'user_agent' => $userAgent
            ]);
            
            error_log("Actividad de super_admin {$superAdminId} registrada directamente");
        } catch (\Exception $e) {
            error_log("Error al registrar actividad directamente: " . $e->getMessage());
        }
    }
        
        // Actualizar registro de actividad solo la primera vez que se llama a startSession()
        if ($sessionStarted) {
            self::updateUserActivity();
        }
    }

    /**
     * Obtiene información del usuario autenticado actualmente
     * 
     * @return array|null Datos del usuario o null si no está autenticado
     */
    public static function getAuthenticatedUser(): ?array
    {
        if (isset($_SESSION['super_admin'])) {
            return [
                'id' => (int)$_SESSION['super_admin']['id'],
                'tenant_id' => null,
                'type' => 'super_admin',
                'role' => $_SESSION['super_admin']['role'] ?? 'superadmin',
                'name' => $_SESSION['super_admin']['name'] ?? null,
                'email' => $_SESSION['super_admin']['email'] ?? null,
                'avatar' => $_SESSION['super_admin']['avatar'] ?? null
            ];
        }

        if (isset($_SESSION['admin'])) {
            return [
                'id' => (int)$_SESSION['admin']['id'],
                'tenant_id' => $_SESSION['admin']['tenant_id'] ?? null,
                'type' => 'admin',
                'role' => $_SESSION['admin']['role'] ?? 'admin',
                'name' => $_SESSION['admin']['name'] ?? null,
                'email' => $_SESSION['admin']['email'] ?? null,
                'avatar' => $_SESSION['admin']['avatar'] ?? null
            ];
        }

        if (isset($_SESSION['user'])) {
            return [
                'id' => (int)$_SESSION['user']['id'],
                'tenant_id' => $_SESSION['user']['tenant_id'] ?? null,
                'type' => 'user',
                'role' => $_SESSION['user']['role'] ?? 'user',
                'name' => $_SESSION['user']['name'] ?? null,
                'email' => $_SESSION['user']['email'] ?? null,
                'avatar' => $_SESSION['user']['avatar'] ?? null
            ];
        }

        return null;
    }

 /**
 * Actualiza el registro de actividad del usuario en la base de datos
 * Utiliza tablas específicas según el tipo de usuario
 */
private static function updateUserActivity()
{
    // Verificar si hay un usuario autenticado
    if (!isset($_SESSION['super_admin']) && !isset($_SESSION['admin']) && !isset($_SESSION['user'])) {
        return;
    }
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $db = self::getDatabase();
        
    try {
        // Super Admin
        if (isset($_SESSION['super_admin'])) {
            $superAdminId = (int)$_SESSION['super_admin']['id'];
            
            $stmt = $db->prepare("
                INSERT INTO super_admin_activity 
                (super_admin_id, last_active, ip, user_agent) 
                VALUES (:id, NOW(), :ip, :user_agent)
                ON DUPLICATE KEY UPDATE 
                    last_active = NOW(),
                    ip = :ip,
                    user_agent = :user_agent
            ");
            
            $stmt->execute([
                'id' => $superAdminId,
                'ip' => $ip,
                'user_agent' => $userAgent
            ]);
            
            error_log("SessionSecurity - Actividad actualizada para super_admin {$superAdminId}");
        }
        
        // Admin
        elseif (isset($_SESSION['admin'])) {
            $adminId = (int)$_SESSION['admin']['id'];
            $tenantId = $_SESSION['admin']['tenant_id'] ?? null;
            
            $stmt = $db->prepare("
                INSERT INTO admin_activity 
                (admin_id, tenant_id, last_active, ip, user_agent) 
                VALUES (:id, :tenant_id, NOW(), :ip, :user_agent)
                ON DUPLICATE KEY UPDATE 
                    last_active = NOW(),
                    tenant_id = :tenant_id,
                    ip = :ip,
                    user_agent = :user_agent
            ");
            
            $stmt->execute([
                'id' => $adminId,
                'tenant_id' => $tenantId,
                'ip' => $ip,
                'user_agent' => $userAgent
            ]);
            
            error_log("SessionSecurity - Actividad actualizada para admin {$adminId}");
        }
        
        // Usuario normal
        elseif (isset($_SESSION['user'])) {
            $userId = (int)$_SESSION['user']['id'];
            $tenantId = $_SESSION['user']['tenant_id'] ?? null;
            
            $stmt = $db->prepare("
                INSERT INTO user_activity 
                (user_id, tenant_id, last_active, ip, user_agent) 
                VALUES (:id, :tenant_id, NOW(), :ip, :user_agent)
                ON DUPLICATE KEY UPDATE 
                    last_active = NOW(),
                    tenant_id = :tenant_id,
                    ip = :ip,
                    user_agent = :user_agent
            ");
            
            $stmt->execute([
                'id' => $userId,
                'tenant_id' => $tenantId,
                'ip' => $ip,
                'user_agent' => $userAgent
            ]);
            
            error_log("SessionSecurity - Actividad actualizada para user {$userId}");
        }
    } catch (\Exception $e) {
        $userType = isset($_SESSION['super_admin']) ? 'super_admin' : 
                   (isset($_SESSION['admin']) ? 'admin' : 
                   (isset($_SESSION['user']) ? 'user' : 'unknown'));
        $userId = isset($_SESSION['super_admin']) ? $_SESSION['super_admin']['id'] : 
                (isset($_SESSION['admin']) ? $_SESSION['admin']['id'] : 
                (isset($_SESSION['user']) ? $_SESSION['user']['id'] : 0));
        
        error_log("Error al actualizar actividad de {$userType} {$userId}: " . $e->getMessage());
    }
}
    /**
     * Regenera el ID de sesión, manteniendo los datos
     */
    public static function regenerate()
    {
        $oldSessionId = session_id();
        session_regenerate_id(true);
        $newSessionId = session_id();
        
        error_log("SessionSecurity - Sesión regenerada: {$oldSessionId} -> {$newSessionId}");
    }

    /**
     * Destruye la sesión actual y elimina cookies relacionadas
     */
    public static function destroy()
    {
        // Obtener información del usuario antes de limpiar la sesión
        $user = self::getAuthenticatedUser();
        
        // Eliminar el token remember me de la base de datos si existe
        if ($user && isset($_COOKIE['remember_token'])) {
            try {
                $token = $_COOKIE['remember_token'];
                $tokenHash = hash('sha256', $token);
                $db = self::getDatabase();
                
                switch ($user['type']) {
                    case 'super_admin':
                        $sql = "DELETE FROM super_admin_session_tokens WHERE token = :token";
                        break;
                    case 'admin':
                        $sql = "DELETE FROM admin_session_tokens WHERE token = :token";
                        break;
                    case 'user':
                        $sql = "DELETE FROM user_session_tokens WHERE token = :token";
                        break;
                    default:
                        $sql = null;
                }
                
                if ($sql) {
                    $stmt = $db->prepare($sql);
                    $stmt->execute(['token' => $tokenHash]);
                    
                    $rowsAffected = $stmt->rowCount();
                    error_log("Token remember eliminado para {$user['type']}: " . ($rowsAffected > 0 ? 'Sí' : 'No'));
                }
            } catch (\Exception $e) {
                error_log("Error al eliminar token de la base de datos: " . $e->getMessage());
            }
        }
        
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            setcookie(session_name(), '', time() - 3600, "/");
        }

        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        session_destroy();
        
        error_log("SessionSecurity - Sesión destruida");
    }

    /**
     * Crea un token "recordarme" para un super_admin
     * 
     * @param int $userId ID del super_admin
     * @return bool
     */
    public static function rememberSuperAdmin($userId)
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);

        try {
            $db = self::getDatabase();
            
            // Eliminar tokens antiguos primero
            $stmt = $db->prepare("DELETE FROM super_admin_session_tokens WHERE super_admin_id = :id");
            $stmt->execute(['id' => $userId]);
            
            // Crear nuevo token
            $stmt = $db->prepare("
                INSERT INTO super_admin_session_tokens 
                (super_admin_id, token, ip, user_agent, persistent, created_at, expires_at, last_used_at)
                VALUES 
                (:id, :token, :ip, :agent, 1, NOW(), DATE_ADD(NOW(), INTERVAL :days DAY), NOW())
            ");

            $stmt->execute([
                'id' => $userId,
                'token' => $hash,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'days' => self::PERSISTENT_DAYS
            ]);

            setcookie(
                'remember_token', 
                $token, 
                [
                    'expires' => time() + (86400 * self::PERSISTENT_DAYS),
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );
            
            error_log("Token remember creado para super_admin {$userId}");
            return true;
        } catch (\Exception $e) {
            error_log("Error al crear token remember para super_admin: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Crea un token "recordarme" para un admin
     * 
     * @param int $userId ID del admin
     * @return bool
     */
    public static function rememberAdmin($userId)
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);

        try {
            $db = self::getDatabase();
            
            // Eliminar tokens antiguos primero
            $stmt = $db->prepare("DELETE FROM admin_session_tokens WHERE admin_id = :id");
            $stmt->execute(['id' => $userId]);
            
            // Crear nuevo token
            $stmt = $db->prepare("
                INSERT INTO admin_session_tokens 
                (admin_id, token, ip, user_agent, persistent, created_at, expires_at, last_used_at)
                VALUES 
                (:id, :token, :ip, :agent, 1, NOW(), DATE_ADD(NOW(), INTERVAL :days DAY), NOW())
            ");

            $stmt->execute([
                'id' => $userId,
                'token' => $hash,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'days' => self::PERSISTENT_DAYS
            ]);

            setcookie(
                'remember_token', 
                $token, 
                [
                    'expires' => time() + (86400 * self::PERSISTENT_DAYS),
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );
            
            error_log("Token remember creado para admin {$userId}");
            return true;
        } catch (\Exception $e) {
            error_log("Error al crear token remember para admin: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Crea un token "recordarme" para un usuario normal
     * 
     * @param int $userId ID del usuario
     * @return bool
     */
    public static function rememberUser($userId)
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);

        try {
            $db = self::getDatabase();
            
            // Eliminar tokens antiguos primero
            $stmt = $db->prepare("DELETE FROM user_session_tokens WHERE user_id = :id");
            $stmt->execute(['id' => $userId]);
            
            // Crear nuevo token
            $stmt = $db->prepare("
                INSERT INTO user_session_tokens 
                (user_id, token, ip, user_agent, persistent, created_at, expires_at, last_used_at)
                VALUES 
                (:id, :token, :ip, :agent, 1, NOW(), DATE_ADD(NOW(), INTERVAL :days DAY), NOW())
            ");

            $stmt->execute([
                'id' => $userId,
                'token' => $hash,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'days' => self::PERSISTENT_DAYS
            ]);

            setcookie(
                'remember_token', 
                $token, 
                [
                    'expires' => time() + (86400 * self::PERSISTENT_DAYS),
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );
            
            error_log("Token remember creado para user {$userId}");
            return true;
        } catch (\Exception $e) {
            error_log("Error al crear token remember para user: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Método general para crear un token "recordarme" basado en el tipo de usuario
     * 
     * @param int $userId ID del usuario
     * @param string $userType Tipo de usuario
     * @return bool
     */
    public static function rememberMe($userId, $userType)
    {
        switch ($userType) {
            case 'super_admin':
                return self::rememberSuperAdmin($userId);
            case 'admin':
                return self::rememberAdmin($userId);
            case 'user':
                return self::rememberUser($userId);
            default:
                error_log("Tipo de usuario no válido para remember: {$userType}");
                return false;
        }
    }

    /**
     * Verifica si hay un token "recordarme" válido y restaura la sesión para cualquier tipo de usuario
     *
     * @param bool $skipSessionStart Si es true, no llama a startSession() (usado internamente)
     * @return bool
     */
    public static function checkRemembered($skipSessionStart = false)
    {
        if (!isset($_COOKIE['remember_token'])) {
            return false;
        }
        
        $token = $_COOKIE['remember_token'];
        $tokenHash = hash('sha256', $token);
        $db = self::getDatabase();
        
        try {
            // 1. Verificar si es un super_admin
            $stmt = $db->prepare("
                SELECT st.*, sa.* 
                FROM super_admin_session_tokens st
                JOIN super_admins sa ON st.super_admin_id = sa.id
                WHERE st.token = :token 
                AND st.expires_at > NOW()
            ");
            
            $stmt->execute(['token' => $tokenHash]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($result) {
                // Es un super_admin
                if (!$skipSessionStart) {
                    self::startSession();
                    self::regenerate();
                }

                $_SESSION['super_admin'] = [
                    'id' => $result['super_admin_id'],
                    'email' => $result['email'],
                    'name' => $result['name'] ?? 'Super Admin',
                    'role' => $result['role'] ?? 'superadmin',
                    'avatar' => $result['avatar'] ?? null
                ];

                // Marcar sesión como persistente
                $_SESSION['persistent'] = true;

                // Actualizar la fecha de último uso del token
                $db->prepare("
                    UPDATE super_admin_session_tokens 
                    SET last_used_at = NOW()
                    WHERE token = :token
                ")->execute(['token' => $tokenHash]);
                
                // Refrescar token si está próximo a expirar
                self::refreshSuperAdminToken($result['super_admin_id']);
                
                error_log("Sesión restaurada desde token para super_admin: {$result['super_admin_id']}");
                return true;
            }
            
            // 2. Verificar si es un admin
            $stmt = $db->prepare("
                SELECT st.*, a.* 
                FROM admin_session_tokens st
                JOIN admins a ON st.admin_id = a.id
                WHERE st.token = :token 
                AND st.expires_at > NOW()
            ");
            
            $stmt->execute(['token' => $tokenHash]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($result) {
                // Es un admin
                if (!$skipSessionStart) {
                    self::startSession();
                    self::regenerate();
                }

                $_SESSION['admin'] = [
                    'id' => $result['admin_id'],
                    'email' => $result['email'],
                    'name' => $result['name'] ?? 'Admin',
                    'tenant_id' => $result['tenant_id'],
                    'role' => 'admin',
                    'avatar' => $result['avatar'] ?? null
                ];

                // Marcar sesión como persistente
                $_SESSION['persistent'] = true;

                // Actualizar la fecha de último uso del token
                $db->prepare("
                    UPDATE admin_session_tokens 
                    SET last_used_at = NOW()
                    WHERE token = :token
                ")->execute(['token' => $tokenHash]);
                
                // Refrescar token si está próximo a expirar
                self::refreshAdminToken($result['admin_id']);
                
                error_log("Sesión restaurada desde token para admin: {$result['admin_id']}");
                return true;
            }
            
            // 3. Verificar si es un usuario normal
            $stmt = $db->prepare("
                SELECT st.*, u.* 
                FROM user_session_tokens st
                JOIN users u ON st.user_id = u.id
                WHERE st.token = :token 
                AND st.expires_at > NOW()
            ");
            
            $stmt->execute(['token' => $tokenHash]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($result) {
                // Es un usuario normal
                if (!$skipSessionStart) {
                    self::startSession();
                    self::regenerate();
                }

                $_SESSION['user'] = [
                    'id' => $result['user_id'],
                    'email' => $result['email'],
                    'name' => $result['name'] ?? 'Usuario',
                    'tenant_id' => $result['tenant_id'],
                    'avatar' => $result['avatar'] ?? null
                ];

                // Marcar sesión como persistente
                $_SESSION['persistent'] = true;

                // Actualizar la fecha de último uso del token
                $db->prepare("
                    UPDATE user_session_tokens 
                    SET last_used_at = NOW()
                    WHERE token = :token
                ")->execute(['token' => $tokenHash]);
                
                // Refrescar token si está próximo a expirar
                self::refreshUserToken($result['user_id']);
                
                error_log("Sesión restaurada desde token para user: {$result['user_id']}");
                return true;
            }
            
            // Si llegamos aquí, el token no es válido para ningún tipo de usuario
            error_log("Token remember no válido para ningún tipo de usuario");
            return false;
            
        } catch (\Exception $e) {
            error_log("Error al verificar token remember: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Refresca el token de super_admin si está próximo a expirar
     * 
     * @param int $userId ID del super_admin
     */
    private static function refreshSuperAdminToken($userId)
    {
        try {
            $db = self::getDatabase();
            $stmt = $db->prepare("
                UPDATE super_admin_session_tokens
                SET expires_at = DATE_ADD(NOW(), INTERVAL :days DAY)
                WHERE super_admin_id = :id 
                AND expires_at < DATE_ADD(NOW(), INTERVAL 7 DAY)
            ");
            
            $stmt->execute([
                'id' => $userId,
                'days' => self::PERSISTENT_DAYS
            ]);
            
            if ($stmt->rowCount() > 0) {
                error_log("Token remember refrescado para super_admin {$userId}");
            }
        } catch (\Exception $e) {
            error_log("Error al refrescar token remember de super_admin: " . $e->getMessage());
        }
    }
    
    /**
     * Refresca el token de admin si está próximo a expirar
     * 
     * @param int $userId ID del admin
     */
    private static function refreshAdminToken($userId)
    {
        try {
            $db = self::getDatabase();
            $stmt = $db->prepare("
                UPDATE admin_session_tokens
                SET expires_at = DATE_ADD(NOW(), INTERVAL :days DAY)
                WHERE admin_id = :id 
                AND expires_at < DATE_ADD(NOW(), INTERVAL 7 DAY)
            ");
            
            $stmt->execute([
                'id' => $userId,
                'days' => self::PERSISTENT_DAYS
            ]);
            
            if ($stmt->rowCount() > 0) {
                error_log("Token remember refrescado para admin {$userId}");
            }
        } catch (\Exception $e) {
            error_log("Error al refrescar token remember de admin: " . $e->getMessage());
        }
    }
    
    /**
     * Refresca el token de usuario si está próximo a expirar
     * 
     * @param int $userId ID del usuario
     */
    private static function refreshUserToken($userId)
    {
        try {
            $db = self::getDatabase();
            $stmt = $db->prepare("
                UPDATE user_session_tokens
                SET expires_at = DATE_ADD(NOW(), INTERVAL :days DAY)
                WHERE user_id = :id 
                AND expires_at < DATE_ADD(NOW(), INTERVAL 7 DAY)
            ");
            
            $stmt->execute([
                'id' => $userId,
                'days' => self::PERSISTENT_DAYS
            ]);
            
            if ($stmt->rowCount() > 0) {
                error_log("Token remember refrescado para user {$userId}");
            }
        } catch (\Exception $e) {
            error_log("Error al refrescar token remember de user: " . $e->getMessage());
        }
    }
}