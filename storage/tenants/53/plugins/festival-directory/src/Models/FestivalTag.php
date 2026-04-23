<?php

namespace FestivalDirectory\Models;

use Screenart\Musedock\Database\Model;

class FestivalTag extends Model
{
    protected static string $table = 'festival_tags';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'tenant_id',
        'name',
        'slug',
        'festival_count',
    ];

    protected array $casts = [
        'id'             => 'int',
        'tenant_id'      => 'int',
        'festival_count' => 'int',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];
}
