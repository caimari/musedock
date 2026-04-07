<?php

namespace Shop\Models;

use Screenart\Musedock\Database\Model;
use Screenart\Musedock\Database;

class OrderItem extends Model
{
    protected static string $table = 'shop_order_items';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_type',
        'quantity',
        'unit_price',
        'total',
        'metadata',
    ];

    protected array $casts = [
        'order_id' => 'integer',
        'product_id' => 'nullable',
        'quantity' => 'integer',
        'unit_price' => 'integer',
        'total' => 'integer',
    ];

    public static function query(): \Screenart\Musedock\Database\QueryBuilder
    {
        return Database::table(static::$table);
    }

    public function order(): ?Order
    {
        return Order::find($this->order_id);
    }

    public function product(): ?Product
    {
        if (!$this->product_id) return null;
        return Product::find($this->product_id);
    }

    public function getFormattedUnitPrice(): string
    {
        $order = $this->order();
        $currency = $order ? $order->currency : 'eur';
        return shop_format_price($this->unit_price, $currency);
    }

    public function getFormattedTotal(): string
    {
        $order = $this->order();
        $currency = $order ? $order->currency : 'eur';
        return shop_format_price($this->total, $currency);
    }

    public function getMetadata(): array
    {
        if (empty($this->metadata)) return [];
        $decoded = json_decode($this->metadata, true);
        return is_array($decoded) ? $decoded : [];
    }
}
