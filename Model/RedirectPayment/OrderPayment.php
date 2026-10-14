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
 * What a payment on the provider's own page does to the order once the
 * provider has answered (Zero_Monobank, Zero_LiqPay, Zero_Mollie,
 * Zero_PaypalCheckout, Zero_Klarna, Zero_Adyen): the order waited in
 * pending_payment, and now it is
 * - captured: paid; an invoice is registered (Magento's capture notification);
 * - authorized: the money is reserved and taken later (Klarna, Adyen with
 *   manual capture); the order is processing without an invoice;
 * - cancelled.
 * Captured and authorized send the order email, which waited for the payment.
 * Each is done once: settled() tells whether the order is past waiting.
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
