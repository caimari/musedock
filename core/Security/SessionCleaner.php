<?php
namespace Screenart\Musedock\Security;
use Screenart\Musedock\Database;

class SessionCleaner
{
    /**
     * Limpia tokens expirados de superadmins
     * 
     * @return int|false Número de tokens eliminados o false en caso de error
     */
    public static function cleanExpiredSuperAdminTokens()
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("DELETE FROM super_admin_session_tokens WHERE expires_at < NOW()");
            $stmt->execute();
            
            $count = $stmt->rowCount();
            if ($count > 0) {
                error_log("Se eliminaron {$count} tokens expirados de superadmins");
            }
            
            return $count;
        } catch (\Exception $e) {
            error_log("Error al limpiar tokens expirados de superadmins: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Limpia tokens expirados de admins
     * 
     * @return int|false Número de tokens eliminados o false en caso de error
     */
    public static function cleanExpiredAdminTokens()
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("DELETE FROM admin_session_tokens WHERE expires_at < NOW()");
            $stmt->execute();
            
            $count = $stmt->rowCount();
            if ($count > 0) {
                error_log("Se eliminaron {$count} tokens expirados de admins");
            }
            
            return $count;
        } catch (\Exception $e) {
            error_log("Error al limpiar tokens expirados de admins: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Limpia tokens expirados de usuarios
     * 
     * @return int|false Número de tokens eliminados o false en caso de error
     */
    public static function cleanExpiredUserTokens()
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("DELETE FROM user_session_tokens WHERE expires_at < NOW()");
            $stmt->execute();
            
            $count = $stmt->rowCount();
            if ($count > 0) {
                error_log("Se eliminaron {$count} tokens expirados de usuarios");
            }
            
            return $count;
        } catch (\Exception $e) {
            error_log("Error al limpiar tokens expirados de usuarios: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Limpia registros de actividad antiguos de superadmins
     * 
     * @param int $hours Horas de inactividad para considerar eliminable
     * @return int|false Número de registros eliminados o false en caso de error
     */
    public static function cleanInactiveSuperAdmins($hours = 48)
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("
                DELETE FROM super_admin_activity 
                WHERE last_active < DATE_SUB(NOW(), INTERVAL :hours HOUR)
            ");
            
            $stmt->execute(['hours' => $hours]);
            
            $count = $stmt->rowCount();
            if ($count > 0) {
                error_log("Se eliminaron {$count} registros de actividad de superadmins inactivos");
            }
            
            return $count;
        } catch (\Exception $e) {
            error_log("Error al limpiar superadmins inactivos: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Limpia registros de actividad antiguos de admins
     * 
     * @param int $hours Horas de inactividad para considerar eliminable
     * @return int|false Número de registros eliminados o false en caso de error
     */
    public static function cleanInactiveAdmins($hours = 48)
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("
                DELETE FROM admin_activity 
                WHERE last_active < DATE_SUB(NOW(), INTERVAL :hours HOUR)
            ");
            
            $stmt->execute(['hours' => $hours]);
            
            $count = $stmt->rowCount();
            if ($count > 0) {
                error_log("Se eliminaron {$count} registros de actividad de admins inactivos");
            }
            
            return $count;
        } catch (\Exception $e) {
            error_log("Error al limpiar admins inactivos: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Limpia registros de actividad antiguos de usuarios
     * 
     * @param int $hours Horas de inactividad para considerar eliminable
     * @return int|false Número de registros eliminados o false en caso de error
     */
    public static function cleanInactiveUsers($hours = 48)
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("
                DELETE FROM user_activity 
                WHERE last_active < DATE_SUB(NOW(), INTERVAL :hours HOUR)
            ");
            
            $stmt->execute(['hours' => $hours]);
            
            $count = $stmt->rowCount();
            if ($count > 0) {
                error_log("Se eliminaron {$count} registros de actividad de usuarios inactivos");
            }
            
            return $count;
        } catch (\Exception $e) {
            error_log("Error al limpiar usuarios inactivos: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Elimina sesiones de usuarios que no han estado activos en determinado tiempo
     * 
     * @param int $minutes Minutos de inactividad para considerar eliminable
     * @return int|false Número de sesiones eliminadas o false en caso de error
     */
    public static function cleanExpiredSessions($minutes = 30)
    {
        try {
            // Verificar si la sesión está activa - estrategia conservadora
            if (session_status() === PHP_SESSION_ACTIVE) {
                error_log("SessionCleaner - No se ejecuta session_gc() porque la sesión aún está activa.");
                
                // No intentamos cambiar la configuración de sesión cuando la sesión está activa
                error_log("SessionCleaner - gc_maxlifetime actual: " . ini_get('session.gc_maxlifetime') . " segundos");
                return true;
            }
            
            // Si llegamos aquí, no hay sesión activa, podemos actualizar gc_maxlifetime
            if (ini_get('session.gc_maxlifetime') < ($minutes * 60)) {
                ini_set('session.gc_maxlifetime', $minutes * 60);
                error_log("SessionCleaner - gc_maxlifetime actualizado a " . ($minutes * 60) . " segundos");
            }
            
            // Guardar datos de sesión actual si es necesario
            $currentSessionId = session_id();
            $currentSessionData = isset($_SESSION) ? $_SESSION : [];
            
            // Creamos una sesión temporal para ejecutar GC
            session_id('');
            session_start([
                'use_strict_mode' => 1,
                'cookie_lifetime' => 0,
                'cookie_secure' => true,
                'cookie_httponly' => true,
                'cookie_samesite' => 'Strict'
            ]);
            session_write_close();

            // Ejecutamos GC
            $gcResult = @session_gc();
            if ($gcResult === false) {
                error_log("SessionCleaner - session_gc falló al ejecutarse");
            } else {
                error_log("SessionCleaner - session_gc ejecutado correctamente");
            }

            // Restauramos la sesión del usuario si existía
            if (!empty($currentSessionId)) {
                session_id($currentSessionId);
                session_start();
                $_SESSION = $currentSessionData;
                error_log("SessionCleaner - Sesión original restaurada tras GC");
            }

            return true;
        } catch (\Exception $e) {
            error_log("Error al limpiar sesiones expiradas: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Limpia tokens de remember me para superadmins que no han usado su sesión
     * 
     * @param int $days Días de inactividad para considerar eliminable
     * @return int|false Número de tokens eliminados o false en caso de error
     */
    public static function cleanUnusedSuperAdminTokens($days = 90)
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("
                DELETE FROM super_admin_session_tokens 
                WHERE last_used_at < DATE_SUB(NOW(), INTERVAL :days DAY)
            ");
            
            $stmt->execute(['days' => $days]);
            
            $count = $stmt->rowCount();
            if ($count > 0) {
                error_log("Se eliminaron {$count} tokens sin uso de superadmins");
            }
            
            return $count;
        } catch (\Exception $e) {
            error_log("Error al limpiar tokens sin uso de superadmins: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Limpia tokens de remember me para admins que no han usado su sesión
     * 
     * @param int $days Días de inactividad para considerar eliminable
     * @return int|false Número de tokens eliminados o false en caso de error
     */
    public static function cleanUnusedAdminTokens($days = 90)
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("
                DELETE FROM admin_session_tokens 
                WHERE last_used_at < DATE_SUB(NOW(), INTERVAL :days DAY)
            ");
            
            $stmt->execute(['days' => $days]);
            
            $count = $stmt->rowCount();
            if ($count > 0) {
                error_log("Se eliminaron {$count} tokens sin uso de admins");
            }
            
            return $count;
        } catch (\Exception $e) {
            error_log("Error al limpiar tokens sin uso de admins: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Limpia tokens de remember me para usuarios que no han usado su sesión
     * 
     * @param int $days Días de inactividad para considerar eliminable
     * @return int|false Número de tokens eliminados o false en caso de error
     */
    public static function cleanUnusedUserTokens($days = 90)
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("
                DELETE FROM user_session_tokens 
                WHERE last_used_at < DATE_SUB(NOW(), INTERVAL :days DAY)
            ");
            
            $stmt->execute(['days' => $days]);
            
            $count = $stmt->rowCount();
            if ($count > 0) {
                error_log("Se eliminaron {$count} tokens sin uso de usuarios");
            }
            
            return $count;
        } catch (\Exception $e) {
            error_log("Error al limpiar tokens sin uso de usuarios: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ejecuta todas las tareas de limpieza de sesión programadas
     * 
     * @return array Resultados de las operaciones de limpieza
     */
    public static function runAllCleanupTasks()
    {
        $results = [
            'super_admin_tokens' => self::cleanExpiredSuperAdminTokens(),
            'admin_tokens' => self::cleanExpiredAdminTokens(),
            'user_tokens' => self::cleanExpiredUserTokens(),
            'inactive_super_admins' => self::cleanInactiveSuperAdmins(48), // 2 días
            'inactive_admins' => self::cleanInactiveAdmins(48), // 2 días
            'inactive_users' => self::cleanInactiveUsers(48), // 2 días
            'expired_sessions' => self::cleanExpiredSessions(30), // 30 minutos
            'unused_super_admin_tokens' => self::cleanUnusedSuperAdminTokens(90), // 90 días
            'unused_admin_tokens' => self::cleanUnusedAdminTokens(90), // 90 días
            'unused_user_tokens' => self::cleanUnusedUserTokens(90) // 90 días
        ];
        
        error_log("Limpieza de sesiones completada: " . json_encode($results));
        
        return $results;
    }
}