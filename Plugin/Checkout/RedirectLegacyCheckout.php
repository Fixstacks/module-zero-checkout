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
 * Sends stock's checkout route (checkout/index/index, the Knockout checkout) to
 * Zero Checkout, so old bookmarks and hand-typed /checkout/ URLs don't land on a
 * page this theme can't run.
 *
 * A plugin rather than a controller override: stock's own checks (empty cart,
 * guest checkout disabled, minimum amount) still run first, and only a page it
 * would actually render is replaced by the redirect.
 *
 * With Zero Checkout switched off (Model\Config::isEnabled()) this does nothing,
 * and stock's unmodified checkout is the fallback. Only stock payment methods
 * work there — Zero's own methods have no Knockout renderer (see
 * Observer\HideZeroPaymentMethodsOnStockCheckout).
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
