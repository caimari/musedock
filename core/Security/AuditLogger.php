<?php

namespace Screenart\Musedock\Security;

use Screenart\Musedock\Logger;
use Screenart\Musedock\Database;

/**
 * AuditLogger - Sistema de auditoría para eventos de seguridad
 *
 * Registra eventos críticos del sistema para cumplimiento, forense y detección de amenazas.
 *
 * Eventos auditables:
 * - Autenticación (login, logout, intentos fallidos)
 * - Autorización (acceso denegado, escalación de privilegios)
 * - Modificación de datos críticos (usuarios, roles, permisos)
 * - Cambios de configuración
 * - Operaciones administrativas
 * - Violaciones de seguridad
 *
 * @package Screenart\Musedock\Security
 */
class AuditLogger
{
    const EVENT_LOGIN_SUCCESS = 'auth.login.success';
    const EVENT_LOGIN_FAILED = 'auth.login.failed';
    const EVENT_LOGOUT = 'auth.logout';
    const EVENT_PASSWORD_CHANGED = 'auth.password.changed';
    const EVENT_PASSWORD_RESET_REQUESTED = 'auth.password.reset_requested';
    const EVENT_PASSWORD_RESET_COMPLETED = 'auth.password.reset_completed';

    const EVENT_USER_CREATED = 'user.created';
    const EVENT_USER_UPDATED = 'user.updated';
    const EVENT_USER_DELETED = 'user.deleted';
    const EVENT_USER_ROLE_CHANGED = 'user.role.changed';

    const EVENT_PERMISSION_GRANTED = 'permission.granted';
    const EVENT_PERMISSION_REVOKED = 'permission.revoked';
    const EVENT_ACCESS_DENIED = 'permission.access_denied';

    const EVENT_CONFIG_CHANGED = 'config.changed';
    const EVENT_TENANT_CREATED = 'tenant.created';
    const EVENT_TENANT_UPDATED = 'tenant.updated';
    const EVENT_TENANT_DELETED = 'tenant.deleted';

    const EVENT_SECURITY_VIOLATION = 'security.violation';
    const EVENT_RATE_LIMIT_EXCEEDED = 'security.rate_limit_exceeded';
    const EVENT_CSRF_VIOLATION = 'security.csrf_violation';
    const EVENT_FILE_UPLOAD_REJECTED = 'security.file_upload_rejected';
    const EVENT_SQL_INJECTION_ATTEMPT = 'security.sql_injection_attempt';
    const EVENT_XSS_ATTEMPT = 'security.xss_attempt';

    const SEVERITY_INFO = 'INFO';
    const SEVERITY_WARNING = 'WARNING';
    const SEVERITY_CRITICAL = 'CRITICAL';

    /**
     * Registra un evento de auditoría
     *
     * @param string $eventType Tipo de evento (usar constantes EVENT_*)
     * @param string $severity Severidad (INFO, WARNING, CRITICAL)
     * @param array $data Datos adicionales del evento
     * @return bool
     */
    public static function log(string $eventType, string $severity = self::SEVERITY_INFO, array $data = []): bool
    {
        try {
            // Recopilar contexto automático
            $context = self::gatherContext();

            // Preparar datos del evento
            $eventData = [
                'event_type' => $eventType,
                'severity' => $severity,
                'user_id' => $context['user_id'],
                'user_type' => $context['user_type'],
                'tenant_id' => $context['tenant_id'],
                'ip_address' => $context['ip'],
                'user_agent' => $context['user_agent'],
                'uri' => $context['uri'],
                'method' => $context['method'],
                'data' => json_encode($data),
                'created_at' => date('Y-m-d H:i:s')
            ];

            // Loguear también en archivos para redundancia
            Logger::log("AUDIT: {$eventType}", $severity, array_merge($context, $data));

            // Intentar guardar en base de datos
            return self::saveToDatabase($eventData);

        } catch (\Exception $e) {
            // Si falla, al menos loguear en archivos
            Logger::exception($e, 'ERROR', ['context' => 'AuditLogger failed']);
            return false;
        }
    }

    /**
     * Registra evento de login exitoso
     */
    public static function logLoginSuccess(int $userId, string $userType, array $additionalData = []): bool
    {
        return self::log(
            self::EVENT_LOGIN_SUCCESS,
            self::SEVERITY_INFO,
            array_merge(['user_id' => $userId, 'user_type' => $userType], $additionalData)
        );
    }

    /**
     * Registra intento de login fallido
     */
    public static function logLoginFailed(string $email, string $reason, array $additionalData = []): bool
    {
        return self::log(
            self::EVENT_LOGIN_FAILED,
            self::SEVERITY_WARNING,
            array_merge(['email' => $email, 'reason' => $reason], $additionalData)
        );
    }

    /**
     * Registra evento de logout
     */
    public static function logLogout(int $userId, string $userType): bool
    {
        return self::log(
            self::EVENT_LOGOUT,
            self::SEVERITY_INFO,
            ['user_id' => $userId, 'user_type' => $userType]
        );
    }

    /**
     * Registra cambio de contraseña
     */
    public static function logPasswordChanged(int $userId, string $userType, bool $forced = false): bool
    {
        return self::log(
            self::EVENT_PASSWORD_CHANGED,
            self::SEVERITY_WARNING,
            ['user_id' => $userId, 'user_type' => $userType, 'forced' => $forced]
        );
    }

    /**
     * Registra creación de usuario
     */
    public static function logUserCreated(int $userId, string $userType, string $email): bool
    {
        return self::log(
            self::EVENT_USER_CREATED,
            self::SEVERITY_INFO,
            ['created_user_id' => $userId, 'user_type' => $userType, 'email' => $email]
        );
    }

    /**
     * Registra modificación de usuario
     */
    public static function logUserUpdated(int $userId, string $userType, array $changedFields): bool
    {
        return self::log(
            self::EVENT_USER_UPDATED,
            self::SEVERITY_INFO,
            ['modified_user_id' => $userId, 'user_type' => $userType, 'fields' => $changedFields]
        );
    }

    /**
     * Registra eliminación de usuario
     */
    public static function logUserDeleted(int $userId, string $userType, string $email): bool
    {
        return self::log(
            self::EVENT_USER_DELETED,
            self::SEVERITY_WARNING,
            ['deleted_user_id' => $userId, 'user_type' => $userType, 'email' => $email]
        );
    }

    /**
     * Registra cambio de rol
     */
    public static function logRoleChanged(int $userId, array $oldRoles, array $newRoles): bool
    {
        return self::log(
            self::EVENT_USER_ROLE_CHANGED,
            self::SEVERITY_WARNING,
            [
                'user_id' => $userId,
                'old_roles' => $oldRoles,
                'new_roles' => $newRoles
            ]
        );
    }

    /**
     * Registra acceso denegado
     */
    public static function logAccessDenied(string $resource, string $permission, string $reason): bool
    {
        return self::log(
            self::EVENT_ACCESS_DENIED,
            self::SEVERITY_WARNING,
            [
                'resource' => $resource,
                'permission' => $permission,
                'reason' => $reason
            ]
        );
    }

    /**
     * Registra violación de seguridad
     */
    public static function logSecurityViolation(string $type, string $description, array $data = []): bool
    {
        return self::log(
            self::EVENT_SECURITY_VIOLATION,
            self::SEVERITY_CRITICAL,
            array_merge(['type' => $type, 'description' => $description], $data)
        );
    }

    /**
     * Registra violación CSRF
     */
    public static function logCsrfViolation(): bool
    {
        return self::log(
            self::EVENT_CSRF_VIOLATION,
            self::SEVERITY_CRITICAL,
            ['referer' => $_SERVER['HTTP_REFERER'] ?? 'none']
        );
    }

    /**
     * Registra rate limiting excedido
     */
    public static function logRateLimitExceeded(string $identifier, int $attempts): bool
    {
        return self::log(
            self::EVENT_RATE_LIMIT_EXCEEDED,
            self::SEVERITY_WARNING,
            ['identifier' => $identifier, 'attempts' => $attempts]
        );
    }

    /**
     * Recopila contexto de la petición actual
     */
    private static function gatherContext(): array
    {
        $userId = null;
        $userType = 'guest';
        $tenantId = null;

        // Detectar usuario autenticado
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (isset($_SESSION['super_admin'])) {
            $userId = $_SESSION['super_admin']['id'] ?? null;
            $userType = 'super_admin';
        } elseif (isset($_SESSION['admin'])) {
            $userId = $_SESSION['admin']['id'] ?? null;
            $userType = 'admin';
            $tenantId = $_SESSION['admin']['tenant_id'] ?? null;
        } elseif (isset($_SESSION['user'])) {
            $userId = $_SESSION['user']['id'] ?? null;
            $userType = 'user';
            $tenantId = $_SESSION['user']['tenant_id'] ?? null;
        }

        // Detectar tenant desde global si no está en sesión
        if (!$tenantId && isset($GLOBALS['tenant'])) {
            $tenantId = $GLOBALS['tenant']['id'] ?? null;
        }

        return [
            'user_id' => $userId,
            'user_type' => $userType,
            'tenant_id' => $tenantId,
            'ip' => self::getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Obtiene la IP real del cliente (considerando proxies)
     */
    private static function getClientIp(): string
    {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP',  // Cloudflare
            'HTTP_X_FORWARDED_FOR',   // Proxies estándar
            'HTTP_X_REAL_IP',          // Nginx
            'REMOTE_ADDR'              // Directo
        ];

        foreach ($ipKeys as $key) {
            if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
                // Si es X-Forwarded-For, puede tener múltiples IPs
                if ($key === 'HTTP_X_FORWARDED_FOR') {
                    $ips = explode(',', $_SERVER[$key]);
                    $ip = trim($ips[0]);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                } else {
                    return $_SERVER[$key];
                }
            }
        }

        return 'unknown';
    }

    /**
     * Guarda el evento en la base de datos
     */
    private static function saveToDatabase(array $eventData): bool
    {
        try {
            $pdo = Database::connect();

            // Verificar si la tabla existe
            $stmt = $pdo->query("SHOW TABLES LIKE 'audit_logs'");
            if ($stmt->rowCount() === 0) {
                // La tabla no existe, solo loguear en archivos
                Logger::warning("Tabla audit_logs no existe. Cree la tabla usando el script SQL proporcionado.");
                return false;
            }

            // Insertar evento
            $sql = "INSERT INTO audit_logs (
                event_type, severity, user_id, user_type, tenant_id,
                ip_address, user_agent, uri, method, data, created_at
            ) VALUES (
                :event_type, :severity, :user_id, :user_type, :tenant_id,
                :ip_address, :user_agent, :uri, :method, :data, :created_at
            )";

            $stmt = $pdo->prepare($sql);
            return $stmt->execute($eventData);

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['context' => 'AuditLogger::saveToDatabase']);
            return false;
        }
    }

    /**
     * Consulta logs de auditoría
     *
     * @param array $filters Filtros opcionales
     * @param int $limit Límite de resultados
     * @return array
     */
    public static function query(array $filters = [], int $limit = 100): array
    {
        try {
            $pdo = Database::connect();

            $sql = "SELECT * FROM audit_logs WHERE 1=1";
            $params = [];

            if (!empty($filters['event_type'])) {
                $sql .= " AND event_type = :event_type";
                $params[':event_type'] = $filters['event_type'];
            }

            if (!empty($filters['user_id'])) {
                $sql .= " AND user_id = :user_id";
                $params[':user_id'] = $filters['user_id'];
            }

            if (!empty($filters['severity'])) {
                $sql .= " AND severity = :severity";
                $params[':severity'] = $filters['severity'];
            }

            if (!empty($filters['from_date'])) {
                $sql .= " AND created_at >= :from_date";
                $params[':from_date'] = $filters['from_date'];
            }

            if (!empty($filters['to_date'])) {
                $sql .= " AND created_at <= :to_date";
                $params[':to_date'] = $filters['to_date'];
            }

            $sql .= " ORDER BY created_at DESC LIMIT :limit";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['context' => 'AuditLogger::query']);
            return [];
        }
    }

    /**
     * Limpia logs antiguos (mantener solo últimos N días)
     *
     * @param int $days Días a conservar (por defecto 90)
     * @return int Número de registros eliminados
     */
    public static function cleanup(int $days = 90): int
    {
        try {
            $pdo = Database::connect();

            $sql = "DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':days' => $days]);

            $deletedCount = $stmt->rowCount();
            Logger::info("AuditLogger cleanup: eliminados {$deletedCount} registros antiguos");

            return $deletedCount;

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['context' => 'AuditLogger::cleanup']);
            return 0;
        }
    }
}
