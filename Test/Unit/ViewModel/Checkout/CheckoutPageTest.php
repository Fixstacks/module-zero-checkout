<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Test\Unit\ViewModel\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Payment\Model\MethodInterface;
use Magento\Payment\Model\MethodList;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Zero\Checkout\Model\SupportedPaymentMethods;
use Zero\Checkout\ViewModel\Checkout\CheckoutPage;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

#[AllowMockObjectsWithoutExpectations]
class CheckoutPageTest extends TestCase
{
    public function testOnlySupportedMethodsAreListedAndListIsMemoized(): void
    {
        $methodList = $this->createMock(MethodList::class);
        $methodList->expects($this->once())->method('getAvailableMethods')->willReturn([
            $this->method('checkmo'),
            $this->method('paypal_express'),
            $this->method('zero_stripe'),
            $this->method('free'),
        ]);
        $page = $this->page($methodList);

        $codes = array_map(static fn (MethodInterface $m) => $m->getCode(), $page->getAvailablePaymentMethods());

        $this->assertSame(['checkmo', 'zero_stripe', 'free'], $codes);
        $this->assertTrue($page->isPaymentMethodAvailable('zero_stripe'));
        $this->assertFalse($page->isPaymentMethodAvailable('paypal_express'));
        $this->assertFalse($page->isPaymentMethodAvailable('braintree'));
    }

    public function testEverySupportedMethodHasADescription(): void
    {
        $page = $this->page($this->createStub(MethodList::class));

        foreach (CheckoutPage::SUPPORTED_METHODS as $code) {
            $this->assertNotSame('', $page->getPaymentDescription($code), $code);
        }
        $this->assertSame('', $page->getPaymentDescription('unknown_method'));
    }

    public function testAModuleCanRegisterItsOwnPaymentMethod(): void
    {
        $methodList = $this->createStub(MethodList::class);
        $methodList->method('getAvailableMethods')->willReturn([$this->method('acme_pay'), $this->method('checkmo')]);
        $page = $this->page($methodList, new SupportedPaymentMethods(['acme_pay' => 'Pay with Acme.']));

        $this->assertTrue($page->isPaymentMethodAvailable('acme_pay'));
        $this->assertSame('Pay with Acme.', $page->getPaymentDescription('acme_pay'));
    }

    private function page(MethodList $methodList, ?SupportedPaymentMethods $supported = null): CheckoutPage
    {
        $session = $this->createStub(CheckoutSession::class);
        $session->method('getQuote')->willReturn($this->createStub(Quote::class));

        return (new ObjectManager($this))->getObject(CheckoutPage::class, [
            'checkoutSession' => $session,
            'methodList' => $methodList,
            'supportedPaymentMethods' => $supported ?? new SupportedPaymentMethods(),
        ]);
    }

    private function method(string $code): MethodInterface
    {
        $method = $this->createStub(MethodInterface::class);
        $method->method('getCode')->willReturn($code);

        return $method;
    }
}
