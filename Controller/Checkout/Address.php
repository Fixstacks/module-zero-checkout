<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Controller\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Result\Layout;
use Magento\Framework\View\Result\LayoutFactory;
use Zero\Checkout\Model\Checkout\ShippingAddressUpdater;
use Zero\Core\Controller\PrivateFragmentActionInterface;

/**
 * Saved-address picker: applies one of the customer's addresses to the quote
 * and re-renders the fields, plus shipping methods and totals (hx-swap-oob).
 * getById() loads any address, so one not owned by the session customer is
 * ignored.
 */
class Address implements PrivateFragmentActionInterface
{
    private const FIELDS_BLOCK_NAME = 'zero.checkout.addressfields';
    private const SHIPPINGMETHODS_BLOCK_NAME = 'zero.checkout.shippingmethods';
    private const TOTALS_BLOCK_NAME = 'zero.checkout.ordersummarytotals';

    public function __construct(
        private readonly LayoutFactory $resultLayoutFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly CustomerSession $customerSession,
        private readonly AddressRepositoryInterface $addressRepository,
        private readonly ShippingAddressUpdater $addressUpdater,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * @return Layout
     */
    public function execute()
    {
        $addressId = (int) $this->request->getParam('saved_address_id', 0);

        if ($addressId > 0 && $this->customerSession->isLoggedIn()) {
            try {
                $customerAddress = $this->addressRepository->getById($addressId);
            } catch (\Exception $e) {
                $customerAddress = null;
            }

            if ($customerAddress
                && (int) $customerAddress->getCustomerId() === (int) $this->customerSession->getCustomerId()
            ) {
                $this->addressUpdater->applyCustomerAddress($this->checkoutSession->getQuote(), $customerAddress);
            }
        }

        $resultLayout = $this->resultLayoutFactory->create();
        $resultLayout->addDefaultHandle();
        $resultLayout->getLayout()->addOutputElement(self::FIELDS_BLOCK_NAME);
        $resultLayout->getLayout()->addOutputElement(self::SHIPPINGMETHODS_BLOCK_NAME);
        $resultLayout->getLayout()->addOutputElement(self::TOTALS_BLOCK_NAME);

        return $resultLayout;
    }
}
