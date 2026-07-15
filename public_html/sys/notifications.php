<?php
// notifications.php — the admin notification bell.
require_once __DIR__ . '/db_connect.php';

function create_notification($conn, $type, $title, $message, $link = null, $inquiry_id = null) {
    $stmt = $conn->prepare("INSERT INTO notifications (type, title, message, link, inquiry_id) VALUES (?,?,?,?,?)");
    $stmt->bind_param('ssssi', $type, $title, $message, $link, $inquiry_id);
    $stmt->execute();
    $stmt->close();
}

function get_unread_count($conn) {
    $res = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE is_read = 0 AND is_dismissed = 0");
    return (int)$res->fetch_assoc()['c'];
}

function get_recent_notifications($conn, $limit = 8) {
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE is_dismissed = 0 ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function mark_notification_read($conn, $id) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

function mark_all_notifications_read($conn) {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
}

function dismiss_notification($conn, $id) {
    $stmt = $conn->prepare("UPDATE notifications SET is_dismissed = 1 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}
