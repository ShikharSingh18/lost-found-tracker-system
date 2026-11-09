<?php
/*
 * Module 10: Retention clean-up (Administrator) – Phase 3 NFR3 "a defined retention period
 * for claim records", POPIA s14 / GDPR Art. 5(1)(e) (storage limitation).
 *
 * After RETENTION_MONTHS (includes/privacy.php) from the decision on a claim:
 *   - its proof-of-ownership text is replaced with a "removed" note
 *     (one CLAIM_PROOF_REMOVED audit row per claim, so it is visible in the Audit Log);
 *   - notifications that have been read are deleted.
 * Kept: the claim row (item, claimant, result, dates) and the whole audit trail, because the
 * item history and FR10 depend on them. Pending claims are never touched.
 * Run by an admin with one click (e.g. once a month); shows a preview first.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/privacy.php';

$admin = requireRole('Admin');
$pdo = db();
$cutoff = retention_cutoff();

$claimsSql = "FROM claims WHERE verification_status <> 'Pending' AND date_verified < :cut AND proof_description <> :removed";
$notesSql = 'FROM notifications WHERE is_read = TRUE AND created_at < :cut';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT claim_id, item_id $claimsSql FOR UPDATE");
        $stmt->execute([':cut' => $cutoff, ':removed' => PROOF_REMOVED_TEXT]);
        $old = $stmt->fetchAll();
        $upd = $pdo->prepare('UPDATE claims SET proof_description = :t WHERE claim_id = :id');
        foreach ($old as $c) {
            $upd->execute([':t' => PROOF_REMOVED_TEXT, ':id' => $c['claim_id']]);
            writeAudit($pdo, 'CLAIM_PROOF_REMOVED', (int) $c['item_id'], (int) $c['claim_id'], $admin['user_id']);
        }
        $stmt = $pdo->prepare("DELETE $notesSql");
        $stmt->execute([':cut' => $cutoff]);
        $deletedNotes = $stmt->rowCount();
        $pdo->commit();
        flash('ok', 'Retention clean-up done: proof text removed from ' . count($old) . ' claim(s), ' . $deletedNotes . ' old read notification(s) deleted.');
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('retention failed: ' . $ex->getMessage());
        flash('bad', DEV_MODE ? 'Clean-up failed: ' . $ex->getMessage() : 'The clean-up failed. Nothing was changed.');
    }
    redirect('admin/retention.php');
}

$stmt = $pdo->prepare("SELECT claim_id, item_id, verification_status, date_verified $claimsSql ORDER BY date_verified LIMIT 50");
$stmt->execute([':cut' => $cutoff, ':removed' => PROOF_REMOVED_TEXT]);
$due = $stmt->fetchAll();
$stmt = $pdo->prepare("SELECT COUNT(*) $claimsSql");
$stmt->execute([':cut' => $cutoff, ':removed' => PROOF_REMOVED_TEXT]);
$dueCount = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) $notesSql");
$stmt->execute([':cut' => $cutoff]);
$notesDue = (int) $stmt->fetchColumn();
$removedSoFar = (int) $pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'CLAIM_PROOF_REMOVED'")->fetchColumn();

$pageTitle = 'Retention clean-up';
$activeNav = 'dashboard';
$extraCss  = ['assets/css/photos.css'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Retention clean-up</h1><p class="muted">Removes personal information that is older than the retention period (<?= RETENTION_MONTHS ?> months). <a href="<?= e(url('privacy.php')) ?>">Privacy notice</a></p></div>

<div class="stats">
<div class="stat<?= $dueCount ? ' stat-warn' : '' ?>"><b><?= $dueCount ?></b><span>Decided claims older than <?= RETENTION_MONTHS ?> months with proof text</span></div>
<div class="stat"><b><?= $notesDue ?></b><span>Read notifications older than <?= RETENTION_MONTHS ?> months</span></div>
<div class="stat"><b><?= e(substr($cutoff, 0, 10)) ?></b><span>Cut-off date (decided before this)</span></div>
<div class="stat"><b><?= $removedSoFar ?></b><span>Proof texts removed so far</span></div>
</div>

<div class="card" style="margin-top:1rem">
<?php if (!$dueCount && !$notesDue): ?>
<p style="margin:0">Nothing is older than the retention period. Nothing to clean up.</p>
<?php else: ?>
<?php if ($due): ?>
<h2 style="margin-top:0">Claims that will lose their proof text</h2>
<div class="table-wrap"><table><thead><tr><th>Claim</th><th>Item</th><th>Result</th><th>Decided</th></tr></thead><tbody>
<?php foreach ($due as $c): ?>
<tr><td data-label="Claim">#<?= (int) $c['claim_id'] ?></td><td data-label="Item"><a href="<?= e(url('admin/audit_log.php?item=' . (int) $c['item_id'])) ?>">#<?= (int) $c['item_id'] ?></a></td>
<td data-label="Result"><span class="pill pill-<?= strtolower(e($c['verification_status'])) ?>"><?= e($c['verification_status']) ?></span></td>
<td data-label="Decided" class="nowrap"><?= e(substr($c['date_verified'], 0, 10)) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if ($dueCount > count($due)): ?><p class="small muted">… and <?= $dueCount - count($due) ?> more.</p><?php endif; ?>
<?php endif; ?>
<form method="post" action="<?= e(url('admin/retention.php')) ?>" onsubmit="return confirm('Remove proof text from <?= $dueCount ?> claim(s) and delete <?= $notesDue ?> old notification(s)? This cannot be undone.');">
<?= csrf_field() ?>
<button class="btn" type="submit">Run clean-up now</button>
</form>
<?php endif; ?>
<p class="small muted" style="margin:1rem 0 0">Kept: the claim itself (item, claimant, result, dates) and the audit trail. Pending claims are never touched. Each removal is recorded in the Audit Log as "Proof text removed".</p>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
