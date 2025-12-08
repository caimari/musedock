<?php

namespace Screenart\Musedock\Models;

use Screenart\Musedock\Database\Model;

class Tenant extends Model
{
    protected static string $table = 'tenants';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'name',
        'slug',
        'domain',
        'status',
        'admin_path',
        'theme',
    ];

    protected array $casts = [
        'id' => 'int',
    ];

    // Relaciones
    public function permissions()
    {
        return $this->hasMany(Permission::class, 'tenant_id');
    }

    public function resources()
    {
        return $this->hasMany(ResourcePermission::class, 'tenant_id');
    }

    // Métodos de utilidad
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getAdminPath(): string
    {
        return $this->admin_path ?? 'admin';
    }

    public function getTheme(): string
    {
        return $this->theme ?? 'default';
    }
}
