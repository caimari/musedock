<?php

namespace Screenart\Musedock\Models;

use Screenart\Musedock\Database\Model;

class Slug extends Model
{
    protected static string $table = 'slugs';
    protected static string $primaryKey = 'id';

    protected array $fillable = [
        'slug',
        'module',
        'reference_id',
        'tenant_id',
        'prefix'
    ];

    protected array $casts = [
        'tenant_id' => 'int',
        'reference_id' => 'int',
    ];
}
