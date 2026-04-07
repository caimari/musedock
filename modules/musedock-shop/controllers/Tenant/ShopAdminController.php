<?php

namespace Shop\Controllers\Tenant;

use Screenart\Musedock\View;
use Shop\Models\Product;
use Shop\Models\Order;
use Screenart\Musedock\Services\SlugService;

/**
 * Tenant admin controller for managing shop (products, orders, settings)
 */
class ShopAdminController
{
    // ========== PRODUCTS ==========

    public function products()
    {
        $tenantId = tenant_id();
        $search = trim($_GET['search'] ?? '');

        $query = Product::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order', 'ASC');

        if (!empty($search)) {
            $query->whereRaw("(name LIKE ? OR slug LIKE ?)",
                ["%{$search}%", "%{$search}%"]);
        }

        $products = $query->get();
        $products = array_map(fn($row) => new Product($row), $products);

        echo View::renderModule('musedock-shop', 'tenant.shop.admin.products', [
            'title' => __('shop.products'),
            'products' => $products,
            'search' => $search,
        ]);
    }

    public function createProduct()
    {
        echo View::renderModule('musedock-shop', 'tenant.shop.admin.product-form', [
            'title' => __('shop.product.new'),
            'product' => null,
        ]);
    }

    public function storeProduct()
    {
        $tenantId = tenant_id();
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '') ?: SlugService::generateSlug($name);

        if (empty($name)) {
            flash('error', 'El nombre es obligatorio.');
            header('Location: ' . admin_url('shop/products/create'));
            exit;
        }

        $price = (int) round(((float) str_replace(',', '.', $_POST['price'] ?? '0')) * 100);

        Product::create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'slug' => $slug,
            'description' => $_POST['description'] ?? null,
            'short_description' => $_POST['short_description'] ?? null,
            'type' => $_POST['type'] ?? 'digital',
            'price' => $price,
            'currency' => $_POST['currency'] ?? 'eur',
            'billing_period' => ($_POST['type'] ?? '') === 'subscription' ? ($_POST['billing_period'] ?? 'monthly') : null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        ]);

        flash('success', __('shop.product.created'));
        header('Location: ' . admin_url('shop/products'));
        exit;
    }

    public function editProduct($id)
    {
        $product = Product::find($id);
        if (!$product || $product->tenant_id != tenant_id()) {
            flash('error', 'Producto no encontrado.');
            header('Location: ' . admin_url('shop/products'));
            exit;
        }

        echo View::renderModule('musedock-shop', 'tenant.shop.admin.product-form', [
            'title' => __('shop.product.edit'),
            'product' => $product,
        ]);
    }

    public function updateProduct($id)
    {
        $product = Product::find($id);
        if (!$product || $product->tenant_id != tenant_id()) {
            flash('error', 'Producto no encontrado.');
            header('Location: ' . admin_url('shop/products'));
            exit;
        }

        $price = (int) round(((float) str_replace(',', '.', $_POST['price'] ?? '0')) * 100);

        $product->update([
            'name' => trim($_POST['name'] ?? $product->name),
            'slug' => trim($_POST['slug'] ?? '') ?: $product->slug,
            'description' => $_POST['description'] ?? null,
            'short_description' => $_POST['short_description'] ?? null,
            'type' => $_POST['type'] ?? $product->type,
            'price' => $price,
            'currency' => $_POST['currency'] ?? $product->currency,
            'billing_period' => ($_POST['type'] ?? '') === 'subscription' ? ($_POST['billing_period'] ?? 'monthly') : null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        ]);

        flash('success', __('shop.product.updated'));
        header('Location: ' . admin_url('shop/products'));
        exit;
    }

    public function destroyProduct($id)
    {
        $product = Product::find($id);
        if ($product && $product->tenant_id == tenant_id()) {
            $product->delete();
            flash('success', __('shop.product.deleted'));
        }
        header('Location: ' . admin_url('shop/products'));
        exit;
    }

    // ========== ORDERS ==========

    public function orders()
    {
        $tenantId = tenant_id();
        $orders = Order::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'DESC')
            ->get();
        $orders = array_map(fn($row) => new Order($row), $orders);

        echo View::renderModule('musedock-shop', 'tenant.shop.admin.orders', [
            'title' => __('shop.orders'),
            'orders' => $orders,
        ]);
    }

    public function showOrder($id)
    {
        $order = Order::find($id);
        if (!$order || $order->tenant_id != tenant_id()) {
            flash('error', 'Pedido no encontrado.');
            header('Location: ' . admin_url('shop/orders'));
            exit;
        }

        echo View::renderModule('musedock-shop', 'tenant.shop.admin.order-detail', [
            'title' => "Pedido {$order->order_number}",
            'order' => $order,
            'items' => $order->items(),
            'customer' => $order->customer(),
        ]);
    }

    // ========== SETTINGS ==========

    public function settings()
    {
        echo View::renderModule('musedock-shop', 'tenant.shop.admin.settings', [
            'title' => __('shop.settings_page.title'),
            'shopSettings' => [
                'stripe_secret_key' => tenant_setting('shop_stripe_secret_key', ''),
                'stripe_publishable_key' => tenant_setting('shop_stripe_publishable_key', ''),
                'stripe_webhook_secret' => tenant_setting('shop_stripe_webhook_secret', ''),
                'default_currency' => tenant_setting('shop_default_currency', 'eur'),
                'default_tax_rate' => tenant_setting('shop_default_tax_rate', '21'),
                'order_email' => tenant_setting('shop_order_email', ''),
            ],
        ]);
    }

    public function saveSettings()
    {
        $fields = [
            'stripe_secret_key',
            'stripe_publishable_key',
            'stripe_webhook_secret',
            'default_currency',
            'default_tax_rate',
            'order_email',
        ];

        foreach ($fields as $field) {
            $value = $_POST[$field] ?? '';
            set_tenant_setting("shop_{$field}", $value);
        }

        flash('success', __('shop.settings_page.saved'));
        header('Location: ' . admin_url('shop/settings'));
        exit;
    }
}
