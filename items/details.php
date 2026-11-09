<?php
/*
 * Module 3: Item details (Phase 4, Section 6.4 and Table 20).
 *
 * - Any logged-in user. Students and Staff can only open items with status Found;
 *   anything else shows "not available" (same as a wrong ID, so nothing leaks).
 * - Shows category, description, date, location, status and all photos.
 * - logged_by (who recorded the item) is shown to Administrators only.
 * - Students and Staff get a "submit a claim" button, unless they already
 *   have a Pending claim on this item.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$user = requireLogin();
$isAdmin = $user['role'] === 'Admin';
$pdo = db();

$itemId = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

$item = false;
if ($itemId) {
    $stmt = $pdo->prepare(
        'SELECT i.item_id, i.description, i.date_found, i.location_found, i.status, i.created_at,
                c.category_name, u.full_name AS logged_by_name
         FROM items i
         JOIN categories c ON c.category_id = i.category_id
         JOIN users u ON u.user_id = i.logged_by
         WHERE i.item_id = :id'
    );
    $stmt->execute([':id' => $itemId]);
    $item = $stmt->fetch();
}

if (!$item || (!$isAdmin && $item['status'] !== 'Found')) {
    http_response_code(404);
    $pageTitle = 'Item not available';
    $activeNav = 'search';
    require __DIR__ . '/../includes/header.php';
    echo '<div class="alert alert-warn" role="status">This item is not available. It may already have been claimed.</div>';
    echo '<p><a class="btn btn-ghost" href="' . e(url('items/search.php')) . '">Back to search</a></p>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare('SELECT photo_id FROM item_photos WHERE item_id = :id ORDER BY sort_order');
$stmt->execute([':id' => $itemId]);
$photoIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

// Does this user already have an open claim on the item? (Module 4 rule)
$hasPendingClaim = false;
if (!$isAdmin) {
    $stmt = $pdo->prepare(
        "SELECT 1 FROM claims WHERE item_id = :item AND claimant_user_id = :user AND verification_status = 'Pending' LIMIT 1"
    );
    $stmt->execute([':item' => $itemId, ':user' => $user['user_id']]);
    $hasPendingClaim = (bool) $stmt->fetchColumn();
}

$icons = ['Keys' => '🔑', 'Wallet' => '👛', 'Phone' => '📱', 'ID Card' => '🪪', 'Clothing' => '🧥', 'Stationery' => '✏️', 'Other' => '📦'];
$dateText = (new DateTimeImmutable($item['date_found']))->format('j F Y');

$pageTitle = $item['category_name'] . ' – item details';
$activeNav = 'search';
$extraCss  = ['assets/css/photos.css'];
require __DIR__ . '/../includes/header.php';
?>
<div class="crumbs"><a href="<?= e(url('items/search.php')) ?>">Search</a> › Item details</div>
<div class="grid layout-2">
<div class="stack">
<?php if ($photoIds): ?>
<div class="photo"><img id="main-photo" src="<?= e(url('items/photo.php?id=' . $photoIds[0])) ?>" alt="Photo 1 of <?= count($photoIds) ?> of the found <?= e(strtolower($item['category_name'])) ?>"></div>
<?php if (count($photoIds) > 1): ?>
<div class="gallery" role="group" aria-label="Choose a photo">
<?php foreach ($photoIds as $i => $pid): ?>
<button type="button" data-src="<?= e(url('items/photo.php?id=' . $pid)) ?>" data-n="<?= $i + 1 ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?> aria-label="Show photo <?= $i + 1 ?>"><img src="<?= e(url('items/photo.php?id=' . $pid)) ?>" alt="" loading="lazy"></button>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php else: ?>
<div class="photo" aria-label="No photograph for this item"><?= $icons[$item['category_name']] ?? '📦' ?></div>
<div class="small muted">No photo was added for this item.</div>
<?php endif; ?>
</div>
<div class="card stack"><div><h1><?= e($item['category_name']) ?></h1><span class="pill pill-<?= e(strtolower($item['status'])) ?>"><?= e($item['status']) ?></span></div>
<dl class="dl"><div><dt>Description</dt><dd><?= nl2br(e($item['description'])) ?></dd></div>
<div><dt>Date found</dt><dd><?= e($dateText) ?></dd></div>
<div><dt>Location found</dt><dd><?= e($item['location_found']) ?></dd></div>
<div><dt>Category</dt><dd><?= e($item['category_name']) ?></dd></div>
<?php if ($isAdmin): ?>
<div><dt>Item number</dt><dd>#<?= (int) $item['item_id'] ?></dd></div>
<div><dt>Logged by</dt><dd><?= e($item['logged_by_name']) ?> (<?= e(substr($item['created_at'], 0, 16)) ?>)</dd></div>
<?php endif; ?>
</dl>
<div class="btn-row">
<?php if (!$isAdmin && $item['status'] === 'Found'): ?>
<?php if ($hasPendingClaim): ?>
<a class="btn btn-outline" href="<?= e(url('claims/my_claims.php')) ?>">You have a pending claim – view it</a>
<?php else: ?>
<a class="btn" href="<?= e(url('claims/submit.php?item_id=' . (int) $item['item_id'])) ?>">This is mine – submit a claim</a>
<?php endif; ?>
<?php endif; ?>
<a class="btn btn-ghost" href="<?= e(url('items/search.php')) ?>">Back to results</a></div>
<?php if (!$isAdmin): ?>
<p class="small muted" style="margin:0">To prove the item is yours, your claim must describe details that aren't shown here. An administrator checks every claim before an item is released.</p>
<?php endif; ?>
</div></div>
<?php if (count($photoIds) > 1): ?>
<script>
// Clicking a small photo shows it in the big frame.
document.querySelectorAll('.gallery button').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var main = document.getElementById('main-photo');
    main.src = btn.dataset.src;
    main.alt = 'Photo ' + btn.dataset.n + ' of <?= count($photoIds) ?>';
    document.querySelectorAll('.gallery button').forEach(function (b) { b.removeAttribute('aria-current'); });
    btn.setAttribute('aria-current', 'true');
  });
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
