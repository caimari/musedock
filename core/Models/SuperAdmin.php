<?php

namespace Screenart\Musedock\Models;

use Screenart\Musedock\Models\QueryBuilder;
use Screenart\Musedock\Database\Model;

class SuperAdmin extends Model
{
    protected static string $table = 'super_admins';

    protected array $fillable = [
        'email',
        'password',
        'name',
        'role',
        'created_at',
        'updated_at',
    ];

    public static function query(): QueryBuilder
    {
        return new QueryBuilder(static::class, (new static)->getTable());
    }

    public static function where($column, $operator = null, $value = null, $boolean = 'and'): QueryBuilder
    {
        return static::query()->where($column, $operator, $value, $boolean);
    }

    public static function findByEmail(string $email): ?self
    {
        return static::where('email', $email)->first();
    }
}
