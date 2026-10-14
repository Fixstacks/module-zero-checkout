<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Address autocomplete providers (Model\AddressLookup\Pool).
 */
class AddressAutocomplete implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => __('Off')],
            ['value' => 'google', 'label' => __('Google Places')],
            ['value' => 'photon', 'label' => __('Photon (OpenStreetMap, your own server)')],
        ];
    }
}
