<?php

namespace CrossPublisher\Models;

use Screenart\Musedock\Database;

/**
 * Modelo para la configuración del Cross-Publisher
 */
class Settings
{
    /**
     * Obtener configuración de un tenant
     */
    public static function get(int $tenantId): ?object
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM cross_publish_settings WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Guardar o actualizar configuración
     */
    public static function save(int $tenantId, array $data): bool
    {
        $pdo = Database::connect();

        $existing = self::get($tenantId);

        if ($existing) {
            $stmt = $pdo->prepare("
                UPDATE cross_publish_settings
                SET ai_provider_id = ?,
                    auto_translate = ?,
                    default_status = ?,
                    include_featured_image = ?,
                    add_canonical_link = ?,
                    add_source_credit = ?,
                    source_credit_template = ?,
                    enabled = ?,
                    updated_at = NOW()
                WHERE tenant_id = ?
            ");
            return $stmt->execute([
                $data['ai_provider_id'] ?? null,
                $data['auto_translate'] ?? false,
                $data['default_status'] ?? 'draft',
                $data['include_featured_image'] ?? true,
                $data['add_canonical_link'] ?? true,
                $data['add_source_credit'] ?? true,
                $data['source_credit_template'] ?? 'Publicado originalmente en {source_name}',
                $data['enabled'] ?? true,
                $tenantId
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO cross_publish_settings
                (tenant_id, ai_provider_id, auto_translate, default_status, include_featured_image,
                 add_canonical_link, add_source_credit, source_credit_template, enabled)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            return $stmt->execute([
                $tenantId,
                $data['ai_provider_id'] ?? null,
                $data['auto_translate'] ?? false,
                $data['default_status'] ?? 'draft',
                $data['include_featured_image'] ?? true,
                $data['add_canonical_link'] ?? true,
                $data['add_source_credit'] ?? true,
                $data['source_credit_template'] ?? 'Publicado originalmente en {source_name}',
                $data['enabled'] ?? true
            ]);
        }
    }

    /**
     * Obtener configuración con valores por defecto
     */
    public static function getWithDefaults(int $tenantId): array
    {
        $settings = self::get($tenantId);

        return [
            'ai_provider_id' => $settings->ai_provider_id ?? null,
            'auto_translate' => $settings->auto_translate ?? false,
            'default_status' => $settings->default_status ?? 'draft',
            'include_featured_image' => $settings->include_featured_image ?? true,
            'add_canonical_link' => $settings->add_canonical_link ?? true,
            'add_source_credit' => $settings->add_source_credit ?? true,
            'source_credit_template' => $settings->source_credit_template ?? 'Publicado originalmente en {source_name}',
            'enabled' => $settings->enabled ?? true
        ];
    }
}
