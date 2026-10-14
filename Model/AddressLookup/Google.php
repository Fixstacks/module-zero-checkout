<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Model\AddressLookup;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

/**
 * Google Places API (New): Autocomplete, then Place Details (address
 * components only) with the same session token, which Google bills as one
 * session. The key stays on the server. Needs the Places API (New) enabled
 * for the key in Google Cloud.
 */
class Google implements ProviderInterface
{
    public const XML_PATH_API_KEY = 'checkout/zero_checkout/google_places_api_key';
    private const BASE_URL = 'https://places.googleapis.com/v1/';
    private const LIMIT = 5;

    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
        private readonly LoggerInterface $logger,
        private readonly int $timeout = 3
    ) {
    }

    /**
     * @inheritDoc
     */
    public function suggest(string $query, string $countryId, string $sessionToken): array
    {
        $body = ['input' => $query, 'sessionToken' => $sessionToken];
        if ($countryId !== '') {
            $body['includedRegionCodes'] = [strtolower($countryId)];
        }
        $data = $this->request('POST', 'places:autocomplete', $body);
        $suggestions = [];
        foreach ($data['suggestions'] ?? [] as $suggestion) {
            $prediction = $suggestion['placePrediction'] ?? null;
            if (!$prediction || empty($prediction['placeId'])) {
                continue;
            }
            $suggestions[] = [
                'id' => (string) $prediction['placeId'],
                'label' => (string) ($prediction['text']['text'] ?? ''),
            ];
            if (count($suggestions) === self::LIMIT) {
                break;
            }
        }

        return $suggestions;
    }

    /**
     * @inheritDoc
     */
    public function details(string $id, string $sessionToken): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
            return null;
        }
        $data = $this->request('GET', 'places/' . $id . '?sessionToken=' . rawurlencode($sessionToken), null, 'addressComponents');
        $parts = [];
        foreach ($data['addressComponents'] ?? [] as $component) {
            foreach ((array) ($component['types'] ?? []) as $type) {
                $parts[$type] ??= $component;
            }
        }
        $long = static fn (string $type): string => (string) ($parts[$type]['longText'] ?? '');
        $short = static fn (string $type): string => (string) ($parts[$type]['shortText'] ?? '');
        $route = $long('route');
        if ($route === '' || $short('country') === '') {
            return null;
        }
        $number = $long('street_number');

        return [
            'street' => $number !== '' ? $number . ' ' . $route : $route,
            'city' => $long('locality') ?: $long('postal_town') ?: $long('sublocality') ?: $long('administrative_area_level_2'),
            'postcode' => $long('postal_code'),
            'region' => $long('administrative_area_level_1'),
            'region_code' => $short('administrative_area_level_1'),
            'country_id' => strtoupper($short('country')),
        ];
    }

    private function request(string $method, string $path, ?array $body, string $fieldMask = ''): array
    {
        $key = trim((string) $this->encryptor->decrypt(
            (string) $this->scopeConfig->getValue(self::XML_PATH_API_KEY, ScopeInterface::SCOPE_STORE)
        ));
        if ($key === '') {
            return [];
        }
        try {
            $curl = $this->curlFactory->create();
            $curl->setTimeout($this->timeout);
            $curl->addHeader('X-Goog-Api-Key', $key);
            $curl->addHeader('Content-Type', 'application/json');
            if ($fieldMask !== '') {
                $curl->addHeader('X-Goog-FieldMask', $fieldMask);
            }
            if ($method === 'POST') {
                $curl->post(self::BASE_URL . $path, (string) json_encode($body));
            } else {
                $curl->get(self::BASE_URL . $path);
            }
            if ($curl->getStatus() !== 200) {
                $this->logger->warning('Zero Checkout address lookup (Google): HTTP ' . $curl->getStatus());

                return [];
            }

            return (array) json_decode($curl->getBody(), true);
        } catch (\Throwable $e) {
            $this->logger->warning('Zero Checkout address lookup (Google): ' . $e->getMessage());

            return [];
        }
    }
}
