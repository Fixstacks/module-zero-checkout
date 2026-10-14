# Zero_Checkout

A one-page checkout for Magento Open Source that replaces the Knockout
checkout: server-rendered HTML, one native `<form>`, HTMX for the parts that
refresh (shipping rates, totals, regions, saved addresses, discount code).
Offline payment methods work with JavaScript turned off.

## Install

Requires Magento Open Source 2.4.7–2.4.9 and PHP 8.2–8.4.

```bash
composer require fixstacks/module-zero-checkout
bin/magento setup:upgrade
bin/magento setup:di:compile           # production mode
bin/magento setup:static-content:deploy # production mode
bin/magento cache:flush
```

Composer installs `fixstacks/module-zero-core` with it.

Part of [`fixstacks/zero`](https://fixstacks.com/zero), which installs the Zero theme and every Zero module at once.

## Features

| Feature | Where |
| --- | --- |
| Contact, shipping and billing addresses, saved-address picker | `checkout/index.phtml`, `shipping-address-fields.phtml`, `billing-address-fields.phtml` |
| Default shipping address prefilled for signed-in customers; new addresses saved to the address book | `Controller/Checkout/Index`, `Model/Checkout/ShippingAddressUpdater` |
| Browser autofill (`autocomplete` tokens per section) | address templates |
| Live shipping rates and totals | `Controller/Checkout/Shippingmethods` |
| Discount code in the order summary | `Controller/Checkout/Coupon`, `coupon.phtml` |
| Order comment, saved on the order | `Model/PlaceOrder/OrderComment`, `order-comment.phtml` |
| Terms and Conditions, checked on the server | `Model/PlaceOrder/AgreementsValidator`, `agreements.phtml` |
| Sign in from the checkout page | `Controller/Checkout/Login`, `login.phtml` |
| Order summary with item options | `ViewModel/Checkout/CheckoutPage` |
| Payment: offline methods, purchase order; Braintree, Stripe, Authorize.Net, PayPal Standard from their own Zero modules | `zero.checkout.payment.*` containers |

## Settings

Stores > Configuration > **Zero** > **Checkout**:

| Group | Setting | Path | Default |
| --- | --- | --- | --- |
| Zero Checkout | Enabled | `checkout/zero_checkout/enabled` | Yes |
| Checkout Page | Discount Code Field | `checkout/zero_checkout/coupon` | Yes |
| Checkout Page | Order Comment | `checkout/zero_checkout/order_comment` | Yes |
| Checkout Page | Sign In at Checkout | `checkout/zero_checkout/login` | Yes |
| Terms and Conditions | Enable Terms and Conditions | `checkout/options/enable_agreements` (Magento's) | No |

The agreements are edited in Stores > Terms and Conditions. Payment methods
are configured in Sales > Payment Methods.

## Extending the checkout

Four extension points, all wired with layout XML or `di.xml`; nothing in
Zero_Checkout needs to change.

### 1. Add a field or a block to the page

Layout containers in `zero_checkout_index.xml`:

| Container | Position | Inside the checkout form? |
| --- | --- | --- |
| `zero.checkout.login` (block) | above the form | no |
| `zero.checkout.shipping.after` | right after the shipping methods (a carrier's own fields) | yes |
| `zero.checkout.payment.express` | above the payment methods | yes |
| `zero.checkout.payment.forms` | below the payment methods (card fields) | yes |
| `zero.checkout.summary.totals.before` | order summary, above the totals | yes |
| `zero.checkout.place-order.before` | right above Place Order | yes |

Fields inside the form are posted with the order.

```xml
<!-- Acme/GiftWrap/view/frontend/layout/zero_checkout_index.xml -->
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="zero.checkout.place-order.before">
            <block class="Magento\Framework\View\Element\Template" name="acme.giftwrap"
                   template="Acme_GiftWrap::checkout/gift-wrap.phtml"/>
        </referenceContainer>
    </body>
</page>
```

```php
<!-- Acme/GiftWrap/view/frontend/templates/checkout/gift-wrap.phtml -->
<label class="flex items-center gap-2 mt-4 text-sm">
    <input type="checkbox" name="acme_gift_wrap" value="1" class="checkbox checkbox-sm" />
    <?= $escaper->escapeHtml(__('Gift-wrap this order')) ?>
</label>
```

Style with the classes Zero's templates already use, or the theme's
Tailwind build won't include them (see the theme's `docs/child-themes.md`).

### 2. Check something before the order is placed

Implement `Zero\Checkout\Api\PlaceOrderValidatorInterface`. It runs after the
form's addresses, shipping method and payment are on the quote; throw
`LocalizedException` to stop the order, and the message is shown on the
checkout page.

```php
class GiftWrapValidator implements \Zero\Checkout\Api\PlaceOrderValidatorInterface
{
    public function validate(\Magento\Quote\Model\Quote $quote, array $request): void
    {
        if (!empty($request['acme_gift_wrap']) && $quote->getIsVirtual()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Virtual items can\'t be gift-wrapped.'));
        }
    }
}
```

### 3. Do something once the order exists

Implement `Zero\Checkout\Api\AfterPlaceOrderInterface`. A failure here is
logged and the shopper still reaches the success page.

```php
class SaveGiftWrap implements \Zero\Checkout\Api\AfterPlaceOrderInterface
{
    public function execute(\Magento\Sales\Api\Data\OrderInterface $order, array $request): void
    {
        if (!empty($request['acme_gift_wrap'])) {
            $order->addCommentToStatusHistory('Gift wrap requested.');
            $this->orderRepository->save($order);
        }
    }
}
```

Register both:

```xml
<!-- Acme/GiftWrap/etc/frontend/di.xml -->
<type name="Zero\Checkout\Controller\Checkout\Placeorder">
    <arguments>
        <argument name="validators" xsi:type="array">
            <item name="acme_gift_wrap" xsi:type="object">Acme\GiftWrap\Model\GiftWrapValidator</item>
        </argument>
        <argument name="afterPlaceOrder" xsi:type="array">
            <item name="acme_gift_wrap" xsi:type="object">Acme\GiftWrap\Model\SaveGiftWrap</item>
        </argument>
    </arguments>
</type>
```

Items are keyed by name, so a module can also replace or remove a built-in
one (`agreements`, `order_comment`) by reusing its key.

### 4. Add a payment method

Zero Checkout lists only the payment methods it knows how to complete. A
payment module registers its method code (and an optional one-line
description) with `Zero\Checkout\Model\SupportedPaymentMethods`:

```xml
<!-- Acme/Pay/etc/frontend/di.xml -->
<type name="Zero\Checkout\Model\SupportedPaymentMethods">
    <arguments>
        <argument name="methods" xsi:type="array">
            <item name="acme_pay" xsi:type="string" translate="true">Pay with your Acme wallet.</item>
        </argument>
    </arguments>
</type>
```

Then, depending on the method:

- **Offline / redirect after placing** — nothing else; a method that needs an
  off-site step puts the payment page's URL in the order payment's
  additional information as `zero_redirect_url`
  (`Placeorder::REDIRECT_URL`) while the order is placed, and the customer
  goes there instead of the success page (see `Zero_Monobank`,
  `Zero_LiqPay`).
- **Card fields or a client SDK** — add the form to
  `zero.checkout.payment.forms` and show it only while the method is selected
  (`#payment-methods:has(input[value="acme_pay"]:checked) ~ #acme-pay-panel`
  in CSS, as Zero_Braintree, Zero_Stripe and Zero_AuthorizeNet do). The
  script tokenizes on submit into a hidden field named
  `payment_additional_data[<key>]`; Placeorder puts every such field into the
  payment's `additional_data`, where your method's `assignData()` (or an
  observer) reads it.

Zero_Braintree is the smallest complete example: a view model, a template, a
script and one layout file.

## What not to change

- The checkout `<form>` posts to `zero/checkout/placeorder`; don't nest other
  `<form>` elements inside it (use `type="button"` + `hx-post`, like the
  discount code).
- `hx-sync="#shipping-methods:replace"` on address fields keeps the newest
  rate request winning.
- `form_key` is in the form; HTMX posts include it with
  `hx-include="[name='form_key']"`.

## License

OSL-3.0 or AFL-3.0, your choice: see `NOTICE.md`.

---

Zero is made by [Fixstacks](https://fixstacks.com/zero) in partnership with [Vilmate](https://vilmate.com).
Documentation: https://fixstacks.com/zero/docs. Demo store: https://zero.fixstacks.com.
