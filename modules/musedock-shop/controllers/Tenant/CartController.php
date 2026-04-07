<?php

namespace Shop\Controllers\Tenant;

use Screenart\Musedock\View;
use Shop\Models\Product;
use Shop\Models\Coupon;
use Shop\Services\CheckoutService;
use Shop\Services\StripeService;
use Screenart\Musedock\Logger;

class CartController
{
    public function index()
    {
        $totals = CheckoutService::getCartTotals();

        echo View::renderModule('musedock-shop', 'tenant.shop.cart.index', [
            'title' => __('shop.cart.title'),
            'totals' => $totals,
        ]);
    }

    public function add()
    {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));

        $result = CheckoutService::addToCart($productId, $quantity);

        if (!$result['success']) {
            flash('error', $result['message']);
        } else {
            flash('success', __('shop.cart.added'));
        }

        // Redirect back or to cart
        $redirect = $_POST['redirect'] ?? $_SERVER['HTTP_REFERER'] ?? '/shop/cart';
        header("Location: {$redirect}");
        exit;
    }

    public function update()
    {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $quantity = (int) ($_POST['quantity'] ?? 1);

        CheckoutService::updateCartItem($productId, $quantity);
        flash('success', __('shop.cart.updated'));

        header('Location: /shop/cart');
        exit;
    }

    public function remove()
    {
        $productId = (int) ($_POST['product_id'] ?? 0);
        CheckoutService::removeFromCart($productId);
        flash('success', __('shop.cart.removed'));

        header('Location: /shop/cart');
        exit;
    }

    public function applyCoupon()
    {
        $code = strtoupper(trim($_POST['coupon_code'] ?? ''));
        $tenantId = tenant_id();

        if (empty($code)) {
            flash('error', 'Introduce un código de cupón.');
            header('Location: /shop/cart');
            exit;
        }

        $coupon = Coupon::findByCode($code, $tenantId);

        if (!$coupon || !$coupon->isValid()) {
            flash('error', 'Cupón no válido o caducado.');
            header('Location: /shop/cart');
            exit;
        }

        $_SESSION['shop_coupon'] = $code;
        flash('success', "Cupón {$code} aplicado.");

        header('Location: /shop/cart');
        exit;
    }

    public function checkout()
    {
        $totals = CheckoutService::getCartTotals();

        if (empty($totals['items'])) {
            header('Location: /shop/cart');
            exit;
        }

        echo View::renderModule('musedock-shop', 'tenant.shop.checkout.index', [
            'title' => __('shop.checkout.title'),
            'totals' => $totals,
            'stripePublishableKey' => StripeService::getPublishableKey(),
        ]);
    }

    public function processCheckout()
    {
        $totals = CheckoutService::getCartTotals();
        if (empty($totals['items'])) {
            header('Location: /shop/cart');
            exit;
        }

        $billingEmail = trim($_POST['billing_email'] ?? '');
        $billingName = trim($_POST['billing_name'] ?? '');
        $billingPhone = trim($_POST['billing_phone'] ?? '') ?: null;

        if (empty($billingEmail) || empty($billingName)) {
            flash('error', 'Nombre y email son obligatorios.');
            header('Location: /shop/checkout');
            exit;
        }

        try {
            $order = CheckoutService::createOrderFromCart(
                $billingEmail,
                $billingName,
                $billingPhone
            );

            // If Stripe configured, redirect to Stripe Checkout
            if (shop_setting('stripe_secret_key')) {
                $tenantId = tenant_id();
                $stripe = new StripeService($tenantId);
                $customer = $order->customer();

                $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                    . '://' . $_SERVER['HTTP_HOST'];

                $session = $stripe->createCheckoutSession(
                    $order,
                    $customer,
                    $baseUrl . '/shop/checkout/success',
                    $baseUrl . '/shop/checkout/cancel'
                );

                header("Location: {$session->url}");
                exit;
            }

            // No Stripe: mark as completed directly (free/test)
            CheckoutService::completeOrder($order);
            header('Location: /shop/checkout/success?order=' . $order->order_number);
            exit;

        } catch (\Throwable $e) {
            Logger::error("Shop checkout error: " . $e->getMessage());
            flash('error', __('shop.checkout.error'));
            header('Location: /shop/checkout');
            exit;
        }
    }

    public function checkoutSuccess()
    {
        $sessionId = $_GET['session_id'] ?? null;
        $orderNumber = $_GET['order'] ?? null;

        $order = null;
        if ($sessionId) {
            $order = \Shop\Models\Order::findByCheckoutSession($sessionId);
        } elseif ($orderNumber) {
            $order = \Shop\Models\Order::findByOrderNumber($orderNumber);
        }

        CheckoutService::clearCart();

        echo View::renderModule('musedock-shop', 'tenant.shop.checkout.success', [
            'title' => __('shop.checkout.success'),
            'order' => $order,
        ]);
    }

    public function checkoutCancel()
    {
        echo View::renderModule('musedock-shop', 'tenant.shop.checkout.cancel', [
            'title' => 'Pago cancelado',
        ]);
    }
}
