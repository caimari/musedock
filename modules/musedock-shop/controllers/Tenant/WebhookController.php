<?php

namespace Shop\Controllers\Tenant;

use Shop\Models\Order;
use Shop\Models\Subscription;
use Shop\Models\Customer;
use Shop\Services\StripeService;
use Shop\Services\CheckoutService;
use Shop\Events\ShopEvents;
use Screenart\Musedock\Logger;

class WebhookController
{
    public function handleStripe()
    {
        $payload = file_get_contents('php://input');
        $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        try {
            $stripe = new StripeService(tenant_id());
            $event = $stripe->constructWebhookEvent($payload, $sigHeader);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Logger::warning("Shop Webhook: Invalid signature");
            http_response_code(400);
            echo json_encode(['error' => 'Invalid signature']);
            exit;
        } catch (\Throwable $e) {
            Logger::error("Shop Webhook: " . $e->getMessage());
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }

        Logger::info("Shop Webhook: Received event {$event->type}");

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
            'payment_intent.succeeded' => $this->handlePaymentSucceeded($event->data->object),
            'invoice.paid' => $this->handleInvoicePaid($event->data->object),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event->data->object),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
            default => Logger::debug("Shop Webhook: Unhandled event type {$event->type}"),
        };

        http_response_code(200);
        echo json_encode(['received' => true]);
        exit;
    }

    private function handleCheckoutCompleted($session): void
    {
        $order = Order::findByCheckoutSession($session->id);
        // Auditoría 2026-10-05: el objeto debe ser del tenant cuyo secreto firmó el aviso
        if ($order && !$this->belongsToCurrentTenant($order)) {
            Logger::warning("Shop Webhook: object from another tenant ignored");
            return;
        }
        if (!$order) {
            Logger::warning("Shop Webhook: Order not found for session {$session->id}");
            return;
        }

        if ($order->isCompleted()) return;

        // For subscription mode, the subscription ID is in the session
        if ($session->mode === 'subscription' && !empty($session->subscription)) {
            // Link subscriptions
            $subs = Subscription::query()
                ->where('order_id', $order->id)
                ->get();
            foreach ($subs as $sub) {
                (new Subscription($sub))->update([
                    'stripe_subscription_id' => $session->subscription,
                ]);
            }
        }

        CheckoutService::completeOrder($order);
        Logger::info("Shop Webhook: Order {$order->order_number} completed via checkout session");
    }

    private function handlePaymentSucceeded($paymentIntent): void
    {
        $order = Order::findByPaymentIntent($paymentIntent->id);
        // Auditoría 2026-10-05: el objeto debe ser del tenant cuyo secreto firmó el aviso
        if ($order && !$this->belongsToCurrentTenant($order)) {
            Logger::warning("Shop Webhook: object from another tenant ignored");
            return;
        }
        if (!$order || $order->isCompleted()) return;

        CheckoutService::completeOrder($order);
        Logger::info("Shop Webhook: Order {$order->order_number} completed via payment intent");
    }

    private function handleInvoicePaid($invoice): void
    {
        $stripeSubId = $invoice->subscription ?? null;
        if (!$stripeSubId) return;

        $subscription = Subscription::findByStripeId($stripeSubId);
        // Auditoría 2026-10-05: el objeto debe ser del tenant cuyo secreto firmó el aviso
        if ($subscription && !$this->belongsToCurrentTenant($subscription)) {
            Logger::warning("Shop Webhook: object from another tenant ignored");
            return;
        }
        if (!$subscription) return;

        $periodEnd = $invoice->lines?->data[0]?->period?->end ?? null;

        $subscription->update([
            'status' => 'active',
            'current_period_start' => date('Y-m-d H:i:s', $invoice->lines?->data[0]?->period?->start ?? time()),
            'current_period_end' => $periodEnd ? date('Y-m-d H:i:s', $periodEnd) : null,
        ]);

        ShopEvents::subscriptionRenewed($subscription);
        Logger::info("Shop Webhook: Subscription {$subscription->id} renewed");
    }

    private function handleInvoicePaymentFailed($invoice): void
    {
        $stripeSubId = $invoice->subscription ?? null;
        if (!$stripeSubId) return;

        $subscription = Subscription::findByStripeId($stripeSubId);
        // Auditoría 2026-10-05: el objeto debe ser del tenant cuyo secreto firmó el aviso
        if ($subscription && !$this->belongsToCurrentTenant($subscription)) {
            Logger::warning("Shop Webhook: object from another tenant ignored");
            return;
        }
        if (!$subscription) return;

        $subscription->update(['status' => 'past_due']);
        ShopEvents::subscriptionPastDue($subscription);
        Logger::warning("Shop Webhook: Subscription {$subscription->id} payment failed");
    }

    private function handleSubscriptionUpdated($stripeSubscription): void
    {
        $subscription = Subscription::findByStripeId($stripeSubscription->id);
        // Auditoría 2026-10-05: el objeto debe ser del tenant cuyo secreto firmó el aviso
        if ($subscription && !$this->belongsToCurrentTenant($subscription)) {
            Logger::warning("Shop Webhook: object from another tenant ignored");
            return;
        }
        if (!$subscription) return;

        $subscription->update([
            'status' => match ($stripeSubscription->status) {
                'active' => 'active',
                'past_due' => 'past_due',
                'canceled' => 'cancelled',
                'paused' => 'paused',
                default => $subscription->status,
            },
            'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end ? 1 : 0,
            'current_period_start' => date('Y-m-d H:i:s', $stripeSubscription->current_period_start),
            'current_period_end' => date('Y-m-d H:i:s', $stripeSubscription->current_period_end),
        ]);

        Logger::info("Shop Webhook: Subscription {$subscription->id} updated to {$stripeSubscription->status}");
    }

    private function handleSubscriptionDeleted($stripeSubscription): void
    {
        $subscription = Subscription::findByStripeId($stripeSubscription->id);
        // Auditoría 2026-10-05: el objeto debe ser del tenant cuyo secreto firmó el aviso
        if ($subscription && !$this->belongsToCurrentTenant($subscription)) {
            Logger::warning("Shop Webhook: object from another tenant ignored");
            return;
        }
        if (!$subscription) return;

        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s'),
        ]);

        ShopEvents::subscriptionCancelled($subscription);
        Logger::info("Shop Webhook: Subscription {$subscription->id} cancelled");
    }

    /** Auditoría 2026-10-05: ¿pertenece el pedido/suscripción al tenant actual? */
    private function belongsToCurrentTenant($obj): bool
    {
        $current = function_exists('tenant_id') ? tenant_id() : null;
        return (int) ($obj->tenant_id ?? 0) === (int) ($current ?? 0);
    }
}
