<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Test\Unit\Model\PlaceOrder;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;
use Zero\Checkout\Model\Config;
use Zero\Checkout\Model\PlaceOrder\OrderComment;

class OrderCommentTest extends TestCase
{
    public function testTooLongCommentStopsTheOrder(): void
    {
        $this->expectException(LocalizedException::class);
        $this->handler(true)->validate($this->createStub(Quote::class), [
            OrderComment::FIELD => str_repeat('a', OrderComment::MAX_LENGTH + 1),
        ]);
    }

    public function testLengthIsIgnoredWhenTheFieldIsOff(): void
    {
        $this->handler(false)->validate($this->createStub(Quote::class), [
            OrderComment::FIELD => str_repeat('a', OrderComment::MAX_LENGTH + 1),
        ]);
        $this->addToAssertionCount(1);
    }

    public function testCommentIsSavedAsNoteAndVisibleHistory(): void
    {
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()
            ->onlyMethods(['setCustomerNote', 'addCommentToStatusHistory', 'getStoreId'])->getMock();
        $order->expects($this->once())->method('setCustomerNote')->with('Leave at the door');
        $order->expects($this->once())->method('addCommentToStatusHistory')
            ->with($this->stringContains('Leave at the door'), false, true);
        $repository = $this->createMock(OrderRepositoryInterface::class);
        $repository->expects($this->once())->method('save')->with($order);

        $this->handler(true, $repository)->execute($order, [OrderComment::FIELD => '  Leave at the door ']);
    }

    public function testEmptyCommentSavesNothing(): void
    {
        $repository = $this->createMock(OrderRepositoryInterface::class);
        $repository->expects($this->never())->method('save');

        $this->handler(true, $repository)->execute($this->createStub(Order::class), [OrderComment::FIELD => '  ']);
    }

    private function handler(bool $enabled, ?OrderRepositoryInterface $repository = null): OrderComment
    {
        $config = $this->createStub(Config::class);
        $config->method('isOrderCommentEnabled')->willReturn($enabled);

        return new OrderComment($config, $repository ?? $this->createStub(OrderRepositoryInterface::class));
    }
}
