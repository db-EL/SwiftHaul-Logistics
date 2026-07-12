<?php
// Outbound email — sends via Gmail SMTP (PHPMailer) so every email
// genuinely originates from a monitored mailbox (SMTP_FROM_EMAIL in
// config.php), rather than an unauthenticated PHP mail() call that
// most mail providers will spam-filter or reject outright.
//
// Requires `composer install` (see composer.json) and a Gmail App
// Password set in config.php. If either isn't set up yet, this falls
// back to PHP's mail() so the rest of the app doesn't break — but
// that fallback is unreliable for real delivery and mainly useful for
// local testing (see APP_ENV's dev-only reset-link display).
function send_email(string $to, string $subject, string $htmlBody): bool {
    if (COMPOSER_DEPENDENCIES_LOADED && class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        return send_email_via_smtp($to, $subject, $htmlBody);
    }
    return send_email_via_php_mail($to, $subject, $htmlBody);
}

function send_email_via_smtp(string $to, string $subject, string $htmlBody): bool {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

        $mail->send();
        return true;
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('[mailer] SMTP send failed: ' . $mail->ErrorInfo);
        return false;
    }
}

function send_email_via_php_mail(string $to, string $subject, string $htmlBody): bool {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SITE_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";

    // @ suppresses PHP's own warning noise when no MTA is configured;
    // we handle the failure explicitly via the return value instead.
    return @mail($to, $subject, $htmlBody, $headers);
}
