<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Zero\Checkout\Model\RedirectPayment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

/**
 * What a provider's webhook or callback does to the order it names, once
 * the controller has checked that the call is the provider's and about this
 * order. Registered with Webhooks by payment method code (di.xml), which
 * runs it now or from the queue.
 */
interface WebhookHandlerInterface
{
    /**
     * @param array<string, mixed> $data what the call carried, verified
     * @throws LocalizedException when the provider can't be asked now (tried again later)
     */
    public function webhook(Order $order, array $data): void;
}
