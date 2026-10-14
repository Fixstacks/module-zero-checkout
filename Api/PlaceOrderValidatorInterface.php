<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;

/**
 * A check run before Zero Checkout places the order (the form's addresses,
 * shipping and payment are already on the quote). Add it to Placeorder's
 * `validators` in di.xml; a LocalizedException stops the order and its
 * message is shown. The request is the checkout form's raw POST, so fields
 * added in zero.checkout.place-order.before can be read here.
 */
interface PlaceOrderValidatorInterface
{
    /**
     * @param Quote $quote
     * @param array<string, mixed> $request
     * @return void
     * @throws LocalizedException
     */
    public function validate(Quote $quote, array $request): void;
}
