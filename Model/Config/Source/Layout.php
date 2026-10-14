<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Zero\Checkout\Model\Config;

/**
 * Checkout page layout: everything on one page, or three steps.
 */
class Layout implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::LAYOUT_ONE_PAGE, 'label' => __('One page')],
            ['value' => Config::LAYOUT_STEPS, 'label' => __('Steps: address, shipping, payment')],
        ];
    }
}
