<?php

namespace Screenart\Musedock\Services;

use Screenart\Musedock\Database;

/**
 * Servicio de auditoría para registrar acciones críticas
 * Registra quién hizo qué, cuándo y desde dónde
 */
class AuditLogger
{
    /**
     * Registra una acción en el log de auditoría
     *
     * @param string $action Acción realizada (ej: 'create_post', 'delete_user', 'login_attempt')
     * @param string $resourceType Tipo de recurso (ej: 'blog_post', 'user', 'category')
     * @param int|null $resourceId ID del recurso afectado
     * @param array $data Datos adicionales a registrar
     * @return bool
     */
    public static function log(string $action, string $resourceType, ?int $resourceId = null, array $data = []): bool
    {
        try {
            $pdo = Database::connect();

            // Verificar si la tabla existe
            $tableExists = $pdo->query("SHOW TABLES LIKE 'audit_logs'")->rowCount() > 0;

            if (!$tableExists) {
                // Crear tabla si no existe
                self::createAuditTable($pdo);
            }

            $userId = null;
            $userType = null;
            $tenantId = null;

            // Determinar el tipo de usuario y su ID
            if (isset($_SESSION['super_admin'])) {
                $userId = $_SESSION['super_admin']['id'] ?? null;
                $userType = 'super_admin';
                $tenantId = null; // Los super admins no tienen tenant
            } elseif (isset($_SESSION['admin'])) {
                $userId = $_SESSION['admin']['id'] ?? null;
                $userType = 'admin';
                $tenantId = $_SESSION['admin']['tenant_id'] ?? TenantManager::currentTenantId();
            } elseif (isset($_SESSION['user'])) {
                $userId = $_SESSION['user']['id'] ?? null;
                $userType = 'user';
                $tenantId = $_SESSION['user']['tenant_id'] ?? TenantManager::currentTenantId();
            }

            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (
                    user_id,
                    user_type,
                    tenant_id,
                    action,
                    resource_type,
                    resource_id,
                    data,
                    ip_address,
                    user_agent,
                    created_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $result = $stmt->execute([
                $userId,
                $userType,
                $tenantId,
                $action,
                $resourceType,
                $resourceId,
                json_encode($data),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);

            return $result;

        } catch (\Exception $e) {
            error_log("Error al registrar en audit log: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Crea la tabla de audit_logs si no existe
     */
    private static function createAuditTable(\PDO $pdo): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                user_type VARCHAR(50) NULL COMMENT 'super_admin, admin, user',
                tenant_id INT NULL,
                action VARCHAR(100) NOT NULL COMMENT 'create_post, delete_user, login_attempt, etc',
                resource_type VARCHAR(100) NOT NULL COMMENT 'blog_post, user, category, etc',
                resource_id INT NULL,
                data TEXT NULL COMMENT 'JSON con datos adicionales',
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_user (user_id, user_type),
                INDEX idx_tenant (tenant_id),
                INDEX idx_action (action),
                INDEX idx_resource (resource_type, resource_id),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Registro de auditoría de acciones críticas';
        ";

        $pdo->exec($sql);
        error_log("✅ Tabla audit_logs creada exitosamente");
    }

    /**
     * Obtiene los logs de auditoría con filtros
     *
     * @param array $filters Filtros: user_id, tenant_id, action, resource_type, date_from, date_to
     * @param int $limit Límite de registros
     * @param int $offset Offset para paginación
     * @return array
     */
    public static function getLogs(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        try {
            $pdo = Database::connect();

            $where = [];
            $params = [];

            if (isset($filters['user_id'])) {
                $where[] = "user_id = ?";
                $params[] = $filters['user_id'];
            }

            if (isset($filters['user_type'])) {
                $where[] = "user_type = ?";
                $params[] = $filters['user_type'];
            }

            if (isset($filters['tenant_id'])) {
                $where[] = "tenant_id = ?";
                $params[] = $filters['tenant_id'];
            }

            if (isset($filters['action'])) {
                $where[] = "action = ?";
                $params[] = $filters['action'];
            }

            if (isset($filters['resource_type'])) {
                $where[] = "resource_type = ?";
                $params[] = $filters['resource_type'];
            }

            if (isset($filters['date_from'])) {
                $where[] = "created_at >= ?";
                $params[] = $filters['date_from'];
            }

            if (isset($filters['date_to'])) {
                $where[] = "created_at <= ?";
                $params[] = $filters['date_to'];
            }

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "
                SELECT * FROM audit_logs
                {$whereClause}
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ";

            $params[] = $limit;
            $params[] = $offset;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            error_log("Error al obtener audit logs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Limpia logs antiguos (opcional, ejecutar periódicamente)
     *
     * @param int $daysToKeep Días de logs a mantener
     * @return int Número de registros eliminados
     */
    public static function cleanup(int $daysToKeep = 90): int
    {
        try {
            $pdo = Database::connect();

            $stmt = $pdo->prepare("
                DELETE FROM audit_logs
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");

            $stmt->execute([$daysToKeep]);

            $deletedCount = $stmt->rowCount();

            if ($deletedCount > 0) {
                error_log("🧹 Limpieza de audit_logs: {$deletedCount} registros eliminados (más de {$daysToKeep} días)");
            }

            return $deletedCount;

        } catch (\Exception $e) {
            error_log("Error al limpiar audit logs: " . $e->getMessage());
            return 0;
        }
    }
}
