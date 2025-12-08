<?php

namespace Screenart\Musedock\Models;

use Screenart\Musedock\Database\Model;

class Permission extends Model
{
    protected static string $table = 'permissions';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'name',
        'description',
        'category',
        'tenant_id',
        'scope',
    ];

    protected array $casts = [
        'tenant_id' => 'int',
    ];

    // Relaciones opcionales si las necesitas más adelante
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
