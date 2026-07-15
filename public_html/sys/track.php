<?php
// track.php — records one page view per request on the public pages.
// Include AFTER db_connect.php + helpers.php. Never breaks the page:
// any tracking error is swallowed and logged.
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/helpers.php';

sl_session_start();

try {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($ua === '' || is_bot_ua($ua)) {
        return; // skip crawlers and empty agents
    }

    // Stable per-visitor id (not the PHP session id itself)
    if (empty($_SESSION['track_id'])) {
        $_SESSION['track_id'] = bin2hex(random_bytes(16));
    }

    $page    = substr(strtok($_SERVER['REQUEST_URI'] ?? '/', '?'), 0, 255);
    if (preg_match('/\.(ico|png|jpe?g|svg|webp|css|js|txt|xml|map)$/i', $page)) {
        return; // asset requests that fell through to PHP — not page views
    }
    $referer = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 255);
    if ($referer === '') $referer = null;
    $iph     = ip_hash($conn);
    $device  = device_type_from_ua($ua);

    $stmt = $conn->prepare("INSERT INTO page_views (session_id, page_url, referer, ip_hash, device_type) VALUES (?,?,?,?,?)");
    $stmt->bind_param('sssss', $_SESSION['track_id'], $page, $referer, $iph, $device);
    $stmt->execute();
    $stmt->close();

    // Housekeeping: ~1 in 100 requests, drop analytics + rate-limit rows
    // older than 180 days so the tables never grow unbounded.
    if (random_int(1, 100) === 1) {
        $conn->query("DELETE FROM page_views WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)");
        $conn->query("DELETE FROM rate_limit WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
    }
} catch (Throwable $e) {
    error_log('track.php: ' . $e->getMessage());
}
