<?php

namespace Shop\Models;

use Screenart\Musedock\Database\Model;
use Screenart\Musedock\Database;

class Invoice extends Model
{
    protected static string $table = 'shop_invoices';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'tenant_id',
        'order_id',
        'customer_id',
        'subscription_id',
        'invoice_number',
        'stripe_invoice_id',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total',
        'currency',
        'status',
        'issued_at',
        'paid_at',
        'due_at',
        'pdf_url',
        'factubase_id',
        'metadata',
    ];

    protected array $casts = [
        'tenant_id' => 'nullable',
        'order_id' => 'nullable',
        'subscription_id' => 'nullable',
        'subtotal' => 'integer',
        'tax_amount' => 'integer',
        'total' => 'integer',
    ];

    public static function query(): \Screenart\Musedock\Database\QueryBuilder
    {
        return Database::table(static::$table);
    }

    public function order(): ?Order
    {
        if (!$this->order_id) return null;
        return Order::find($this->order_id);
    }

    public function customer(): ?Customer
    {
        return Customer::find($this->customer_id);
    }

    public function getFormattedTotal(): string
    {
        return shop_format_price($this->total, $this->currency);
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'draft' => 'bg-secondary',
            'issued' => 'bg-info',
            'paid' => 'bg-success',
            'void' => 'bg-danger',
            default => 'bg-secondary',
        };
    }

    public static function generateNumber(?int $tenantId = null): string
    {
        $year = date('Y');
        $prefix = 'INV';

        try {
            $query = self::query()->where('invoice_number', 'LIKE', "{$prefix}-{$year}-%");
            if ($tenantId !== null) {
                $query->where('tenant_id', $tenantId);
            }
            $last = $query->orderBy('id', 'DESC')->first();

            if ($last && preg_match('/INV-\d{4}-(\d+)/', $last['invoice_number'], $m)) {
                $next = (int) $m[1] + 1;
            } else {
                $next = 1;
            }
        } catch (\Throwable) {
            $next = 1;
        }

        return sprintf('%s-%s-%05d', $prefix, $year, $next);
    }
}
