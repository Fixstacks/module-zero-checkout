<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Test\Unit\Model\AddressLookup;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Zero\Checkout\Model\AddressLookup\Google;
use Zero\Checkout\Model\AddressLookup\Photon;

class ProvidersTest extends TestCase
{
    /** @var string[] URLs requested */
    private array $requested = [];

    public function testPhotonSuggestsAddressesInTheChosenCountryOnly(): void
    {
        $photon = new Photon($this->curl([
            'features' => [
                ['properties' => ['housenumber' => '1600', 'street' => 'Pennsylvania Avenue NW', 'city' => 'Washington',
                    'state' => 'District of Columbia', 'postcode' => '20500', 'countrycode' => 'US']],
                ['properties' => ['housenumber' => '1600', 'street' => 'Pennsylvania Ave', 'city' => 'Toronto',
                    'state' => 'Ontario', 'postcode' => 'M5V', 'countrycode' => 'CA']],
                ['properties' => ['type' => 'street', 'name' => 'Pennsylvania Avenue', 'city' => 'Baltimore',
                    'state' => 'Maryland', 'countrycode' => 'US']],
                ['properties' => ['name' => 'No street here', 'countrycode' => 'US']],
            ],
        ]), $this->config([Photon::XML_PATH_URL => 'https://photon.example/api/']), $this->createStub(LoggerInterface::class));

        $suggestions = $photon->suggest('1600 Penn', 'us', 'token');

        $this->assertSame(
            ['1600 Pennsylvania Avenue NW, Washington, District of Columbia 20500', 'Pennsylvania Avenue, Baltimore, Maryland'],
            array_column($suggestions, 'label')
        );
        $this->assertStringStartsWith('https://photon.example/api/?q=1600+Penn', $this->requested[0]);
        $this->assertSame([
            'street' => '1600 Pennsylvania Avenue NW',
            'city' => 'Washington',
            'postcode' => '20500',
            'region' => 'District of Columbia',
            'region_code' => '',
            'country_id' => 'US',
        ], $photon->details($suggestions[0]['id'], 'token'));
    }

    public function testPhotonRejectsAnIdThatIsNotAnAddress(): void
    {
        $photon = new Photon($this->curl([]), $this->config([]), $this->createStub(LoggerInterface::class));

        $this->assertNull($photon->details('not base64 json', 't'));
        $this->assertNull($photon->details(base64_encode('{"city":"x"}'), 't'));
    }

    public function testGoogleSuggestsAndResolvesAddressComponents(): void
    {
        $google = new Google(
            $this->curl(
                ['suggestions' => [
                    ['placePrediction' => ['placeId' => 'ChIJ37HL3ry3t4kRv3YLbdhpWXE', 'text' => ['text' => '1600 Pennsylvania Avenue NW, Washington, DC, USA']]],
                    ['queryPrediction' => ['text' => ['text' => 'not a place']]],
                ]],
                ['addressComponents' => [
                    ['longText' => '1600', 'shortText' => '1600', 'types' => ['street_number']],
                    ['longText' => 'Pennsylvania Avenue Northwest', 'shortText' => 'Pennsylvania Avenue NW', 'types' => ['route']],
                    ['longText' => 'Washington', 'shortText' => 'Washington', 'types' => ['locality', 'political']],
                    ['longText' => 'District of Columbia', 'shortText' => 'DC', 'types' => ['administrative_area_level_1', 'political']],
                    ['longText' => 'United States', 'shortText' => 'US', 'types' => ['country', 'political']],
                    ['longText' => '20500', 'shortText' => '20500', 'types' => ['postal_code']],
                ]]
            ),
            $this->config([Google::XML_PATH_API_KEY => 'encrypted']),
            $this->encryptor(),
            $this->createStub(LoggerInterface::class)
        );

        $suggestions = $google->suggest('1600 Penn', 'US', 'token');
        $this->assertSame([['id' => 'ChIJ37HL3ry3t4kRv3YLbdhpWXE', 'label' => '1600 Pennsylvania Avenue NW, Washington, DC, USA']], $suggestions);
        $this->assertSame([
            'street' => '1600 Pennsylvania Avenue Northwest',
            'city' => 'Washington',
            'postcode' => '20500',
            'region' => 'District of Columbia',
            'region_code' => 'DC',
            'country_id' => 'US',
        ], $google->details('ChIJ37HL3ry3t4kRv3YLbdhpWXE', 'token'));
        $this->assertStringContainsString('places/ChIJ37HL3ry3t4kRv3YLbdhpWXE?sessionToken=token', $this->requested[1]);
    }

    public function testGoogleWithoutAKeyAsksNothing(): void
    {
        $google = new Google($this->curl([]), $this->config([]), $this->encryptor(), $this->createStub(LoggerInterface::class));

        $this->assertSame([], $google->suggest('1600 Penn', 'US', 't'));
        $this->assertSame([], $this->requested);
        $this->assertNull($google->details('../../etc', 't'));
    }

    /**
     * @param array ...$responses JSON bodies, one per request
     * @return CurlFactory
     */
    private function curl(array ...$responses): CurlFactory
    {
        $factory = $this->createStub(CurlFactory::class);
        $factory->method('create')->willReturnCallback(function () use (&$responses) {
            $curl = $this->createStub(Curl::class);
            $body = (string) json_encode(array_shift($responses) ?? []);
            $curl->method('get')->willReturnCallback(function ($url) {
                $this->requested[] = $url;
            });
            $curl->method('post')->willReturnCallback(function ($url) {
                $this->requested[] = $url;
            });
            $curl->method('getStatus')->willReturn(200);
            $curl->method('getBody')->willReturn($body);

            return $curl;
        });

        return $factory;
    }

    private function config(array $values): ScopeConfigInterface
    {
        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('getValue')->willReturnCallback(fn ($path) => $values[$path] ?? null);

        return $config;
    }

    private function encryptor(): EncryptorInterface
    {
        $encryptor = $this->createStub(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturnCallback(fn ($value) => $value === 'encrypted' ? 'key-123' : '');

        return $encryptor;
    }
}
