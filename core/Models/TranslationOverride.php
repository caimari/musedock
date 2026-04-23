<?php

namespace Screenart\Musedock\Models;

use Screenart\Musedock\Database\Model;

class TranslationOverride extends Model
{
    protected static string $table = 'translation_overrides';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'tenant_id',
        'context',
        'locale',
        'translation_key',
        'translation_value',
    ];

    protected array $casts = [
        'id' => 'int',
        'tenant_id' => 'int',
        'context' => 'string',
        'locale' => 'string',
        'translation_key' => 'string',
        'translation_value' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
