<?php

namespace Modules\InstagramGallery\Models;

use PDO;
use Exception;

class InstagramSetting
{
    private static ?PDO $pdo = null;

    public int $id;
    public ?int $tenant_id;
    public string $setting_key;
    public ?string $setting_value;
    public string $setting_type; // string, int, bool, json, array
    public ?string $created_at;
    public ?string $updated_at;

    public static function setPdo(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    private static function getPdo(): PDO
    {
        if (self::$pdo === null) {
            throw new Exception('PDO instance not set. Call setPdo() first.');
        }
        return self::$pdo;
    }

    /**
     * Get setting value with tenant inheritance
     * 1. Check tenant-specific setting
     * 2. Fall back to global setting (tenant_id = NULL)
     * 3. Fall back to default value
     */
    public static function get(string $key, ?int $tenantId = null, $default = null)
    {
        $pdo = self::getPdo();

        // Try tenant-specific setting first
        if ($tenantId !== null) {
            $stmt = $pdo->prepare('
                SELECT setting_value, setting_type
                FROM instagram_settings
                WHERE setting_key = ? AND tenant_id = ?
            ');
            $stmt->execute([$key, $tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                return self::castValue($row['setting_value'], $row['setting_type']);
            }
        }

        // Fall back to global setting
        $stmt = $pdo->prepare('
            SELECT setting_value, setting_type
            FROM instagram_settings
            WHERE setting_key = ? AND (tenant_id IS NULL OR tenant_id = 0)
        ');
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return self::castValue($row['setting_value'], $row['setting_type']);
        }

        // Fall back to default from module config
        $defaults = self::getDefaults();
        if (isset($defaults[$key])) {
            return $defaults[$key];
        }

        return $default;
    }

    /**
     * Set setting value
     */
    public static function set(string $key, $value, ?int $tenantId = null, string $type = 'string'): bool
    {
        $pdo = self::getPdo();

        // Serialize value based on type
        $serializedValue = self::serializeValue($value, $type);

        // Check if setting exists
        if ($tenantId !== null) {
            $stmt = $pdo->prepare('
                SELECT id FROM instagram_settings
                WHERE setting_key = ? AND tenant_id = ?
            ');
            $stmt->execute([$key, $tenantId]);
        } else {
            $stmt = $pdo->prepare('
                SELECT id FROM instagram_settings
                WHERE setting_key = ? AND (tenant_id IS NULL OR tenant_id = 0)
            ');
            $stmt->execute([$key]);
        }

        $exists = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($exists) {
            // Update existing
            $stmt = $pdo->prepare('
                UPDATE instagram_settings
                SET setting_value = ?, setting_type = ?, updated_at = NOW()
                WHERE id = ?
            ');
            return $stmt->execute([$serializedValue, $type, $exists['id']]);
        } else {
            // Insert new
            $stmt = $pdo->prepare('
                INSERT INTO instagram_settings (
                    tenant_id, setting_key, setting_value, setting_type, created_at, updated_at
                ) VALUES (?, ?, ?, ?, NOW(), NOW())
            ');
            return $stmt->execute([$tenantId, $key, $serializedValue, $type]);
        }
    }

    /**
     * Get all settings for a tenant
     */
    public static function getAll(?int $tenantId = null): array
    {
        $pdo = self::getPdo();

        // Get global settings
        $stmt = $pdo->query('
            SELECT setting_key, setting_value, setting_type
            FROM instagram_settings
            WHERE tenant_id IS NULL OR tenant_id = 0
        ');

        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = self::castValue($row['setting_value'], $row['setting_type']);
        }

        // Override with tenant-specific settings
        if ($tenantId !== null) {
            $stmt = $pdo->prepare('
                SELECT setting_key, setting_value, setting_type
                FROM instagram_settings
                WHERE tenant_id = ?
            ');
            $stmt->execute([$tenantId]);

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = self::castValue($row['setting_value'], $row['setting_type']);
            }
        }

        // Merge with defaults for missing keys
        $defaults = self::getDefaults();
        foreach ($defaults as $key => $value) {
            if (!isset($settings[$key])) {
                $settings[$key] = $value;
            }
        }

        return $settings;
    }

    /**
     * Remove setting
     */
    public static function remove(string $key, ?int $tenantId = null): bool
    {
        $pdo = self::getPdo();

        if ($tenantId !== null) {
            $stmt = $pdo->prepare('
                DELETE FROM instagram_settings
                WHERE setting_key = ? AND tenant_id = ?
            ');
            return $stmt->execute([$key, $tenantId]);
        } else {
            $stmt = $pdo->prepare('
                DELETE FROM instagram_settings
                WHERE setting_key = ? AND (tenant_id IS NULL OR tenant_id = 0)
            ');
            return $stmt->execute([$key]);
        }
    }

    /**
     * Get default settings from module configuration
     */
    public static function getDefaults(): array
    {
        return [
            // Instagram API settings
            'instagram_app_id' => '',
            'instagram_app_secret' => '',
            'instagram_redirect_uri' => '',

            // Gallery display settings
            'default_layout' => 'grid',
            'default_columns' => 3,
            'default_gap' => 10,
            'max_posts_per_gallery' => 50,

            // Caching settings
            'cache_duration_hours' => 6,
            'auto_refresh_tokens' => true,
            'token_refresh_threshold_days' => 7,

            // Display options
            'show_captions' => true,
            'caption_max_length' => 150,
            'enable_lightbox' => true,
            'enable_lazy_loading' => true,
            'show_video_indicator' => true,
            'show_carousel_indicator' => true,

            // Layout options
            'hover_effect' => 'zoom', // zoom, fade, none
            'border_radius' => 8,
            'image_aspect_ratio' => '1:1', // 1:1, 4:3, 16:9, original

            // Allowed layouts
            'allowed_layouts' => ['grid', 'masonry', 'carousel', 'lightbox', 'justified']
        ];
    }

    /**
     * Get available layouts
     */
    public static function getAvailableLayouts(): array
    {
        return [
            'grid' => [
                'name' => 'Cuadrícula',
                'description' => 'Disposición uniforme en filas y columnas',
                'icon' => 'bi-grid-3x3'
            ],
            'masonry' => [
                'name' => 'Masonry',
                'description' => 'Disposición tipo Pinterest con alturas variables',
                'icon' => 'bi-columns-gap'
            ],
            'carousel' => [
                'name' => 'Carrusel',
                'description' => 'Slider horizontal con navegación',
                'icon' => 'bi-collection-play'
            ],
            'lightbox' => [
                'name' => 'Lightbox',
                'description' => 'Miniaturas con vista ampliada al hacer clic',
                'icon' => 'bi-fullscreen'
            ],
            'justified' => [
                'name' => 'Justificado',
                'description' => 'Filas con altura uniforme y anchos variables',
                'icon' => 'bi-justify'
            ]
        ];
    }

    /**
     * Cast value from database to proper type
     */
    private static function castValue($value, string $type)
    {
        if ($value === null) {
            return null;
        }

        switch ($type) {
            case 'int':
                return (int) $value;
            case 'bool':
                return (bool) $value || $value === '1' || $value === 'true';
            case 'json':
                return json_decode($value, true);
            case 'array':
                return is_array($value) ? $value : json_decode($value, true);
            case 'string':
            default:
                return (string) $value;
        }
    }

    /**
     * Serialize value for database storage
     */
    private static function serializeValue($value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        switch ($type) {
            case 'int':
                return (string) (int) $value;
            case 'bool':
                return $value ? '1' : '0';
            case 'json':
            case 'array':
                return json_encode($value);
            case 'string':
            default:
                return (string) $value;
        }
    }

    /**
     * Hydrate object from database row
     */
    private static function hydrate(array $data): self
    {
        $instance = new self();
        $instance->id = (int) $data['id'];
        $instance->tenant_id = $data['tenant_id'] !== null ? (int) $data['tenant_id'] : null;
        $instance->setting_key = $data['setting_key'];
        $instance->setting_value = $data['setting_value'];
        $instance->setting_type = $data['setting_type'];
        $instance->created_at = $data['created_at'];
        $instance->updated_at = $data['updated_at'];

        return $instance;
    }
}
