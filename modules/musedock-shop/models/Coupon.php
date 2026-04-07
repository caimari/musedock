<?php

namespace Shop\Models;

use Screenart\Musedock\Database\Model;
use Screenart\Musedock\Database;

class Coupon extends Model
{
    protected static string $table = 'shop_coupons';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'tenant_id',
        'code',
        'description',
        'type',
        'value',
        'min_order_amount',
        'max_discount_amount',
        'max_uses',
        'used_count',
        'valid_from',
        'valid_until',
        'applicable_products',
        'is_active',
    ];

    protected array $casts = [
        'tenant_id' => 'nullable',
        'value' => 'integer',
        'min_order_amount' => 'nullable',
        'max_discount_amount' => 'nullable',
        'max_uses' => 'nullable',
        'used_count' => 'integer',
        'is_active' => 'boolean',
    ];

    public static function query(): \Screenart\Musedock\Database\QueryBuilder
    {
        return Database::table(static::$table);
    }

    public function isValid(): bool
    {
        if (!$this->is_active) return false;

        $now = date('Y-m-d H:i:s');

        if ($this->valid_from && $this->valid_from > $now) return false;
        if ($this->valid_until && $this->valid_until < $now) return false;
        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) return false;

        return true;
    }

    public function calculateDiscount(int $subtotalCents): int
    {
        if (!$this->isValid()) return 0;

        if ($this->min_order_amount && $subtotalCents < $this->min_order_amount) return 0;

        if ($this->type === 'percentage') {
            $discount = (int) round($subtotalCents * $this->value / 100);
            if ($this->max_discount_amount && $discount > $this->max_discount_amount) {
                $discount = $this->max_discount_amount;
            }
            return $discount;
        }

        // Fixed amount
        return min($this->value, $subtotalCents);
    }

    public function incrementUsage(): void
    {
        Database::query(
            "UPDATE shop_coupons SET used_count = used_count + 1 WHERE id = :id",
            ['id' => $this->id]
        );
    }

    public function getFormattedValue(): string
    {
        if ($this->type === 'percentage') {
            return $this->value . '%';
        }
        return shop_format_price($this->value);
    }

    public function getApplicableProducts(): array
    {
        if (empty($this->applicable_products)) return [];
        $decoded = json_decode($this->applicable_products, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function findByCode(string $code, ?int $tenantId = null): ?self
    {
        $query = self::query()->where('code', strtoupper($code));
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }
        $row = $query->first();
        return $row ? new self($row) : null;
    }
}
