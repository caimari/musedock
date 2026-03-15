<?php

namespace CrossPublisher\Models;

use Screenart\Musedock\Database;

/**
 * Modelo para la red de tenants del Cross-Publisher
 */
class Network
{
    /**
     * Obtener todos los tenants de una red
     */
    public static function getNetworkTenants(string $networkKey): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT n.*, t.name as tenant_name, t.domain
            FROM cross_publish_network n
            JOIN tenants t ON n.tenant_id = t.id
            WHERE n.network_key = ? AND n.is_active = true
            ORDER BY t.name
        ");
        $stmt->execute([$networkKey]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * Obtener network_key de un tenant
     */
    public static function getNetworkKey(int $tenantId): ?string
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT network_key FROM cross_publish_network WHERE tenant_id = ? LIMIT 1");
        $stmt->execute([$tenantId]);
        $result = $stmt->fetch(\PDO::FETCH_OBJ);
        return $result ? $result->network_key : null;
    }

    /**
     * Verificar si un tenant pertenece a una red
     */
    public static function belongsToNetwork(int $tenantId, string $networkKey): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM cross_publish_network
            WHERE tenant_id = ? AND network_key = ? AND is_active = true
        ");
        $stmt->execute([$tenantId, $networkKey]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Obtener tenants destino disponibles (excluyendo el origen)
     */
    public static function getTargetTenants(int $sourceTenantId): array
    {
        $networkKey = self::getNetworkKey($sourceTenantId);

        if (!$networkKey) {
            return [];
        }

        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT n.tenant_id, t.name as tenant_name, t.domain, n.default_language
            FROM cross_publish_network n
            JOIN tenants t ON n.tenant_id = t.id
            WHERE n.network_key = ?
              AND n.is_active = true
              AND n.tenant_id != ?
              AND n.can_receive = true
            ORDER BY t.name
        ");
        $stmt->execute([$networkKey, $sourceTenantId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * Registrar un tenant en la red
     */
    public static function register(int $tenantId, string $networkKey, array $options = []): bool
    {
        $pdo = Database::connect();

        // Verificar si ya existe
        $stmt = $pdo->prepare("SELECT id FROM cross_publish_network WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);

        if ($stmt->fetch()) {
            // Actualizar
            $stmt = $pdo->prepare("
                UPDATE cross_publish_network
                SET network_key = ?,
                    default_language = ?,
                    can_publish = ?,
                    can_receive = ?,
                    is_active = true,
                    updated_at = NOW()
                WHERE tenant_id = ?
            ");
            return $stmt->execute([
                $networkKey,
                $options['default_language'] ?? 'es',
                $options['can_publish'] ?? true,
                $options['can_receive'] ?? true,
                $tenantId
            ]);
        } else {
            // Insertar
            $stmt = $pdo->prepare("
                INSERT INTO cross_publish_network
                (tenant_id, network_key, default_language, can_publish, can_receive, is_active)
                VALUES (?, ?, ?, ?, ?, true)
            ");
            return $stmt->execute([
                $tenantId,
                $networkKey,
                $options['default_language'] ?? 'es',
                $options['can_publish'] ?? true,
                $options['can_receive'] ?? true
            ]);
        }
    }

    /**
     * Obtener configuración de un tenant en la red
     */
    public static function getConfig(int $tenantId): ?object
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM cross_publish_network WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Actualizar configuración
     */
    public static function updateConfig(int $tenantId, array $data): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            UPDATE cross_publish_network
            SET default_language = ?,
                can_publish = ?,
                can_receive = ?,
                is_active = ?,
                updated_at = NOW()
            WHERE tenant_id = ?
        ");
        return $stmt->execute([
            $data['default_language'] ?? 'es',
            $data['can_publish'] ?? true,
            $data['can_receive'] ?? true,
            $data['is_active'] ?? true,
            $tenantId
        ]);
    }
}
