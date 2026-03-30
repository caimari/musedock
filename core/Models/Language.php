<?php

namespace Screenart\Musedock\Models;

use Screenart\Musedock\Database\Model;

class Language extends Model
{
    protected static string $table = 'languages';
    protected array $fillable = ['code', 'name', 'active', 'tenant_id'];

    public static function getActiveLanguages(?int $tenantId = null): array
    {
        if ($tenantId !== null) {
            // Primero buscar idiomas específicos del tenant
            $tenantLanguages = static::where('active', 1)
                ->where('tenant_id', $tenantId)
                ->orderBy('order_position')
                ->get();

            // Si el tenant tiene idiomas configurados, usarlos
            if (!empty($tenantLanguages)) {
                return $tenantLanguages;
            }
        }

        // Fallback: idiomas globales (tenant_id IS NULL)
        return static::where('active', 1)
            ->whereNull('tenant_id')
            ->orderBy('order_position')
            ->get();
    }
}
