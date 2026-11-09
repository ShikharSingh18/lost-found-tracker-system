<?php
/*
 * Module 6: unread-notification count for the bell, as JSON.
 * Called every 30 seconds by assets/js/notifications.js.
 * Returns {"unread": 3, "latest": "message text"} for the logged-in user only.
 * Not logged in -> HTTP 401 {"unread": null} (the script then stops asking).
 */
define('LFT_BACKGROUND_REQUEST', true);   // DEV-03: polling doesn't keep the session alive
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user = current_user();
session_write_close();   // read-only from here: don't hold the session lock

if ($user === null) {
    http_response_code(401);
    echo json_encode(['unread' => null]);
    exit;
}

$stmt = db()->prepare(
    'SELECT COUNT(*) AS unread,
            (SELECT message FROM notifications WHERE user_id = :me2 AND is_read = FALSE
             ORDER BY created_at DESC, notification_id DESC LIMIT 1) AS latest
     FROM notifications WHERE user_id = :me AND is_read = FALSE'
);
$stmt->execute([':me' => $user['user_id'], ':me2' => $user['user_id']]);
$row = $stmt->fetch();

echo json_encode(['unread' => (int) $row['unread'], 'latest' => $row['latest']]);
