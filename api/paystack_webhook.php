<?php
// ============================================================
// Paystack Webhook — the AUTHORITATIVE source of truth for
// payment confirmation.
//
// Configure this URL in your Paystack dashboard under
// Settings → API Keys & Webhooks → Webhook URL:
//   https://yourdomain.com/logistics/api/paystack_webhook.php
//
// Why this matters: api/paystack_verify.php only fires if the
// customer's browser stays open and completes the callback. If
// they close the tab right after paying, lose signal, or their
// browser crashes, that call never happens — but Paystack still
// took the money. This webhook is Paystack's server calling YOUR
// server directly, so the shipment gets marked paid regardless of
// what happens on the customer's device.
//
// Every request is verified via the X-Paystack-Signature header
// (HMAC-SHA512 of the raw body, using your secret key) before any
// action is taken, so this endpoint can't be spoofed by a random
// POST from the internet.
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/payments.php';
require_once __DIR__ . '/../includes/payouts.php';

$rawBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

if (!$signature || !hash_equals(hash_hmac('sha512', $rawBody, PAYSTACK_SECRET_KEY), $signature)) {
    http_response_code(401);
    error_log('[paystack_webhook] Rejected: invalid or missing signature.');
    echo 'Invalid signature';
    exit;
}

$event = json_decode($rawBody, true);
$eventType = $event['event'] ?? '';

// Always respond 200 quickly so Paystack doesn't endlessly retry —
// but only after we've actually processed what we can.
http_response_code(200);

if ($eventType === 'charge.success') {
    $data = $event['data'] ?? [];
    $reference = $data['reference'] ?? '';
    $shipmentId = (int) ($data['metadata']['shipment_id'] ?? 0);

    if ($reference && $shipmentId) {
        $result = confirm_shipment_payment($pdo, $shipmentId, $reference);
        error_log("[paystack_webhook] charge.success shipment #$shipmentId ref=$reference => " . json_encode($result));
    } else {
        error_log('[paystack_webhook] charge.success missing reference or shipment_id in metadata.');
    }
} elseif (in_array($eventType, ['transfer.success', 'transfer.failed', 'transfer.reversed'], true)) {
    // Optional: reconcile rider payout status if a transfer that we thought
    // succeeded actually failed/reversed later, or vice versa. Logged for
    // visibility; the automatic payout + cron retry already handle the
    // common path, so this is a reconciliation safety net, not the primary
    // trigger for anything.
    $transferCode = $event['data']['transfer_code'] ?? null;
    error_log("[paystack_webhook] $eventType transfer_code=$transferCode");
} else {
    // Unhandled event types are fine to ignore — just acknowledge receipt.
    error_log("[paystack_webhook] Ignored event type: $eventType");
}

echo 'ok';
