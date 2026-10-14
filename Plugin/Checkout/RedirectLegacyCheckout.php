<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Plugin\Checkout;

use Magento\Checkout\Controller\Index\Index as LegacyCheckoutController;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Zero\Checkout\Model\Config;

/**
 * Sends stock's Knockout checkout (checkout/index/index) to Zero Checkout. A
 * plugin, so stock's own checks (empty cart, guest checkout, minimum amount)
 * still run first. With Zero Checkout off it does nothing; Zero's payment
 * methods are then hidden there (Observer\HideZeroPaymentMethodsOnStockCheckout).
 */
class RedirectLegacyCheckout
{
    public function __construct(
        private readonly RedirectFactory $redirectFactory,
        private readonly Config $config
    ) {
    }

    /**
     * Redirect a rendered stock checkout page to Zero Checkout, unless it's switched off.
     *
     * @param LegacyCheckoutController $subject
     * @param \Closure $proceed
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function aroundExecute(LegacyCheckoutController $subject, \Closure $proceed)
    {
        $result = $proceed();

        if (!$this->config->isEnabled()) {
            return $result;
        }

        // Stock's own controller already redirected away for a real reason
        // (empty cart, guest checkout disabled, etc.) — respect that as-is.
        if (!$result instanceof \Magento\Framework\View\Result\Page) {
            return $result;
        }

        return $this->redirectFactory->create()->setPath('zero/checkout/index');
    }
}
