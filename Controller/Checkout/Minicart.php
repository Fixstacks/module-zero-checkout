<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Controller\Checkout;

use Zero\Core\Controller\PrivateFragmentActionInterface;
use Magento\Framework\View\Result\Layout;
use Magento\Framework\View\Result\LayoutFactory;

/**
 * Mini-cart content fragment (items, subtotal, checkout link) — the HTMX
 * replacement for stock's Knockout mini-cart. Loaded on the trigger button's
 * click (the popover itself opens natively via popovertarget) and again on
 * `cart:updated` after Controller\Cart\Update.
 */
class Minicart implements PrivateFragmentActionInterface
{
    private const BLOCK_NAME = 'zero.checkout.minicart';

    /**
     * @param LayoutFactory $resultLayoutFactory
     */
    public function __construct(
        private readonly LayoutFactory $resultLayoutFactory
    ) {
    }

    /**
     * @return Layout
     */
    public function execute()
    {
        $resultLayout = $this->resultLayoutFactory->create();
        $resultLayout->addDefaultHandle();
        $resultLayout->getLayout()->addOutputElement(self::BLOCK_NAME);

        return $resultLayout;
    }
}
