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
 * Saved-address picker (billing) — the billing twin of Controller\Checkout\Address
 * (read that one first). Applies the address via ShippingAddressUpdater::
 * applyBillingFromAddress(); ignores billing_same_as_shipping because the picker
 * only renders while that box is unchecked.
 *
 * Responds with #billing-fields plus #order-summary-totals (hx-swap-oob, in case a
 * store taxes by billing address). No #shipping-methods: billing can't change
 * shipping rates.
 *
 * Security: same customer_id ownership check as Address.
 */
class BillingAddress implements PrivateFragmentActionInterface
{
    private const FIELDS_BLOCK_NAME = 'zero.checkout.billingaddressfields';
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
        $addressId = (int) $this->request->getParam('billing_saved_address_id', 0);

        if ($addressId > 0 && $this->customerSession->isLoggedIn()) {
            try {
                $customerAddress = $this->addressRepository->getById($addressId);
            } catch (\Exception $e) {
                $customerAddress = null;
            }

            if ($customerAddress
                && (int) $customerAddress->getCustomerId() === (int) $this->customerSession->getCustomerId()
            ) {
                $region = $customerAddress->getRegion();
                $street = $customerAddress->getStreet();

                $data = [
                    'firstname' => (string) $customerAddress->getFirstname(),
                    'lastname' => (string) $customerAddress->getLastname(),
                    'street' => (string) ($street[0] ?? ''),
                    'city' => (string) $customerAddress->getCity(),
                    'region' => $region ? (string) $region->getRegion() : '',
                    'region_id' => $region ? (string) $region->getRegionId() : '',
                    'postcode' => (string) $customerAddress->getPostcode(),
                    'country_id' => (string) $customerAddress->getCountryId(),
                    'telephone' => (string) $customerAddress->getTelephone(),
                ];

                $this->addressUpdater->applyBillingFromAddress($this->checkoutSession->getQuote(), $data);
            }
        }

        $resultLayout = $this->resultLayoutFactory->create();
        $resultLayout->addDefaultHandle();
        $resultLayout->getLayout()->addOutputElement(self::FIELDS_BLOCK_NAME);
        $resultLayout->getLayout()->addOutputElement(self::TOTALS_BLOCK_NAME);

        return $resultLayout;
    }
}
