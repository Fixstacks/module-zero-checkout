<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Model\AddressLookup;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

/**
 * Photon: OpenStreetMap addresses, no key, open source (komoot/photon).
 * Point Photon Server URL at your own instance: the public server at
 * photon.komoot.io is for light use and can take seconds to answer, too
 * slow for suggestions while typing.
 *
 * Photon has no lookup by id, so a suggestion's id carries its address
 * itself (base64 JSON): details() needs no second request.
 */
class Photon implements ProviderInterface
{
    public const XML_PATH_URL = 'checkout/zero_checkout/photon_url';
    private const DEFAULT_URL = 'https://photon.komoot.io/api/';
    private const LIMIT = 5;

    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger,
        private readonly int $timeout = 6
    ) {
    }

    /**
     * @inheritDoc
     */
    public function suggest(string $query, string $countryId, string $sessionToken): array
    {
        $params = http_build_query(['q' => $query, 'limit' => self::LIMIT * 3, 'lang' => 'en'])
            . '&layer=house&layer=street';
        $url = trim((string) $this->scopeConfig->getValue(self::XML_PATH_URL, ScopeInterface::SCOPE_STORE))
            ?: self::DEFAULT_URL;
        $data = $this->get($url . (str_contains($url, '?') ? '&' : '?') . $params);
        $suggestions = [];
        foreach ($data['features'] ?? [] as $feature) {
            $address = $this->address((array) ($feature['properties'] ?? []));
            if ($address === null || ($countryId !== '' && $address['country_id'] !== strtoupper($countryId))) {
                continue;
            }
            $label = implode(', ', array_filter([
                $address['street'],
                $address['city'],
                trim($address['region'] . ' ' . $address['postcode']),
            ]));
            $suggestions[$label] = [
                'id' => base64_encode((string) json_encode($address)),
                'label' => $label,
            ];
            if (count($suggestions) === self::LIMIT) {
                break;
            }
        }

        return array_values($suggestions);
    }

    /**
     * @inheritDoc
     */
    public function details(string $id, string $sessionToken): ?array
    {
        $address = json_decode((string) base64_decode($id, true), true);
        if (!is_array($address) || !isset($address['street'], $address['country_id'])) {
            return null;
        }

        return [
            'street' => (string) $address['street'],
            'city' => (string) ($address['city'] ?? ''),
            'postcode' => (string) ($address['postcode'] ?? ''),
            'region' => (string) ($address['region'] ?? ''),
            'region_code' => '',
            'country_id' => (string) $address['country_id'],
        ];
    }

    /**
     * @param array $properties A Photon feature's properties
     * @return array{street: string, city: string, postcode: string, region: string, region_code: string, country_id: string}|null
     */
    private function address(array $properties): ?array
    {
        $street = trim((string) ($properties['street'] ?? ''));
        if ($street === '' && ($properties['type'] ?? '') === 'street') {
            $street = trim((string) ($properties['name'] ?? ''));
        }
        if ($street === '' || empty($properties['countrycode'])) {
            return null;
        }
        $number = trim((string) ($properties['housenumber'] ?? ''));

        return [
            'street' => $number !== '' ? $number . ' ' . $street : $street,
            'city' => (string) ($properties['city'] ?? $properties['town'] ?? $properties['village'] ?? $properties['locality'] ?? ''),
            'postcode' => (string) ($properties['postcode'] ?? ''),
            'region' => (string) ($properties['state'] ?? ''),
            'region_code' => '',
            'country_id' => strtoupper((string) $properties['countrycode']),
        ];
    }

    private function get(string $url): array
    {
        try {
            $curl = $this->curlFactory->create();
            $curl->setTimeout($this->timeout);
            $curl->addHeader('Accept', 'application/json');
            $curl->addHeader('User-Agent', 'Zero-Checkout-Magento/1.0');
            $curl->get($url);
            if ($curl->getStatus() !== 200) {
                return [];
            }

            return (array) json_decode($curl->getBody(), true);
        } catch (\Throwable $e) {
            $this->logger->warning('Zero Checkout address lookup (Photon): ' . $e->getMessage());

            return [];
        }
    }
}
