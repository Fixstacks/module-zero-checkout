<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Model\AddressLookup;

use Zero\Checkout\Model\Config;

/**
 * The address autocomplete provider chosen in Zero > Checkout (none = off).
 */
class Pool
{
    /**
     * @param Config $config
     * @param array<string, ProviderInterface> $providers By setting value
     */
    public function __construct(
        private readonly Config $config,
        private readonly array $providers = []
    ) {
    }

    public function get(): ?ProviderInterface
    {
        return $this->providers[$this->config->getAddressAutocomplete()] ?? null;
    }
}
