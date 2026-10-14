<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Test\Unit\Model\PlaceOrder;

use Magento\Checkout\Api\AgreementsValidatorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use Zero\Checkout\Model\PlaceOrder\AgreementsValidator;

class AgreementsValidatorTest extends TestCase
{
    public function testTickedIdsArePassedToCoreAsIntegers(): void
    {
        $core = $this->createMock(AgreementsValidatorInterface::class);
        $core->expects($this->once())->method('isValid')->with([1, 3])->willReturn(true);

        (new AgreementsValidator($core))->validate($this->createStub(Quote::class), ['agreement_ids' => ['1', '3']]);
    }

    public function testMissingTickStopsTheOrder(): void
    {
        $core = $this->createStub(AgreementsValidatorInterface::class);
        $core->method('isValid')->willReturn(false);

        $this->expectException(LocalizedException::class);
        (new AgreementsValidator($core))->validate($this->createStub(Quote::class), []);
    }
}
