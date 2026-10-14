<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Model\RedirectPayment;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Psr\Log\LoggerInterface;

/**
 * What a provider's answer does to an order that waited in pending_payment
 * (Monobank, LiqPay, Mollie, PayPal, Klarna, Adyen):
 * - captured: paid, invoice registered;
 * - authorized: reserved, captured later; processing without an invoice;
 * - cancelled.
 * Captured and authorized send the order email. Each is done once (settled()).
 */
class OrderPayment
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderSender $orderSender,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * ReturnResult::PAID or ReturnResult::FAILED once the order has been
     * settled; null while it waits for the payment.
     */
    public function settled(Order $order): ?string
    {
        return match ($order->getState()) {
            Order::STATE_PENDING_PAYMENT, Order::STATE_NEW => null,
            Order::STATE_CANCELED => ReturnResult::FAILED,
            default => ReturnResult::PAID,
        };
    }

    public function capture(Order $order, string $transactionId): void
    {
        $order->getPayment()
            ->setTransactionId($transactionId)
            ->setIsTransactionClosed(true)
            ->registerCaptureNotification((float) $order->getBaseGrandTotal(), true);
        $this->save($order);
    }

    public function authorize(Order $order, string $transactionId): void
    {
        $order->getPayment()
            ->setTransactionId($transactionId)
            ->setIsTransactionClosed(false)
            ->registerAuthorizationNotification((float) $order->getBaseGrandTotal());
        $this->save($order);
    }

    public function cancel(Order $order, string $reason): void
    {
        if ($order->canCancel()) {
            $order->registerCancellation($reason);
            $this->orderRepository->save($order);
        }
    }

    /**
     * The provider reports something the order doesn't match (another amount
     * or currency): the order keeps waiting, with a comment for the shop.
     */
    public function flag(Order $order, string $comment, string $details): void
    {
        $this->logger->error('Zero Checkout: order ' . $order->getIncrementId() . ': ' . $details);
        $order->addCommentToStatusHistory($comment);
        $this->orderRepository->save($order);
    }

    private function save(Order $order): void
    {
        $this->orderRepository->save($order);
        if (!$order->getEmailSent()) {
            try {
                $this->orderSender->send($order);
            } catch (\Throwable $e) {
                $this->logger->warning('Zero Checkout: order email for ' . $order->getIncrementId() . ': ' . $e->getMessage());
            }
        }
    }
}
