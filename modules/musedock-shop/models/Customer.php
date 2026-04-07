<?php

namespace Shop\Models;

use Screenart\Musedock\Database\Model;
use Screenart\Musedock\Database;
use Shop\Contracts\BillableCustomer;

class Customer extends Model implements BillableCustomer
{
    protected static string $table = 'shop_customers';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'tenant_id',
        'user_id',
        'user_type',
        'email',
        'name',
        'phone',
        'company',
        'tax_id',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'stripe_customer_id',
        'metadata',
    ];

    protected array $casts = [
        'tenant_id' => 'nullable',
        'user_id' => 'nullable',
    ];

    public static function query(): \Screenart\Musedock\Database\QueryBuilder
    {
        return Database::table(static::$table);
    }

    // BillableCustomer interface
    public function getShopCustomerEmail(): string
    {
        return $this->email;
    }

    public function getShopCustomerName(): string
    {
        return $this->name;
    }

    public function getStripeCustomerId(): ?string
    {
        return $this->stripe_customer_id;
    }

    public function setStripeCustomerId(string $id): void
    {
        $this->update(['stripe_customer_id' => $id]);
    }

    public static function findByEmail(string $email, ?int $tenantId = null): ?self
    {
        $query = self::query()->where('email', $email);
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }
        $row = $query->first();
        return $row ? new self($row) : null;
    }

    public static function findByStripeId(string $stripeCustomerId): ?self
    {
        $row = self::query()->where('stripe_customer_id', $stripeCustomerId)->first();
        return $row ? new self($row) : null;
    }

    public static function findOrCreateByEmail(string $email, string $name, ?int $tenantId = null): self
    {
        $existing = self::findByEmail($email, $tenantId);
        if ($existing) return $existing;

        return self::create([
            'tenant_id' => $tenantId,
            'email' => $email,
            'name' => $name,
        ]);
    }

    public function orders(): array
    {
        $rows = Order::query()
            ->where('customer_id', $this->id)
            ->orderBy('created_at', 'DESC')
            ->get();
        return array_map(fn($row) => new Order($row), $rows);
    }

    public function subscriptions(): array
    {
        $rows = Subscription::query()
            ->where('customer_id', $this->id)
            ->orderBy('created_at', 'DESC')
            ->get();
        return array_map(fn($row) => new Subscription($row), $rows);
    }

    public function getFullAddress(): string
    {
        return implode(', ', array_filter([
            $this->address_line1,
            $this->address_line2,
            $this->postal_code,
            $this->city,
            $this->state,
            $this->country,
        ]));
    }
}
