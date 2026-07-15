<?php
// helpers.php — small shared utilities used by the public site AND the admin.
// Requires db_connect.php to have been included first (for $conn / settings).

// Start a hardened session (used on public pages for CSRF + view tracking,
// and by admin/auth.php). cookie_secure only over HTTPS so localhost works.
function sl_session_start() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', !empty($_SERVER['HTTPS']) ? 1 : 0);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// HTML-escape shortcut — use for EVERY dynamic value echoed into HTML.
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Editable text block (already HTML-escaped). $fallback shows if the key
// is missing from the database.
function t($key, $fallback = '') {
    return h($GLOBALS['site_content'][$key] ?? $fallback);
}

// Setting value (raw — escape with h() when echoing into HTML).
function setting($key, $default = '') {
    $v = $GLOBALS['site_settings'][$key] ?? '';
    return ($v === '' || $v === null) ? $default : $v;
}

// CSRF helpers (shared by the public contact form and all admin forms)
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . h(generateCsrfToken()) . '">';
}

function validateCsrfToken() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Privacy-friendly visitor identifier: sha256(ip + secret salt). The salt is
// generated once on first use and stored in the settings table, so raw IPs
// are never written to the database.
function ip_hash($conn) {
    $salt = setting('ip_salt');
    if ($salt === '') {
        $salt = bin2hex(random_bytes(16));
        // Upsert: works even if the seeded ip_salt row is missing; keeps any
        // existing non-empty salt (first writer wins).
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('ip_salt', ?)
            ON DUPLICATE KEY UPDATE setting_value = IF(setting_value = '' OR setting_value IS NULL, VALUES(setting_value), setting_value)");
        $stmt->bind_param('s', $salt);
        $stmt->execute();
        $stmt->close();
        // Another request may have won the race — re-read the stored value.
        $res = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'ip_salt'");
        $row = $res->fetch_assoc();
        $salt = $row['setting_value'] ?? $salt;
        $GLOBALS['site_settings']['ip_salt'] = $salt;
    }
    return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . $salt);
}

// wa.me link with an optional prefilled message
function wa_link($message = '') {
    $num = preg_replace('/\D/', '', setting('business_whatsapp', '94777488746'));
    $url = 'https://wa.me/' . $num;
    if ($message !== '') $url .= '?text=' . rawurlencode($message);
    return $url;
}

// "5m ago" style timestamps for the admin UI
function time_ago($datetime) {
    $ts = strtotime($datetime);
    if (!$ts) return '';
    $diff = time() - $ts;
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('Y-m-d', $ts);
}

// Rough device bucket from the user agent (for analytics only)
function device_type_from_ua($ua) {
    $ua = strtolower($ua);
    if (strpos($ua, 'ipad') !== false || strpos($ua, 'tablet') !== false) return 'tablet';
    if (strpos($ua, 'mobi') !== false || strpos($ua, 'android') !== false) return 'mobile';
    return 'desktop';
}

// True for known crawlers — used to skip analytics rows
function is_bot_ua($ua) {
    return (bool)preg_match('/bot|crawl|spider|slurp|preview|curl|wget|monitor|lighthouse|headless/i', $ua);
}
