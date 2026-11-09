<?php
/*
 * Module 4: My claims (Phase 4, Table 20 "My claims").
 * Students and Staff see ONLY their own claims (claimant taken from the session),
 * with item, date submitted, status, decision date and a short note.
 * Their own proof text can be expanded so they remember what they wrote.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$user = requireRole('Student', 'Staff');

$stmt = db()->prepare(
    'SELECT cl.claim_id, cl.item_id, cl.proof_description, cl.verification_status,
            cl.date_submitted, cl.date_verified, i.status AS item_status, i.description, c.category_name
     FROM claims cl
     JOIN items i ON i.item_id = cl.item_id
     JOIN categories c ON c.category_id = i.category_id
     WHERE cl.claimant_user_id = :me
     ORDER BY cl.date_submitted DESC'
);
$stmt->execute([':me' => $user['user_id']]);
$claims = $stmt->fetchAll();

// Items this user already has a Pending claim on (no "submit a new claim" hint for those).
$pendingItems = [];
foreach ($claims as $cl) {
    if ($cl['verification_status'] === 'Pending') {
        $pendingItems[(int) $cl['item_id']] = true;
    }
}

$notes = [
    'Pending'  => 'Awaiting review by an administrator.',
    'Approved' => 'Approved. Collect it from the lost-and-found office.',
    'Rejected' => 'Not approved.',
];

$pageTitle = 'My claims';
$activeNav = 'my_claims';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>My claims</h1><p class="muted">Track the status of claims you have submitted.</p></div>
<?php if (!$claims): ?>
<div class="card"><p style="margin:0">You haven't submitted any claims yet. <a href="<?= e(url('items/search.php')) ?>">Search found items</a> to find yours.</p></div>
<?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Item</th><th>Submitted</th><th>Status</th><th>Decided</th><th>Note</th></tr></thead><tbody>
<?php foreach ($claims as $cl):
    $status = $cl['verification_status'];
    $itemLabel = $cl['category_name'] . ' (item #' . (int) $cl['item_id'] . ')';
    $note = $notes[$status];
    if ($status === 'Rejected' && $cl['item_status'] === 'Found' && !isset($pendingItems[(int) $cl['item_id']])) {
        $note .= ' You can submit a new claim with more detail.';
    } ?>
<tr>
<td data-label="Item"><?php if ($cl['item_status'] === 'Found'): ?><a href="<?= e(url('items/details.php?id=' . (int) $cl['item_id'])) ?>"><?= e($itemLabel) ?></a><?php else: ?><?= e($itemLabel) ?><?php endif; ?>
<details class="small"><summary>Your proof</summary><?= nl2br(e($cl['proof_description'])) ?></details></td>
<td data-label="Submitted"><?= e(substr($cl['date_submitted'], 0, 16)) ?></td>
<td data-label="Status"><span class="pill pill-<?= e(strtolower($status)) ?>"><?= e($status) ?></span></td>
<td data-label="Decided"><?= $cl['date_verified'] ? e(substr($cl['date_verified'], 0, 16)) : '–' ?></td>
<td data-label="Note"><?= e($note) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
