<?php

namespace Shop\Services;

use Shop\Models\Product;
use Shop\Models\Customer;
use Shop\Models\Order;
use Shop\Models\Subscription;
use Screenart\Musedock\Logger;

/**
 * Stripe API wrapper.
 * Uses Stripe PHP SDK via Composer (stripe/stripe-php).
 */
class StripeService
{
    private \Stripe\StripeClient $stripe;
    private ?int $tenantId;

    public function __construct(?int $tenantId = null)
    {
        $this->tenantId = $tenantId;
        $secretKey = shop_setting('stripe_secret_key');

        if (empty($secretKey)) {
            throw new \RuntimeException('Stripe secret key not configured');
        }

        $this->stripe = new \Stripe\StripeClient($secretKey);
    }

    public static function getPublishableKey(): string
    {
        return shop_setting('stripe_publishable_key', '');
    }

    // ========== CUSTOMERS ==========

    public function createCustomer(Customer $customer): string
    {
        $stripeCustomer = $this->stripe->customers->create([
            'email' => $customer->email,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'metadata' => [
                'shop_customer_id' => $customer->id,
                'tenant_id' => $this->tenantId,
            ],
        ]);

        $customer->setStripeCustomerId($stripeCustomer->id);
        return $stripeCustomer->id;
    }

    public function getOrCreateStripeCustomer(Customer $customer): string
    {
        if ($customer->getStripeCustomerId()) {
            return $customer->getStripeCustomerId();
        }
        return $this->createCustomer($customer);
    }

    // ========== PRODUCTS & PRICES ==========

    public function syncProduct(Product $product): array
    {
        $stripeProductData = [
            'name' => $product->name,
            'description' => $product->short_description ?? $product->description ?? '',
            'metadata' => [
                'shop_product_id' => $product->id,
                'tenant_id' => $this->tenantId,
            ],
        ];

        if ($product->featured_image) {
            $stripeProductData['images'] = [$product->featured_image];
        }

        // Create or update Stripe product
        if ($product->stripe_product_id) {
            $stripeProduct = $this->stripe->products->update(
                $product->stripe_product_id,
                $stripeProductData
            );
        } else {
            $stripeProduct = $this->stripe->products->create($stripeProductData);
            $product->update(['stripe_product_id' => $stripeProduct->id]);
        }

        // Create or update price
        $priceData = [
            'product' => $stripeProduct->id,
            'unit_amount' => $product->price,
            'currency' => $product->currency,
        ];

        if ($product->isSubscription() && $product->billing_period) {
            $priceData['recurring'] = [
                'interval' => $product->billing_period === 'yearly' ? 'year' : 'month',
            ];
        }

        // Always create a new price (Stripe prices are immutable)
        $stripePrice = $this->stripe->prices->create($priceData);

        // Deactivate old price if exists
        if ($product->stripe_price_id) {
            try {
                $this->stripe->prices->update($product->stripe_price_id, ['active' => false]);
            } catch (\Exception $e) {
                Logger::warning("Could not deactivate old Stripe price: " . $e->getMessage());
            }
        }

        $product->update(['stripe_price_id' => $stripePrice->id]);

        return [
            'product_id' => $stripeProduct->id,
            'price_id' => $stripePrice->id,
        ];
    }

    // ========== CHECKOUT SESSIONS ==========

    public function createCheckoutSession(Order $order, Customer $customer, string $successUrl, string $cancelUrl): \Stripe\Checkout\Session
    {
        $stripeCustomerId = $this->getOrCreateStripeCustomer($customer);

        $lineItems = [];
        $hasSubscription = false;

        foreach ($order->items() as $item) {
            $product = $item->product();
            if (!$product || !$product->stripe_price_id) {
                // Fallback: create price data inline
                $lineItem = [
                    'price_data' => [
                        'currency' => $order->currency,
                        'unit_amount' => $item->unit_price,
                        'product_data' => [
                            'name' => $item->product_name,
                        ],
                    ],
                    'quantity' => $item->quantity,
                ];
            } else {
                $lineItem = [
                    'price' => $product->stripe_price_id,
                    'quantity' => $item->quantity,
                ];
                if ($product->isSubscription()) {
                    $hasSubscription = true;
                }
            }
            $lineItems[] = $lineItem;
        }

        $sessionData = [
            'customer' => $stripeCustomerId,
            'line_items' => $lineItems,
            'mode' => $hasSubscription ? 'subscription' : 'payment',
            'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'tenant_id' => $this->tenantId,
            ],
            'client_reference_id' => $order->order_number,
        ];

        // Add tax if configured
        $taxRate = shop_setting('default_tax_rate', 0);
        if ($taxRate > 0) {
            // Use automatic tax or manual
            // For now, tax is calculated in our system, not Stripe
        }

        $session = $this->stripe->checkout->sessions->create($sessionData);

        $order->update([
            'stripe_checkout_session_id' => $session->id,
        ]);

        return $session;
    }

    // ========== PAYMENT INTENTS ==========

    public function createPaymentIntent(Order $order, Customer $customer): \Stripe\PaymentIntent
    {
        $stripeCustomerId = $this->getOrCreateStripeCustomer($customer);

        $intent = $this->stripe->paymentIntents->create([
            'amount' => $order->total,
            'currency' => $order->currency,
            'customer' => $stripeCustomerId,
            'metadata' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'tenant_id' => $this->tenantId,
            ],
        ]);

        $order->update([
            'stripe_payment_intent_id' => $intent->id,
        ]);

        return $intent;
    }

    // ========== SUBSCRIPTIONS ==========

    public function cancelSubscription(string $stripeSubscriptionId, bool $atPeriodEnd = true): \Stripe\Subscription
    {
        if ($atPeriodEnd) {
            return $this->stripe->subscriptions->update($stripeSubscriptionId, [
                'cancel_at_period_end' => true,
            ]);
        }

        return $this->stripe->subscriptions->cancel($stripeSubscriptionId);
    }

    // ========== REFUNDS ==========

    public function refundPayment(string $paymentIntentId, ?int $amountCents = null): \Stripe\Refund
    {
        $refundData = ['payment_intent' => $paymentIntentId];
        if ($amountCents !== null) {
            $refundData['amount'] = $amountCents;
        }
        return $this->stripe->refunds->create($refundData);
    }

    // ========== WEBHOOKS ==========

    public function constructWebhookEvent(string $payload, string $sigHeader): \Stripe\Event
    {
        $webhookSecret = shop_setting('stripe_webhook_secret');

        if (empty($webhookSecret)) {
            throw new \RuntimeException('Stripe webhook secret not configured');
        }

        return \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
    }

    // ========== PORTAL ==========

    public function createBillingPortalSession(string $stripeCustomerId, string $returnUrl): \Stripe\BillingPortal\Session
    {
        return $this->stripe->billingPortal->sessions->create([
            'customer' => $stripeCustomerId,
            'return_url' => $returnUrl,
        ]);
    }
}
