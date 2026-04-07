<?php

namespace Shop\Models;

use Screenart\Musedock\Database\Model;
use Screenart\Musedock\Database;

class Subscription extends Model
{
    protected static string $table = 'shop_subscriptions';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'tenant_id',
        'customer_id',
        'product_id',
        'order_id',
        'stripe_subscription_id',
        'stripe_price_id',
        'status',
        'billing_period',
        'current_period_start',
        'current_period_end',
        'cancel_at_period_end',
        'cancelled_at',
        'trial_ends_at',
        'metadata',
    ];

    protected array $casts = [
        'tenant_id' => 'nullable',
        'customer_id' => 'integer',
        'product_id' => 'integer',
        'order_id' => 'nullable',
        'cancel_at_period_end' => 'boolean',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancelled_at' => 'datetime',
        'trial_ends_at' => 'datetime',
    ];

    public static function query(): \Screenart\Musedock\Database\QueryBuilder
    {
        return Database::table(static::$table);
    }

    public function customer(): ?Customer
    {
        return Customer::find($this->customer_id);
    }

    public function product(): ?Product
    {
        return Product::find($this->product_id);
    }

    public function isActive(): bool { return $this->status === 'active'; }
    public function isPastDue(): bool { return $this->status === 'past_due'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }
    public function isPaused(): bool { return $this->status === 'paused'; }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'active' => 'bg-success',
            'past_due' => 'bg-warning',
            'cancelled' => 'bg-danger',
            'paused' => 'bg-secondary',
            default => 'bg-secondary',
        };
    }

    public function getMetadata(): array
    {
        if (empty($this->metadata)) return [];
        $decoded = json_decode($this->metadata, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function findByStripeId(string $stripeSubscriptionId): ?self
    {
        $row = self::query()->where('stripe_subscription_id', $stripeSubscriptionId)->first();
        return $row ? new self($row) : null;
    }
}
