<?php
namespace Screenart\Musedock\Models;

use Screenart\Musedock\Database\Model;

class ThemeOption extends Model
{
    protected static string $table = 'theme_options';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true; // Usará created_at y updated_at

    protected array $fillable = [
        'tenant_id',
        'theme_slug',
        'value', // Guardará el JSON de opciones
    ];

    // Cast para decodificar/codificar automáticamente el JSON
    protected array $casts = [
        'tenant_id' => 'nullable|integer',
        'value' => 'json', // <--- Importante
    ];

    /**
     * Helper para obtener las opciones de un tema/tenant.
     * Prioriza tenant, luego global.
     *
     * @param string $themeSlug
     * @param int|null $tenantId
     * @return array Las opciones decodificadas o un array vacío.
     */
public static function getOptions(string $themeSlug, ?int $tenantId): array
{
    $query = static::query()->where('theme_slug', $themeSlug);
    
    if ($tenantId === null) {
        $query->whereNull('tenant_id');
    } else {
        $query->where('tenant_id', $tenantId);
    }
    
    $record = $query->first();
    
    if (!$record) {
        return [];
    }
    
    // Asegurarse de que el valor se decodifica como array
    $value = $record->value;
    
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : [];
    } elseif (is_object($value) || is_array($value)) {
        return (array) $value;
    }
    
    return [];
}

    /**
     * Helper para guardar las opciones de un tema/tenant.
     * Usa updateOrInsert.
     *
     * @param string $themeSlug
     * @param int|null $tenantId
     * @param array $optionsArray Las opciones a guardar (serán codificadas a JSON).
     * @return bool Éxito o fallo.
     */
public static function saveOptions(string $themeSlug, ?int $tenantId, array $optionsArray): bool
{
    // Primero verificamos si ya existe una entrada
    $query = static::query()->where('theme_slug', $themeSlug);
    
    // Manejar NULL correctamente en el WHERE
    if ($tenantId === null) {
        $query->whereNull('tenant_id');
    } else {
        $query->where('tenant_id', $tenantId);
    }
    
    $existing = $query->first();
    
    if ($existing) {
        // Si existe, usar UPDATE directo en lugar de llamar a ->update() en el objeto
        return static::query()
            ->where('id', $existing->id)
            ->update([
                'value' => json_encode($optionsArray, JSON_UNESCAPED_UNICODE),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
    } else {
        // Si no existe, crear un nuevo registro
        return (bool) static::query()->insert([
            'tenant_id' => $tenantId,
            'theme_slug' => $themeSlug,
            'value' => json_encode($optionsArray, JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
}

    /**
     * Elimina las opciones de un tema/tenant para restaurar valores por defecto.
     *
     * @param string $themeSlug
     * @param int|null $tenantId
     * @return bool True si se eliminó algo, false si no había nada que eliminar.
     */
    public static function deleteOptions(string $themeSlug, ?int $tenantId): bool
    {
        $query = static::query()->where('theme_slug', $themeSlug);

        if ($tenantId === null) {
            $query->whereNull('tenant_id');
        } else {
            $query->where('tenant_id', $tenantId);
        }

        $existing = $query->first();

        if ($existing) {
            return static::query()->where('id', $existing->id)->delete();
        }

        return false;
    }
}
