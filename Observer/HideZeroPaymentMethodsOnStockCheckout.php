<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Api\Data\CartInterface;
use Zero\Checkout\Model\Config;

/**
 * Ties Zero's own payment methods (zero_stripe, zero_authorizenet,
 * zero_paypal_standard) to the Zero Checkout toggle: while it's off they are
 * unavailable everywhere, including REST/GraphQL. Stock's Knockout checkout
 * can't render them (no JS method renderer), so leaving them "available" would
 * only let an API client pick a method nothing can complete.
 *
 * Stock methods are never touched; with Zero Checkout on this is a no-op.
 */
class HideZeroPaymentMethodsOnStockCheckout implements ObserverInterface
{
    /** Methods that only Zero Checkout can render/complete. */
    private const ZERO_ONLY_METHODS = [
        'zero_stripe',
        'zero_authorizenet',
        'zero_paypal_standard',
    ];

    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * `payment_method_is_active`: hide Zero-only methods while Zero Checkout is off.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $method = $observer->getEvent()->getData('method_instance');
        if (!$method || !in_array($method->getCode(), self::ZERO_ONLY_METHODS, true)) {
            return;
        }

        $quote = $observer->getEvent()->getData('quote');
        $storeId = $quote instanceof CartInterface ? $quote->getStoreId() : null;

        if ($this->config->isEnabled($storeId)) {
            return;
        }

        $observer->getEvent()->getData('result')->setData('is_available', false);
    }
}
