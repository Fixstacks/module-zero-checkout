/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 *
 * Zero Checkout in steps (Layout: Steps): shows one step at a time, the
 * elements marked data-step="1|2|3" in checkout/index.phtml. Continue checks
 * the current step's fields with the browser's own validation before moving
 * on; the step indicator lets the shopper go back to any step reached. Place
 * Order ([data-step-final]) shows on the last step; Enter in a field on an
 * earlier step continues instead of submitting. Focus moves to the new
 * step's first heading. Without this script every step shows, as one page.
 */
(function () {
    'use strict';

    var form = document.getElementById('checkout-form');
    if (!form || !form.hasAttribute('data-checkout-steps')) {
        return;
    }
    var parts = Array.prototype.slice.call(form.querySelectorAll('[data-step]'));
    var numbers = parts
        .map(function (el) { return parseInt(el.getAttribute('data-step'), 10); })
        .filter(function (n, i, all) { return all.indexOf(n) === i; })
        .sort();
    var last = numbers[numbers.length - 1];
    var nav = form.querySelector('[data-step-nav]');
    var current = numbers[0];
    var reached = current;

    function show(n, moveFocus) {
        current = n;
        reached = Math.max(reached, n);
        parts.forEach(function (el) {
            el.hidden = parseInt(el.getAttribute('data-step'), 10) !== n;
        });
        form.querySelectorAll('[data-step-final]').forEach(function (el) {
            el.hidden = n !== last;
        });
        if (nav) {
            nav.querySelectorAll('[data-step-item]').forEach(function (item) {
                var itemStep = parseInt(item.getAttribute('data-step-item'), 10);
                item.classList.toggle('step-primary', itemStep <= n);
                var button = item.querySelector('button');
                if (itemStep === n) {
                    button.setAttribute('aria-current', 'step');
                } else {
                    button.removeAttribute('aria-current');
                }
                button.disabled = itemStep > reached;
            });
        }
        if (moveFocus) {
            var heading = form.querySelector('[data-step="' + n + '"] h2');
            if (heading) {
                heading.setAttribute('tabindex', '-1');
                heading.focus({ preventScroll: true });
                var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                heading.scrollIntoView({ block: 'start', behavior: reduceMotion ? 'auto' : 'smooth' });
            }
        }
    }

    /** First invalid visible field of step n reports itself; true when there is none. */
    function stepIsValid(n) {
        var fields = form.querySelectorAll(
            '[data-step="' + n + '"] input, [data-step="' + n + '"] select, [data-step="' + n + '"] textarea'
        );
        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            if (field.disabled || field.type === 'hidden' || field.offsetParent === null) {
                continue;
            }
            if (!field.checkValidity()) {
                field.reportValidity();
                return false;
            }
        }
        return true;
    }

    function next() {
        if (current < last && stepIsValid(current)) {
            show(numbers[numbers.indexOf(current) + 1], true);
        }
    }

    form.addEventListener('click', function (event) {
        var target = event.target instanceof Element ? event.target : null;
        if (!target) {
            return;
        }
        if (target.closest('[data-step-next]')) {
            next();
        } else if (target.closest('[data-step-back]')) {
            show(numbers[Math.max(0, numbers.indexOf(current) - 1)], true);
        } else {
            var goto = target.closest('[data-step-goto]');
            if (goto && !goto.disabled) {
                show(parseInt(goto.getAttribute('data-step-goto'), 10), true);
            }
        }
    });

    // Enter in a field submits the form natively: before the last step it continues instead.
    form.addEventListener('submit', function (event) {
        if (current !== last) {
            event.preventDefault();
            event.stopImmediatePropagation();
            next();
        }
    }, true);

    if (nav) {
        nav.hidden = false;
    }
    show(current, false);
})();
