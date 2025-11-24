(function () {
    // Ensure FluentCart loader enables the button for LINE Pay
    window.addEventListener('fluent_cart_load_payments_' + (window.fct_linepay_frontend ? window.fct_linepay_frontend.method : 'linepay'), function (evt) {
        try {
            // Tell FluentCart UI to show & enable the default button
            if (evt && evt.detail && evt.detail.paymentLoader) {
                evt.detail.paymentLoader.showCheckoutButton();
                evt.detail.paymentLoader.enableCheckoutButton('Place Order');
            }

            // Nothing else to render (redirect-based)
            var container = document.querySelector('.fluent-cart-checkout_embed_payment_container_' + (window.fct_linepay_frontend ? window.fct_linepay_frontend.method : 'linepay'));
            if (container) {
                container.innerHTML = '';
            }
        } catch (e) {
            console && console.warn && console.warn('LINE Pay frontend init error', e);
        }
    });
})();


