<?php

namespace Shop\Models;

use Screenart\Musedock\Database\Model;
use Screenart\Musedock\Database;

class Order extends Model
{
    protected static string $table = 'shop_orders';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'tenant_id',
        'customer_id',
        'order_number',
        'status',
        'subtotal',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'total',
        'currency',
        'coupon_id',
        'stripe_payment_intent_id',
        'stripe_checkout_session_id',
        'stripe_invoice_id',
        'billing_name',
        'billing_email',
        'billing_phone',
        'billing_address',
        'notes',
        'metadata',
        'completed_at',
        'cancelled_at',
        'refunded_at',
    ];

    protected array $casts = [
        'tenant_id' => 'nullable',
        'customer_id' => 'nullable',
        'subtotal' => 'integer',
        'discount_amount' => 'integer',
        'tax_amount' => 'integer',
        'total' => 'integer',
        'coupon_id' => 'nullable',
    ];

    public static function query(): \Screenart\Musedock\Database\QueryBuilder
    {
        return Database::table(static::$table);
    }

    public function customer(): ?Customer
    {
        if (!$this->customer_id) return null;
        return Customer::find($this->customer_id);
    }

    public function items(): array
    {
        $rows = OrderItem::query()
            ->where('order_id', $this->id)
            ->get();
        return array_map(fn($row) => new OrderItem($row), $rows);
    }

    public function getFormattedTotal(): string
    {
        return shop_format_price($this->total, $this->currency);
    }

    public function getFormattedSubtotal(): string
    {
        return shop_format_price($this->subtotal, $this->currency);
    }

    public function getFormattedTax(): string
    {
        return shop_format_price($this->tax_amount, $this->currency);
    }

    public function getFormattedDiscount(): string
    {
        return shop_format_price($this->discount_amount, $this->currency);
    }

    public function getMetadata(): array
    {
        if (empty($this->metadata)) return [];
        $decoded = json_decode($this->metadata, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getBillingAddress(): array
    {
        if (empty($this->billing_address)) return [];
        $decoded = json_decode($this->billing_address, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function isPending(): bool { return $this->status === 'pending'; }
    public function isProcessing(): bool { return $this->status === 'processing'; }
    public function isCompleted(): bool { return $this->status === 'completed'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }
    public function isRefunded(): bool { return $this->status === 'refunded'; }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'pending' => 'bg-warning',
            'processing' => 'bg-info',
            'completed' => 'bg-success',
            'cancelled' => 'bg-secondary',
            'refunded' => 'bg-danger',
            default => 'bg-secondary',
        };
    }

    public static function findByOrderNumber(string $orderNumber): ?self
    {
        $row = self::query()->where('order_number', $orderNumber)->first();
        return $row ? new self($row) : null;
    }

    public static function findByPaymentIntent(string $paymentIntentId): ?self
    {
        $row = self::query()->where('stripe_payment_intent_id', $paymentIntentId)->first();
        return $row ? new self($row) : null;
    }

    public static function findByCheckoutSession(string $sessionId): ?self
    {
        $row = self::query()->where('stripe_checkout_session_id', $sessionId)->first();
        return $row ? new self($row) : null;
    }
}
