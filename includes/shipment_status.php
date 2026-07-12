<?php
// Shared shipment status transition logic.
//
// Used by BOTH admin/shipments.php (manual override) and the
// rider's own dashboard (self-service — the normal path now that
// riders manage their own deliveries). Keeping this in one place
// means the OTP check and the automatic payout trigger can't
// drift out of sync between the two call sites.
require_once __DIR__ . '/payouts.php';
require_once __DIR__ . '/file_upload.php';

const VALID_SHIPMENT_STATUSES = ['Order Placed', 'Rider Assigned', 'Picked Up', 'In Transit', 'Out for Delivery', 'Delivered', 'Cancelled'];

/**
 * @param int|null $actingRiderId If set, the update is only allowed when
 *                                this rider is the one assigned to the
 *                                shipment (self-service authorization).
 *                                Pass null for admin (no ownership check).
 * @param string|null $signatureData Base64 PNG data URL from the canvas
 *                                    signature pad, captured on the
 *                                    receiver's behalf by the rider at
 *                                    drop-off. An alternative to $otpInput.
 * @return array{success:bool, message:string}
 */
function transition_shipment_status(
    PDO $pdo,
    int $shipmentId,
    string $newStatus,
    ?string $note,
    ?string $otpInput,
    ?int $actingRiderId,
    ?string $signatureData = null
): array {
    if (!in_array($newStatus, VALID_SHIPMENT_STATUSES, true)) {
        return ['success' => false, 'message' => 'Invalid status.'];
    }

    $stmt = $pdo->prepare("SELECT * FROM shipments WHERE id = ?");
    $stmt->execute([$shipmentId]);
    $ship = $stmt->fetch();

    if (!$ship) {
        return ['success' => false, 'message' => 'Shipment not found.'];
    }

    if ($actingRiderId !== null && (int) $ship['rider_id'] !== $actingRiderId) {
        return ['success' => false, 'message' => 'This delivery is not assigned to you.'];
    }

    // Marking Delivered triggers a real payout, so it can't rest on anyone's
    // word alone: EITHER the customer-issued delivery code must match, OR
    // the rider must have captured the receiver's signature at drop-off.
    $signaturePath = null;
    if ($newStatus === 'Delivered' && $ship['current_status'] !== 'Delivered') {
        $otpProvided = $otpInput !== null && $otpInput !== '';
        $signatureProvided = $signatureData !== null && $signatureData !== '';

        if (!$otpProvided && !$signatureProvided) {
            return ['success' => false, 'message' => 'Please confirm delivery with either the confirmation code or a captured signature.'];
        }

        if ($otpProvided) {
            if (!$ship['delivery_otp']) {
                return ['success' => false, 'message' => 'This shipment has no active delivery code on file.'];
            }
            if (!hash_equals($ship['delivery_otp'], (string) $otpInput)) {
                return ['success' => false, 'message' => 'The delivery confirmation code does not match. Ask the receiver for the code the customer shared with them.'];
            }
        } elseif ($signatureProvided) {
            $signaturePath = save_signature_png($signatureData);
            if (!$signaturePath) {
                return ['success' => false, 'message' => 'Could not save the signature — please try capturing it again.'];
            }
        }
    }

    $pdo->prepare("UPDATE shipments SET current_status = ? WHERE id = ?")->execute([$newStatus, $shipmentId]);
    $pdo->prepare("INSERT INTO tracking_status_history (shipment_id, status, note) VALUES (?, ?, ?)")->execute([$shipmentId, $newStatus, $note ?: null]);

    if ($newStatus === 'Delivered') {
        $confirmationMethod = $signaturePath ? 'signature' : 'otp';
        $pdo->prepare("UPDATE shipments SET delivery_otp = NULL, delivered_confirmed_at = NOW(), delivery_confirmation_method = ?, signature_path = ? WHERE id = ?")
            ->execute([$confirmationMethod, $signaturePath, $shipmentId]);

        // Escrow payout: credit the rider's wallet (minus platform commission)
        // and attempt to pay them out immediately. The UNIQUE key on
        // rider_earnings.shipment_id prevents double-crediting if this
        // somehow runs twice.
        if ($ship['rider_id'] && $ship['payment_status'] === 'paid') {
            $alreadyCredited = $pdo->prepare("SELECT id FROM rider_earnings WHERE shipment_id = ?");
            $alreadyCredited->execute([$shipmentId]);

            if (!$alreadyCredited->fetch()) {
                $commissionPercent = PLATFORM_COMMISSION_PERCENT;
                $platformCommission = round($ship['fee'] * ($commissionPercent / 100), 2);
                $riderEarning = round($ship['fee'] - $platformCommission, 2);
                $riderId = (int) $ship['rider_id'];

                $pdo->prepare("INSERT INTO rider_earnings (rider_id, shipment_id, shipment_fee, commission_percent, platform_commission, rider_earning, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')")
                    ->execute([$riderId, $shipmentId, $ship['fee'], $commissionPercent, $platformCommission, $riderEarning]);

                $pdo->prepare("UPDATE riders SET wallet_balance = wallet_balance + ?, total_earned = total_earned + ?, status = 'available' WHERE id = ?")
                    ->execute([$riderEarning, $riderEarning, $riderId]);

                $pdo->prepare("UPDATE shipments SET rider_payout_status = 'pending' WHERE id = ?")->execute([$shipmentId]);

                $payoutResult = attempt_rider_payout($pdo, $riderId);
                if ($payoutResult['success']) {
                    return ['success' => true, 'message' => 'Delivery confirmed and rider paid out automatically — ' . $payoutResult['message']];
                }
                return ['success' => true, 'message' => 'Delivery confirmed. Rider earning recorded; automatic payout will retry shortly (' . $payoutResult['message'] . ').'];
            }
        }
    }

    // Freed up: once a rider finishes (or the shipment is cancelled), they go
    // back to "available" so they immediately reappear in the claim pool.
    if (in_array($newStatus, ['Delivered', 'Cancelled'], true) && $ship['rider_id']) {
        $pdo->prepare("UPDATE riders SET status = 'available' WHERE id = ? AND status = 'on_delivery'")->execute([$ship['rider_id']]);
    }

    return ['success' => true, 'message' => 'Shipment updated to "' . $newStatus . '".'];
}

/**
 * Bolt-style atomic claim: the first rider to accept wins, even if two
 * riders tap Accept on the same order at nearly the same moment. Relies
 * on the WHERE rider_id IS NULL guard — only one UPDATE can ever match a
 * given row, so whoever's query lands first (per MySQL's row locking)
 * gets it; the loser's UPDATE affects 0 rows and we detect that via
 * rowCount().
 *
 * @return array{success:bool, message:string}
 */
function claim_order(PDO $pdo, int $shipmentId, int $riderId): array {
    $riderStmt = $pdo->prepare("SELECT * FROM riders WHERE id = ?");
    $riderStmt->execute([$riderId]);
    $rider = $riderStmt->fetch();

    if (!$rider) {
        return ['success' => false, 'message' => 'Rider not found.'];
    }
    if ($rider['status'] !== 'available') {
        return ['success' => false, 'message' => 'You need to be Available (not already on a delivery) to accept new orders.'];
    }

    $stmt = $pdo->prepare("
        UPDATE shipments
        SET rider_id = ?, current_status = 'Rider Assigned', accepted_at = NOW()
        WHERE id = ? AND rider_id IS NULL AND payment_status = 'paid' AND current_status = 'Order Placed'
    ");
    $stmt->execute([$riderId, $shipmentId]);

    if ($stmt->rowCount() === 0) {
        return ['success' => false, 'message' => 'Sorry — another rider already accepted this order.'];
    }

    $pdo->prepare("INSERT INTO tracking_status_history (shipment_id, status, note) VALUES (?, 'Rider Assigned', ?)")
        ->execute([$shipmentId, $rider['full_name'] . ' accepted this delivery.']);

    $pdo->prepare("UPDATE riders SET status = 'on_delivery' WHERE id = ?")->execute([$riderId]);

    return ['success' => true, 'message' => 'Order accepted! Head to the pickup address.'];
}

/**
 * Reject: removes the order from THIS rider's pool only. It stays fully
 * available for every other rider, exactly like Bolt.
 */
function reject_order(PDO $pdo, int $shipmentId, int $riderId): array {
    $pdo->prepare("INSERT IGNORE INTO order_rejections (shipment_id, rider_id) VALUES (?, ?)")->execute([$shipmentId, $riderId]);
    return ['success' => true, 'message' => 'Order skipped.'];
}
