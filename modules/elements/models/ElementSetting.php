<?php

namespace Elements\Models;

use Screenart\Musedock\Database\Model;

/**
 * ElementSetting Model
 *
 * Stores module-wide settings for elements
 */
class ElementSetting extends Model
{
    protected static string $table = 'element_settings';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'tenant_id',
        'setting_key',
        'setting_value'
    ];

    /**
     * Get all settings for a tenant
     */
    public static function getAll(?int $tenantId = null): array
    {
        $query = self::query();

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }

        $results = $query->get();
        $settings = [];

        foreach ($results as $row) {
            $settings[$row->setting_key] = $row->setting_value;
        }

        return $settings;
    }

    /**
     * Get a specific setting
     */
    public static function get(string $key, ?int $tenantId = null, $default = null)
    {
        $query = self::query()->where('setting_key', $key);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }

        $result = $query->first();
        return $result ? $result->setting_value : $default;
    }

    /**
     * Set a setting value
     */
    public static function set(string $key, $value, ?int $tenantId = null): bool
    {
        $query = self::query()->where('setting_key', $key);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }

        $existing = $query->first();

        if ($existing) {
            return self::query()
                ->where('id', $existing->id)
                ->update(['setting_value' => $value]);
        }

        return self::create([
            'tenant_id' => $tenantId,
            'setting_key' => $key,
            'setting_value' => $value
        ]) !== null;
    }

    /**
     * Delete a setting
     */
    public static function remove(string $key, ?int $tenantId = null): bool
    {
        $query = self::query()->where('setting_key', $key);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }

        return $query->delete();
    }
}
