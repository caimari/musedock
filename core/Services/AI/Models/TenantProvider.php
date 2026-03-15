<?php

namespace Screenart\Musedock\Services\AI\Models;

use Screenart\Musedock\Database;

class TenantProvider
{
    /**
     * Obtener el proveedor activo de un tenant (si tiene key propia)
     */
    public static function getForTenant(int $tenantId): ?array
    {
        $result = Database::query(
            "SELECT * FROM ai_tenant_providers WHERE tenant_id = :tid AND active = true LIMIT 1",
            ['tid' => $tenantId]
        )->fetch();

        return $result ?: null;
    }

    /**
     * Crear o actualizar proveedor del tenant (upsert por tenant_id + provider_type)
     */
    public static function save(int $tenantId, array $data): int
    {
        // Un tenant solo tiene un proveedor activo — buscar por tenant_id
        $existing = Database::query(
            "SELECT id FROM ai_tenant_providers WHERE tenant_id = :tid LIMIT 1",
            ['tid' => $tenantId]
        )->fetch();

        if ($existing) {
            Database::query("
                UPDATE ai_tenant_providers
                SET provider_type = :type, api_key = :api_key, model = :model, max_tokens = :max_tokens,
                    temperature = :temperature, endpoint = :endpoint, active = :active, updated_at = NOW()
                WHERE id = :id
            ", [
                'type' => $data['provider_type'] ?? 'openai',
                'api_key' => $data['api_key'],
                'model' => $data['model'] ?? 'gpt-4',
                'max_tokens' => (int) ($data['max_tokens'] ?? 1500),
                'temperature' => (float) ($data['temperature'] ?? 0.7),
                'endpoint' => !empty($data['endpoint']) ? $data['endpoint'] : null,
                'active' => true,
                'id' => $existing['id']
            ]);
            return (int) $existing['id'];
        }

        Database::query("
            INSERT INTO ai_tenant_providers (tenant_id, provider_type, api_key, model, max_tokens, temperature, endpoint, active, created_at, updated_at)
            VALUES (:tid, :type, :api_key, :model, :max_tokens, :temperature, :endpoint, true, NOW(), NOW())
        ", [
            'tid' => $tenantId,
            'type' => $data['provider_type'] ?? 'openai',
            'api_key' => $data['api_key'],
            'model' => $data['model'] ?? 'gpt-4',
            'max_tokens' => (int) ($data['max_tokens'] ?? 1500),
            'temperature' => (float) ($data['temperature'] ?? 0.7),
            'endpoint' => !empty($data['endpoint']) ? $data['endpoint'] : null,
        ]);

        return (int) Database::query("SELECT currval(pg_get_serial_sequence('ai_tenant_providers', 'id'))")->fetchColumn();
    }

    /**
     * Eliminar proveedor del tenant (vuelve a usar el del sistema)
     */
    public static function delete(int $tenantId): bool
    {
        Database::query(
            "DELETE FROM ai_tenant_providers WHERE tenant_id = :tid",
            ['tid' => $tenantId]
        );
        return true;
    }
}
