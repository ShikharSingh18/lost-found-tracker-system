<?php
/*
 * Module 9: Dashboard (Administrator) – Phase 4 Table 21 "Dashboard summary" and FR8.
 * Summary cards (items logged, Found, Claimed, collected, Disposed, pending claims,
 * approved/rejected claims), charts (status split, items by category, activity per month;
 * switchable chart types, PNG download, print), claims waiting longest, Found items
 * waiting longest (helps the disposal/retention decision) and the latest audit events.
 * Read-only: nothing on this page changes data.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/reports.php';
require_once __DIR__ . '/../includes/chart_data.php';

requireRole('Admin');
$pdo = db();

$items = $pdo->query(
    "SELECT COUNT(*) AS total,
            SUM(CASE WHEN status = 'Found' THEN 1 ELSE 0 END) AS found,
            SUM(CASE WHEN status = 'Claimed' THEN 1 ELSE 0 END) AS claimed,
            SUM(CASE WHEN status = 'Disposed' THEN 1 ELSE 0 END) AS disposed
     FROM items"
)->fetch();
$claims = $pdo->query(
    "SELECT COUNT(*) AS total,
            SUM(CASE WHEN verification_status = 'Pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN verification_status = 'Approved' THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN verification_status = 'Rejected' THEN 1 ELSE 0 END) AS rejected
     FROM claims"
)->fetch();
$collected = (int) $pdo->query("SELECT COUNT(DISTINCT item_id) FROM audit_log WHERE action = 'ITEM_RELEASED'")->fetchColumn();
$n = fn ($v) => (int) $v;   // SUM() is NULL on an empty table

$thisMonth = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
$stmt = $pdo->prepare('SELECT COUNT(*) FROM items WHERE created_at >= :d');
$stmt->execute([':d' => $thisMonth]);
$loggedThisMonth = (int) $stmt->fetchColumn();

$byCategory = $pdo->query(
    "SELECT c.category_name, COUNT(i.item_id) AS total,
            SUM(CASE WHEN i.status = 'Found' THEN 1 ELSE 0 END) AS found,
            SUM(CASE WHEN i.status = 'Claimed' THEN 1 ELSE 0 END) AS claimed,
            SUM(CASE WHEN i.status = 'Disposed' THEN 1 ELSE 0 END) AS disposed
     FROM categories c LEFT JOIN items i ON i.category_id = c.category_id
     GROUP BY c.category_id, c.category_name
     ORDER BY total DESC, c.category_name"
)->fetchAll();

$pendingOldest = $pdo->query(
    "SELECT cl.claim_id, cl.date_submitted, i.item_id, c.category_name, u.full_name
     FROM claims cl JOIN items i ON i.item_id = cl.item_id JOIN categories c ON c.category_id = i.category_id
     JOIN users u ON u.user_id = cl.claimant_user_id
     WHERE cl.verification_status = 'Pending'
     ORDER BY cl.date_submitted ASC LIMIT 5"
)->fetchAll();

$foundOldest = $pdo->query(
    "SELECT i.item_id, i.date_found, i.location_found, c.category_name
     FROM items i JOIN categories c ON c.category_id = i.category_id
     WHERE i.status = 'Found' ORDER BY i.date_found ASC, i.item_id ASC LIMIT 5"
)->fetchAll();

$recent = $pdo->query(
    'SELECT a.action, a.item_id, a.claim_id, a.occurred_at, u.full_name
     FROM audit_log a JOIN users u ON u.user_id = a.performed_by
     ORDER BY a.log_id DESC LIMIT 8'
)->fetchAll();

$today = new DateTimeImmutable('today');
$daysAgo = function (string $date) use ($today): string {
    $d = (new DateTimeImmutable(substr($date, 0, 10)))->diff($today)->days;
    return $d === 0 ? 'today' : ($d === 1 ? '1 day' : "$d days");
};

// ---------- Charts ----------
$catRows = array_values(array_filter($byCategory, fn ($r) => (int) $r['total'] > 0));
$charts = [
    chart_status_cfg('items-status', 'Items by status', STATUS_LABELS,
        ['Found' => $n($items['found']), 'Claimed' => $n($items['claimed']), 'Disposed' => $n($items['disposed'])], 'Items'),
    chart_status_cfg('claims-status', 'Claims by status', CLAIM_STATUSES,
        ['Pending' => $n($claims['pending']), 'Approved' => $n($claims['approved']), 'Rejected' => $n($claims['rejected'])], 'Claims'),
    chart_cfg('items-category', 'Items by category', 'hbar', array_column($catRows, 'category_name'), [
        ['name' => 'Found', 'data' => array_map(fn ($r) => (int) $r['found'], $catRows), 'color' => CHART_STATUS_COLORS['Found']],
        ['name' => 'Claimed', 'data' => array_map(fn ($r) => (int) $r['claimed'], $catRows), 'color' => CHART_STATUS_COLORS['Claimed']],
        ['name' => 'Disposed', 'data' => array_map(fn ($r) => (int) $r['disposed'], $catRows), 'color' => CHART_STATUS_COLORS['Disposed']],
    ], ['types' => ['hbar', 'stacked', 'bar', 'doughnut', 'pie']]),
    chart_monthly($pdo),
];

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$extraCss  = ['assets/css/photos.css', 'assets/css/charts.css'];
$extraJs   = ['assets/js/charts.js'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Dashboard</h1><p class="muted">Summary of items and claims. Figures are live – reload the page to update them.
<button type="button" class="btn btn-ghost btn-sm no-print" data-print style="margin-left:.5rem">Print dashboard</button></p></div>
<div class="print-only"><p class="small">Dashboard · printed <?= e(date('Y-m-d H:i')) ?></p></div>

<h2 class="dash-h">Items</h2>
<div class="stats">
<a class="stat stat-link" href="<?= e(url('admin/reports.php')) ?>"><b><?= $n($items['total']) ?></b><span>Items logged (<?= $loggedThisMonth ?> this month)</span></a>
<a class="stat stat-link" href="<?= e(url('admin/reports.php?status=Found')) ?>"><b><?= $n($items['found']) ?></b><span>Found – waiting for owner</span></a>
<a class="stat stat-link" href="<?= e(url('admin/reports.php?status=Claimed')) ?>"><b><?= $n($items['claimed']) ?></b><span>Claimed (<?= $collected ?> collected)</span></a>
<a class="stat stat-link" href="<?= e(url('admin/reports.php?status=Disposed')) ?>"><b><?= $n($items['disposed']) ?></b><span>Disposed</span></a>
</div>

<h2 class="dash-h">Claims</h2>
<div class="stats">
<a class="stat stat-link<?= $n($claims['pending']) ? ' stat-warn' : '' ?>" href="<?= e(url('admin/claim_review.php')) ?>"><b><?= $n($claims['pending']) ?></b><span>Pending – waiting for a decision</span></a>
<a class="stat stat-link" href="<?= e(url('admin/reports.php?type=claims&status=Approved')) ?>"><b><?= $n($claims['approved']) ?></b><span>Approved</span></a>
<a class="stat stat-link" href="<?= e(url('admin/reports.php?type=claims&status=Rejected')) ?>"><b><?= $n($claims['rejected']) ?></b><span>Rejected</span></a>
<a class="stat stat-link" href="<?= e(url('admin/reports.php?type=claims')) ?>"><b><?= $n($claims['total']) ?></b><span>Claims submitted</span></a>
</div>

<h2 class="dash-h">Charts</h2>
<p class="small muted" style="margin-top:0">Hover a bar, slice or point for the exact figure. Use the dropdown on a chart to change its type, and PNG to save it as an image.</p>
<div class="chart-grid">
<?php foreach ($charts as $k => $ch) echo chart_card($ch, $k === 3 ? 'chart-wide' : ''), "\n"; ?>
</div>

<div class="dash-grid">
<section class="card">
<h2 style="margin-top:0">Claims waiting longest</h2>
<?php if (!$pendingOldest): ?><p class="muted" style="margin-bottom:0">All claims have been decided.</p><?php else: ?>
<ul class="dash-list">
<?php foreach ($pendingOldest as $p): ?>
<li><a href="<?= e(url('admin/claim_review.php')) ?>">Claim #<?= (int) $p['claim_id'] ?></a> – <?= e($p['category_name']) ?> (item #<?= (int) $p['item_id'] ?>) by <?= e($p['full_name']) ?> <span class="small muted">· waiting <?= e($daysAgo($p['date_submitted'])) ?></span></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</section>

<section class="card">
<h2 style="margin-top:0">Found items waiting longest</h2>
<?php if (!$foundOldest): ?><p class="muted" style="margin-bottom:0">No Found items at the moment.</p><?php else: ?>
<ul class="dash-list">
<?php foreach ($foundOldest as $f): ?>
<li><a href="<?= e(url('items/details.php?id=' . (int) $f['item_id'])) ?>"><?= e($f['category_name']) ?> (item #<?= (int) $f['item_id'] ?>)</a> – <?= e($f['location_found']) ?> <span class="small muted">· found <?= e($f['date_found']) ?>, <?= e($daysAgo($f['date_found']) === 'today' ? 'today' : $daysAgo($f['date_found']) . ' ago') ?></span></li>
<?php endforeach; ?>
</ul>
<p class="small muted" style="margin-bottom:0">Useful when deciding what to dispose of under the retention rule.</p>
<?php endif; ?>
</section>

<section class="card">
<h2 style="margin-top:0">Latest activity</h2>
<?php if (!$recent): ?><p class="muted" style="margin-bottom:0">No activity yet.</p><?php else: ?>
<ul class="dash-list">
<?php foreach ($recent as $r): ?>
<li><?= e(AUDIT_LABELS[$r['action']] ?? $r['action']) ?>
<?= $r['item_id'] ? ' · item #' . (int) $r['item_id'] : '' ?><?= $r['claim_id'] ? ' · claim #' . (int) $r['claim_id'] : '' ?> <span class="small muted">· <?= e($r['full_name']) ?>, <?= e(substr($r['occurred_at'], 0, 16)) ?></span></li>
<?php endforeach; ?>
</ul>
<p style="margin-bottom:0"><a href="<?= e(url('admin/audit_log.php')) ?>">Full audit log →</a></p>
<?php endif; ?>
</section>
</div>

<div class="btn-row dash-links" style="margin-top:1rem">
<a class="btn" href="<?= e(url('admin/reports.php')) ?>">Items report</a>
<a class="btn btn-outline" href="<?= e(url('admin/reports.php?type=claims')) ?>">Claims report</a>
<a class="btn btn-outline" href="<?= e(url('admin/audit_log.php')) ?>">Audit log</a>
<a class="btn btn-outline" href="<?= e(url('admin/retention.php')) ?>">Retention clean-up</a>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
