<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Checkout\Test\Unit\Controller\Cart;

use Magento\Checkout\Model\Cart;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\View\Result\Layout;
use Magento\Framework\View\Result\LayoutFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zero\Checkout\Controller\Cart\Update;

/**
 * Mini-cart qty/remove: updates through stock Cart::updateItems() and always
 * answers with the refreshed fragment plus the cart:updated HTMX event.
 */
#[AllowMockObjectsWithoutExpectations]
class UpdateTest extends TestCase
{
    private Cart&MockObject $cart;
    private HttpResponse&MockObject $response;
    private MessageManager&MockObject $messages;

    protected function setUp(): void
    {
        $this->cart = $this->createMock(Cart::class);
        $this->response = $this->createMock(HttpResponse::class);
        $this->messages = $this->createMock(MessageManager::class);
    }

    public function testQtyChange(): void
    {
        $this->cart->expects($this->once())->method('updateItems')
            ->with([5 => ['qty' => '3', 'remove' => false]]);
        $this->cart->expects($this->once())->method('save');
        $this->response->expects($this->once())->method('setHeader')->with('HX-Trigger', 'cart:updated');

        $this->controller(['item_id' => '5', 'qty' => '3'])->execute();
    }

    public function testRemove(): void
    {
        $this->cart->expects($this->once())->method('updateItems')
            ->with([5 => ['qty' => 0, 'remove' => true]]);

        $this->controller(['item_id' => '5', 'remove' => '1', 'qty' => '3'])->execute();
    }

    public function testInvalidFormKeyChangesNothingButStillRendersFragment(): void
    {
        $this->cart->expects($this->never())->method('updateItems');
        $this->response->expects($this->once())->method('setNoCacheHeaders');

        $this->controller(['item_id' => '5', 'qty' => '3'], false)->execute();
    }

    public function testCartErrorBecomesMessage(): void
    {
        $this->cart->method('updateItems')->willThrowException(new LocalizedException(__('The requested qty is not available')));
        $this->messages->expects($this->once())->method('addErrorMessage')->with('The requested qty is not available');

        $this->controller(['item_id' => '5', 'qty' => '999'])->execute();
    }

    /**
     * @param array<string, string> $params
     */
    private function controller(array $params, bool $formKeyValid = true): Update
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(static fn (string $key) => $params[$key] ?? null);
        $validator = $this->createStub(FormKeyValidator::class);
        $validator->method('validate')->willReturn($formKeyValid);

        $layout = $this->createMock(LayoutInterface::class);
        $layout->expects($this->once())->method('addOutputElement')->with('zero.checkout.minicart');
        $result = $this->createStub(Layout::class);
        $result->method('getLayout')->willReturn($layout);
        $factory = $this->createStub(LayoutFactory::class);
        $factory->method('create')->willReturn($result);

        return new Update($request, $this->response, $factory, $this->cart, $validator, $this->messages);
    }
}
