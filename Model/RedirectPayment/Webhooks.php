<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Zero\Checkout\Model\RedirectPayment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Sales\Model\Order;
use Zero\Checkout\Model\Config;

/**
 * A provider's verified webhook or callback, handled now, or from the queue
 * (Payment Webhooks > Process Through the Queue): the provider gets its
 * answer at once and the consumer zero.checkout.webhook brings the order in
 * line (WebhookConsumer). Handlers by payment method code, from di.xml.
 */
class Webhooks
{
    public const TOPIC = 'zero.checkout.webhook';

    /**
     * @param array<string, WebhookHandlerInterface> $handlers payment method code => handler
     */
    public function __construct(
        private readonly Config $config,
        private readonly PublisherInterface $publisher,
        private readonly array $handlers = []
    ) {
    }

    /**
     * @param array<string, mixed> $data what the call carried, verified
     * @throws LocalizedException when handled now and the provider can't be asked (answer 503: it calls again)
     */
    public function handle(Order $order, array $data): void
    {
        $method = (string) $order->getPayment()?->getMethod();
        if ($this->config->isWebhookQueued()) {
            $this->publish($order, $method, $data, 1);
            return;
        }
        $this->handler($method)->webhook($order, $data);
    }

    /**
     * @throws \InvalidArgumentException for a method without a handler
     */
    public function handler(string $method): WebhookHandlerInterface
    {
        $handler = $this->handlers[$method] ?? null;
        if (!$handler instanceof WebhookHandlerInterface) {
            throw new \InvalidArgumentException(sprintf('Zero Checkout: no webhook handler for payment method "%s".', $method));
        }
        return $handler;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function publish(Order $order, string $method, array $data, int $attempt): void
    {
        $this->publisher->publish(self::TOPIC, json_encode([
            'order_id' => (int) $order->getId(),
            'method' => $method,
            'data' => $data,
            'attempt' => $attempt,
        ]));
    }
}
