<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Controller\Checkout;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Url\EncoderInterface;
use Magento\Framework\UrlInterface;

/**
 * "Sign in" from the checkout page (checkout/login.phtml). Doesn't log in
 * itself: it points the post-login redirect back at checkout and forwards the
 * POST to core's customer/account/loginPost, which keeps captcha, lockout,
 * the form-key check, the customer_login event and the guest-cart merge.
 *
 * Both of core's redirect paths are covered: the `referer` parameter (used
 * when "Redirect Customer to Account Dashboard after Logging in" is No) and
 * the session's after-auth URL (used when it's Yes). A failed login lands on
 * core's sign-in page with its message; signing in there still returns here.
 */
class Login implements HttpPostActionInterface
{
    public function __construct(
        private readonly ForwardFactory $forwardFactory,
        private readonly RequestInterface $request,
        private readonly CustomerSession $customerSession,
        private readonly UrlInterface $url,
        private readonly EncoderInterface $urlEncoder
    ) {
    }

    /**
     * @return Forward
     */
    public function execute()
    {
        $checkoutUrl = $this->url->getUrl('zero/checkout/index');
        $this->customerSession->setAfterAuthUrl($checkoutUrl);
        $this->request->setParam(CustomerUrl::REFERER_QUERY_PARAM_NAME, $this->urlEncoder->encode($checkoutUrl));

        return $this->forwardFactory->create()
            ->setModule('customer')
            ->setController('account')
            ->forward('loginPost');
    }
}
