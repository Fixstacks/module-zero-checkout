<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Test\Unit\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\Data\CartInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zero\Checkout\Model\Config;
use Zero\Checkout\Observer\HideZeroPaymentMethodsOnStockCheckout;

class HideZeroPaymentMethodsOnStockCheckoutTest extends TestCase
{
    /**
     * @return array<string, array{string, bool, bool}>
     */
    public static function cases(): array
    {
        return [
            'zero method, zero checkout off' => ['zero_stripe', false, false],
            'zero method, zero checkout on' => ['zero_stripe', true, true],
            'authorizenet, off' => ['zero_authorizenet', false, false],
            'paypal standard, off' => ['zero_paypal_standard', false, false],
            'stock method, off' => ['checkmo', false, true],
            'braintree, off' => ['braintree', false, true],
        ];
    }

    #[DataProvider('cases')]
    public function testAvailability(string $code, bool $zeroCheckoutEnabled, bool $expectedAvailable): void
    {
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturnCallback(
            static fn ($storeId) => $storeId === 4 ? $zeroCheckoutEnabled : !$zeroCheckoutEnabled
        );
        $method = $this->createStub(MethodInterface::class);
        $method->method('getCode')->willReturn($code);
        $quote = $this->createStub(CartInterface::class);
        $quote->method('getStoreId')->willReturn(4);
        $result = new DataObject(['is_available' => true]);

        $observer = new Observer(['event' => new Event([
            'method_instance' => $method,
            'quote' => $quote,
            'result' => $result,
        ])]);
        (new HideZeroPaymentMethodsOnStockCheckout($config))->execute($observer);

        $this->assertSame($expectedAvailable, $result->getData('is_available'));
    }
}
