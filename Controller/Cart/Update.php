<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Controller\Cart;

use Magento\Checkout\Model\Cart;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Framework\View\Result\Layout;
use Magento\Framework\View\Result\LayoutFactory;

/**
 * Mini-cart qty update / remove (POST from the mini-cart popover). Uses stock
 * Cart::updateItems(), which only touches items of the current quote, so another
 * customer's item id is ignored.
 *
 * CSRF: Magento's automatic check is opted out (CsrfAwareActionInterface) and the
 * form key is validated manually in execute(), same as Placeorder. On an invalid
 * form key nothing is applied and the current cart is re-rendered.
 *
 * Re-renders the same zero.checkout.minicart block as the GET fragment and sends
 * `HX-Trigger: cart:updated`, which refreshes the header cart counter and the
 * mini-cart trigger in place.
 */
class Update implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const BLOCK_NAME = 'zero.checkout.minicart';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly HttpResponse $response,
        private readonly LayoutFactory $resultLayoutFactory,
        private readonly Cart $cart,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly MessageManager $messageManager
    ) {
    }

    /**
     * @return Layout
     */
    public function execute()
    {
        if ($this->formKeyValidator->validate($this->request)) {
            $itemId = (int) $this->request->getParam('item_id');
            $remove = (bool) $this->request->getParam('remove');
            $qty = $this->request->getParam('qty');

            if ($itemId > 0) {
                try {
                    $this->cart->updateItems([
                        $itemId => [
                            'qty' => $remove ? 0 : $qty,
                            'remove' => $remove,
                        ],
                    ]);
                    $this->cart->save();
                } catch (LocalizedException $e) {
                    $this->messageManager->addErrorMessage($e->getMessage());
                }
            }
        }

        $this->response->setNoCacheHeaders();
        $this->response->setHeader('HX-Trigger', 'cart:updated');

        $resultLayout = $this->resultLayoutFactory->create();
        $resultLayout->addDefaultHandle();
        $resultLayout->getLayout()->addOutputElement(self::BLOCK_NAME);

        return $resultLayout;
    }

    /**
     * @inheritdoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
