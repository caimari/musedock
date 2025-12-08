<?php

namespace Screenart\Musedock\Models;

use Screenart\Musedock\Database\Model;

class Language extends Model
{
    protected static string $table = 'languages';
    protected array $fillable = ['code', 'name', 'active', 'tenant_id'];

    public static function getActiveLanguages(?int $tenantId = null): array
    {
        $query = static::where('active', 1);

        if ($tenantId !== null) {
            // Usar parámetros nombrados para evitar mezcla con posicionales
            $query->whereRaw("(tenant_id = :tenant_id OR tenant_id IS NULL)", ['tenant_id' => $tenantId]);
        } else {
            $query->whereNull('tenant_id');
        }

        return $query->get();
    }
}
