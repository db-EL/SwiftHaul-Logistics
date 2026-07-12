<?php
// Paystack API helpers (server-side only — uses PAYSTACK_SECRET_KEY)
// Docs: https://paystack.com/docs/api/
/**
 * Low-level cURL wrapper for Paystack API calls.
 */
function paystack_request($method, $endpoint, $payload = null) {
    $ch = curl_init("https://api.paystack.co" . $endpoint);

    $headers = [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        "Content-Type: application/json",
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['ok' => false, 'message' => 'Network error contacting Paystack: ' . $curlError];
    }

    $data = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && ($data['status'] ?? false)) {
        return ['ok' => true, 'data' => $data['data'] ?? null, 'message' => $data['message'] ?? ''];
    }

    return ['ok' => false, 'message' => $data['message'] ?? 'Paystack request failed.', 'raw' => $data];
}

/**
 * Verify a transaction reference after the customer completes checkout.
 */
function paystack_verify_transaction($reference) {
    return paystack_request('GET', '/transaction/verify/' . rawurlencode($reference));
}

/**
 * Create (or reuse) a Paystack transfer recipient for a rider's bank account.
 * Required once per rider before the first payout.
 */
function paystack_create_transfer_recipient($accountName, $accountNumber, $bankCode) {
    return paystack_request('POST', '/transferrecipient', [
        'type' => 'nuban',
        'name' => $accountName,
        'account_number' => $accountNumber,
        'bank_code' => $bankCode,
        'currency' => 'NGN',
    ]);
}

/**
 * Initiate a payout transfer to a rider's recipient code.
 */
function paystack_initiate_transfer($recipientCode, $amountNaira, $reason) {
    return paystack_request('POST', '/transfer', [
        'source' => 'balance',
        'amount' => (int) round($amountNaira * 100), // Paystack expects kobo
        'recipient' => $recipientCode,
        'reason' => $reason,
    ]);
}

/**
 * Initiate a refund for a completed transaction via Paystack's Refund API.
 * Never called automatically — only ever triggered manually by an admin
 * reviewing a specific complaint (see admin/complaints.php), since
 * deciding whether a refund is warranted requires human judgment.
 */
function paystack_refund_transaction($reference, $amountNaira, $reason = '') {
    return paystack_request('POST', '/refund', [
        'transaction' => $reference,
        'amount' => (int) round($amountNaira * 100), // kobo
        'merchant_note' => $reason ?: 'Refund issued after customer complaint review.',
    ]);
}

/**
 * A short list of common Nigerian bank codes for the rider bank-details forms.
 * (Paystack also exposes a live /bank endpoint if you'd rather fetch dynamically.)
 */
function nigerian_banks_list() {
    return [
        '044' => 'Access Bank',
        '023' => 'Citibank Nigeria',
        '050' => 'Ecobank Nigeria',
        '070' => 'Fidelity Bank',
        '011' => 'First Bank of Nigeria',
        '214' => 'First City Monument Bank',
        '058' => 'Guaranty Trust Bank',
        '030' => 'Heritage Bank',
        '301' => 'Jaiz Bank',
        '082' => 'Keystone Bank',
        '076' => 'Polaris Bank',
        '221' => 'Stanbic IBTC Bank',
        '068' => 'Standard Chartered Bank',
        '232' => 'Sterling Bank',
        '032' => 'Union Bank of Nigeria',
        '033' => 'United Bank for Africa',
        '215' => 'Unity Bank',
        '035' => 'Wema Bank',
        '057' => 'Zenith Bank',
        '090267' => 'Kuda Bank',
        '100004' => 'Opay',
        '100033' => 'PalmPay',
    ];
}
