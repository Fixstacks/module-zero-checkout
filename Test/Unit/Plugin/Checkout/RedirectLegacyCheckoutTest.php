<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Test\Unit\Plugin\Checkout;

use Magento\Checkout\Controller\Index\Index as LegacyCheckoutController;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\View\Result\Page;
use PHPUnit\Framework\TestCase;
use Zero\Checkout\Model\Config;
use Zero\Checkout\Plugin\Checkout\RedirectLegacyCheckout;

class RedirectLegacyCheckoutTest extends TestCase
{
    public function testRenderedStockCheckoutRedirectsToZeroCheckout(): void
    {
        $redirect = $this->createMock(Redirect::class);
        $redirect->expects($this->once())->method('setPath')->with('zero/checkout/index')->willReturnSelf();

        $result = $this->plugin(true, $redirect)->aroundExecute(
            $this->createStub(LegacyCheckoutController::class),
            fn () => $this->createStub(Page::class)
        );

        $this->assertSame($redirect, $result);
    }

    public function testStockRedirectsArePassedThrough(): void
    {
        $stockRedirect = $this->createStub(Redirect::class);

        $result = $this->plugin(true)->aroundExecute(
            $this->createStub(LegacyCheckoutController::class),
            static fn () => $stockRedirect
        );

        $this->assertSame($stockRedirect, $result);
    }

    public function testDisabledZeroCheckoutLeavesStockPage(): void
    {
        $page = $this->createStub(Page::class);

        $result = $this->plugin(false)->aroundExecute(
            $this->createStub(LegacyCheckoutController::class),
            static fn () => $page
        );

        $this->assertSame($page, $result);
    }

    private function plugin(bool $enabled, ?Redirect $redirect = null): RedirectLegacyCheckout
    {
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn($enabled);
        $factory = $this->createStub(RedirectFactory::class);
        $factory->method('create')->willReturn($redirect ?? $this->createStub(Redirect::class));

        return new RedirectLegacyCheckout($factory, $config);
    }
}
