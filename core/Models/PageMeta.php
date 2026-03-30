<?php
namespace Screenart\Musedock\Models;

use Screenart\Musedock\Database\Model; // Usa tu clase Model base

class PageMeta extends Model
{
    protected static string $table = 'page_meta';
    protected static string $primaryKey = 'id';
    // Habilita timestamps si tu tabla los tiene y tu Model base los maneja
    protected static bool $timestamps = true;

    // Campos que se pueden asignar masivamente
    protected array $fillable = [
        'page_id',
        'meta_key',
        'meta_value',
    ];

    // No se necesitan casts especiales generalmente para meta
    // protected array $casts = [];

    /**
     * Relación inversa con Page (opcional pero útil)
     */
    public function page()
    {
        // Asume que tienes un método belongsTo en tu Model base
        return $this->belongsTo(Page::class, 'page_id');
    }

    /**
     * Método helper para obtener un valor meta específico para una página.
     *
     * @param int|null $pageId
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getMeta(?int $pageId, string $key, $default = null)
    {
        // Si el pageId es null, devolvemos el valor por defecto
        if ($pageId === null) {
            return $default;
        }

        $meta = static::query() // Usa el QueryBuilder de tu Model base
            ->where('page_id', $pageId)
            ->where('meta_key', $key)
            ->value('meta_value'); // Obtiene solo el valor

        return $meta ?? $default;
    }

    /**
     * Método helper para actualizar o insertar un valor meta para una página.
     *
     * @param int $pageId
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public static function updateOrInsertMeta(int $pageId, string $key, $value): bool
    {
         // Usar updateOrInsert si tu QueryBuilder lo tiene
         return static::query()->updateOrInsert(
             ['page_id' => $pageId, 'meta_key' => $key], // Condiciones para buscar/insertar
             ['meta_value' => $value]                   // Datos a actualizar/insertar
         );

        // Alternativa si no tienes updateOrInsert:
        /*
        $existing = static::query()
            ->where('page_id', $pageId)
            ->where('meta_key', $key)
            ->first();

        if ($existing) {
            // Asegúrate que tu modelo base tiene método update()
            return $existing->update(['meta_value' => $value]);
        } else {
            // Asegúrate que tu modelo base tiene método create()
            return static::create([
                'page_id' => $pageId,
                'meta_key' => $key,
                'meta_value' => $value,
            ]) !== null;
        }
        */
    }
}