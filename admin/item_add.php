<?php
/*
 * Module 2: Log a found item (Phase 4, Section 6.3 and Table 15).
 *
 * Group decisions:
 * - Finders hand items in physically. Staff or an Administrator logs them here.
 * - No finder name or contact details are asked for or stored.
 * - Up to 5 photos per item, 15 MB each (needs database/002_item_photos.sql).
 *
 * Rules: description, category, date found and location are required; the date
 * can't be in the future; status always starts as Found; the item, its photos
 * and its audit entry are saved in ONE transaction.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/photos.php';

$user = requireRole('Staff', 'Admin');
$pdo = db();

$categories = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_id')->fetchAll();
$validCategoryIds = array_map('intval', array_column($categories, 'category_id'));

$errors = [];
$old = ['category_id' => '', 'date_found' => date('Y-m-d'), 'location_found' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If the whole upload is bigger than post_max_size, PHP silently empties
    // $_POST and $_FILES. Catch that before the CSRF check gives a confusing error.
    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $errors[] = 'The upload was too large in total. Check post_max_size in php.ini (needs 80M), then add the photos again.';
    } else {
        verify_csrf();

        $categoryId = filter_var($_POST['category_id'] ?? '', FILTER_VALIDATE_INT);
        $dateFound  = trim((string) ($_POST['date_found'] ?? ''));
        $location   = trim((string) ($_POST['location_found'] ?? ''));
        $desc       = trim((string) ($_POST['description'] ?? ''));
        $old = ['category_id' => (string) $categoryId, 'date_found' => $dateFound, 'location_found' => $location, 'description' => $desc];

        // ---- Field validation (Table 15 / Table 19) ----
        if (!in_array($categoryId, $validCategoryIds, true)) {
            $errors[] = 'Choose a category from the list.';
        }
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $dateFound);
        if (!$d || $d->format('Y-m-d') !== $dateFound) {
            $errors[] = 'Enter a valid date found.';
        } elseif ($dateFound > date('Y-m-d')) {
            $errors[] = 'Date found cannot be in the future.';
        }
        if ($location === '' || mb_strlen($location) > 200) {
            $errors[] = 'Location found is required (maximum 200 characters).';
        }
        if ($desc === '' || mb_strlen($desc) > 1000) {
            $errors[] = 'Description is required (maximum 1,000 characters).';
        }

        // ---- Photo validation ----
        [$photos, $photoErrors] = collect_uploaded_photos('photos');
        $errors = array_merge($errors, $photoErrors);
        foreach ($photos as $p) {
            if ($msg = validate_photo($p)) {
                $errors[] = $msg;
            }
        }

        if (!$errors) {
            ini_set('memory_limit', '512M');   // decoding a large phone photo needs room
            $storedKeys = [];
            try {
                foreach ($photos as $p) {
                    $storedKeys[] = store_photo($p);
                }

                $pdo->beginTransaction();

                $pdo->prepare(
                    "INSERT INTO items (category_id, description, date_found, location_found, photo_storage_key, status, logged_by)
                     VALUES (:category_id, :description, :date_found, :location_found, :photo, 'Found', :logged_by)"
                )->execute([
                    ':category_id'    => $categoryId,
                    ':description'    => $desc,
                    ':date_found'     => $dateFound,
                    ':location_found' => $location,
                    ':photo'          => $storedKeys[0] ?? null,   // first photo = thumbnail
                    ':logged_by'      => $user['user_id'],
                ]);
                $itemId = (int) $pdo->lastInsertId();

                $insPhoto = $pdo->prepare(
                    'INSERT INTO item_photos (item_id, storage_key, sort_order) VALUES (:item_id, :key, :ord)'
                );
                foreach ($storedKeys as $i => $key) {
                    $insPhoto->execute([':item_id' => $itemId, ':key' => $key, ':ord' => $i + 1]);
                }

                writeAudit($pdo, 'ITEM_LOGGED', $itemId, null, $user['user_id']);

                $pdo->commit();

                flash('ok', "Item #$itemId saved with status Found" .
                    ($storedKeys ? ' and ' . count($storedKeys) . ' photo(s).' : '.'));
                redirect('admin/item_add.php');   // POST-redirect-GET: refresh won't save twice
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                delete_stored_photos($storedKeys);   // no orphan files without a database row
                error_log('item_add failed: ' . $ex->getMessage());
                $errors[] = DEV_MODE ? 'Saving failed: ' . $ex->getMessage()
                                     : 'The item could not be saved. Please try again.';
            }
        }
    }
    if ($errors) {
        $errors[] = 'For security, browsers clear chosen photos after an error. Please add the photos again.';
    }
}

$pageTitle = 'Log found item';
$activeNav = 'item_add';
$extraCss  = ['assets/css/upload.css'];
$extraJs   = ['assets/js/upload.js'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Log a found item</h1><p class="muted">Status is set to Found automatically. Don't record who handed the item in.</p></div>
<?php if ($errors): ?>
<div class="alert alert-bad" role="alert"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<form class="card form form-2" action="<?= e(url('admin/item_add.php')) ?>" method="post" enctype="multipart/form-data" style="max-width:860px">
<?= csrf_field() ?>
<div class="field"><label for="category">Category <span class="req">*</span></label><select id="category" name="category_id" required><option value="">Select a category</option>
<?php foreach ($categories as $c): ?>
<option value="<?= (int) $c['category_id'] ?>"<?= (string) $c['category_id'] === $old['category_id'] ? ' selected' : '' ?>><?= e($c['category_name']) ?></option>
<?php endforeach; ?>
</select></div>
<div class="field"><label for="date">Date found <span class="req">*</span></label><input id="date" name="date_found" type="date" max="<?= e(date('Y-m-d')) ?>" required value="<?= e($old['date_found']) ?>"></div>
<div class="field full"><label for="location">Location found <span class="req">*</span></label><input id="location" name="location_found" maxlength="200" required value="<?= e($old['location_found']) ?>"></div>
<div class="field full"><label for="desc">Description <span class="req">*</span></label><textarea id="desc" name="description" maxlength="1000" required><?= e($old['description']) ?></textarea><div class="hint">Keep it general. Leave out details only the owner would know (used for verification).</div></div>

<!-- ===== Photo upload (max 5 images, 15 MB each) ===== -->
<div class="field full">
  <div class="upl-head"><span class="legend" style="margin:0">Photographs</span><span id="upl-count" class="upl-count" aria-live="polite">0 / 5 photos</span></div>
  <div class="hint" style="margin:.25rem 0 .75rem">Optional, but photos help owners recognise their item. Up to 5 photos, JPG or PNG, 15 MB each.</div>
  <div class="uploader">
    <div class="demo" role="group" aria-label="Example photo angles">
      <div class="demo-stage"><div class="demo-frame"><svg viewBox="0 0 200 150" aria-hidden="true"><rect x="55" y="15" width="90" height="120" rx="18" fill="#dbe6ff" stroke="#2f5bea" stroke-width="3"/><rect x="70" y="55" width="60" height="38" rx="8" fill="#fff" stroke="#2f5bea" stroke-width="2"/><path d="M80 30h40" stroke="#2f5bea" stroke-width="4" stroke-linecap="round"/><circle cx="100" cy="112" r="6" fill="#2f5bea"/></svg><span class="chip">1. Front</span></div><div class="demo-frame"><svg viewBox="0 0 200 150" aria-hidden="true"><g class="pulse"><path d="M12 30V12h18M170 12h18v18M188 120v18h-18M30 138H12v-18" fill="none" stroke="#157347" stroke-width="4" stroke-linecap="round"/></g><rect x="70" y="40" width="60" height="75" rx="12" fill="#dbe6ff" stroke="#2f5bea" stroke-width="3"/><rect x="82" y="68" width="36" height="22" rx="5" fill="#fff" stroke="#2f5bea" stroke-width="2"/></svg><span class="chip">2. Overall</span></div><div class="demo-frame"><svg viewBox="0 0 200 150" aria-hidden="true"><rect x="82" y="15" width="36" height="120" rx="14" fill="#dbe6ff" stroke="#2f5bea" stroke-width="3"/><path d="M118 45q22 0 22 25t-22 25" fill="none" stroke="#2f5bea" stroke-width="3"/><path d="M92 40v70" stroke="#2f5bea" stroke-width="2" stroke-dasharray="4 5"/></svg><span class="chip">3. Side</span></div><div class="demo-frame"><svg viewBox="0 0 200 150" aria-hidden="true"><rect x="45" y="20" width="110" height="115" rx="18" fill="#dbe6ff" stroke="#2f5bea" stroke-width="3"/><path d="M100 20v-6" stroke="#2f5bea" stroke-width="3"/><g class="sway"><path d="M100 20c0 20 8 28 8 36" fill="none" stroke="#2f5bea" stroke-width="2.5"/><g transform="translate(78 56)"><rect width="56" height="38" rx="6" fill="#fff" stroke="#157347" stroke-width="3"/><circle cx="8" cy="8" r="3" fill="#157347"/><path d="M14 17h34M14 26h24" stroke="#5d6b7e" stroke-width="3" stroke-linecap="round"/></g></g><circle class="pulse" cx="106" cy="75" r="40" fill="none" stroke="#157347" stroke-width="3"/></svg><span class="chip">4. Label / tag</span></div><div class="demo-frame"><svg viewBox="0 0 200 150" aria-hidden="true"><rect x="55" y="15" width="90" height="120" rx="18" fill="#dbe6ff" stroke="#2f5bea" stroke-width="3"/><path d="M90 60l22 26M98 56l18 22M84 70l16 18" stroke="#b3261e" stroke-width="3" stroke-linecap="round"/><circle class="pulse" cx="100" cy="72" r="30" fill="none" stroke="#b3261e" stroke-width="3" stroke-dasharray="6 5"/></svg><span class="chip">5. Marks / damage</span></div></div>
      <div class="small muted">Suggested shots, in this order. The first photo is used as the thumbnail in search results.</div>
      <ul class="shots" id="shots"><li>Front</li><li>Overall</li><li>Side</li><li>Label / tag</li><li>Marks / damage</li></ul>
    </div>
    <label class="drop" id="drop">
      <span class="big" aria-hidden="true">📷</span>
      <b>Tap to add photos</b>
      <span class="small muted">or drag and drop here</span>
      <input id="photos" name="photos[]" type="file" accept="image/jpeg,image/png" multiple>
    </label>
    <div id="upl-errors" class="alert alert-bad upl-errors" role="alert"></div>
    <ul class="previews" id="previews"></ul>
    <div class="hint">Avoid photos showing names, ID numbers or card details. Those are used to verify ownership.</div>
  </div>
</div>
<!-- ===== end photo upload ===== -->

<div class="btn-row full"><button class="btn" type="submit">Save item</button><a class="btn btn-ghost" href="<?= e(url('index.php')) ?>">Cancel</a></div>
</form>
<script>
// upload.js disables the file input once 5 photos are chosen, and browsers
// don't send disabled inputs. Re-enable it just before the form is sent.
document.querySelector('form.form').addEventListener('submit', function () {
  var p = document.getElementById('photos');
  if (p) { p.disabled = false; }
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
