<?php
// admin/settings.php — business details, email (SMTP) settings and the
// admin's own password. Three separate forms, each saved on its own.
require_once __DIR__ . '/../sys/auth.php';
require_once __DIR__ . '/../sys/mailer.php';

$page_title = 'Settings';
$msg = ''; $err = '';

// Insert a setting or update it if the key already exists.
function save_setting($conn, $key, $val) {
    $stmt = $conn->prepare(
        "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    $stmt->bind_param('ss', $key, $val);
    $stmt->execute();
    $stmt->close();
}

// Short redirect codes -> [banner text, colour]. Anything not in this list
// is ignored, so nothing from the URL is ever echoed raw.
$codes = [
    'saved_business' => ['Business details saved.', 'green'],
    'saved_smtp'     => ['Email settings saved.', 'green'],
    'saved_password' => ['Password changed.', 'green'],
    'test_ok'        => ['Test email sent — check the business email inbox (and spam folder).', 'green'],
    'test_fail'      => ['Sending failed — check the SMTP values and the error log.', 'red'],
    'test_no_email'  => ['Set a business email in the first card before sending a test.', 'red'],
    'bad_email'      => ['Business email is not a valid email address — nothing was saved.', 'red'],
    'bad_from'       => ['The From address is not a valid email address — nothing was saved.', 'red'],
    'pw_wrong'       => ['Current password is incorrect.', 'red'],
    'pw_short'       => ['New password must be at least 10 characters.', 'red'],
    'pw_mismatch'    => ['New password and confirmation do not match.', 'red'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $err = 'Session expired — please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        // Tiny helper: finish a POST by redirecting back with a result code
        // (Post/Redirect/Get — stops the browser re-submitting on refresh).
        $done = function ($code) { header('Location: settings.php?msg=' . $code); exit; };

        if ($action === 'business') {
            $email = trim($_POST['business_email'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $done('bad_email');
            save_setting($conn, 'business_email', $email);
            save_setting($conn, 'business_phone', trim($_POST['business_phone'] ?? ''));
            // WhatsApp is stored digits-only (e.g. 94777123456) for wa.me links
            save_setting($conn, 'business_whatsapp', preg_replace('/\D/', '', $_POST['business_whatsapp'] ?? ''));
            save_setting($conn, 'business_address', trim($_POST['business_address'] ?? ''));
            save_setting($conn, 'notify_email_on_inquiry', isset($_POST['notify_email_on_inquiry']) ? '1' : '0');
            $done('saved_business');
        }

        if ($action === 'smtp') {
            $from = trim($_POST['smtp_from_email'] ?? '');
            if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) $done('bad_from');
            $port = (int)($_POST['smtp_port'] ?? 587);
            if ($port <= 0) $port = 587;
            save_setting($conn, 'smtp_host', trim($_POST['smtp_host'] ?? ''));
            save_setting($conn, 'smtp_port', (string)$port);
            save_setting($conn, 'smtp_username', trim($_POST['smtp_username'] ?? ''));
            save_setting($conn, 'smtp_from_email', $from);
            save_setting($conn, 'smtp_from_name', trim($_POST['smtp_from_name'] ?? ''));
            // Password: only overwrite when a new one was typed. Leaving the
            // field blank keeps the saved password (it is never shown back).
            $pw = (string)($_POST['smtp_password'] ?? '');
            if ($pw !== '') save_setting($conn, 'smtp_password', $pw);
            $done('saved_smtp');
        }

        if ($action === 'test_email') {
            $to = getSettings($conn)['business_email'] ?? '';
            if ($to === '') $done('test_no_email');
            $ok = send_email(
                $conn, $to, 'Sha Lanka Travels',
                'Test email from your website admin',
                email_wrap('Test successful', '<p>Your SMTP settings work.</p>')
            );
            $done($ok ? 'test_ok' : 'test_fail');
        }

        if ($action === 'password') {
            $current = (string)($_POST['current_password'] ?? '');
            $new     = (string)($_POST['new_password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');
            if (strlen($new) < 10)  $done('pw_short');
            if ($new !== $confirm)  $done('pw_mismatch');
            // Check the current password against the logged-in admin's row
            $stmt = $conn->prepare("SELECT password_hash FROM admin_users WHERE id = ? AND is_active = 1");
            $stmt->bind_param('i', $_SESSION['admin_id']);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row || !password_verify($current, $row['password_hash'])) $done('pw_wrong');
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?");
            $stmt->bind_param('si', $hash, $_SESSION['admin_id']);
            $stmt->execute();
            $stmt->close();
            $done('saved_password');
        }
    }
}

// Banner from the redirect code (only known codes are shown)
if (isset($_GET['msg'], $codes[$_GET['msg']])) {
    [$text, $colour] = $codes[$_GET['msg']];
    if ($colour === 'green') $msg = $text; else $err = $text;
}

// Fresh settings for the form values (after any save above)
$s = getSettings($conn);

// Shared Tailwind classes so every input looks the same
$inp = 'w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40';
$lbl = 'block text-sm font-medium mb-1';

include __DIR__ . '/inc/header.php';
?>

<?php if ($msg): ?>
  <div class="bg-green-100 border border-green-300 text-green-800 text-sm rounded px-4 py-3 mb-6"><?= h($msg) ?></div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="bg-red-100 border border-red-300 text-red-800 text-sm rounded px-4 py-3 mb-6"><?= h($err) ?></div>
<?php endif; ?>

<div class="max-w-2xl space-y-6">

  <!-- 1. Business details -->
  <div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold mb-1">Business details</h2>
    <p class="text-sm text-gray-500 mb-4">These appear in the site footer/contact section and control where inquiry emails go.</p>
    <form method="post" class="space-y-4">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="business">
      <div>
        <label class="<?= $lbl ?>" for="business_phone">Phone (display format)</label>
        <input class="<?= $inp ?>" type="text" id="business_phone" name="business_phone"
               value="<?= h($s['business_phone'] ?? '') ?>" placeholder="+94 77 123 4567">
      </div>
      <div>
        <label class="<?= $lbl ?>" for="business_whatsapp">WhatsApp number</label>
        <input class="<?= $inp ?>" type="text" id="business_whatsapp" name="business_whatsapp"
               value="<?= h($s['business_whatsapp'] ?? '') ?>" placeholder="94771234567">
        <p class="text-xs text-gray-400 mt-1">Country code + number. Anything that isn't a digit is removed on save.</p>
      </div>
      <div>
        <label class="<?= $lbl ?>" for="business_email">Business email</label>
        <input class="<?= $inp ?>" type="email" id="business_email" name="business_email"
               value="<?= h($s['business_email'] ?? '') ?>" required>
      </div>
      <div>
        <label class="<?= $lbl ?>" for="business_address">Address</label>
        <textarea class="<?= $inp ?>" id="business_address" name="business_address" rows="2"><?= h($s['business_address'] ?? '') ?></textarea>
      </div>
      <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="notify_email_on_inquiry" value="1" class="rounded border-gray-300"
               <?= ($s['notify_email_on_inquiry'] ?? '') === '1' ? 'checked' : '' ?>>
        Email me at the business address when a new inquiry arrives
      </label>
      <button type="submit" class="bg-brand text-white text-sm px-3 py-1.5 rounded hover:bg-brandglow">Save business details</button>
    </form>
  </div>

  <!-- 2. Email sending (SMTP via Brevo) -->
  <div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold mb-1">Email sending (Brevo)</h2>
    <p class="text-sm text-gray-500 mb-4">Get these from Brevo &rarr; SMTP &amp; API. Until your own domain is verified in Brevo, use a From address on a domain you already verified.</p>
    <form method="post" class="space-y-4">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="smtp">
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="<?= $lbl ?>" for="smtp_host">SMTP host</label>
          <input class="<?= $inp ?>" type="text" id="smtp_host" name="smtp_host"
                 value="<?= h($s['smtp_host'] ?? '') ?>" placeholder="smtp-relay.brevo.com">
        </div>
        <div>
          <label class="<?= $lbl ?>" for="smtp_port">SMTP port</label>
          <input class="<?= $inp ?>" type="number" id="smtp_port" name="smtp_port"
                 value="<?= h($s['smtp_port'] ?? '587') ?>" min="1" max="65535">
          <p class="text-xs text-gray-400 mt-1">587 recommended; 465 also works.</p>
        </div>
      </div>
      <div>
        <label class="<?= $lbl ?>" for="smtp_username">SMTP username (login)</label>
        <input class="<?= $inp ?>" type="text" id="smtp_username" name="smtp_username"
               value="<?= h($s['smtp_username'] ?? '') ?>" autocomplete="off">
      </div>
      <div>
        <label class="<?= $lbl ?>" for="smtp_password">SMTP password (key)</label>
        <!-- The saved password is never printed here — leave blank to keep it -->
        <input class="<?= $inp ?>" type="password" id="smtp_password" name="smtp_password" value=""
               placeholder="<?= !empty($s['smtp_password']) ? '•••••• (saved)' : 'Paste your Brevo SMTP key' ?>"
               autocomplete="new-password">
        <p class="text-xs text-gray-400 mt-1">Leave blank to keep the current password.</p>
      </div>
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="<?= $lbl ?>" for="smtp_from_email">From email</label>
          <input class="<?= $inp ?>" type="email" id="smtp_from_email" name="smtp_from_email"
                 value="<?= h($s['smtp_from_email'] ?? '') ?>">
          <p class="text-xs text-gray-400 mt-1">Must be a Brevo-verified sender.</p>
        </div>
        <div>
          <label class="<?= $lbl ?>" for="smtp_from_name">From name</label>
          <input class="<?= $inp ?>" type="text" id="smtp_from_name" name="smtp_from_name"
                 value="<?= h($s['smtp_from_name'] ?? '') ?>" placeholder="Sha Lanka Travels">
        </div>
      </div>
      <button type="submit" class="bg-brand text-white text-sm px-3 py-1.5 rounded hover:bg-brandglow">Save email settings</button>
    </form>

    <!-- Separate small form: send a test email to the business address -->
    <form method="post" class="mt-4 pt-4 border-t border-gray-100 flex items-center gap-3">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="test_email">
      <button type="submit" class="bg-white border border-brand text-brand text-sm px-3 py-1.5 rounded hover:bg-brand hover:text-white">Send test email</button>
      <span class="text-xs text-gray-400">Sends a test to the business email using the saved settings.</span>
    </form>
  </div>

  <!-- 3. Change my password -->
  <div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold mb-4">Change my password</h2>
    <form method="post" class="space-y-4">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="password">
      <div>
        <label class="<?= $lbl ?>" for="current_password">Current password</label>
        <input class="<?= $inp ?>" type="password" id="current_password" name="current_password"
               required autocomplete="current-password">
      </div>
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="<?= $lbl ?>" for="new_password">New password (min 10 characters)</label>
          <input class="<?= $inp ?>" type="password" id="new_password" name="new_password"
                 required minlength="10" autocomplete="new-password">
        </div>
        <div>
          <label class="<?= $lbl ?>" for="confirm_password">Confirm new password</label>
          <input class="<?= $inp ?>" type="password" id="confirm_password" name="confirm_password"
                 required minlength="10" autocomplete="new-password">
        </div>
      </div>
      <button type="submit" class="bg-brand text-white text-sm px-3 py-1.5 rounded hover:bg-brandglow">Change password</button>
    </form>
  </div>

</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
