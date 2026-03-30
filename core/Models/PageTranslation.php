<?php

namespace Screenart\Musedock\Models;

use Screenart\Musedock\Database\Model;
use Screenart\Musedock\Database\QueryBuilder;

class PageTranslation extends Model
{
    protected static string $table = 'page_translations';

    protected array $fillable = [
        'page_id',
        'locale',
        'title',
        'content',
        'tenant_id',
        'created_at',
        'updated_at',
    ];

    public static function query(): QueryBuilder
    {
        $qb = new QueryBuilder(static::$table);
        $qb->setModelClass(static::class); // Configurar la clase del modelo para hidratación
        return $qb;
    }

    public static function where($column, $operator = null, $value = null): QueryBuilder
    {
        // Si solo hay 2 argumentos (column, value), pasar solo 2
        if (func_num_args() === 2) {
            return static::query()->where($column, $operator);
        }
        // Si hay 3 argumentos (column, operator, value), pasar los 3
        return static::query()->where($column, $operator, $value);
    }

    public function page(): ?Page
    {
        return Page::find($this->page_id);
    }
}
