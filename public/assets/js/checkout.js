// Opens the Razorpay checkout for the customer "Pay invoice" page and posts
// the signed result back to the panel for verification.
(function () {
    'use strict';
    var card = document.getElementById('payCard');
    var button = document.getElementById('payButton');
    var message = document.getElementById('payMessage');
    var result = document.getElementById('payResult');
    if (!card || !button || !result) {
        return;
    }

    function show(type, text) {
        message.className = 'alert small text-start alert-' + type;
        message.textContent = text;
    }

    function ready(label) {
        button.disabled = false;
        button.textContent = label;
    }

    if (typeof window.Razorpay !== 'function') {
        show('danger', 'The payment window could not be loaded. Check your connection or disable content blockers, then reload this page.');
        button.textContent = 'Payment window unavailable';
        return;
    }

    var options = JSON.parse(card.getAttribute('data-checkout'));
    var submitting = false;
    options.handler = function (response) {
        submitting = true;
        button.disabled = true;
        button.textContent = 'Confirming payment…';
        result.elements.razorpay_order_id.value = response.razorpay_order_id || '';
        result.elements.razorpay_payment_id.value = response.razorpay_payment_id || '';
        result.elements.razorpay_signature.value = response.razorpay_signature || '';
        result.submit();
    };
    options.modal = {
        ondismiss: function () {
            if (!submitting) {
                show('secondary', 'Payment window closed. No money was taken. You can try again whenever you are ready.');
                ready('Pay now');
            }
        }
    };

    var rzp = new window.Razorpay(options);
    rzp.on('payment.failed', function (resp) {
        var reason = resp && resp.error && resp.error.description ? resp.error.description : 'The payment did not go through.';
        show('danger', reason + ' You can try again or use another payment method.');
    });
    button.addEventListener('click', function () {
        message.className = 'alert d-none';
        rzp.open();
    });
    ready('Pay now');
    rzp.open();
})();
