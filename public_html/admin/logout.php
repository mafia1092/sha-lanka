<?php
// logout.php — ends the admin session and returns to the login page.
require_once __DIR__ . '/../sys/db_connect.php';
require_once __DIR__ . '/../sys/helpers.php';

sl_session_start();

// Clear all session data
session_unset();

// Expire the session cookie in the browser too (not just server-side)
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

header('Location: login.php');
exit;
