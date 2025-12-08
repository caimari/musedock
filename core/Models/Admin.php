<?php

namespace Screenart\Musedock\Models;

use Screenart\Musedock\Models\QueryBuilder;
use Screenart\Musedock\Database\Model;

class Admin extends Model
{
    protected static string $table = 'admins';

    protected array $fillable = [
        'tenant_id',
        'email',
        'password',
        'name',
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

    public static function findByEmail(string $email, int $tenantId): ?self
    {
        return static::where('email', $email)
                     ->where('tenant_id', $tenantId)
                     ->first();
    }
}
