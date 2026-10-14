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
 * "Sign in" from the checkout page: sets the post-login redirect to checkout
 * (both the `referer` parameter and the session's after-auth URL) and forwards
 * to core's customer/account/loginPost, which keeps captcha, lockout, form key,
 * customer_login and the cart merge.
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
