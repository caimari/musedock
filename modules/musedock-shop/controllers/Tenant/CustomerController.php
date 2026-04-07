<?php

namespace Shop\Controllers\Tenant;

use Screenart\Musedock\View;
use Shop\Models\Customer;
use Shop\Models\Order;
use Shop\Models\Subscription;
use Shop\Models\Invoice;
use Shop\Services\StripeService;
use Shop\Events\ShopEvents;
use Screenart\Musedock\Logger;

class CustomerController
{
    private function getCurrentCustomer(): ?Customer
    {
        $userId = $_SESSION['user']['id'] ?? $_SESSION['admin']['id'] ?? null;
        $userType = isset($_SESSION['admin']) ? 'admin' : 'user';
        $tenantId = tenant_id();

        if (!$userId) return null;

        $row = Customer::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('user_type', $userType)
            ->first();

        return $row ? new Customer($row) : null;
    }

    public function dashboard()
    {
        $customer = $this->getCurrentCustomer();

        if (!$customer) {
            flash('error', 'No se encontró tu cuenta de cliente.');
            header('Location: /shop');
            exit;
        }

        $recentOrders = Order::query()
            ->where('customer_id', $customer->id)
            ->orderBy('created_at', 'DESC')
            ->limit(5)
            ->get();
        $recentOrders = array_map(fn($row) => new Order($row), $recentOrders);

        $activeSubscriptions = Subscription::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->get();
        $activeSubscriptions = array_map(fn($row) => new Subscription($row), $activeSubscriptions);

        echo View::renderModule('musedock-shop', 'tenant.shop.customer.dashboard', [
            'title' => __('shop.customer.my_orders'),
            'customer' => $customer,
            'recentOrders' => $recentOrders,
            'activeSubscriptions' => $activeSubscriptions,
        ]);
    }

    public function orders()
    {
        $customer = $this->getCurrentCustomer();
        if (!$customer) {
            header('Location: /shop');
            exit;
        }

        $orders = $customer->orders();

        echo View::renderModule('musedock-shop', 'tenant.shop.customer.orders', [
            'title' => __('shop.customer.my_orders'),
            'orders' => $orders,
        ]);
    }

    public function orderDetail($id)
    {
        $customer = $this->getCurrentCustomer();
        if (!$customer) {
            header('Location: /shop');
            exit;
        }

        $order = Order::find($id);
        if (!$order || $order->customer_id != $customer->id) {
            flash('error', 'Pedido no encontrado.');
            header('Location: /shop/account/orders');
            exit;
        }

        echo View::renderModule('musedock-shop', 'tenant.shop.customer.order-detail', [
            'title' => "Pedido {$order->order_number}",
            'order' => $order,
            'items' => $order->items(),
        ]);
    }

    public function subscriptions()
    {
        $customer = $this->getCurrentCustomer();
        if (!$customer) {
            header('Location: /shop');
            exit;
        }

        $subscriptions = $customer->subscriptions();

        // Enrich with product names
        foreach ($subscriptions as $sub) {
            $sub->_product = $sub->product();
        }

        echo View::renderModule('musedock-shop', 'tenant.shop.customer.subscriptions', [
            'title' => __('shop.customer.my_subscriptions'),
            'subscriptions' => $subscriptions,
        ]);
    }

    public function cancelSubscription($id)
    {
        $customer = $this->getCurrentCustomer();
        if (!$customer) {
            header('Location: /shop');
            exit;
        }

        $subscription = Subscription::find($id);
        if (!$subscription || $subscription->customer_id != $customer->id) {
            flash('error', 'Suscripción no encontrada.');
            header('Location: /shop/account/subscriptions');
            exit;
        }

        // Cancel at period end (customer-initiated)
        if ($subscription->stripe_subscription_id && shop_setting('stripe_secret_key')) {
            try {
                $stripe = new StripeService(tenant_id());
                $stripe->cancelSubscription($subscription->stripe_subscription_id, true);
            } catch (\Throwable $e) {
                Logger::warning("Shop: Customer cancel failed: " . $e->getMessage());
            }
        }

        $subscription->update(['cancel_at_period_end' => 1]);

        flash('success', 'Tu suscripción se cancelará al final del período actual.');
        header('Location: /shop/account/subscriptions');
        exit;
    }
}
