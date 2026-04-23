<?php

namespace FilmLibrary\Models;

use Screenart\Musedock\Database\Model;

class FilmGenre extends Model
{
    protected static string $table = 'film_genres';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'tenant_id',
        'tmdb_id',
        'name',
        'slug',
        'description',
        'color',
        'film_count',
        'seo_title',
        'seo_description',
        'sort_order',
    ];

    protected array $casts = [
        'id'          => 'int',
        'tenant_id'   => 'int',
        'tmdb_id'     => 'int',
        'film_count'  => 'int',
        'sort_order'  => 'int',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];
}
