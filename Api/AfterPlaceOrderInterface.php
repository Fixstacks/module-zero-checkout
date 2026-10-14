<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Api;

use Magento\Sales\Api\Data\OrderInterface;

/**
 * Work Zero Checkout does right after an order is placed (store a field from
 * the form on the order, notify a service, ...). Register an implementation in
 * di.xml on Zero\Checkout\Controller\Checkout\Placeorder's `afterPlaceOrder`
 * argument.
 *
 * The order already exists: an exception here is logged and the shopper still
 * goes to the success page, so nothing here can lose a paid order.
 */
interface AfterPlaceOrderInterface
{
    /**
     * @param OrderInterface $order
     * @param array<string, mixed> $request Raw POST of the checkout form.
     * @return void
     */
    public function execute(OrderInterface $order, array $request): void;
}
