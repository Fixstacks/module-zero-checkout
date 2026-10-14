<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Model\PlaceOrder;

use Magento\Checkout\Api\AgreementsValidatorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Zero\Checkout\Api\PlaceOrderValidatorInterface;

/**
 * Terms and Conditions (Stores > Terms and Conditions, switched on with
 * Sales > Checkout > Checkout Options > Enable Terms and Conditions): every
 * active "Manually" agreement of the store must be ticked. Core's validator
 * decides which are required, exactly as for stock checkout, and returns
 * "valid" when the feature is off or Magento_CheckoutAgreements is disabled.
 * Checked on the server, so a missing tick can't be bypassed by skipping the
 * browser's `required`.
 */
class AgreementsValidator implements PlaceOrderValidatorInterface
{
    public function __construct(
        private readonly AgreementsValidatorInterface $agreementsValidator
    ) {
    }

    /**
     * @inheritDoc
     */
    public function validate(Quote $quote, array $request): void
    {
        $ticked = array_map('intval', (array) ($request['agreement_ids'] ?? []));
        if (!$this->agreementsValidator->isValid($ticked)) {
            throw new LocalizedException(__(
                "The order wasn't placed. First, agree to the terms and conditions, then try placing your order again."
            ));
        }
    }
}
