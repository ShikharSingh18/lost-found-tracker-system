<?php
/*
 * Module 7: Manage Items (Administrator) – Phase 4 Table 24 "Edit or delete item
 * records", Section 8.5 and Section 6.6 (ITEM_RELEASED).
 *
 * - Table of ALL items (any status) with the same filters as search plus Status.
 * - Edit -> items/edit.php
 * - Dispose: there is no real delete (foreign keys use ON DELETE RESTRICT and the
 *   audit history must stay). "Delete" = status Disposed + ITEM_DISPOSED audit row.
 *   Only Found items can be disposed. Any Pending claims on the item are rejected
 *   automatically and the claimants are told. One transaction, item row locked.
 * - Mark collected: when the owner physically picks up a Claimed item, the handover
 *   is recorded as a separate ITEM_RELEASED audit event (approval alone doesn't
 *   prove the item was handed over). Can only be recorded once per item.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$admin = requireRole('Admin');
$pdo = db();
const MANAGE_PER_PAGE = 20;

// ======================= Actions (POST) =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $itemId = filter_var(is_string($_POST['item_id'] ?? null) ? $_POST['item_id'] : '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $action = $_POST['action'] ?? '';
    $back = is_string($_POST['back'] ?? null) && str_starts_with($_POST['back'], 'items/manage.php') ? $_POST['back'] : 'items/manage.php';

    if (!$itemId || !in_array($action, ['dispose', 'released'], true)) {
        flash('bad', 'Unknown action.');
        redirect($back);
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'SELECT i.status, c.category_name FROM items i JOIN categories c ON c.category_id = i.category_id
             WHERE i.item_id = :i FOR UPDATE'
        );
        $stmt->execute([':i' => $itemId]);
        $item = $stmt->fetch();
        if (!$item) {
            $pdo->rollBack();
            flash('bad', 'That item does not exist.');
            redirect($back);
        }
        $label = "{$item['category_name']} (item #$itemId)";

        if ($action === 'dispose') {
            if ($item['status'] !== 'Found') {
                $pdo->rollBack();
                flash('warn', "The $label is {$item['status']}, so it can't be disposed. Only Found items can be disposed.");
                redirect($back);
            }
            $pdo->prepare("UPDATE items SET status = 'Disposed' WHERE item_id = :i")->execute([':i' => $itemId]);
            writeAudit($pdo, 'ITEM_DISPOSED', $itemId, null, $admin['user_id']);

            // Pending claims can no longer succeed: reject them and tell the claimants.
            $stmt = $pdo->prepare(
                "SELECT claim_id, claimant_user_id FROM claims
                 WHERE item_id = :i AND verification_status = 'Pending' FOR UPDATE"
            );
            $stmt->execute([':i' => $itemId]);
            $pending = $stmt->fetchAll();
            $reject = $pdo->prepare(
                "UPDATE claims SET verification_status = 'Rejected', verified_by = :a, date_verified = NOW(6)
                 WHERE claim_id = :c"
            );
            $notify = $pdo->prepare('INSERT INTO notifications (user_id, claim_id, message) VALUES (:u, :c, :m)');
            foreach ($pending as $p) {
                $reject->execute([':a' => $admin['user_id'], ':c' => $p['claim_id']]);
                writeAudit($pdo, 'CLAIM_REJECTED', $itemId, (int) $p['claim_id'], $admin['user_id']);
                $notify->execute([
                    ':u' => $p['claimant_user_id'], ':c' => $p['claim_id'],
                    ':m' => "Your claim for $label was not approved. The item is no longer available.",
                ]);
            }
            if ($pending) {
                $in = implode(',', array_map(fn ($p) => (int) $p['claim_id'], $pending));
                $pdo->exec("UPDATE notifications SET is_read = TRUE WHERE claim_id IN ($in)
                            AND user_id IN (SELECT user_id FROM users WHERE role = 'Admin')");
            }
            $msg = "The $label was marked Disposed." . ($pending ? ' ' . count($pending) . ' pending claim(s) were rejected automatically.' : '');
        } else {
            // Mark collected (physical handover)
            if ($item['status'] !== 'Claimed') {
                $pdo->rollBack();
                flash('warn', "Only Claimed items can be marked as collected. The $label is {$item['status']}.");
                redirect($back);
            }
            $stmt = $pdo->prepare("SELECT 1 FROM audit_log WHERE item_id = :i AND action = 'ITEM_RELEASED' LIMIT 1");
            $stmt->execute([':i' => $itemId]);
            if ($stmt->fetchColumn()) {
                $pdo->rollBack();
                flash('warn', "The $label was already marked as collected.");
                redirect($back);
            }
            $stmt = $pdo->prepare(
                "SELECT claim_id, claimant_user_id FROM claims WHERE item_id = :i AND verification_status = 'Approved' LIMIT 1"
            );
            $stmt->execute([':i' => $itemId]);
            $owner = $stmt->fetch();

            writeAudit($pdo, 'ITEM_RELEASED', $itemId, $owner ? (int) $owner['claim_id'] : null, $admin['user_id']);
            if ($owner) {
                $pdo->prepare('INSERT INTO notifications (user_id, claim_id, message) VALUES (:u, :c, :m)')->execute([
                    ':u' => $owner['claimant_user_id'], ':c' => $owner['claim_id'],
                    ':m' => "You collected your $label. Thank you for using Lost & Found Tracker.",
                ]);
            }
            $msg = "The $label was marked as collected by its owner.";
        }

        $pdo->commit();
        flash('ok', $msg);
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('manage items failed: ' . $ex->getMessage());
        flash('bad', DEV_MODE ? 'Saving failed: ' . $ex->getMessage() : 'The change could not be saved. Please try again.');
    }
    redirect($back);
}

// ======================= List (GET) =======================
$categories = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_id')->fetchAll();
$categoryIds = array_map('intval', array_column($categories, 'category_id'));

$get = fn (string $k): string => is_string($_GET[$k] ?? null) ? trim($_GET[$k]) : '';
$f = ['q' => mb_substr($get('q'), 0, 100), 'category' => $get('category'), 'location' => mb_substr($get('location'), 0, 200),
      'from' => $get('from'), 'to' => $get('to'), 'status' => $get('status')];
if ($f['category'] !== '' && !in_array((int) $f['category'], $categoryIds, true)) $f['category'] = '';
foreach (['from', 'to'] as $k) {
    $d = $f[$k] !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d', $f[$k]) : null;
    if ($f[$k] !== '' && (!$d || $d->format('Y-m-d') !== $f[$k])) $f[$k] = '';
}
if ($f['from'] !== '' && $f['to'] !== '' && $f['from'] > $f['to']) [$f['from'], $f['to']] = [$f['to'], $f['from']];
if (!in_array($f['status'], ['', 'Found', 'Claimed', 'Disposed'], true)) $f['status'] = '';

$where = [];
$params = [];
if ($f['status'] !== '') { $where[] = 'i.status = :status'; $params[':status'] = $f['status']; }
if ($f['q'] !== '') {
    $like = '%' . addcslashes($f['q'], '%_\\') . '%';
    $where[] = '(i.description LIKE :q1 OR c.category_name LIKE :q2 OR i.location_found LIKE :q3)';
    $params[':q1'] = $params[':q2'] = $params[':q3'] = $like;
}
if ($f['category'] !== '') { $where[] = 'i.category_id = :category'; $params[':category'] = (int) $f['category']; }
if ($f['location'] !== '') { $where[] = 'i.location_found LIKE :location'; $params[':location'] = '%' . addcslashes($f['location'], '%_\\') . '%'; }
if ($f['from'] !== '') { $where[] = 'i.date_found >= :from'; $params[':from'] = $f['from']; }
if ($f['to'] !== '') { $where[] = 'i.date_found <= :to'; $params[':to'] = $f['to']; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM items i JOIN categories c ON c.category_id = i.category_id $whereSql");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$pages = max(1, (int) ceil($total / MANAGE_PER_PAGE));
$page = min($pages, max(1, (int) $get('page')));

$stmt = $pdo->prepare(
    "SELECT i.item_id, i.description, i.date_found, i.location_found, i.status, c.category_name,
            (SELECT COUNT(*) FROM claims x WHERE x.item_id = i.item_id AND x.verification_status = 'Pending') AS pending_claims,
            (SELECT a.occurred_at FROM audit_log a WHERE a.item_id = i.item_id AND a.action = 'ITEM_RELEASED' ORDER BY a.log_id LIMIT 1) AS released_at
     FROM items i JOIN categories c ON c.category_id = i.category_id
     $whereSql
     ORDER BY i.item_id DESC
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $k => $v) $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
$stmt->bindValue(':limit', MANAGE_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':offset', ($page - 1) * MANAGE_PER_PAGE, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll();

$query = array_filter($f, fn ($v) => $v !== '');
$here = 'items/manage.php' . (($query || $page > 1) ? '?' . http_build_query($query + ($page > 1 ? ['page' => $page] : [])) : '');

$pageTitle = 'Manage items';
$activeNav = 'manage';
$extraCss  = ['assets/css/photos.css'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Manage items</h1><p class="muted">Edit item records, dispose of unclaimed items and record when owners collect their items.</p><a class="btn" href="<?= e(url('admin/item_add.php')) ?>">+ Log found item</a></div>
<details class="card filters" open><summary>Search &amp; filters</summary>
<form class="form form-2" action="<?= e(url('items/manage.php')) ?>" method="get" style="margin-top:.75rem">
<div class="field full"><label for="q">Keyword</label><input id="q" name="q" type="search" maxlength="100" placeholder="e.g. black, lanyard" value="<?= e($f['q']) ?>"></div>
<div class="field"><label for="category">Category</label><select id="category" name="category"><option value="">All categories</option>
<?php foreach ($categories as $c): ?><option value="<?= (int) $c['category_id'] ?>"<?= (string) $c['category_id'] === $f['category'] ? ' selected' : '' ?>><?= e($c['category_name']) ?></option><?php endforeach; ?>
</select></div>
<div class="field"><label for="location">Location found</label><input id="location" name="location" maxlength="200" value="<?= e($f['location']) ?>"></div>
<div class="field"><label for="from">Date found: from</label><input id="from" name="from" type="date" value="<?= e($f['from']) ?>"></div>
<div class="field"><label for="to">Date found: to</label><input id="to" name="to" type="date" value="<?= e($f['to']) ?>"></div>
<div class="field full"><label for="status">Status</label><select id="status" name="status"><option value="">Any</option>
<?php foreach (['Found', 'Claimed', 'Disposed'] as $s): ?><option<?= $f['status'] === $s ? ' selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
</select></div>
<div class="btn-row full"><button class="btn" type="submit">Apply filters</button><a class="btn btn-ghost" href="<?= e(url('items/manage.php')) ?>">Clear</a></div></form></details>

<p class="small muted" style="margin-top:1rem"><?= $total ?> item<?= $total === 1 ? '' : 's' ?></p>
<?php if (!$items): ?>
<div class="card"><p style="margin:0">No items match these filters.</p></div>
<?php else: ?>
<div class="table-wrap"><table><thead><tr><th>ID</th><th>Category</th><th>Description</th><th>Location</th><th>Date found</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($items as $it):
    $id = (int) $it['item_id']; ?>
<tr>
<td data-label="ID"><a href="<?= e(url('items/details.php?id=' . $id)) ?>">#<?= $id ?></a></td>
<td data-label="Category"><?= e($it['category_name']) ?></td>
<td data-label="Description"><?= e(mb_strimwidth($it['description'], 0, 60, '…')) ?></td>
<td data-label="Location"><?= e($it['location_found']) ?></td>
<td data-label="Date found"><?= e($it['date_found']) ?></td>
<td data-label="Status"><span class="pill pill-<?= e(strtolower($it['status'])) ?>"><?= e($it['status']) ?></span>
<?php if ((int) $it['pending_claims'] > 0): ?><br><a class="small" href="<?= e(url('admin/claim_review.php')) ?>"><?= (int) $it['pending_claims'] ?> pending claim<?= (int) $it['pending_claims'] === 1 ? '' : 's' ?></a><?php endif; ?>
<?php if ($it['released_at']): ?><br><span class="small muted">Collected <?= e(substr($it['released_at'], 0, 16)) ?></span><?php endif; ?></td>
<td class="actions" data-label="Actions">
<a class="btn btn-sm btn-outline" href="<?= e(url('items/edit.php?id=' . $id)) ?>">Edit</a>
<?php if ($it['status'] === 'Found'): ?>
<form method="post" action="<?= e(url('items/manage.php')) ?>" style="display:inline" onsubmit="return confirm('Dispose of item #<?= $id ?>?<?= (int) $it['pending_claims'] > 0 ? ' Its pending claims will be rejected.' : '' ?> This cannot be undone on the site.');">
<?= csrf_field() ?><input type="hidden" name="item_id" value="<?= $id ?>"><input type="hidden" name="action" value="dispose"><input type="hidden" name="back" value="<?= e($here) ?>">
<button class="btn btn-sm btn-ghost" type="submit">Dispose</button></form>
<?php elseif ($it['status'] === 'Claimed' && !$it['released_at']): ?>
<form method="post" action="<?= e(url('items/manage.php')) ?>" style="display:inline" onsubmit="return confirm('Record that the owner has collected item #<?= $id ?>?');">
<?= csrf_field() ?><input type="hidden" name="item_id" value="<?= $id ?>"><input type="hidden" name="action" value="released"><input type="hidden" name="back" value="<?= e($here) ?>">
<button class="btn btn-sm btn-ok" type="submit">Mark collected</button></form>
<?php endif; ?>
</td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if ($pages > 1): ?>
<nav class="pager" aria-label="Pagination">
<?php for ($p = 1; $p <= $pages; $p++): ?>
<?php if ($p === $page): ?><span class="on" aria-current="page"><?= $p ?></span><?php else: ?><a href="<?= e(url('items/manage.php?' . http_build_query($query + ['page' => $p]))) ?>"><?= $p ?></a><?php endif; ?>
<?php endfor; ?>
</nav>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
