<?php

namespace FestivalDirectory\Models;

use Screenart\Musedock\Database\Model;
use Screenart\Musedock\Database;

class FestivalType extends Model
{
    protected static string $table = 'festival_types';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'sort_order',
        'festival_count',
    ];

    protected array $casts = [
        'id'             => 'int',
        'tenant_id'      => 'int',
        'sort_order'     => 'int',
        'festival_count' => 'int',
    ];

    /**
     * Get types as key=>value for dropdowns.
     */
    public static function getTypesForTenant(int $tenantId): array
    {
        $types = self::where('tenant_id', $tenantId)->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->get();
        $result = [];
        foreach ($types as $t) {
            $result[$t->slug] = $t->name;
        }
        return $result;
    }

    /**
     * Get full type objects for tenant.
     */
    public static function getAllForTenant(int $tenantId): array
    {
        return self::where('tenant_id', $tenantId)->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->get();
    }

    /**
     * Update festival_count for this type.
     */
    public function updateCount(): void
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM festivals WHERE type = ? AND tenant_id = ? AND deleted_at IS NULL");
            $stmt->execute([$this->slug, $this->tenant_id]);
            $count = (int)$stmt->fetch(\PDO::FETCH_ASSOC)['cnt'];

            $pdo->prepare("UPDATE festival_types SET festival_count = ? WHERE id = ?")->execute([$count, $this->id]);
        } catch (\Exception $e) {
            error_log("FestivalType updateCount error: " . $e->getMessage());
        }
    }
}
