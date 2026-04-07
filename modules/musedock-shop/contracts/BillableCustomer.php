<?php

namespace Shop\Contracts;

/**
 * Interface for entities that can be billed through Shop.
 */
interface BillableCustomer
{
    public function getShopCustomerEmail(): string;

    public function getShopCustomerName(): string;

    public function getStripeCustomerId(): ?string;

    public function setStripeCustomerId(string $id): void;
}
