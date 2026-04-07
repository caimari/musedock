<?php

namespace Shop\Controllers\Tenant;

use Screenart\Musedock\View;
use Shop\Models\Product;

class ShopController
{
    public function catalog()
    {
        $tenantId = tenant_id();
        $products = Product::getActive($tenantId);

        echo View::renderModule('musedock-shop', 'tenant.shop.catalog.index', [
            'title' => __('shop.shop'),
            'products' => $products,
        ]);
    }

    public function product($slug)
    {
        $tenantId = tenant_id();
        $product = Product::findBySlug($slug, $tenantId);

        if (!$product || !$product->is_active) {
            http_response_code(404);
            echo View::renderTenant('errors.404');
            return;
        }

        // Related products (same type, exclude current)
        $related = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', 1)
            ->where('type', $product->type)
            ->whereRaw('id != ?', [$product->id])
            ->limit(4)
            ->get();
        $related = array_map(fn($row) => new Product($row), $related);

        echo View::renderModule('musedock-shop', 'tenant.shop.catalog.product', [
            'title' => $product->name,
            'product' => $product,
            'related' => $related,
        ]);
    }
}
