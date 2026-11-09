<?php
/*
 * Serves an item photo to logged-in users only (Phase 4, Section 4.2).
 * There is no public link to a photo: uploads/item_photos/ is blocked by
 * its own .htaccess, and this script checks the user's role first.
 *
 * Use in pages:  <img src="<?= e(url('items/photo.php?id=' . $photoId)) ?>" alt="...">
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/photos.php';

$user = requireLogin();
$photoId = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

$row = false;
if ($photoId) {
    $stmt = db()->prepare(
        'SELECT p.storage_key, i.status
         FROM item_photos p JOIN items i ON i.item_id = p.item_id
         WHERE p.photo_id = :id'
    );
    $stmt->execute([':id' => $photoId]);
    $row = $stmt->fetch();
}

// Students and Staff only see photos of items that are still Found (Table 24).
$allowed = $row && ($user['role'] === 'Admin' || $row['status'] === 'Found');
$path = $allowed ? PHOTO_DIR . '/' . $row['storage_key'] : '';

if (!$allowed || !preg_match('/^[a-f0-9]{32}\.jpg$/', $row['storage_key']) || !is_file($path)) {
    http_response_code(404);
    exit('Photo not found.');
}

session_write_close();   // don't hold the session lock while sending the file
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
readfile($path);
