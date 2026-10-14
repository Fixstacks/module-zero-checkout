<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Model\RedirectPayment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Sales\Model\Order;

/**
 * The customer back from the provider's payment page: which order it is,
 * and where they go next.
 *
 * - PAID: the success page;
 * - PENDING: the success page, with a note that the order will follow the payment;
 * - UNCONFIRMED: the same, when the provider couldn't be asked;
 * - FAILED: the cart restored, back to the checkout page with a message
 *   (the order was cancelled before).
 */
class ReturnResult
{
    public const PAID = 'paid';
    public const PENDING = 'pending';
    public const FAILED = 'failed';
    public const UNCONFIRMED = 'unconfirmed';
    /** No payment was started on the provider's page. */
    public const NOT_STARTED = 'not_started';

    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly ResultFactory $resultFactory,
        private readonly MessageManager $messageManager
    ) {
    }

    /**
     * The checkout session's last order, when it is the one named in the
     * return URL and paid with this method. Nobody else's order is looked at.
     */
    public function lastOrder(string $methodCode, string $incrementId): ?Order
    {
        $order = $this->checkoutSession->getLastRealOrder();

        return $order->getId()
            && $incrementId !== ''
            && $order->getIncrementId() === $incrementId
            && $order->getPayment()?->getMethod() === $methodCode
            ? $order
            : null;
    }

    public function redirect(string $state, string $provider): Redirect
    {
        /** @var Redirect $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        switch ($state) {
            case self::FAILED:
            case self::NOT_STARTED:
                $this->checkoutSession->restoreQuote();
                $this->messageManager->addErrorMessage(
                    __('The payment didn\'t go through. Your cart is unchanged: try again or choose another payment method.')
                );

                return $result->setPath('zero/checkout/index');
            case self::PENDING:
                $this->messageManager->addNoticeMessage(
                    __('%1 is processing the payment. The order will be updated when it is done.', $provider)
                );
                break;
            case self::UNCONFIRMED:
                $this->messageManager->addNoticeMessage(
                    __('We are waiting for %1 to confirm the payment. The order will be updated when it does.', $provider)
                );
                break;
        }

        return $result->setPath('checkout/onepage/success');
    }

    /**
     * Not this customer's payment to come back from.
     */
    public function elsewhere(): Redirect
    {
        /** @var Redirect $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $result->setPath('checkout/cart');
    }
}
