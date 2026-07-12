<?php
// Payout Safety Net — retries any rider wallet balances that
// haven't been paid out yet (e.g. the automatic attempt made at
// delivery-confirmation time failed due to a network blip or a
// temporary Paystack issue).
//
// This is designed to run WITHOUT any admin present, either:
//   (a) as a real cron job (recommended), or
//   (b) hit periodically by an external uptime/cron service
//       (e.g. cron-job.org, EasyCron) pointed at this URL.
//
// It requires a secret key so random visitors can't trigger it.
//
// Command line (XAMPP / Linux cron / Windows Task Scheduler):
//   php /path/to/logistics/cron/retry_payouts.php change-this-to-a-long-random-string
//
// Or via HTTP (for an external cron/uptime service):
//   https://yourdomain.com/logistics/cron/retry_payouts.php?key=change-this-to-a-long-random-string
//
// Suggested schedule: every 15 minutes.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/payouts.php';

$isCli = (php_sapi_name() === 'cli');
$providedKey = $isCli ? ($argv[1] ?? '') : ($_GET['key'] ?? '');

if (!hash_equals(CRON_SECRET, (string) $providedKey)) {
    http_response_code(403);
    echo "Forbidden: invalid or missing key.\n";
    exit;
}

$results = retry_all_pending_payouts($pdo);

if (!$results) {
    echo "No pending rider balances. Nothing to do.\n";
    exit;
}

foreach ($results as $r) {
    $status = $r['result']['success'] ? 'OK' : 'FAILED';
    echo "[$status] {$r['rider']}: {$r['result']['message']}\n";
}
