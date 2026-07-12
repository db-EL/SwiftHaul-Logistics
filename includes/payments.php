<?php
// ============================================================
// Shared payment confirmation logic.
//
// Called from two places:
//  1. api/paystack_verify.php — fired by the browser right after
//     checkout, for fast UX (the customer sees "paid" instantly).
//  2. api/paystack_webhook.php — fired by Paystack's servers
//     directly, independent of whether the customer's browser
//     stayed open. THIS is the authoritative source of truth:
//     if the browser call never happens (tab closed, network
//     drop), the webhook still marks the shipment paid.
//
// Idempotent: safe to call multiple times for the same reference.
// ============================================================
require_once __DIR__ . '/paystack.php';
require_once __DIR__ . '/push.php';

/**
 * @return array{success:bool, message:string}
 */
function confirm_shipment_payment(PDO $pdo, int $shipmentId, string $reference): array {
    $stmt = $pdo->prepare("SELECT * FROM shipments WHERE id = ?");
    $stmt->execute([$shipmentId]);
    $shipment = $stmt->fetch();

    if (!$shipment) {
        return ['success' => false, 'message' => 'Shipment not found.'];
    }

    if ($shipment['payment_status'] === 'paid') {
        // Already confirmed (likely by the other path) — nothing to do.
        return ['success' => true, 'message' => 'Already confirmed as paid.'];
    }

    $result = paystack_verify_transaction($reference);

    if (!$result['ok']) {
        return ['success' => false, 'message' => 'Could not verify payment: ' . $result['message']];
    }

    $tx = $result['data'];
    $expectedKobo = (int) round($shipment['fee'] * 100);

    if (($tx['status'] ?? '') !== 'success') {
        $pdo->prepare("UPDATE shipments SET payment_status = 'failed' WHERE id = ? AND payment_status != 'paid'")->execute([$shipmentId]);
        return ['success' => false, 'message' => 'Payment was not successful.'];
    }

    if ((int) $tx['amount'] !== $expectedKobo) {
        return ['success' => false, 'message' => 'Payment amount does not match the shipment fee.'];
    }

    $pdo->prepare("UPDATE shipments SET payment_status = 'paid', paystack_reference = ?, paid_at = NOW() WHERE id = ? AND payment_status != 'paid'")
        ->execute([$reference, $shipmentId]);

    // The order is now visible in the riders' claim pool — push a real
    // notification to every available rider (not just the in-app toast,
    // which only fires while a rider's tab happens to be open).
    notify_available_riders_of_new_order($pdo, $shipmentId);

    return ['success' => true, 'message' => 'Payment confirmed.'];
}
