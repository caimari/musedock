<?php

namespace FestivalDirectory\Models;

use Screenart\Musedock\Database\Model;

class FestivalCategory extends Model
{
    protected static string $table = 'festival_categories';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'image',
        'color',
        'sort_order',
        'festival_count',
        'seo_title',
        'seo_description',
    ];

    protected array $casts = [
        'id'             => 'int',
        'tenant_id'      => 'int',
        'sort_order'     => 'int',
        'festival_count' => 'int',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];
}
