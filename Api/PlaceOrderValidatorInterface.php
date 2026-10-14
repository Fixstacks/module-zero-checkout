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
 * A check Zero Checkout runs before it places the order, after the addresses,
 * shipping method and payment from the form are on the quote. Register an
 * implementation in di.xml on Zero\Checkout\Controller\Checkout\Placeorder's
 * `validators` argument; throw LocalizedException to stop the order, and its
 * message is shown to the shopper on the checkout page.
 *
 * The request is the raw POST of the checkout form, so a module that adds its
 * own field to the form (layout container zero.checkout.place-order.before)
 * reads it here.
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
