<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\ViewModel\Checkout;

use Magento\CheckoutAgreements\Api\CheckoutAgreementsListInterface;
use Magento\CheckoutAgreements\Api\Data\AgreementInterface;
use Magento\CheckoutAgreements\Model\AgreementModeOptions;
use Magento\CheckoutAgreements\Model\Api\SearchCriteria\ActiveStoreAgreementsFilter;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * The store's active Terms and Conditions for the checkout form
 * (checkout/agreements.phtml), when Enable Terms and Conditions is on. Manual
 * ones render as required checkboxes, automatic ones as a notice; the server
 * check is Model\PlaceOrder\AgreementsValidator.
 */
class Agreements implements ArgumentInterface
{
    private const XML_PATH_ENABLED = 'checkout/options/enable_agreements';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CheckoutAgreementsListInterface $agreementsList,
        private readonly ActiveStoreAgreementsFilter $activeStoreFilter
    ) {
    }

    /**
     * @return AgreementInterface[]
     */
    public function getAgreements(): array
    {
        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE)) {
            return [];
        }

        return $this->agreementsList->getList($this->activeStoreFilter->buildSearchCriteria());
    }

    public function isManual(AgreementInterface $agreement): bool
    {
        return (int) $agreement->getMode() === AgreementModeOptions::MODE_MANUAL;
    }
}
