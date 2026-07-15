<?php
// db_connect.php — opens the MySQL connection ($conn) and loads settings +
// site content into memory. Include this once at the top of every PHP page.
if (!isset($GLOBALS['__db_loaded'])):
$GLOBALS['__db_loaded'] = true;

// DB credentials live OUTSIDE the webroot in private/db.ini (never in git).
// Locally:   <repo>/private/db.ini
// Hostinger: /home/uXXXX/domains/<domain>/private/db.ini (sibling of public_html)
$__db_ini_candidates = [
    __DIR__ . '/../../private/db.ini',
];
$__db_cfg = null;
foreach ($__db_ini_candidates as $__p) {
    if (is_readable($__p)) {
        $__parsed = @parse_ini_file($__p, true);
        if (is_array($__parsed) && isset($__parsed['database'])) {
            $__db_cfg = $__parsed['database'];
            break;
        }
    }
}

if (!$__db_cfg) {
    error_log('db_connect: private/db.ini not found or unreadable');
    http_response_code(503);
    die('Service temporarily unavailable. Please try again later.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli(
        $__db_cfg['host']     ?? 'localhost',
        $__db_cfg['username'] ?? '',
        $__db_cfg['password'] ?? '',
        $__db_cfg['dbname']   ?? ''
    );
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(503);
    die('Service temporarily unavailable. Please try again later.');
}
unset($__db_ini_candidates, $__db_cfg, $__parsed, $__p);

// All settings as [key => value]
function getSettings($conn) {
    $out = [];
    $res = $conn->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $res->fetch_assoc()) {
        $out[$row['setting_key']] = $row['setting_value'];
    }
    return $out;
}

// All editable text blocks as [key => value]
function getSiteContent($conn) {
    $out = [];
    $res = $conn->query("SELECT content_key, content_value FROM site_content");
    while ($row = $res->fetch_assoc()) {
        $out[$row['content_key']] = $row['content_value'];
    }
    return $out;
}

$site_settings = getSettings($conn);
$site_content  = getSiteContent($conn);

endif;
