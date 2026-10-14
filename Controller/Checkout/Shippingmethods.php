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
 * HTMX shipping-rate refresh: address fields hx-get this with the whole form;
 * the address is applied as Placeorder does and #shipping-methods plus the
 * totals (hx-swap-oob) come back. In memory only:
 * - the posted shipping_method is kept when still offered, or each edit would
 *   reset the radio to the first rate;
 * - setTotalsCollectedFlag(false) before collectTotals(): apply() collected
 *   once already, and the flag would leave shipping out of the total.
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
