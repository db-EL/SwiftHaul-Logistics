<?php
// Shared rider payout logic.
// Called automatically the instant a paid shipment is marked
// Delivered, AND by the cron retry script as a safety net if
// that automatic attempt failed (network blip, Paystack transfer
// temporarily disabled, etc). Never depends on an admin being
// online to click a button.
require_once __DIR__ . '/paystack.php';

/**
 * Attempt to pay out a rider's full current wallet balance.
 * Safe to call repeatedly — if there's nothing to pay, it's a no-op.
 *
 * @return array{success:bool, message:string, amount?:float}
 */
function attempt_rider_payout(PDO $pdo, int $riderId): array {
    $stmt = $pdo->prepare("SELECT * FROM riders WHERE id = ? FOR UPDATE");
    $pdo->beginTransaction();
    $stmt->execute([$riderId]);
    $rider = $stmt->fetch();

    if (!$rider) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Rider not found.'];
    }

    $amount = (float) $rider['wallet_balance'];

    if ($amount <= 0) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'No pending balance.'];
    }

    if (!$rider['account_number'] || !$rider['bank_code'] || !$rider['account_name']) {
        $pdo->rollBack();
        log_payout_attempt($pdo, $riderId, $amount, false, 'Missing bank details on file.');
        return ['success' => false, 'message' => 'Missing bank details for ' . $rider['full_name'] . '.'];
    }

    $recipientCode = $rider['paystack_recipient_code'];

    if (!$recipientCode) {
        $recipientResult = paystack_create_transfer_recipient($rider['account_name'], $rider['account_number'], $rider['bank_code']);
        if (!$recipientResult['ok']) {
            $pdo->rollBack();
            log_payout_attempt($pdo, $riderId, $amount, false, 'Recipient setup failed: ' . $recipientResult['message']);
            return ['success' => false, 'message' => 'Could not register ' . $rider['full_name'] . ' with Paystack: ' . $recipientResult['message']];
        }
        $recipientCode = $recipientResult['data']['recipient_code'];
        $pdo->prepare("UPDATE riders SET paystack_recipient_code = ? WHERE id = ?")->execute([$recipientCode, $riderId]);
    }

    $transferResult = paystack_initiate_transfer($recipientCode, $amount, 'SwiftHaul rider payout — ' . $rider['full_name']);

    if (!$transferResult['ok']) {
        $pdo->rollBack();
        log_payout_attempt($pdo, $riderId, $amount, false, $transferResult['message']);
        return ['success' => false, 'message' => 'Payout failed for ' . $rider['full_name'] . ': ' . $transferResult['message']];
    }

    $transferCode = $transferResult['data']['transfer_code'] ?? null;

    $pdo->prepare("UPDATE riders SET wallet_balance = 0 WHERE id = ?")->execute([$riderId]);
    $pdo->prepare("UPDATE rider_earnings SET status = 'paid', paystack_transfer_code = ?, paid_at = NOW() WHERE rider_id = ? AND status = 'pending'")
        ->execute([$transferCode, $riderId]);
    $pdo->prepare("UPDATE shipments SET rider_payout_status = 'paid' WHERE rider_id = ? AND rider_payout_status = 'pending'")->execute([$riderId]);

    log_payout_attempt($pdo, $riderId, $amount, true, 'Transfer code: ' . $transferCode);
    $pdo->commit();

    return ['success' => true, 'message' => 'Paid out ₦' . number_format($amount, 0) . ' to ' . $rider['full_name'] . '.', 'amount' => $amount];
}

/**
 * Best-effort audit log of every payout attempt (success or failure) so
 * failures are visible to an admin later even if nobody was watching live.
 */
function log_payout_attempt(PDO $pdo, int $riderId, float $amount, bool $success, string $note): void {
    $pdo->prepare("INSERT INTO payout_attempts (rider_id, amount, success, note) VALUES (?, ?, ?, ?)")
        ->execute([$riderId, $amount, $success ? 1 : 0, $note]);
}

/**
 * Run a payout attempt for every rider currently holding a balance.
 * Used by the cron retry script.
 *
 * @return array List of result rows for reporting.
 */
function retry_all_pending_payouts(PDO $pdo): array {
    $riders = $pdo->query("SELECT id, full_name FROM riders WHERE wallet_balance > 0")->fetchAll();
    $results = [];
    foreach ($riders as $r) {
        $result = attempt_rider_payout($pdo, (int) $r['id']);
        $results[] = ['rider' => $r['full_name'], 'result' => $result];
    }
    return $results;
}
