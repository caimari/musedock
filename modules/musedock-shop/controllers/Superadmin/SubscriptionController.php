<?php

namespace Shop\Controllers\Superadmin;

use Screenart\Musedock\View;
use Shop\Models\Subscription;
use Shop\Models\Customer;
use Shop\Models\Product;
use Shop\Services\StripeService;
use Shop\Events\ShopEvents;
use Screenart\Musedock\Logger;

class SubscriptionController
{
    private function checkPermission(): void
    {
        if (!userCan('shop.manage')) {
            flash('error', 'No tienes permiso para gestionar suscripciones.');
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

        $query = Subscription::query()
            ->whereNull('tenant_id')
            ->orderBy('created_at', 'DESC');

        if (!empty($status)) {
            $query->where('status', $status);
        }

        $totalItems = (clone $query)->count();
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $offset = ($currentPage - 1) * $perPage;
        $subscriptions = $query->limit($perPage)->offset($offset)->get();
        $subscriptions = array_map(fn($row) => new Subscription($row), $subscriptions);

        echo View::renderModule('musedock-shop', 'superadmin.shop.subscriptions.index', [
            'title' => __('shop.subscriptions'),
            'subscriptions' => $subscriptions,
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

        $subscription = Subscription::find($id);
        if (!$subscription) {
            flash('error', 'Suscripción no encontrada.');
            header('Location: ' . route('shop.subscriptions.index'));
            exit;
        }

        $customer = $subscription->customer();
        $product = $subscription->product();

        echo View::renderModule('musedock-shop', 'superadmin.shop.subscriptions.show', [
            'title' => "Suscripción #{$subscription->id}",
            'subscription' => $subscription,
            'customer' => $customer,
            'product' => $product,
        ]);
    }

    public function cancel($id)
    {
        $this->checkPermission();

        $subscription = Subscription::find($id);
        if (!$subscription) {
            flash('error', 'Suscripción no encontrada.');
            header('Location: ' . route('shop.subscriptions.index'));
            exit;
        }

        $atPeriodEnd = isset($_POST['at_period_end']);

        // Cancel in Stripe
        if ($subscription->stripe_subscription_id && shop_setting('stripe_secret_key')) {
            try {
                $stripe = new StripeService();
                $stripe->cancelSubscription($subscription->stripe_subscription_id, $atPeriodEnd);
            } catch (\Throwable $e) {
                Logger::warning("Shop: Stripe cancel failed for sub {$subscription->id}: " . $e->getMessage());
            }
        }

        if ($atPeriodEnd) {
            $subscription->update([
                'cancel_at_period_end' => 1,
            ]);
        } else {
            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => date('Y-m-d H:i:s'),
            ]);
            ShopEvents::subscriptionCancelled($subscription);
        }

        flash('success', 'Suscripción cancelada.');
        header('Location: ' . route('shop.subscriptions.index'));
        exit;
    }
}
