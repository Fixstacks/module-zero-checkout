<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Controller\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Model\Registration;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\LayoutFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\CustomerAssignment;
use Magento\Sales\Model\Order\OrderCustomerExtractor;
use Psr\Log\LoggerInterface;

/**
 * "Create an account" on the order success page, in one step: the guest
 * picks a password there, the account is made from the order (name, email,
 * its addresses), the order is attached to it, and the customer is signed
 * in and sent to the order. Stock links to a separate registration form
 * instead (checkout/account/delegateCreate).
 *
 * Only for the session's last order, placed as a guest, whose email has no
 * account yet, while registration is allowed: the same rules as the prompt
 * (Magento\Checkout\Block\Registration). HTMX posts get the prompt back with
 * the error, or an HX-Redirect; a plain form post is redirected either way.
 */
class CreateAccount implements HttpPostActionInterface
{
    private const BLOCK_NAME = 'checkout.registration';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly CheckoutSession $checkoutSession,
        private readonly CustomerSession $customerSession,
        private readonly Registration $registration,
        private readonly AccountManagementInterface $accountManagement,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderCustomerExtractor $orderCustomerExtractor,
        private readonly CustomerAssignment $customerAssignment,
        private readonly LayoutFactory $resultLayoutFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly ResponseInterface $response,
        private readonly ManagerInterface $messageManager,
        private readonly UrlInterface $url,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     */
    public function execute()
    {
        $isHtmx = (bool) $this->request->getHeader('HX-Request');
        try {
            $order = $this->createAccount();
        } catch (LocalizedException $e) {
            return $this->fail($e->getMessage(), $isHtmx);
        } catch (\Throwable $e) {
            $this->logger->error('Zero Checkout: account from order failed: ' . $e->getMessage());

            return $this->fail((string) __('We can\'t create your account right now. Please try again.'), $isHtmx);
        }

        $this->messageManager->addSuccessMessage(
            __('Your account is ready. Order #%1 is in it.', $order->getIncrementId())
        );
        $target = $this->url->getUrl('sales/order/view', ['order_id' => $order->getEntityId()]);
        if ($isHtmx) {
            $this->response->setHeader('HX-Redirect', $target);

            return $this->response;
        }

        return $this->redirectFactory->create()->setUrl($target);
    }

    /**
     * @return \Magento\Sales\Api\Data\OrderInterface
     * @throws LocalizedException
     */
    private function createAccount()
    {
        $orderId = (int) $this->checkoutSession->getLastOrderId();
        if (!$orderId || $this->customerSession->isLoggedIn() || !$this->registration->isAllowed()) {
            throw new LocalizedException(__('This order can no longer get an account here. Create one from Sign In.'));
        }
        $order = $this->orderRepository->get($orderId);
        if ($order->getCustomerId() || !$order->getCustomerIsGuest()) {
            throw new LocalizedException(__('This order already belongs to an account.'));
        }
        if (!$this->accountManagement->isEmailAvailable((string) $order->getCustomerEmail())) {
            throw new LocalizedException(__('There is already an account with this email. Sign in to see your orders.'));
        }

        $password = (string) $this->request->getParam('password');
        if ($password !== (string) $this->request->getParam('password_confirmation')) {
            throw new LocalizedException(__('Please make sure your passwords match.'));
        }

        $customer = $this->orderCustomerExtractor->extract($orderId);
        $customer = $this->accountManagement->createAccount($customer, $password);
        $this->customerAssignment->execute($order, $customer);
        $this->customerSession->setCustomerDataAsLoggedIn($customer);
        $this->customerSession->regenerateId();

        return $order;
    }

    /**
     * @param string $message
     * @param bool $isHtmx
     * @return \Magento\Framework\Controller\ResultInterface
     */
    private function fail(string $message, bool $isHtmx)
    {
        if (!$isHtmx) {
            $this->messageManager->addErrorMessage($message);

            return $this->redirectFactory->create()->setPath('checkout/onepage/success');
        }
        $result = $this->resultLayoutFactory->create();
        $result->addDefaultHandle();
        $layout = $result->getLayout();
        $block = $layout->getBlock(self::BLOCK_NAME);
        if ($block) {
            $block->setData('accountError', $message);
        }
        $layout->addOutputElement(self::BLOCK_NAME);

        return $result;
    }
}
