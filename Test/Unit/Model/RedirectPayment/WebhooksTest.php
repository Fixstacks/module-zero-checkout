<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Zero\Checkout\Test\Unit\Model\RedirectPayment;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zero\Checkout\Model\Config;
use Zero\Checkout\Model\RedirectPayment\WebhookHandlerInterface;
use Zero\Checkout\Model\RedirectPayment\Webhooks;

/**
 * A verified webhook: handled now by the method's handler, or published
 * for the consumer when Process Through the Queue is on.
 */
#[AllowMockObjectsWithoutExpectations]
class WebhooksTest extends TestCase
{
    private bool $queued = false;
    private WebhookHandlerInterface&MockObject $handler;
    private PublisherInterface&MockObject $publisher;
    private Webhooks $webhooks;

    protected function setUp(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(fn ($path) => $path === Config::XML_PATH_WEBHOOK_QUEUE && $this->queued);
        $this->handler = $this->createMock(WebhookHandlerInterface::class);
        $this->publisher = $this->createMock(PublisherInterface::class);
        $this->webhooks = new Webhooks(new Config($scopeConfig), $this->publisher, ['zero_mollie' => $this->handler]);
    }

    public function testHandledNowWhileTheProviderWaits(): void
    {
        $order = $this->order();
        $this->handler->expects($this->once())->method('webhook')->with($order, ['id' => 'tr_1']);
        $this->publisher->expects($this->never())->method('publish');
        $this->webhooks->handle($order, ['id' => 'tr_1']);
    }

    public function testTheProviderNotAnsweringIsPassedOnWhenHandledNow(): void
    {
        $this->handler->method('webhook')->willThrowException(new LocalizedException(__('Mollie did not answer')));
        $this->expectException(LocalizedException::class);
        $this->webhooks->handle($this->order(), []);
    }

    public function testPublishedForTheConsumerWhenQueued(): void
    {
        $this->queued = true;
        $this->handler->expects($this->never())->method('webhook');
        $this->publisher->expects($this->once())->method('publish')->with(
            'zero.checkout.webhook',
            '{"order_id":12,"method":"zero_mollie","data":{"id":"tr_1"},"attempt":1}'
        );
        $this->webhooks->handle($this->order(), ['id' => 'tr_1']);
    }

    public function testAMethodWithoutAHandler(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->webhooks->handle($this->order('zero_other'), []);
    }

    private function order(string $method = 'zero_mollie'): Order&MockObject
    {
        $payment = $this->createStub(Payment::class);
        $payment->method('getMethod')->willReturn($method);
        $order = $this->createMock(Order::class);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getId')->willReturn(12);
        return $order;
    }
}
