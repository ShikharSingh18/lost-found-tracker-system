<?php
/*
 * Live search for the home page (JSON). Any logged-in user.
 *   GET q = what the person typed (up to 100 characters, up to 5 words)
 * Every word must match at least one of: description, category, location, date found
 * (dates match as 2026-11-20, "20 November 2026" or "20 Nov"). Students and Staff only
 * ever see items with status Found; Admins see every status. User input is only passed
 * as bound values, so SQL injection is not possible.
 */
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
$isAdmin = $user['role'] === 'Admin';

const LIVE_LIMIT = 6;
$raw = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
$terms = array_slice(array_values(array_unique(preg_split('/\s+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [])), 0, 5);

if (mb_strlen($raw) < 2 || !$terms) {
    echo json_encode(['q' => $raw, 'total' => 0, 'items' => []]);
    exit;
}

$where = [];
$params = [];
if (!$isAdmin) {
    $where[] = "i.status = 'Found'";
}
foreach ($terms as $n => $term) {
    $like = '%' . addcslashes($term, '%_\\') . '%';
    $where[] = "(i.description LIKE :d$n OR c.category_name LIKE :c$n OR i.location_found LIKE :l$n
                 OR CONCAT_WS(' ', DATE_FORMAT(i.date_found, '%Y-%m-%d'), DATE_FORMAT(i.date_found, '%e %M %Y'), DATE_FORMAT(i.date_found, '%d %b')) LIKE :t$n)";
    foreach (['d', 'c', 'l', 't'] as $k) {
        $params[":$k$n"] = $like;
    }
}
$whereSql = 'WHERE ' . implode(' AND ', $where);
$pdo = db();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM items i JOIN categories c ON c.category_id = i.category_id $whereSql");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT i.item_id, i.description, i.date_found, i.location_found, i.status, c.category_name,
            (SELECT p.photo_id FROM item_photos p WHERE p.item_id = i.item_id ORDER BY p.sort_order LIMIT 1) AS thumb_id
     FROM items i JOIN categories c ON c.category_id = i.category_id
     $whereSql
     ORDER BY i.date_found DESC, i.item_id DESC
     LIMIT :limit"
);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', LIVE_LIMIT, PDO::PARAM_INT);
$stmt->execute();

$items = [];
foreach ($stmt->fetchAll() as $r) {
    $desc = $r['description'];
    $items[] = [
        'id'       => (int) $r['item_id'],
        'category' => $r['category_name'],
        'text'     => mb_strlen($desc) > 80 ? mb_substr($desc, 0, 80) . '…' : $desc,
        'location' => $r['location_found'],
        'date'     => $r['date_found'],
        'status'   => $r['status'],
        'thumb'    => $r['thumb_id'] ? (int) $r['thumb_id'] : null,
    ];
}

echo json_encode(['q' => $raw, 'total' => $total, 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
