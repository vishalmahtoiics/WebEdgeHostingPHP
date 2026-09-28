<?php
/** @var array $invoice @var int $amount @var array $checkout @var string $checkoutJs */
$callback = url('/customer/invoices/' . $invoice['id'] . '/pay/verify');
?>
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
        <div class="card" id="payCard" data-checkout="<?= e(json_encode($checkout, JSON_UNESCAPED_SLASHES)) ?>">
            <div class="card-body text-center p-4">
                <div class="text-muted small mb-1">Invoice <?= e($invoice['invoice_number']) ?></div>
                <div class="display-6 fw-semibold mb-3"><?= e(money($amount)) ?></div>
                <p class="small text-muted mb-4">Pay securely by UPI, card, net banking or wallet. The payment window opens automatically.</p>
                <div id="payMessage" class="alert d-none small text-start" role="status"></div>
                <button type="button" class="btn btn-primary btn-lg w-100 mb-2" id="payButton" disabled>
                    <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading payment window…
                </button>
                <a class="btn btn-link btn-sm" href="<?= e(url('/customer/invoices/' . $invoice['id'])) ?>">Back to invoice</a>
            </div>
        </div>
        <form method="post" action="<?= e($callback) ?>" id="payResult" class="d-none">
            <?= csrf_field() ?>
            <input type="hidden" name="razorpay_order_id">
            <input type="hidden" name="razorpay_payment_id">
            <input type="hidden" name="razorpay_signature">
        </form>
    </div>
</div>
<script src="<?= e($checkoutJs) ?>"></script>
<script src="<?= e(asset('assets/js/checkout.js')) ?>"></script>
