<?php
// Shared password reset logic for both account types (customers
// via `users`, riders via `riders`). Keeps forgot/reset password
// pages thin and consistent between the two.

function create_password_reset_token(PDO $pdo, string $accountType, int $accountId): string {
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 1800); // 30 minutes
    $pdo->prepare("INSERT INTO password_resets (account_type, account_id, token, expires_at) VALUES (?, ?, ?, ?)")
        ->execute([$accountType, $accountId, $token, $expiresAt]);
    return $token;
}

/**
 * @return array|null The password_resets row if the token is valid and unused, else null.
 */
function find_valid_reset_token(PDO $pdo, string $token, string $accountType) {
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND account_type = ?");
    $stmt->execute([$token, $accountType]);
    $row = $stmt->fetch();

    if (!$row || $row['used_at'] || strtotime($row['expires_at']) <= time()) {
        return null;
    }
    return $row;
}

function mark_reset_token_used(PDO $pdo, int $resetId, string $accountType, int $accountId): void {
    $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?")->execute([$resetId]);
    // Invalidate any other outstanding tokens for this account.
    $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE account_type = ? AND account_id = ? AND used_at IS NULL")
        ->execute([$accountType, $accountId]);
}
