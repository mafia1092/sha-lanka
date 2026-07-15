<?php
// auth.php — include at the top of every ADMIN page (after db_connect +
// helpers). Enforces login with a 30-minute idle timeout.
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/helpers.php';

sl_session_start();

// Session timeout — 30 minutes of inactivity
$session_timeout = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $session_timeout) {
    session_unset();
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

// Not logged in -> login page
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
