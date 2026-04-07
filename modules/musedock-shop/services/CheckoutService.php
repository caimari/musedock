<?php

namespace Shop\Services;

use Shop\Models\Product;
use Shop\Models\Order;
use Shop\Models\OrderItem;
use Shop\Models\Customer;
use Shop\Models\Coupon;
use Shop\Models\Invoice;
use Shop\Models\Subscription;
use Shop\Events\ShopEvents;
use Screenart\Musedock\Logger;

class CheckoutService
{
    /**
     * Get or initialize cart from session
     */
    public static function getCart(): array
    {
        return $_SESSION['shop_cart'] ?? [];
    }

    public static function addToCart(int $productId, int $quantity = 1, array $metadata = []): array
    {
        $product = Product::find($productId);
        if (!$product || !$product->is_active) {
            return ['success' => false, 'message' => 'Product not found or inactive'];
        }

        if (!$product->hasStock()) {
            return ['success' => false, 'message' => 'Product out of stock'];
        }

        $cart = self::getCart();
        $key = (string) $productId;

        if (isset($cart[$key])) {
            $cart[$key]['quantity'] += $quantity;
        } else {
            $cart[$key] = [
                'product_id' => $productId,
                'name' => $product->name,
                'price' => $product->price,
                'currency' => $product->currency,
                'type' => $product->type,
                'quantity' => $quantity,
                'metadata' => $metadata,
            ];
        }

        // Subscription products: quantity always 1
        if ($product->isSubscription()) {
            $cart[$key]['quantity'] = 1;
        }

        $_SESSION['shop_cart'] = $cart;
        return ['success' => true, 'cart' => $cart];
    }

    public static function updateCartItem(int $productId, int $quantity): array
    {
        $cart = self::getCart();
        $key = (string) $productId;

        if (!isset($cart[$key])) {
            return ['success' => false, 'message' => 'Item not in cart'];
        }

        if ($quantity <= 0) {
            unset($cart[$key]);
        } else {
            $cart[$key]['quantity'] = $quantity;
        }

        $_SESSION['shop_cart'] = $cart;
        return ['success' => true, 'cart' => $cart];
    }

    public static function removeFromCart(int $productId): array
    {
        $cart = self::getCart();
        unset($cart[(string) $productId]);
        $_SESSION['shop_cart'] = $cart;
        return ['success' => true, 'cart' => $cart];
    }

    public static function clearCart(): void
    {
        unset($_SESSION['shop_cart']);
        unset($_SESSION['shop_coupon']);
    }

    public static function getCartTotals(): array
    {
        $cart = self::getCart();
        $subtotal = 0;
        $currency = 'eur';

        foreach ($cart as $item) {
            $subtotal += $item['price'] * $item['quantity'];
            $currency = $item['currency'] ?? 'eur';
        }

        // Apply coupon
        $discount = 0;
        $couponCode = $_SESSION['shop_coupon'] ?? null;
        if ($couponCode) {
            $tenantId = function_exists('tenant_id') ? tenant_id() : null;
            $coupon = Coupon::findByCode($couponCode, $tenantId);
            if ($coupon && $coupon->isValid()) {
                $discount = $coupon->calculateDiscount($subtotal);
            }
        }

        $afterDiscount = max(0, $subtotal - $discount);

        // Tax
        $taxRate = (float) shop_setting('default_tax_rate', 21); // default 21% IVA
        $taxAmount = (int) round($afterDiscount * $taxRate / 100);
        $total = $afterDiscount + $taxAmount;

        return [
            'items' => $cart,
            'item_count' => array_sum(array_column($cart, 'quantity')),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'coupon_code' => $couponCode,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'currency' => $currency,
        ];
    }

    /**
     * Create an Order from cart contents
     */
    public static function createOrderFromCart(
        string $billingEmail,
        string $billingName,
        ?string $billingPhone = null,
        ?array $billingAddress = null
    ): Order {
        $tenantId = function_exists('tenant_id') ? tenant_id() : null;
        $totals = self::getCartTotals();

        // Find or create customer
        $customer = Customer::findOrCreateByEmail($billingEmail, $billingName, $tenantId);
        if ($billingPhone) $customer->update(['phone' => $billingPhone]);

        // Find coupon
        $couponId = null;
        if ($totals['coupon_code']) {
            $coupon = Coupon::findByCode($totals['coupon_code'], $tenantId);
            if ($coupon) $couponId = $coupon->id;
        }

        // Create order
        $order = Order::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customer->id,
            'order_number' => shop_generate_order_number(),
            'status' => 'pending',
            'subtotal' => $totals['subtotal'],
            'discount_amount' => $totals['discount'],
            'tax_rate' => $totals['tax_rate'],
            'tax_amount' => $totals['tax_amount'],
            'total' => $totals['total'],
            'currency' => $totals['currency'],
            'coupon_id' => $couponId,
            'billing_name' => $billingName,
            'billing_email' => $billingEmail,
            'billing_phone' => $billingPhone,
            'billing_address' => $billingAddress ? json_encode($billingAddress) : null,
        ]);

        // Create order items
        foreach ($totals['items'] as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'product_name' => $item['name'],
                'product_type' => $item['type'] ?? null,
                'quantity' => $item['quantity'],
                'unit_price' => $item['price'],
                'total' => $item['price'] * $item['quantity'],
                'metadata' => !empty($item['metadata']) ? json_encode($item['metadata']) : null,
            ]);
        }

        return $order;
    }

    /**
     * Complete an order after payment confirmation
     */
    public static function completeOrder(Order $order): void
    {
        $order->update([
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
        ]);

        // Increment coupon usage
        if ($order->coupon_id) {
            $coupon = Coupon::find($order->coupon_id);
            if ($coupon) $coupon->incrementUsage();
        }

        // Create subscriptions for subscription items
        foreach ($order->items() as $item) {
            if ($item->product_type === 'subscription') {
                $product = $item->product();
                if ($product) {
                    Subscription::create([
                        'tenant_id' => $order->tenant_id,
                        'customer_id' => $order->customer_id,
                        'product_id' => $product->id,
                        'order_id' => $order->id,
                        'status' => 'active',
                        'billing_period' => $product->billing_period ?? 'monthly',
                        'current_period_start' => date('Y-m-d H:i:s'),
                        'current_period_end' => date('Y-m-d H:i:s', strtotime(
                            $product->billing_period === 'yearly' ? '+1 year' : '+1 month'
                        )),
                        'metadata' => $item->metadata,
                    ]);
                }
            }
        }

        // Create invoice
        $customer = $order->customer();
        if ($customer) {
            Invoice::create([
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'invoice_number' => Invoice::generateNumber($order->tenant_id),
                'subtotal' => $order->subtotal,
                'tax_rate' => $order->tax_rate,
                'tax_amount' => $order->tax_amount,
                'total' => $order->total,
                'currency' => $order->currency,
                'status' => 'paid',
                'issued_at' => date('Y-m-d H:i:s'),
                'paid_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // Clear cart
        self::clearCart();

        // Fire event
        ShopEvents::orderCompleted($order);

        Logger::info("Shop: Order {$order->order_number} completed");
    }

    /**
     * Cancel an order
     */
    public static function cancelOrder(Order $order): void
    {
        $order->update([
            'status' => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s'),
        ]);

        ShopEvents::orderCancelled($order);
        Logger::info("Shop: Order {$order->order_number} cancelled");
    }

    /**
     * Refund an order
     */
    public static function refundOrder(Order $order): void
    {
        $order->update([
            'status' => 'refunded',
            'refunded_at' => date('Y-m-d H:i:s'),
        ]);

        // Update invoice
        $invoice = Invoice::query()
            ->where('order_id', $order->id)
            ->first();
        if ($invoice) {
            (new Invoice($invoice))->update(['status' => 'void']);
        }

        ShopEvents::orderRefunded($order);
        Logger::info("Shop: Order {$order->order_number} refunded");
    }
}
