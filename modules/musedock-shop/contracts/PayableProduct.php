<?php

namespace Shop\Contracts;

use Shop\Models\Order;
use Shop\Models\Subscription;

/**
 * Interface for products that can be purchased through Shop.
 * Other modules (like Cloud) implement this to register their own products.
 */
interface PayableProduct
{
    public function getShopProductName(): string;

    public function getShopProductPrice(): int; // in cents

    public function getShopProductCurrency(): string;

    public function getShopProductType(): string; // 'physical','digital','service','subscription'

    public function getShopBillingPeriod(): ?string; // 'monthly','yearly',null

    public function getShopMetadata(): array;

    public function onPurchaseCompleted(Order $order): void;

    public function onSubscriptionRenewed(Subscription $subscription): void;

    public function onSubscriptionCancelled(Subscription $subscription): void;
}
