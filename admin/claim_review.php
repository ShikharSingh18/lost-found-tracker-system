<?php
/*
 * Module 5: Approve or reject a claim (Phase 4, Section 6.6, Tables 10, 11, 18, 23).
 *
 * Administrator only. Rules:
 * - Only a Pending claim can be decided. A decided claim is final.
 * - Approve: claim -> Approved, item -> Claimed, verified_by = this admin.
 *   Every OTHER Pending claim for the same item is automatically Rejected.
 * - Reject: claim -> Rejected, verified_by set. The item stays Found.
 * - Only one claim per item can ever be Approved: checked here inside the
 *   transaction AND enforced by the unique index on claims.approved_item_id.
 * - Every change writes an audit_log row and the claimant gets a notification,
 *   all in ONE transaction. Any failure rolls everything back (Table 10).
 * - The item and claim rows are locked (SELECT ... FOR UPDATE), so two admins
 *   deciding at the same moment can't both approve.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$admin = requireRole('Admin');
$pdo = db();

/** Marks the admins' "new claim" notifications for these claims as read. */
function mark_admin_alerts_read(PDO $pdo, array $claimIds): void
{
    if (!$claimIds) {
        return;
    }
    $in = implode(',', array_map('intval', $claimIds));   // integers only, safe to inline
    $pdo->exec(
        "UPDATE notifications SET is_read = TRUE
         WHERE claim_id IN ($in) AND user_id IN (SELECT user_id FROM users WHERE role = 'Admin')"
    );
}

function notify(PDO $pdo, int $userId, int $claimId, string $message): void
{
    $pdo->prepare('INSERT INTO notifications (user_id, claim_id, message) VALUES (:u, :c, :m)')
        ->execute([':u' => $userId, ':c' => $claimId, ':m' => $message]);
}

// ======================= Save a decision (POST) =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $claimId  = filter_var(is_string($_POST['claim_id'] ?? null) ? $_POST['claim_id'] : '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $decision = $_POST['decision'] ?? '';

    if (!$claimId || !in_array($decision, ['Approve', 'Reject'], true)) {
        flash('bad', 'Choose Approve or Reject before saving.');
        redirect('admin/claim_review.php');
    }

    // Which item does the claim belong to? (read first so the item can be locked first, Table 10 step 2)
    $stmt = $pdo->prepare('SELECT item_id FROM claims WHERE claim_id = :c');
    $stmt->execute([':c' => $claimId]);
    $itemId = $stmt->fetchColumn();
    if ($itemId === false) {
        flash('bad', 'That claim does not exist.');
        redirect('admin/claim_review.php');
    }
    $itemId = (int) $itemId;

    try {
        // Step 1
        $pdo->beginTransaction();

        // Step 2: lock the item row
        $stmt = $pdo->prepare(
            'SELECT i.status, c.category_name FROM items i JOIN categories c ON c.category_id = i.category_id
             WHERE i.item_id = :i FOR UPDATE'
        );
        $stmt->execute([':i' => $itemId]);
        $item = $stmt->fetch();

        // Step 3: lock and re-read the claim - is it still Pending?
        $stmt = $pdo->prepare('SELECT claimant_user_id, verification_status FROM claims WHERE claim_id = :c FOR UPDATE');
        $stmt->execute([':c' => $claimId]);
        $claim = $stmt->fetch();

        if (!$claim || $claim['verification_status'] !== 'Pending') {
            $pdo->rollBack();
            flash('warn', "Claim #$claimId was already decided. Nothing was changed.");
            redirect('admin/claim_review.php');
        }

        $label = "{$item['category_name']} (item #$itemId)";
        $decidedClaims = [$claimId];

        if ($decision === 'Approve') {
            // Step 4: item must still be Found and have no Approved claim
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM claims WHERE item_id = :i AND verification_status = 'Approved'");
            $stmt->execute([':i' => $itemId]);
            if ($item['status'] !== 'Found' || (int) $stmt->fetchColumn() > 0) {
                $pdo->rollBack();
                flash('bad', "The $label is no longer Found or already has an approved claim. Nothing was changed.");
                redirect('admin/claim_review.php');
            }

            // Step 5: record the decision and who made it
            $pdo->prepare(
                "UPDATE claims SET verification_status = 'Approved', verified_by = :a, date_verified = NOW(6)
                 WHERE claim_id = :c"
            )->execute([':a' => $admin['user_id'], ':c' => $claimId]);

            // Step 6: Found -> Claimed
            $pdo->prepare("UPDATE items SET status = 'Claimed' WHERE item_id = :i")->execute([':i' => $itemId]);

            // Step 7: audit rows
            writeAudit($pdo, 'CLAIM_APPROVED', $itemId, $claimId, $admin['user_id']);
            writeAudit($pdo, 'ITEM_STATUS_CHANGED', $itemId, null, $admin['user_id']);
            notify($pdo, (int) $claim['claimant_user_id'], $claimId,
                "Your claim for $label was approved. Please collect it from the lost-and-found office.");

            // Every other Pending claim for this item is rejected, each with its own audit row.
            $stmt = $pdo->prepare(
                "SELECT claim_id, claimant_user_id FROM claims
                 WHERE item_id = :i AND verification_status = 'Pending' AND claim_id <> :c FOR UPDATE"
            );
            $stmt->execute([':i' => $itemId, ':c' => $claimId]);
            $others = $stmt->fetchAll();

            $reject = $pdo->prepare(
                "UPDATE claims SET verification_status = 'Rejected', verified_by = :a, date_verified = NOW(6)
                 WHERE claim_id = :c"
            );
            foreach ($others as $o) {
                $reject->execute([':a' => $admin['user_id'], ':c' => $o['claim_id']]);
                writeAudit($pdo, 'CLAIM_REJECTED', $itemId, (int) $o['claim_id'], $admin['user_id']);
                notify($pdo, (int) $o['claimant_user_id'], (int) $o['claim_id'],
                    "Your claim for $label was not approved. The item was released to its owner.");
                $decidedClaims[] = (int) $o['claim_id'];
            }

            $message = "Claim #$claimId approved. The $label is now Claimed."
                . ($others ? ' ' . count($others) . ' other pending claim(s) for this item were rejected automatically.' : '');
        } else {
            // Reject: the item stays Found so other people can still claim it.
            $pdo->prepare(
                "UPDATE claims SET verification_status = 'Rejected', verified_by = :a, date_verified = NOW(6)
                 WHERE claim_id = :c"
            )->execute([':a' => $admin['user_id'], ':c' => $claimId]);
            writeAudit($pdo, 'CLAIM_REJECTED', $itemId, $claimId, $admin['user_id']);
            notify($pdo, (int) $claim['claimant_user_id'], $claimId, "Your claim for $label was not approved.");
            $message = "Claim #$claimId rejected. The $label stays Found.";
        }

        mark_admin_alerts_read($pdo, $decidedClaims);

        // Step 8
        $pdo->commit();
        flash('ok', $message);
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('claim decision failed: ' . $ex->getMessage());
        // 23000 here means the unique index on approved_item_id stopped a second approval.
        $msg = ($ex instanceof PDOException && $ex->getCode() === '23000')
            ? 'Another claim for this item was approved at the same moment. Nothing was changed.'
            : (DEV_MODE ? 'Saving failed: ' . $ex->getMessage() : 'The decision could not be saved. Please try again.');
        flash('bad', $msg);
    }
    redirect('admin/claim_review.php');   // POST-redirect-GET
}

// ======================= Show the queue (GET) =======================
$pending = $pdo->query(
    "SELECT cl.claim_id, cl.item_id, cl.proof_description, cl.date_submitted, cl.consent_given_at,
            u.full_name, u.email, i.description, i.location_found, i.date_found, c.category_name,
            (SELECT COUNT(*) FROM claims x WHERE x.item_id = cl.item_id AND x.verification_status = 'Pending') AS pending_for_item,
            (SELECT p.photo_id FROM item_photos p WHERE p.item_id = cl.item_id ORDER BY p.sort_order LIMIT 1) AS thumb_id
     FROM claims cl
     JOIN users u ON u.user_id = cl.claimant_user_id
     JOIN items i ON i.item_id = cl.item_id
     JOIN categories c ON c.category_id = i.category_id
     WHERE cl.verification_status = 'Pending'
     ORDER BY cl.item_id, cl.date_submitted"
)->fetchAll();

$recent = $pdo->query(
    "SELECT cl.claim_id, cl.item_id, cl.verification_status, cl.date_verified, c.category_name,
            u.full_name AS claimant, a.full_name AS verifier
     FROM claims cl
     JOIN users u ON u.user_id = cl.claimant_user_id
     LEFT JOIN users a ON a.user_id = cl.verified_by
     JOIN items i ON i.item_id = cl.item_id
     JOIN categories c ON c.category_id = i.category_id
     WHERE cl.verification_status <> 'Pending'
     ORDER BY cl.date_verified DESC
     LIMIT 10"
)->fetchAll();

$icons = ['Keys' => '🔑', 'Wallet' => '👛', 'Phone' => '📱', 'ID Card' => '🪪', 'Clothing' => '🧥', 'Stationery' => '✏️', 'Other' => '📦'];
$n = count($pending);

$pageTitle = 'Review claims';
$activeNav = 'claim_review';
$extraCss  = ['assets/css/photos.css'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Review claims</h1><p class="muted"><?= $n ? $n . ' pending claim' . ($n === 1 ? '' : 's') . '. Compare the proof with the item record before deciding.' : 'All claims have been decided.' ?></p></div>
<?php if (!$pending): ?>
<div class="card"><p style="margin:0">No claims are waiting for review.</p></div>
<?php else: ?>
<div class="items" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr))">
<?php foreach ($pending as $p): ?>
<form class="card stack" action="<?= e(url('admin/claim_review.php')) ?>" method="post">
<?= csrf_field() ?>
<input type="hidden" name="claim_id" value="<?= (int) $p['claim_id'] ?>">
<div style="display:flex;justify-content:space-between;gap:.5rem;align-items:center"><h3 style="margin:0"><?= e($p['category_name']) ?> <span class="small muted">item #<?= (int) $p['item_id'] ?> · claim #<?= (int) $p['claim_id'] ?></span></h3><span class="pill pill-pending">Pending</span></div>
<?php if ((int) $p['pending_for_item'] > 1): ?>
<div class="alert alert-warn" style="margin:0"><?= (int) $p['pending_for_item'] ?> people have claimed this item. Approving one rejects the others.</div>
<?php endif; ?>
<div class="item" style="box-shadow:none;padding:.5rem">
<div class="thumb"<?= $p['thumb_id'] ? '' : ' aria-hidden="true"' ?>><?php if ($p['thumb_id']): ?><img src="<?= e(url('items/photo.php?id=' . (int) $p['thumb_id'])) ?>" alt="Photo of the item"><?php else: ?><?= $icons[$p['category_name']] ?? '📦' ?><?php endif; ?></div>
<div><p class="small" style="margin:0"><b>Item record:</b> <?= e($p['description']) ?></p><div class="meta"><span>📍 <?= e($p['location_found']) ?></span><span>📅 <?= e($p['date_found']) ?></span></div>
<a class="small" href="<?= e(url('items/details.php?id=' . (int) $p['item_id'])) ?>">View item and all photos</a></div></div>
<dl class="dl"><div><dt>Claimant</dt><dd><?= e($p['full_name']) ?><br><span class="small muted"><?= e($p['email']) ?></span></dd></div>
<div><dt>Submitted</dt><dd><?= e(substr($p['date_submitted'], 0, 16)) ?></dd></div>
<div style="grid-column:1/-1"><dt>Proof of ownership</dt><dd><?= nl2br(e($p['proof_description'])) ?></dd></div></dl>
<fieldset style="border:0;padding:0;margin:0" class="stack"><legend class="legend">Decision <span class="req">*</span></legend>
<label class="radio-card"><input type="radio" name="decision" value="Approve" required><span><b>Approve</b><br><span class="small muted">Item status changes to Claimed<?= (int) $p['pending_for_item'] > 1 ? '; other claims are rejected' : '' ?>.</span></span></label>
<label class="radio-card"><input type="radio" name="decision" value="Reject"><span><b>Reject</b><br><span class="small muted">Item stays Found.</span></span></label></fieldset>
<button class="btn" type="submit">Save decision</button></form>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($recent): ?>
<h2 style="margin-top:2rem">Recently decided</h2>
<div class="table-wrap"><table><thead><tr><th>Claim</th><th>Item</th><th>Claimant</th><th>Decision</th><th>By</th><th>When</th></tr></thead><tbody>
<?php foreach ($recent as $r): ?>
<tr><td data-label="Claim">#<?= (int) $r['claim_id'] ?></td>
<td data-label="Item"><a href="<?= e(url('items/details.php?id=' . (int) $r['item_id'])) ?>"><?= e($r['category_name']) ?> (#<?= (int) $r['item_id'] ?>)</a></td>
<td data-label="Claimant"><?= e($r['claimant']) ?></td>
<td data-label="Decision"><span class="pill pill-<?= e(strtolower($r['verification_status'])) ?>"><?= e($r['verification_status']) ?></span></td>
<td data-label="By"><?= e($r['verifier'] ?? '–') ?></td>
<td data-label="When"><?= e(substr((string) $r['date_verified'], 0, 16)) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
