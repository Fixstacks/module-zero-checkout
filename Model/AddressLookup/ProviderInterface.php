<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Model\AddressLookup;

/**
 * An address autocomplete service, called from the server (the browser
 * never loads its script or sees its key). Register another one in di.xml
 * (Zero\Checkout\Model\AddressLookup\Pool, `providers`) and add it to the
 * Address Autocomplete setting's source model.
 */
interface ProviderInterface
{
    /**
     * Addresses matching what the shopper typed.
     *
     * @param string $query Street line typed so far
     * @param string $countryId ISO 3166-1 alpha-2 country chosen in the form
     * @param string $sessionToken Groups one shopper's lookups (billing for Google)
     * @return array<int, array{id: string, label: string}>
     */
    public function suggest(string $query, string $countryId, string $sessionToken): array;

    /**
     * The address behind a suggestion.
     *
     * @param string $id A suggestion's id
     * @param string $sessionToken Same token as the suggestions
     * @return array{street: string, city: string, postcode: string, region: string, region_code: string, country_id: string}|null
     */
    public function details(string $id, string $sessionToken): ?array;
}
