<?php
/*
 * Module 7: Edit an item (Administrator only – Phase 4 Table 24).
 *
 * - Change category, description, date found and location (same rules as Table 15).
 * - Photos: remove existing ones and/or add new ones; never more than 5 in total.
 *   The first remaining photo becomes the thumbnail (items.photo_storage_key).
 * - Status is NOT edited here. It only changes through the proper actions
 *   (claim approval, Dispose, Mark collected) so every change follows its rules.
 * - Everything is saved in ONE transaction with an ITEM_UPDATED audit row.
 *   Removed photo files are deleted only after the database change succeeds.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/photos.php';

$admin = requireRole('Admin');
$pdo = db();

$rawId = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['item_id'] ?? '') : ($_GET['id'] ?? '');
$itemId = filter_var(is_string($rawId) ? $rawId : '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

$stmt = $pdo->prepare('SELECT * FROM items WHERE item_id = :id');
$stmt->execute([':id' => $itemId ?: 0]);
$item = $stmt->fetch();
if (!$item) {
    flash('bad', 'That item does not exist.');
    redirect('items/manage.php');
}

$categories = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_id')->fetchAll();
$validCategoryIds = array_map('intval', array_column($categories, 'category_id'));

function load_photos(PDO $pdo, int $itemId): array
{
    $stmt = $pdo->prepare('SELECT photo_id, storage_key, sort_order FROM item_photos WHERE item_id = :i ORDER BY sort_order');
    $stmt->execute([':i' => $itemId]);
    return $stmt->fetchAll();
}
$photos = load_photos($pdo, $itemId);

$errors = [];
$old = [
    'category_id'    => (string) $item['category_id'],
    'date_found'     => $item['date_found'],
    'location_found' => $item['location_found'],
    'description'    => $item['description'],
];
$removeIds = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $errors[] = 'The upload was too large in total. Check post_max_size in php.ini (needs 80M).';
    } else {
        verify_csrf();

        $categoryId = filter_var($_POST['category_id'] ?? '', FILTER_VALIDATE_INT);
        $dateFound  = trim((string) ($_POST['date_found'] ?? ''));
        $location   = trim((string) ($_POST['location_found'] ?? ''));
        $desc       = trim((string) ($_POST['description'] ?? ''));
        $old = ['category_id' => (string) $categoryId, 'date_found' => $dateFound, 'location_found' => $location, 'description' => $desc];

        // Only photo IDs that really belong to THIS item count.
        $ownIds = array_map('intval', array_column($photos, 'photo_id'));
        $removeIds = array_values(array_intersect($ownIds, array_map('intval', (array) ($_POST['remove'] ?? []))));

        if (!in_array($categoryId, $validCategoryIds, true)) $errors[] = 'Choose a category from the list.';
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $dateFound);
        if (!$d || $d->format('Y-m-d') !== $dateFound) $errors[] = 'Enter a valid date found.';
        elseif ($dateFound > date('Y-m-d')) $errors[] = 'Date found cannot be in the future.';
        if ($location === '' || mb_strlen($location) > 200) $errors[] = 'Location found is required (maximum 200 characters).';
        if ($desc === '' || mb_strlen($desc) > 1000) $errors[] = 'Description is required (maximum 1,000 characters).';

        [$newPhotos, $photoErrors] = collect_uploaded_photos('photos');
        $errors = array_merge($errors, $photoErrors);
        foreach ($newPhotos as $p) {
            if ($msg = validate_photo($p)) $errors[] = $msg;
        }
        $keptCount = count($photos) - count($removeIds);
        if ($keptCount + count($newPhotos) > PHOTO_MAX_FILES) {
            $errors[] = 'An item can have at most ' . PHOTO_MAX_FILES . " photos. You are keeping $keptCount and adding " . count($newPhotos) . '.';
        }

        if (!$errors) {
            ini_set('memory_limit', '512M');
            $storedKeys = [];
            try {
                foreach ($newPhotos as $p) $storedKeys[] = store_photo($p);

                $pdo->beginTransaction();
                $pdo->prepare('SELECT item_id FROM items WHERE item_id = :i FOR UPDATE')->execute([':i' => $itemId]);

                // Photo order: kept photos (same order) then new ones. Rewrite the rows 1..n.
                $kept = array_values(array_filter($photos, fn ($p) => !in_array((int) $p['photo_id'], $removeIds, true)));
                $removedKeys = array_values(array_column(
                    array_filter($photos, fn ($p) => in_array((int) $p['photo_id'], $removeIds, true)), 'storage_key'));
                $finalKeys = array_merge(array_column($kept, 'storage_key'), $storedKeys);

                $pdo->prepare('DELETE FROM item_photos WHERE item_id = :i')->execute([':i' => $itemId]);
                $ins = $pdo->prepare('INSERT INTO item_photos (item_id, storage_key, sort_order) VALUES (:i, :k, :o)');
                foreach ($finalKeys as $n => $key) {
                    $ins->execute([':i' => $itemId, ':k' => $key, ':o' => $n + 1]);
                }

                $pdo->prepare(
                    'UPDATE items SET category_id = :c, description = :d, date_found = :df, location_found = :l, photo_storage_key = :p
                     WHERE item_id = :i'
                )->execute([
                    ':c' => $categoryId, ':d' => $desc, ':df' => $dateFound, ':l' => $location,
                    ':p' => $finalKeys[0] ?? null, ':i' => $itemId,
                ]);

                writeAudit($pdo, 'ITEM_UPDATED', $itemId, null, $admin['user_id']);
                $pdo->commit();

                delete_stored_photos($removedKeys);   // only now that the database agrees
                $changes = [];
                if ($removedKeys) $changes[] = count($removedKeys) . ' photo(s) removed';
                if ($storedKeys) $changes[] = count($storedKeys) . ' photo(s) added';
                flash('ok', "Item #$itemId updated" . ($changes ? ' (' . implode(', ', $changes) . ')' : '') . '.');
                redirect('items/edit.php?id=' . $itemId);
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                delete_stored_photos($storedKeys);
                error_log('item edit failed: ' . $ex->getMessage());
                $errors[] = DEV_MODE ? 'Saving failed: ' . $ex->getMessage() : 'The item could not be saved. Please try again.';
            }
        }
    }
    if ($errors && !empty($_FILES['photos']['name'][0])) {
        $errors[] = 'For security, browsers clear chosen photos after an error. Please add the new photos again.';
    }
}

$pageTitle = 'Edit item #' . $itemId;
$activeNav = 'manage';
$extraCss  = ['assets/css/photos.css'];
require __DIR__ . '/../includes/header.php';
?>
<div class="crumbs"><a href="<?= e(url('items/manage.php')) ?>">Manage items</a> › Edit item #<?= (int) $itemId ?></div>
<div class="page-head"><h1>Edit item #<?= (int) $itemId ?></h1><p class="muted">Status: <span class="pill pill-<?= e(strtolower($item['status'])) ?>"><?= e($item['status']) ?></span> · Status changes only through claims, Dispose or Mark collected.</p></div>
<?php if ($errors): ?>
<div class="alert alert-bad" role="alert"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<form class="card form form-2" action="<?= e(url('items/edit.php')) ?>" method="post" enctype="multipart/form-data" style="max-width:860px">
<?= csrf_field() ?>
<input type="hidden" name="item_id" value="<?= (int) $itemId ?>">
<div class="field"><label for="category">Category <span class="req">*</span></label><select id="category" name="category_id" required>
<?php foreach ($categories as $c): ?><option value="<?= (int) $c['category_id'] ?>"<?= (string) $c['category_id'] === $old['category_id'] ? ' selected' : '' ?>><?= e($c['category_name']) ?></option><?php endforeach; ?>
</select></div>
<div class="field"><label for="date">Date found <span class="req">*</span></label><input id="date" name="date_found" type="date" max="<?= e(date('Y-m-d')) ?>" required value="<?= e($old['date_found']) ?>"></div>
<div class="field full"><label for="location">Location found <span class="req">*</span></label><input id="location" name="location_found" maxlength="200" required value="<?= e($old['location_found']) ?>"></div>
<div class="field full"><label for="desc">Description <span class="req">*</span></label><textarea id="desc" name="description" maxlength="1000" required><?= e($old['description']) ?></textarea><div class="hint">Keep it general. Leave out details only the owner would know.</div></div>

<div class="field full"><span class="legend">Current photos (<?= count($photos) ?> of <?= PHOTO_MAX_FILES ?>)</span>
<?php if (!$photos): ?><p class="small muted" style="margin:0">No photos yet.</p>
<?php else: ?>
<div class="gallery" style="gap:.75rem">
<?php foreach ($photos as $n => $p): ?>
<label style="display:flex;flex-direction:column;align-items:center;gap:.25rem;cursor:pointer">
<img src="<?= e(url('items/photo.php?id=' . (int) $p['photo_id'])) ?>" alt="Photo <?= $n + 1 ?>" style="width:96px;height:96px;object-fit:cover;border-radius:10px;border:1px solid var(--line)">
<span class="small"><input type="checkbox" name="remove[]" value="<?= (int) $p['photo_id'] ?>" style="width:auto;min-height:0"<?= in_array((int) $p['photo_id'], $removeIds, true) ? ' checked' : '' ?>> Remove<?= $n === 0 ? ' (thumbnail)' : '' ?></span>
</label>
<?php endforeach; ?>
</div>
<?php endif; ?></div>

<div class="field full"><label for="photos">Add photos</label><input id="photos" name="photos[]" type="file" accept="image/jpeg,image/png" multiple>
<div class="hint">JPG or PNG, 15 MB each. At most <?= PHOTO_MAX_FILES ?> photos in total after removing any ticked above.</div></div>

<div class="btn-row full"><button class="btn" type="submit">Save changes</button><a class="btn btn-ghost" href="<?= e(url('items/manage.php')) ?>">Back to Manage items</a></div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
