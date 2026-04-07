<?php

namespace Shop\Models;

use Screenart\Musedock\Database\Model;
use Screenart\Musedock\Database;

class Product extends Model
{
    protected static string $table = 'shop_products';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'short_description',
        'type',
        'price',
        'compare_price',
        'currency',
        'billing_period',
        'stripe_product_id',
        'stripe_price_id',
        'featured_image',
        'gallery',
        'metadata',
        'is_active',
        'is_featured',
        'stock_quantity',
        'sort_order',
    ];

    protected array $casts = [
        'tenant_id' => 'nullable',
        'price' => 'integer',
        'compare_price' => 'nullable',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'stock_quantity' => 'nullable',
        'sort_order' => 'integer',
    ];

    public static function query(): \Screenart\Musedock\Database\QueryBuilder
    {
        return Database::table(static::$table);
    }

    public function getFormattedPrice(): string
    {
        return shop_format_price($this->price, $this->currency);
    }

    public function getFormattedComparePrice(): ?string
    {
        if (!$this->compare_price) return null;
        return shop_format_price($this->compare_price, $this->currency);
    }

    public function isSubscription(): bool
    {
        return $this->type === 'subscription';
    }

    public function hasStock(): bool
    {
        if ($this->stock_quantity === null) return true; // unlimited
        return $this->stock_quantity > 0;
    }

    public function getMetadata(): array
    {
        if (empty($this->metadata)) return [];
        $decoded = json_decode($this->metadata, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getGallery(): array
    {
        if (empty($this->gallery)) return [];
        $decoded = json_decode($this->gallery, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getFeatures(): array
    {
        $meta = $this->getMetadata();
        return $meta['features'] ?? [];
    }

    public static function findBySlug(string $slug, ?int $tenantId = null): ?self
    {
        $query = self::query()->where('slug', $slug);
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }
        $row = $query->first();
        return $row ? new self($row) : null;
    }

    public static function getActive(?int $tenantId = null): array
    {
        $query = self::query()->where('is_active', 1)->orderBy('sort_order', 'ASC');
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }
        $rows = $query->get();
        return array_map(fn($row) => new self($row), $rows);
    }
}
