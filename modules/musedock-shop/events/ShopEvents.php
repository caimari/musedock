<?php

namespace Shop\Events;

use Shop\Models\Order;
use Shop\Models\Subscription;

/**
 * Shop event dispatcher using the CMS hooks system.
 *
 * Usage:
 *   ShopEvents::orderCompleted($order);
 *
 * Listening (from Cloud or other modules):
 *   add_action('shop_order_completed', function(Order $order) { ... });
 */
class ShopEvents
{
    public static function orderCompleted(Order $order): void
    {
        do_action('shop_order_completed', $order);
    }

    public static function orderRefunded(Order $order): void
    {
        do_action('shop_order_refunded', $order);
    }

    public static function orderCancelled(Order $order): void
    {
        do_action('shop_order_cancelled', $order);
    }

    public static function subscriptionCreated(Subscription $subscription): void
    {
        do_action('shop_subscription_created', $subscription);
    }

    public static function subscriptionRenewed(Subscription $subscription): void
    {
        do_action('shop_subscription_renewed', $subscription);
    }

    public static function subscriptionCancelled(Subscription $subscription): void
    {
        do_action('shop_subscription_cancelled', $subscription);
    }

    public static function subscriptionPastDue(Subscription $subscription): void
    {
        do_action('shop_subscription_past_due', $subscription);
    }
}
