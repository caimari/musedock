<?php

namespace ImageGallery\Models;

use Screenart\Musedock\Database\Model;

/**
 * GallerySetting Model
 *
 * Maneja la configuración del módulo de galerías por tenant
 */
class GallerySetting extends Model
{
    protected static string $table = 'gallery_settings';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'tenant_id',
        'setting_key',
        'setting_value',
        'setting_type'
    ];

    /**
     * Obtiene el valor de una configuración
     */
    public static function get(string $key, ?int $tenantId = null, $default = null)
    {
        // Primero buscar configuración del tenant
        if ($tenantId !== null) {
            $setting = self::query()
                ->where('setting_key', $key)
                ->where('tenant_id', $tenantId)
                ->first();

            if ($setting) {
                return self::castValue($setting->setting_value, $setting->setting_type);
            }
        }

        // Buscar configuración global
        $setting = self::query()
            ->where('setting_key', $key)
            ->whereNull('tenant_id')
            ->first();

        if ($setting) {
            return self::castValue($setting->setting_value, $setting->setting_type);
        }

        return $default;
    }

    /**
     * Establece el valor de una configuración
     */
    public static function set(string $key, $value, ?int $tenantId = null, string $type = 'string'): bool
    {
        $pdo = \Screenart\Musedock\Database::connect();

        // Convertir valor según tipo
        $stringValue = self::valueToString($value, $type);

        // Buscar si existe
        $query = self::query()->where('setting_key', $key);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }

        $existing = $query->first();

        if ($existing) {
            $existing = self::newFromBuilder($existing);
            // Actualizar
            return $existing->update([
                'setting_value' => $stringValue,
                'setting_type' => $type
            ]);
        } else {
            // Crear nuevo
            return self::create([
                'tenant_id' => $tenantId,
                'setting_key' => $key,
                'setting_value' => $stringValue,
                'setting_type' => $type
            ]) !== null;
        }
    }

    /**
     * Obtiene todas las configuraciones para un tenant
     */
    public static function getAll(?int $tenantId = null): array
    {
        $settings = [];

        // Cargar configuraciones globales primero
        $globalSettings = self::query()
            ->whereNull('tenant_id')
            ->get();

        foreach ($globalSettings as $setting) {
            $settings[$setting->setting_key] = self::castValue(
                $setting->setting_value,
                $setting->setting_type
            );
        }

        // Sobrescribir con configuraciones del tenant
        if ($tenantId !== null) {
            $tenantSettings = self::query()
                ->where('tenant_id', $tenantId)
                ->get();

            foreach ($tenantSettings as $setting) {
                $settings[$setting->setting_key] = self::castValue(
                    $setting->setting_value,
                    $setting->setting_type
                );
            }
        }

        return $settings;
    }

    /**
     * Elimina una configuración
     */
    public static function remove(string $key, ?int $tenantId = null): bool
    {
        $query = self::query()->where('setting_key', $key);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }

        $setting = $query->first();

        if ($setting) {
            $setting = self::newFromBuilder($setting);
            return $setting->delete();
        }

        return false;
    }

    /**
     * Convierte el valor de string al tipo apropiado
     */
    private static function castValue(string $value, string $type)
    {
        switch ($type) {
            case 'int':
            case 'integer':
                return (int) $value;

            case 'bool':
            case 'boolean':
                return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);

            case 'float':
            case 'double':
                return (float) $value;

            case 'json':
            case 'array':
                $decoded = json_decode($value, true);
                return is_array($decoded) ? $decoded : [];

            case 'string':
            default:
                return $value;
        }
    }

    /**
     * Convierte un valor al formato string para almacenar
     */
    private static function valueToString($value, string $type): string
    {
        switch ($type) {
            case 'bool':
            case 'boolean':
                return $value ? '1' : '0';

            case 'json':
            case 'array':
                return json_encode($value);

            default:
                return (string) $value;
        }
    }

    /**
     * Obtiene los valores por defecto del módulo
     */
    public static function getDefaults(): array
    {
        return [
            'default_layout' => 'grid',
            'default_columns' => 3,
            'default_gap' => 10,
            'max_upload_size_mb' => 10,
            'allowed_extensions' => 'jpg,jpeg,png,gif,webp,svg',
            'image_quality' => 85,
            'auto_generate_thumbnails' => true,
            'thumbnail_width' => 150,
            'thumbnail_height' => 150,
            'medium_width' => 600,
            'medium_height' => 600,
            'enable_lightbox' => true,
            'enable_lazy_loading' => true,
            'watermark_enabled' => false,
            'watermark_text' => '',
            'watermark_position' => 'bottom-right',
        ];
    }
}
