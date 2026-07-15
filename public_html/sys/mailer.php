<?php
// mailer.php — one function to send email through Brevo SMTP using the
// credentials stored in the settings table (Admin -> Settings).
require_once __DIR__ . '/db_connect.php';

// 465 = SMTPS, 587 = STARTTLS. Hardcoding the wrong one makes PHPMailer
// hang for 30+ seconds, so we pick automatically from the port.
function smtp_secure_for_port($port) {
    return ((int)$port === 465)
        ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
        : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
}

/**
 * Send an HTML email. Returns true on success, false on failure (failures
 * are logged with error_log, never shown to visitors).
 */
function send_email($conn, $to_email, $to_name, $subject, $html_body, $reply_to_email = '', $reply_to_name = '') {
    $s = getSettings($conn);
    if (empty($s['smtp_username']) || empty($s['smtp_password']) || empty($s['smtp_from_email'])) {
        error_log('send_email skipped: SMTP settings incomplete (fill them in Admin -> Settings)');
        return false;
    }

    $base = dirname(__DIR__) . '/mail/PHPMailer/src/';
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        require_once $base . 'Exception.php';
        require_once $base . 'PHPMailer.php';
        require_once $base . 'SMTP.php';
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->CharSet    = 'UTF-8'; // default ISO-8859-1 mangles accents/emoji
        $mail->Host       = $s['smtp_host'] ?? 'smtp-relay.brevo.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $s['smtp_username'];
        $mail->Password   = $s['smtp_password'];
        $mail->Port       = intval($s['smtp_port'] ?? 587);
        $mail->SMTPSecure = smtp_secure_for_port($mail->Port);
        $mail->Timeout    = 10; // never hang a visitor's request on SMTP

        $mail->setFrom($s['smtp_from_email'], $s['smtp_from_name'] ?? 'Sha Lanka Travels');
        $mail->addAddress($to_email, $to_name);
        if ($reply_to_email !== '') {
            $mail->addReplyTo($reply_to_email, $reply_to_name);
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html_body));

        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('send_email failed: ' . $e->getMessage());
        return false;
    }
}

// Simple branded wrapper for email bodies
function email_wrap($title, $body_html) {
    $year = date('Y');
    return '<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:20px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;">
  <tr><td style="background:#1577BE;padding:22px 30px;text-align:center;">
    <h1 style="color:#ffffff;margin:0;font-size:22px;">Sha Lanka Travels</h1>
  </td></tr>
  <tr><td style="padding:30px;">
    <h2 style="color:#1C1A17;margin:0 0 18px;font-size:19px;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>
    ' . $body_html . '
  </td></tr>
  <tr><td style="background:#f8f9fa;padding:14px 30px;text-align:center;font-size:12px;color:#888;">
    &copy; ' . $year . ' Sha Lanka Travels &bull; automated message
  </td></tr>
</table>
</td></tr></table>
</body></html>';
}
