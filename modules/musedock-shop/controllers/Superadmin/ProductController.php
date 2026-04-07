<?php

namespace Shop\Controllers\Superadmin;

use Screenart\Musedock\View;
use Shop\Models\Product;
use Shop\Services\StripeService;
use Screenart\Musedock\Services\SlugService;
use Screenart\Musedock\Logger;

class ProductController
{
    private function checkPermission(): void
    {
        if (!userCan('shop.manage')) {
            flash('error', 'No tienes permiso para gestionar productos.');
            header('Location: /musedock/dashboard');
            exit;
        }
    }

    public function index()
    {
        $this->checkPermission();

        $search = trim($_GET['search'] ?? '');
        $perPage = (int) ($_GET['perPage'] ?? 10);
        $currentPage = max(1, (int) ($_GET['page'] ?? 1));
        $orderBy = $_GET['orderby'] ?? 'sort_order';
        $order = strtoupper($_GET['order'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

        $allowedColumns = ['name', 'price', 'type', 'is_active', 'sort_order', 'created_at'];
        if (!in_array($orderBy, $allowedColumns)) $orderBy = 'sort_order';

        $query = Product::query()
            ->whereNull('tenant_id')
            ->orderBy($orderBy, $order);

        if (!empty($search)) {
            $query->whereRaw("(name LIKE ? OR slug LIKE ? OR description LIKE ?)",
                ["%{$search}%", "%{$search}%", "%{$search}%"]);
        }

        if ($perPage == -1) {
            $products = $query->get();
            $totalItems = count($products);
            $totalPages = 1;
        } else {
            $countQuery = Product::query()->whereNull('tenant_id');
            if (!empty($search)) {
                $countQuery->whereRaw("(name LIKE ? OR slug LIKE ? OR description LIKE ?)",
                    ["%{$search}%", "%{$search}%", "%{$search}%"]);
            }
            $totalItems = $countQuery->count();
            $totalPages = max(1, (int) ceil($totalItems / $perPage));
            $offset = ($currentPage - 1) * $perPage;
            $products = $query->limit($perPage)->offset($offset)->get();
        }

        $products = array_map(fn($row) => new Product($row), $products);

        echo View::renderModule('musedock-shop', 'superadmin.shop.products.index', [
            'title' => __('shop.products'),
            'products' => $products,
            'search' => $search,
            'perPage' => $perPage,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'orderBy' => $orderBy,
            'order' => $order,
        ]);
    }

    public function create()
    {
        $this->checkPermission();

        echo View::renderModule('musedock-shop', 'superadmin.shop.products.create', [
            'title' => __('shop.product.new'),
        ]);
    }

    public function store()
    {
        $this->checkPermission();

        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');

        if (empty($name)) {
            flash('error', 'El nombre es obligatorio.');
            header('Location: ' . route('shop.products.create'));
            exit;
        }

        if (empty($slug)) {
            $slug = SlugService::generateSlug($name);
        }

        // Check unique slug
        if (Product::findBySlug($slug)) {
            $slug = $slug . '-' . time();
        }

        $price = (int) round(((float) str_replace(',', '.', $_POST['price'] ?? '0')) * 100);
        $comparePrice = !empty($_POST['compare_price'])
            ? (int) round(((float) str_replace(',', '.', $_POST['compare_price'])) * 100)
            : null;

        $features = array_filter(array_map('trim', explode("\n", $_POST['features'] ?? '')));
        $metadata = ['features' => $features];

        $product = Product::create([
            'tenant_id' => null,
            'name' => $name,
            'slug' => $slug,
            'description' => $_POST['description'] ?? null,
            'short_description' => $_POST['short_description'] ?? null,
            'type' => $_POST['type'] ?? 'digital',
            'price' => $price,
            'compare_price' => $comparePrice,
            'currency' => $_POST['currency'] ?? 'eur',
            'billing_period' => ($_POST['type'] ?? '') === 'subscription' ? ($_POST['billing_period'] ?? 'monthly') : null,
            'featured_image' => $_POST['featured_image'] ?? null,
            'metadata' => json_encode($metadata),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
            'stock_quantity' => !empty($_POST['stock_quantity']) ? (int) $_POST['stock_quantity'] : null,
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        ]);

        // Sync with Stripe if keys configured
        try {
            if (shop_setting('stripe_secret_key')) {
                $stripeService = new StripeService();
                $stripeService->syncProduct($product);
            }
        } catch (\Throwable $e) {
            Logger::warning("Shop: Stripe sync failed for product {$product->id}: " . $e->getMessage());
        }

        flash('success', __('shop.product.created'));
        header('Location: ' . route('shop.products.index'));
        exit;
    }

    public function edit($id)
    {
        $this->checkPermission();

        $product = Product::find($id);
        if (!$product) {
            flash('error', 'Producto no encontrado.');
            header('Location: ' . route('shop.products.index'));
            exit;
        }

        echo View::renderModule('musedock-shop', 'superadmin.shop.products.edit', [
            'title' => __('shop.product.edit'),
            'product' => $product,
        ]);
    }

    public function update($id)
    {
        $this->checkPermission();

        $product = Product::find($id);
        if (!$product) {
            flash('error', 'Producto no encontrado.');
            header('Location: ' . route('shop.products.index'));
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            flash('error', 'El nombre es obligatorio.');
            header('Location: ' . route('shop.products.edit', ['id' => $id]));
            exit;
        }

        $slug = trim($_POST['slug'] ?? '') ?: SlugService::generateSlug($name);
        $existing = Product::findBySlug($slug);
        if ($existing && $existing->id != $id) {
            $slug = $slug . '-' . time();
        }

        $price = (int) round(((float) str_replace(',', '.', $_POST['price'] ?? '0')) * 100);
        $comparePrice = !empty($_POST['compare_price'])
            ? (int) round(((float) str_replace(',', '.', $_POST['compare_price'])) * 100)
            : null;

        $features = array_filter(array_map('trim', explode("\n", $_POST['features'] ?? '')));
        $metadata = ['features' => $features];

        $product->update([
            'name' => $name,
            'slug' => $slug,
            'description' => $_POST['description'] ?? null,
            'short_description' => $_POST['short_description'] ?? null,
            'type' => $_POST['type'] ?? 'digital',
            'price' => $price,
            'compare_price' => $comparePrice,
            'currency' => $_POST['currency'] ?? 'eur',
            'billing_period' => ($_POST['type'] ?? '') === 'subscription' ? ($_POST['billing_period'] ?? 'monthly') : null,
            'featured_image' => $_POST['featured_image'] ?? null,
            'metadata' => json_encode($metadata),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
            'stock_quantity' => !empty($_POST['stock_quantity']) ? (int) $_POST['stock_quantity'] : null,
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        ]);

        // Sync with Stripe
        try {
            if (shop_setting('stripe_secret_key')) {
                $stripeService = new StripeService();
                $stripeService->syncProduct($product);
            }
        } catch (\Throwable $e) {
            Logger::warning("Shop: Stripe sync failed for product {$product->id}: " . $e->getMessage());
        }

        flash('success', __('shop.product.updated'));
        header('Location: ' . route('shop.products.edit', ['id' => $id]));
        exit;
    }

    public function destroy($id)
    {
        $this->checkPermission();

        $product = Product::find($id);
        if ($product) {
            $product->delete();
            flash('success', __('shop.product.deleted'));
        }

        header('Location: ' . route('shop.products.index'));
        exit;
    }
}
