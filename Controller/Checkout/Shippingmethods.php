<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Controller\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Zero\Core\Controller\PrivateFragmentActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Result\Layout;
use Magento\Framework\View\Result\LayoutFactory;
use Zero\Checkout\Model\Checkout\ShippingAddressUpdater;

/**
 * HTMX shipping-rate refresh: every address field hx-gets this endpoint on change
 * (hx-include = the whole form), so the address is re-applied to the quote via
 * the same ShippingAddressUpdater Placeorder uses and fresh rates come back.
 * Responds with #shipping-methods plus #order-summary-totals (hx-swap-oob).
 *
 * Two non-obvious bits in execute(), both in-memory only (Placeorder is what
 * persists a choice):
 * - The submitted shipping_method is re-applied when the new address still
 *   offers it; otherwise every address edit would silently reset the radio to
 *   the first rate (ShippingAddressUpdater only handles address fields).
 * - setTotalsCollectedFlag(false) before collectTotals(): apply() already
 *   collected totals once, and Quote::collectTotals() returns early while that
 *   flag is set — without it the total never includes shipping.
 */
class Shippingmethods implements PrivateFragmentActionInterface
{
    private const BLOCK_NAME = 'zero.checkout.shippingmethods';
    private const TOTALS_BLOCK_NAME = 'zero.checkout.ordersummarytotals';

    public function __construct(
        private readonly LayoutFactory $resultLayoutFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly ShippingAddressUpdater $addressUpdater,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * @return Layout
     */
    public function execute()
    {
        $quote = $this->checkoutSession->getQuote();
        $this->addressUpdater->apply($quote, $this->request->getParams());

        $submittedMethod = (string) $this->request->getParam('shipping_method', '');
        if ($submittedMethod !== '') {
            $address = $quote->getShippingAddress();
            $rate = $address->getShippingRateByCode($submittedMethod);
            if ($rate) {
                $shippingDescription = trim($rate->getCarrierTitle() . ' - ' . $rate->getMethodTitle(), ' -');
                $address->setShippingDescription($shippingDescription);
                $address->setShippingMethod($submittedMethod);
                $address->setCollectShippingRates(true);
                $quote->setTotalsCollectedFlag(false);
                $quote->collectTotals();
            }
        }

        $resultLayout = $this->resultLayoutFactory->create();
        $resultLayout->addDefaultHandle();
        $resultLayout->getLayout()->addOutputElement(self::BLOCK_NAME);
        $resultLayout->getLayout()->addOutputElement(self::TOTALS_BLOCK_NAME);

        return $resultLayout;
    }
}
