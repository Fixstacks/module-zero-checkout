<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;
use Zero\Checkout\Model\Config;

class ConfigTest extends TestCase
{
    public function testReadsStoreScopedFlag(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())->method('isSetFlag')
            ->with('checkout/zero_checkout/enabled', ScopeInterface::SCOPE_STORE, 3)
            ->willReturn(false);

        $this->assertFalse((new Config($scopeConfig))->isEnabled(3));
    }
}
