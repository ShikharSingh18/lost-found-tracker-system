<?php
/*
 * Recently found items for the home page spotlight (JSON). Staff and Admin only.
 * Shows what was found, where and when it was logged. It never shows who logged it.
 * Polled once a minute in the background, so it must not count as user activity
 * (otherwise the 30-minute idle logout would never happen while the home page is open).
 */
define('LFT_BACKGROUND_REQUEST', true);
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$user = current_user();
if ($user === null) {
    http_response_code(401);
    echo json_encode(['error' => 'login']);
    exit;
}
if ($user['role'] === 'Student') {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$rows = db()->query(
    "SELECT i.item_id, i.description, i.location_found, i.date_found, i.created_at, c.category_name,
            (SELECT p.photo_id FROM item_photos p WHERE p.item_id = i.item_id ORDER BY p.sort_order LIMIT 1) AS thumb_id
     FROM items i JOIN categories c ON c.category_id = i.category_id
     WHERE i.status = 'Found'
     ORDER BY i.created_at DESC, i.item_id DESC
     LIMIT 8"
)->fetchAll();

$items = [];
foreach ($rows as $r) {
    $desc = $r['description'];
    $items[] = [
        'id'       => (int) $r['item_id'],
        'category' => $r['category_name'],
        'text'     => mb_strlen($desc) > 70 ? mb_substr($desc, 0, 70) . '…' : $desc,
        'location' => $r['location_found'],
        'date'     => $r['date_found'],
        'logged'   => (new DateTimeImmutable($r['created_at']))->format('c'),
        'thumb'    => $r['thumb_id'] ? (int) $r['thumb_id'] : null,
    ];
}

echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
