<?php
// ============================================================
// Simple DB-backed rate limiter.
// Keyed by an arbitrary string, typically "purpose:ip" or
// "purpose:ip:identifier". Not distributed-system-grade, but a
// real improvement over no throttling at all for a single-server
// deployment.
// ============================================================

function client_ip(): string {
    // Behind a reverse proxy (nginx, Cloudflare, etc.) you may need to
    // trust X-Forwarded-For instead — only do that if you control the proxy,
    // since this header is otherwise trivially spoofable by the client.
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Check whether an action identified by $key is currently allowed.
 * Does NOT record an attempt — call record_attempt() after checking.
 *
 * @return bool true if allowed, false if currently blocked
 */
function rate_limit_check(PDO $pdo, string $key): bool {
    $stmt = $pdo->prepare("SELECT * FROM rate_limits WHERE rl_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();

    if (!$row) {
        return true;
    }

    if ($row['blocked_until'] && strtotime($row['blocked_until']) > time()) {
        return false;
    }

    return true;
}

/**
 * Record a failed attempt for $key. Once $maxAttempts is reached within
 * $windowSeconds, the key is blocked for $blockSeconds.
 */
function rate_limit_record_failure(PDO $pdo, string $key, int $maxAttempts = 5, int $windowSeconds = 300, int $blockSeconds = 900): void {
    $stmt = $pdo->prepare("SELECT * FROM rate_limits WHERE rl_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();

    if (!$row) {
        $pdo->prepare("INSERT INTO rate_limits (rl_key, attempts, first_attempt_at) VALUES (?, 1, NOW())")->execute([$key]);
        return;
    }

    $windowExpired = strtotime($row['first_attempt_at']) < (time() - $windowSeconds);

    if ($windowExpired) {
        $pdo->prepare("UPDATE rate_limits SET attempts = 1, first_attempt_at = NOW(), blocked_until = NULL WHERE rl_key = ?")->execute([$key]);
        return;
    }

    $newAttempts = $row['attempts'] + 1;

    if ($newAttempts >= $maxAttempts) {
        $blockedUntil = date('Y-m-d H:i:s', time() + $blockSeconds);
        $pdo->prepare("UPDATE rate_limits SET attempts = ?, blocked_until = ? WHERE rl_key = ?")->execute([$newAttempts, $blockedUntil, $key]);
    } else {
        $pdo->prepare("UPDATE rate_limits SET attempts = ? WHERE rl_key = ?")->execute([$newAttempts, $key]);
    }
}

/**
 * Clear the throttle for $key (call this on a successful login, for example).
 */
function rate_limit_reset(PDO $pdo, string $key): void {
    $pdo->prepare("DELETE FROM rate_limits WHERE rl_key = ?")->execute([$key]);
}

/**
 * How many seconds until $key is unblocked. 0 if not currently blocked.
 */
function rate_limit_seconds_remaining(PDO $pdo, string $key): int {
    $stmt = $pdo->prepare("SELECT blocked_until FROM rate_limits WHERE rl_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    if (!$row || !$row['blocked_until']) return 0;
    $remaining = strtotime($row['blocked_until']) - time();
    return max(0, $remaining);
}
