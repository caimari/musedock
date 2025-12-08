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
        return new QueryBuilder(static::$table); // ⚠️ Aquí pasamos el nombre de tabla, no la clase
    }

    public static function where($column, $operator = null, $value = null, $boolean = 'and'): QueryBuilder
    {
        return static::query()->where($column, $operator, $value, $boolean);
    }

    public function page(): Page
    {
        return Page::find($this->page_id);
    }
}
