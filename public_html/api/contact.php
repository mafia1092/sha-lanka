<?php
// api/contact.php — receives the contact form (fetch/AJAX), saves the inquiry,
// notifies the admin bell and emails the business. Always answers JSON.
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../sys/db_connect.php';
require_once __DIR__ . '/../sys/helpers.php';
require_once __DIR__ . '/../sys/notifications.php';
require_once __DIR__ . '/../sys/mailer.php';

sl_session_start();

function jout($code, $data) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    jout(405, ['ok' => false, 'error' => 'method']);
}

try {
    // 1. Honeypot — bots fill every field; humans never see this one.
    //    Pretend success so the bot moves on, store nothing.
    if (trim($_POST['website_url'] ?? '') !== '') {
        jout(200, ['ok' => true]);
    }

    // 2. CSRF (token is embedded in the form by index.php)
    if (!validateCsrfToken()) {
        jout(400, ['ok' => false, 'error' => 'expired']);
    }

    // 3. Rate limit — max 3 accepted attempts per visitor per 15 minutes
    $iph  = ip_hash($conn);
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM rate_limit WHERE kind = 'contact' AND ip_hash = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->bind_param('s', $iph);
    $stmt->execute();
    $tries = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    if ($tries >= 3) {
        jout(429, ['ok' => false, 'error' => 'rate']);
    }
    $stmt = $conn->prepare("INSERT INTO rate_limit (kind, ip_hash) VALUES ('contact', ?)");
    $stmt->bind_param('s', $iph);
    $stmt->execute();
    $stmt->close();

    // 4. Validate input
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $choice  = trim($_POST['choice'] ?? '');
    $message = trim($_POST['message'] ?? '');

    $allowed_choices = ['', 'Motorcycle Rental', 'Jeep Rental', 'Motorhome Rental',
        'Motorcycle Tour', 'Jeep Expedition', 'Motorhome Journey',
        'Car Carrier / Transport', 'Something else'];

    if ($name === '' || mb_strlen($name) > 120) {
        jout(400, ['ok' => false, 'error' => 'name']);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        jout(400, ['ok' => false, 'error' => 'email']);
    }
    if ($message === '' || mb_strlen($message) > 5000) {
        jout(400, ['ok' => false, 'error' => 'message']);
    }
    if (mb_strlen($phone) > 40) $phone = mb_substr($phone, 0, 40);
    if (!in_array($choice, $allowed_choices, true)) $choice = 'Something else';

    // 5. Save the inquiry (this is the part that must never fail silently)
    $stmt = $conn->prepare("INSERT INTO inquiries (name, email, phone, service_choice, message, ip_hash) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('ssssss', $name, $email, $phone, $choice, $message, $iph);
    $stmt->execute();
    $inquiry_id = $stmt->insert_id;
    $stmt->close();

    // 6. Notify the admin bell
    $ntitle = 'New inquiry' . ($choice !== '' ? ': ' . $choice : '');
    create_notification($conn, 'inquiry', $ntitle, $name . ' (' . $email . ')',
        'inquiries.php?highlight=' . $inquiry_id, $inquiry_id);

    // 7. Email the business (failure is logged, not shown — the inquiry is
    //    already saved and visible in the admin inbox either way)
    if (setting('notify_email_on_inquiry', '1') === '1') {
        $rows = '';
        $fields = ['Name' => $name, 'Email' => $email, 'Phone' => $phone,
                   'Interested in' => $choice, 'Message' => nl2br(h($message))];
        foreach ($fields as $label => $value) {
            if ($label !== 'Message') $value = h($value);
            if (trim(strip_tags((string)$value)) === '') continue;
            $rows .= '<tr><td style="padding:6px 12px 6px 0;color:#888;white-space:nowrap;vertical-align:top;">' . $label . '</td>'
                   . '<td style="padding:6px 0;color:#1C1A17;">' . $value . '</td></tr>';
        }
        $body = '<p style="margin:0 0 14px;color:#444;">A new inquiry just arrived from the Sha Lanka Travels website:</p>'
              . '<table cellpadding="0" cellspacing="0" style="font-size:14px;">' . $rows . '</table>'
              . '<p style="margin:18px 0 0;font-size:13px;color:#888;">Reply directly to this email to answer '
              . h($name) . ', or manage it in the admin inbox.</p>';
        send_email($conn, setting('business_email'), 'Sha Lanka Travels',
            'New inquiry from ' . $name . ($choice !== '' ? ' — ' . $choice : ''),
            email_wrap('New Website Inquiry #' . $inquiry_id, $body),
            $email, $name);
    }

    jout(200, ['ok' => true, 'id' => $inquiry_id]);
} catch (Throwable $e) {
    error_log('api/contact.php: ' . $e->getMessage());
    jout(500, ['ok' => false, 'error' => 'server']);
}
