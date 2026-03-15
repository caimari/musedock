<?php

namespace NewsAggregator\Models;

use Screenart\Musedock\Database;

/**
 * Modelo para la configuración del News Aggregator
 */
class Settings
{
    /**
     * Obtener configuración de un tenant
     */
    public static function get(int $tenantId): ?object
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM news_aggregator_settings WHERE tenant_id = ?");
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
                UPDATE news_aggregator_settings
                SET ai_provider_id = ?,
                    output_language = ?,
                    rewrite_prompt = ?,
                    default_category_id = ?,
                    auto_rewrite = ?,
                    auto_approve = ?,
                    auto_publish = ?,
                    auto_generate_tags = ?,
                    publish_status = ?,
                    duplicate_check_days = ?,
                    cleanup_unverified_hours = ?,
                    enabled = ?,
                    updated_at = NOW()
                WHERE tenant_id = ?
            ");
            return $stmt->execute([
                $data['ai_provider_id'] ?? null,
                $data['output_language'] ?? 'es',
                $data['rewrite_prompt'] ?? null,
                $data['default_category_id'] ?? null,
                $data['auto_rewrite'] ?? false,
                $data['auto_approve'] ?? false,
                $data['auto_publish'] ?? false,
                $data['auto_generate_tags'] ?? true,
                $data['publish_status'] ?? 'draft',
                $data['duplicate_check_days'] ?? 7,
                $data['cleanup_unverified_hours'] ?? 6,
                $data['enabled'] ?? true,
                $tenantId
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO news_aggregator_settings
                (tenant_id, ai_provider_id, output_language, rewrite_prompt, default_category_id,
                 auto_rewrite, auto_approve, auto_publish, auto_generate_tags, publish_status,
                 duplicate_check_days, cleanup_unverified_hours, enabled)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            return $stmt->execute([
                $tenantId,
                $data['ai_provider_id'] ?? null,
                $data['output_language'] ?? 'es',
                $data['rewrite_prompt'] ?? null,
                $data['default_category_id'] ?? null,
                $data['auto_rewrite'] ?? false,
                $data['auto_approve'] ?? false,
                $data['auto_publish'] ?? false,
                $data['auto_generate_tags'] ?? true,
                $data['publish_status'] ?? 'draft',
                $data['duplicate_check_days'] ?? 7,
                $data['cleanup_unverified_hours'] ?? 6,
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
            'output_language' => $settings->output_language ?? 'es',
            'rewrite_prompt' => $settings->rewrite_prompt ?? self::getDefaultPrompt(),
            'default_category_id' => $settings->default_category_id ?? null,
            'auto_rewrite' => (bool) ($settings->auto_rewrite ?? false),
            'auto_approve' => (bool) ($settings->auto_approve ?? false),
            'auto_publish' => (bool) ($settings->auto_publish ?? false),
            'auto_generate_tags' => (bool) ($settings->auto_generate_tags ?? true),
            'publish_status' => $settings->publish_status ?? 'draft',
            'duplicate_check_days' => (int) ($settings->duplicate_check_days ?? 7),
            'enabled' => (bool) ($settings->enabled ?? true),
            'cleanup_unverified_hours' => (int) ($settings->cleanup_unverified_hours ?? 6)
        ];
    }

    /**
     * Prompt por defecto para reescribir noticias
     */
    public static function getDefaultPrompt(): string
    {
        return "Eres un periodista profesional. Reescribe la siguiente noticia con tu propia voz, sin copiar frases literales. Mantén la información factual pero cambia la estructura y las palabras. Cita la fuente original al final.";
    }
}
