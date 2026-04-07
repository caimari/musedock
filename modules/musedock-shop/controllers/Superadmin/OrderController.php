<?php

namespace Shop\Controllers\Superadmin;

use Screenart\Musedock\View;
use Shop\Models\Order;
use Shop\Models\Customer;
use Shop\Services\CheckoutService;
use Shop\Services\StripeService;
use Screenart\Musedock\Logger;

class OrderController
{
    private function checkPermission(): void
    {
        if (!userCan('shop.manage')) {
            flash('error', 'No tienes permiso para gestionar pedidos.');
            header('Location: /musedock/dashboard');
            exit;
        }
    }

    public function index()
    {
        $this->checkPermission();

        $search = trim($_GET['search'] ?? '');
        $status = $_GET['status'] ?? '';
        $perPage = (int) ($_GET['perPage'] ?? 15);
        $currentPage = max(1, (int) ($_GET['page'] ?? 1));

        $query = Order::query()
            ->whereNull('tenant_id')
            ->orderBy('created_at', 'DESC');

        if (!empty($search)) {
            $query->whereRaw(
                "(order_number LIKE ? OR billing_name LIKE ? OR billing_email LIKE ?)",
                ["%{$search}%", "%{$search}%", "%{$search}%"]
            );
        }

        if (!empty($status)) {
            $query->where('status', $status);
        }

        $totalItems = (clone $query)->count();
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $offset = ($currentPage - 1) * $perPage;
        $orders = $query->limit($perPage)->offset($offset)->get();
        $orders = array_map(fn($row) => new Order($row), $orders);

        // Stats
        $stats = [
            'total' => Order::query()->whereNull('tenant_id')->count(),
            'pending' => Order::query()->whereNull('tenant_id')->where('status', 'pending')->count(),
            'completed' => Order::query()->whereNull('tenant_id')->where('status', 'completed')->count(),
        ];

        echo View::renderModule('musedock-shop', 'superadmin.shop.orders.index', [
            'title' => __('shop.orders'),
            'orders' => $orders,
            'stats' => $stats,
            'search' => $search,
            'status' => $status,
            'perPage' => $perPage,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
        ]);
    }

    public function show($id)
    {
        $this->checkPermission();

        $order = Order::find($id);
        if (!$order) {
            flash('error', 'Pedido no encontrado.');
            header('Location: ' . route('shop.orders.index'));
            exit;
        }

        $customer = $order->customer();
        $items = $order->items();

        echo View::renderModule('musedock-shop', 'superadmin.shop.orders.show', [
            'title' => "Pedido {$order->order_number}",
            'order' => $order,
            'customer' => $customer,
            'items' => $items,
        ]);
    }

    public function update($id)
    {
        $this->checkPermission();

        $order = Order::find($id);
        if (!$order) {
            flash('error', 'Pedido no encontrado.');
            header('Location: ' . route('shop.orders.index'));
            exit;
        }

        $newStatus = $_POST['status'] ?? $order->status;
        $notes = $_POST['notes'] ?? $order->notes;

        if ($newStatus === 'completed' && !$order->isCompleted()) {
            CheckoutService::completeOrder($order);
        } elseif ($newStatus === 'cancelled' && !$order->isCancelled()) {
            CheckoutService::cancelOrder($order);
        } elseif ($newStatus === 'refunded' && !$order->isRefunded()) {
            // Try refund via Stripe
            try {
                if ($order->stripe_payment_intent_id && shop_setting('stripe_secret_key')) {
                    $stripe = new StripeService();
                    $stripe->refundPayment($order->stripe_payment_intent_id);
                }
            } catch (\Throwable $e) {
                Logger::warning("Shop: Stripe refund failed for order {$order->order_number}: " . $e->getMessage());
            }
            CheckoutService::refundOrder($order);
        } else {
            $order->update([
                'status' => $newStatus,
                'notes' => $notes,
            ]);
        }

        flash('success', __('shop.order.updated'));
        header('Location: ' . route('shop.orders.show', ['id' => $id]));
        exit;
    }
}
