<?php
/*
 * Module 9: Audit log (Administrator) – FR10, Phase 4 Table 21 "Audit log report".
 * READ-ONLY: there is no edit or delete anywhere, so the trail can't be changed from the site.
 *
 *   ?log=items     (default) audit_log: every item and claim event (logged, edited, status
 *                  changed, disposed, collected, claim submitted/approved/rejected).
 *   ?log=accounts  user_admin_log (Module 8): account created, role changed, deactivated, …
 *   Filters: action, performed by, date from/to, item # / claim # (items) or account (accounts).
 *   &export=csv    same filters, downloaded as CSV.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/reports.php';

requireRole('Admin');
$pdo = db();
const AUDIT_PER_PAGE = 50;

$get = fn (string $k): string => is_string($_GET[$k] ?? null) ? trim($_GET[$k]) : '';
$posInt = fn (string $k): int => max(0, (int) filter_var($get($k), FILTER_VALIDATE_INT, ['options' => ['default' => 0]]));
$log = $get('log') === 'accounts' ? 'accounts' : 'items';
$labels = $log === 'items' ? AUDIT_LABELS : ACCOUNT_LABELS;
[$from, $to, $dateWarning] = report_dates($get('from'), $get('to'));
$action = array_key_exists($get('action'), $labels) ? $get('action') : '';
$users = $pdo->query('SELECT user_id, full_name, role FROM users ORDER BY full_name')->fetchAll();
$userIds = array_map('intval', array_column($users, 'user_id'));
$by = in_array($posInt('by'), $userIds, true) ? $posInt('by') : 0;
$itemId = $log === 'items' ? $posInt('item') : 0;
$claimId = $log === 'items' ? $posInt('claim') : 0;
$target = $log === 'accounts' && in_array($posInt('account'), $userIds, true) ? $posInt('account') : 0;

$where = [];
$params = [];
if ($action !== '') { $where[] = 'l.action = :a'; $params[':a'] = $action; }
if ($by) { $where[] = 'l.performed_by = :by'; $params[':by'] = $by; }
if ($from) { $where[] = 'l.occurred_at >= :from'; $params[':from'] = $from; }
if ($to) { $where[] = 'l.occurred_at < :to'; $params[':to'] = day_after($to); }
if ($itemId) { $where[] = 'l.item_id = :item'; $params[':item'] = $itemId; }
if ($claimId) { $where[] = 'l.claim_id = :claim'; $params[':claim'] = $claimId; }
if ($target) { $where[] = 'l.target_user_id = :t'; $params[':t'] = $target; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

if ($log === 'items') {
    $fromSql = 'FROM audit_log l JOIN users u ON u.user_id = l.performed_by';
    $select = 'SELECT l.log_id, l.occurred_at, l.action, l.item_id, l.claim_id, u.full_name AS actor, u.role AS actor_role';
} else {
    $fromSql = 'FROM user_admin_log l JOIN users u ON u.user_id = l.performed_by JOIN users t ON t.user_id = l.target_user_id';
    $select = 'SELECT l.log_id, l.occurred_at, l.action, l.detail, t.full_name AS target, t.email AS target_email, u.full_name AS actor, u.role AS actor_role';
}
$order = 'ORDER BY l.occurred_at DESC, l.log_id DESC';

// ---------- CSV ----------
if ($get('export') === 'csv') {
    $stmt = $pdo->prepare("$select $fromSql $whereSql $order");
    $stmt->execute($params);
    $stamp = date('Y-m-d_Hi');
    if ($log === 'items') {
        $rows = (function () use ($stmt) {
            while ($r = $stmt->fetch()) {
                yield [$r['log_id'], substr($r['occurred_at'], 0, 19), $r['action'], AUDIT_LABELS[$r['action']] ?? '', $r['item_id'] ?? '', $r['claim_id'] ?? '', $r['actor'], $r['actor_role']];
            }
        })();
        send_csv("audit_log_$stamp.csv", ['Log ID', 'Date and time', 'Action', 'Meaning', 'Item ID', 'Claim ID', 'Performed by', 'Role'], $rows);
    }
    $rows = (function () use ($stmt) {
        while ($r = $stmt->fetch()) {
            yield [$r['log_id'], substr($r['occurred_at'], 0, 19), $r['action'], ACCOUNT_LABELS[$r['action']] ?? '', $r['detail'] ?? '', $r['target'], $r['target_email'], $r['actor'], $r['actor_role']];
        }
    })();
    send_csv("account_changes_$stamp.csv", ['Log ID', 'Date and time', 'Action', 'Meaning', 'Detail', 'Account', 'Account email', 'Performed by', 'Role'], $rows);
}

// ---------- Screen ----------
$stmt = $pdo->prepare("SELECT COUNT(*) $fromSql $whereSql");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$pages = max(1, (int) ceil($total / AUDIT_PER_PAGE));
$page = min($pages, max(1, (int) $get('page')));
$stmt = $pdo->prepare("$select $fromSql $whereSql $order LIMIT :limit OFFSET :offset");
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', AUDIT_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':offset', ($page - 1) * AUDIT_PER_PAGE, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$query = array_filter(['log' => $log === 'accounts' ? 'accounts' : '', 'action' => $action, 'by' => $by ?: '', 'from' => $from ?? '',
    'to' => $to ?? '', 'item' => $itemId ?: '', 'claim' => $claimId ?: '', 'account' => $target ?: ''], fn ($v) => $v !== '');
$filtered = count(array_diff_key($query, ['log' => 1])) > 0;

$pageTitle = 'Audit log';
$activeNav = 'audit_log';
$extraCss  = ['assets/css/photos.css'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Audit log</h1><p class="muted">Every recorded action, newest first. Read-only: entries can't be edited or deleted.</p></div>

<nav class="tabs" aria-label="Log">
<a href="<?= e(url('admin/audit_log.php')) ?>"<?= $log === 'items' ? ' class="on" aria-current="page"' : '' ?>>Items and claims</a>
<a href="<?= e(url('admin/audit_log.php?log=accounts')) ?>"<?= $log === 'accounts' ? ' class="on" aria-current="page"' : '' ?>>Account changes</a>
</nav>

<?php if ($dateWarning): ?><div class="alert alert-warn" role="status"><?= e($dateWarning) ?></div><?php endif; ?>

<details class="card filters"<?= $filtered ? ' open' : '' ?>><summary>Filters<?= $filtered ? ' (on)' : '' ?></summary>
<form class="form form-2" action="<?= e(url('admin/audit_log.php')) ?>" method="get" style="margin-top:.75rem">
<?php if ($log === 'accounts'): ?><input type="hidden" name="log" value="accounts"><?php endif; ?>
<div class="field"><label for="action">Action</label><select id="action" name="action"><option value="">Any action</option>
<?php foreach ($labels as $k => $l): ?><option value="<?= e($k) ?>"<?= $action === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="by">Performed by</label><select id="by" name="by"><option value="">Anyone</option>
<?php foreach ($users as $u): ?><option value="<?= (int) $u['user_id'] ?>"<?= $by === (int) $u['user_id'] ? ' selected' : '' ?>><?= e($u['full_name']) ?> (<?= e($u['role']) ?>)</option><?php endforeach; ?></select></div>
<div class="field"><label for="from">From</label><input id="from" name="from" type="date" value="<?= e($from ?? '') ?>"></div>
<div class="field"><label for="to">To</label><input id="to" name="to" type="date" value="<?= e($to ?? '') ?>"></div>
<?php if ($log === 'items'): ?>
<div class="field"><label for="item">Item #</label><input id="item" name="item" type="number" min="1" inputmode="numeric" value="<?= $itemId ?: '' ?>"></div>
<div class="field"><label for="claim">Claim #</label><input id="claim" name="claim" type="number" min="1" inputmode="numeric" value="<?= $claimId ?: '' ?>"></div>
<?php else: ?>
<div class="field"><label for="account">Account</label><select id="account" name="account"><option value="">Any account</option>
<?php foreach ($users as $u): ?><option value="<?= (int) $u['user_id'] ?>"<?= $target === (int) $u['user_id'] ? ' selected' : '' ?>><?= e($u['full_name']) ?></option><?php endforeach; ?></select></div>
<?php endif; ?>
<div class="btn-row full"><button class="btn" type="submit">Apply filters</button>
<a class="btn btn-ghost" href="<?= e(url('admin/audit_log.php' . ($log === 'accounts' ? '?log=accounts' : ''))) ?>">Clear</a></div>
</form></details>

<div class="report-bar">
<p class="small muted" style="margin:0"><b><?= $total ?></b> entr<?= $total === 1 ? 'y' : 'ies' ?><?= $pages > 1 ? " · page $page of $pages" : '' ?></p>
<?= csv_buttons('admin/audit_log.php', $query, $total) ?>
</div>

<?php if (!$rows): ?>
<div class="card"><p style="margin:0">No entries match these filters.</p></div>
<?php elseif ($log === 'items'): ?>
<div class="table-wrap"><table><thead><tr><th>#</th><th>When</th><th>Action</th><th>Item</th><th>Claim</th><th>By</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td data-label="#"><?= (int) $r['log_id'] ?></td>
<td data-label="When" class="nowrap"><?= e(substr($r['occurred_at'], 0, 19)) ?></td>
<td data-label="Action"><div><?= e(AUDIT_LABELS[$r['action']] ?? $r['action']) ?><br><span class="small muted"><?= e($r['action']) ?></span></div></td>
<td data-label="Item"><?= $r['item_id'] ? '<a href="' . e(url('admin/audit_log.php?item=' . (int) $r['item_id'])) . '" title="All entries for this item">#' . (int) $r['item_id'] . '</a>' : '<span class="muted">–</span>' ?></td>
<td data-label="Claim"><?= $r['claim_id'] ? '<a href="' . e(url('admin/audit_log.php?claim=' . (int) $r['claim_id'])) . '" title="All entries for this claim">#' . (int) $r['claim_id'] . '</a>' : '<span class="muted">–</span>' ?></td>
<td data-label="By"><div><?= e($r['actor']) ?> <span class="small muted">(<?= e($r['actor_role']) ?>)</span></div></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<p class="small muted">Tip: click an item or claim number to see its full history.</p>
<?php else: ?>
<div class="table-wrap"><table><thead><tr><th>#</th><th>When</th><th>Account</th><th>Change</th><th>By</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td data-label="#"><?= (int) $r['log_id'] ?></td>
<td data-label="When" class="nowrap"><?= e(substr($r['occurred_at'], 0, 19)) ?></td>
<td data-label="Account"><?= e($r['target']) ?></td>
<td data-label="Change"><div><?= e(ACCOUNT_LABELS[$r['action']] ?? $r['action']) ?><?= $r['detail'] ? ' <span class="small muted">(' . e($r['detail']) . ')</span>' : '' ?></div></td>
<td data-label="By"><?= e($r['actor']) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<?= pager_links('admin/audit_log.php', $query, $page, $pages) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
