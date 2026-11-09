<?php
/*
 * Module 9: Reports (Administrator) – Phase 4 Table 21/22 and FR8
 * ("reports summarising items logged, claimed and pending, viewable and exportable").
 *
 *   ?type=items  (default)  Item, category, date found, location, status, logged by, collected
 *                           Filters: date found from/to, status, category.
 *   ?type=claims            Claim, item, claimant, status, submitted, decided, decided by
 *                           Filters: date submitted from/to, status.
 *   &export=csv             Same filters, downloaded as a CSV file (opens in Excel).
 *
 * POPIA (data minimisation): the claims report never includes the proof-of-ownership
 * text, and the claimant's email is only in the CSV, not on screen. Read-only page.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/reports.php';

requireRole('Admin');
$pdo = db();
const REPORT_PER_PAGE = 25;

$get = fn (string $k): string => is_string($_GET[$k] ?? null) ? trim($_GET[$k]) : '';
$type = $get('type') === 'claims' ? 'claims' : 'items';
[$from, $to, $dateWarning] = report_dates($get('from'), $get('to'));
$status = $get('status');
$categories = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name')->fetchAll();
$categoryId = (int) $get('category');
if (!in_array($categoryId, array_map('intval', array_column($categories, 'category_id')), true)) $categoryId = 0;

$where = [];
$params = [];
if ($type === 'items') {
    if (!in_array($status, STATUS_LABELS, true)) $status = '';
    if ($from) { $where[] = 'i.date_found >= :from'; $params[':from'] = $from; }
    if ($to)   { $where[] = 'i.date_found <= :to';   $params[':to'] = $to; }
    if ($status !== '') { $where[] = 'i.status = :st'; $params[':st'] = $status; }
    if ($categoryId) { $where[] = 'i.category_id = :cat'; $params[':cat'] = $categoryId; }
    $from_sql = "FROM items i JOIN categories c ON c.category_id = i.category_id JOIN users u ON u.user_id = i.logged_by
                 LEFT JOIN (SELECT item_id, MIN(occurred_at) AS released_at FROM audit_log WHERE action = 'ITEM_RELEASED' GROUP BY item_id) r ON r.item_id = i.item_id";
    $select = 'SELECT i.item_id, c.category_name, i.description, i.date_found, i.location_found, i.status, u.full_name AS logged_by, i.created_at, r.released_at';
    $order = 'ORDER BY i.date_found DESC, i.item_id DESC';
} else {
    if (!in_array($status, CLAIM_STATUSES, true)) $status = '';
    $categoryId = 0;
    if ($from) { $where[] = 'cl.date_submitted >= :from'; $params[':from'] = $from; }
    if ($to)   { $where[] = 'cl.date_submitted < :to';    $params[':to'] = day_after($to); }
    if ($status !== '') { $where[] = 'cl.verification_status = :st'; $params[':st'] = $status; }
    $from_sql = 'FROM claims cl JOIN items i ON i.item_id = cl.item_id JOIN categories c ON c.category_id = i.category_id
                 JOIN users cu ON cu.user_id = cl.claimant_user_id LEFT JOIN users vu ON vu.user_id = cl.verified_by';
    $select = 'SELECT cl.claim_id, cl.item_id, c.category_name, cu.full_name AS claimant, cu.email AS claimant_email,
                      cl.verification_status, cl.date_submitted, cl.date_verified, vu.full_name AS verifier';
    $order = 'ORDER BY cl.date_submitted DESC, cl.claim_id DESC';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ---------- CSV export (same filters, all rows) ----------
if ($get('export') === 'csv') {
    $stmt = $pdo->prepare("$select $from_sql $whereSql $order");
    $stmt->execute($params);
    $stamp = date('Y-m-d_Hi');
    if ($type === 'items') {
        $rows = (function () use ($stmt) {
            while ($r = $stmt->fetch()) {
                yield [$r['item_id'], $r['category_name'], $r['description'], $r['date_found'], $r['location_found'],
                       $r['status'], $r['released_at'] ? substr($r['released_at'], 0, 16) : '', $r['logged_by'], substr($r['created_at'], 0, 16)];
            }
        })();
        send_csv("items_report_$stamp.csv",
            ['Item ID', 'Category', 'Description', 'Date found', 'Location', 'Status', 'Collected', 'Logged by', 'Logged at'], $rows);
    }
    $rows = (function () use ($stmt) {
        while ($r = $stmt->fetch()) {
            yield [$r['claim_id'], $r['item_id'], $r['category_name'], $r['claimant'], $r['claimant_email'], $r['verification_status'],
                   substr($r['date_submitted'], 0, 16), $r['date_verified'] ? substr($r['date_verified'], 0, 16) : '', $r['verifier'] ?? ''];
        }
    })();
    send_csv("claims_report_$stamp.csv",
        ['Claim ID', 'Item ID', 'Category', 'Claimant', 'Claimant email', 'Status', 'Submitted', 'Decided', 'Decided by'], $rows);
}

// ---------- Screen ----------
$stmt = $pdo->prepare("SELECT COUNT(*) $from_sql $whereSql");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$pages = max(1, (int) ceil($total / REPORT_PER_PAGE));
$page = min($pages, max(1, (int) $get('page')));

$stmt = $pdo->prepare("$select $from_sql $whereSql $order LIMIT :limit OFFSET :offset");
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', REPORT_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':offset', ($page - 1) * REPORT_PER_PAGE, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

// status totals for the current date/category filter (ignores the status filter)
$countWhere = array_values(array_filter($where, fn ($w) => !str_contains($w, ':st')));
$countParams = array_diff_key($params, [':st' => 1]);
$col = $type === 'items' ? 'i.status' : 'cl.verification_status';
$stmt = $pdo->prepare("SELECT $col AS s, COUNT(*) AS n $from_sql " . ($countWhere ? 'WHERE ' . implode(' AND ', $countWhere) : '') . " GROUP BY $col");
$stmt->execute($countParams);
$byStatus = array_column($stmt->fetchAll(), 'n', 's');

$query = array_filter(['type' => $type === 'claims' ? 'claims' : '', 'from' => $from ?? '', 'to' => $to ?? '',
    'status' => $status, 'category' => $categoryId ?: ''], fn ($v) => $v !== '');

$pageTitle = $type === 'items' ? 'Items report' : 'Claims report';
$activeNav = 'reports';
$extraCss  = ['assets/css/photos.css'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Reports</h1><p class="muted">View and export items and claims. Exports use the filters you set here.</p></div>

<nav class="tabs" aria-label="Report type">
<a href="<?= e(url('admin/reports.php')) ?>"<?= $type === 'items' ? ' class="on" aria-current="page"' : '' ?>>Items report</a>
<a href="<?= e(url('admin/reports.php?type=claims')) ?>"<?= $type === 'claims' ? ' class="on" aria-current="page"' : '' ?>>Claims report</a>
</nav>

<?php if ($dateWarning): ?><div class="alert alert-warn" role="status"><?= e($dateWarning) ?></div><?php endif; ?>

<details class="card filters" open><summary>Filters</summary>
<form class="form form-2" action="<?= e(url('admin/reports.php')) ?>" method="get" style="margin-top:.75rem">
<?php if ($type === 'claims'): ?><input type="hidden" name="type" value="claims"><?php endif; ?>
<div class="field"><label for="from"><?= $type === 'items' ? 'Found from' : 'Submitted from' ?></label><input id="from" name="from" type="date" value="<?= e($from ?? '') ?>"></div>
<div class="field"><label for="to"><?= $type === 'items' ? 'Found to' : 'Submitted to' ?></label><input id="to" name="to" type="date" value="<?= e($to ?? '') ?>"></div>
<div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">Any status</option>
<?php foreach ($type === 'items' ? STATUS_LABELS : CLAIM_STATUSES as $s): ?><option<?= $status === $s ? ' selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
</select></div>
<?php if ($type === 'items'): ?>
<div class="field"><label for="category">Category</label><select id="category" name="category"><option value="">Any category</option>
<?php foreach ($categories as $c): ?><option value="<?= (int) $c['category_id'] ?>"<?= $categoryId === (int) $c['category_id'] ? ' selected' : '' ?>><?= e($c['category_name']) ?></option><?php endforeach; ?>
</select></div>
<?php endif; ?>
<div class="btn-row full"><button class="btn" type="submit">Apply filters</button>
<a class="btn btn-ghost" href="<?= e(url('admin/reports.php' . ($type === 'claims' ? '?type=claims' : ''))) ?>">Clear</a></div>
</form></details>

<div class="report-bar">
<p class="small muted" style="margin:0"><b><?= $total ?></b> <?= $type === 'items' ? 'item' : 'claim' ?><?= $total === 1 ? '' : 's' ?>
<?php foreach ($type === 'items' ? STATUS_LABELS : CLAIM_STATUSES as $s): ?> · <?= $s ?> <?= (int) ($byStatus[$s] ?? 0) ?><?php endforeach; ?></p>
<?= csv_buttons('admin/reports.php', $query, $total) ?>
</div>

<?php if (!$rows): ?>
<div class="card"><p style="margin:0">Nothing matches these filters.</p></div>
<?php elseif ($type === 'items'): ?>
<div class="table-wrap"><table><thead><tr><th>Item</th><th>Category</th><th>Date found</th><th>Location</th><th>Status</th><th>Logged by</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td data-label="Item"><a href="<?= e(url('items/details.php?id=' . (int) $r['item_id'])) ?>">#<?= (int) $r['item_id'] ?></a></td>
<td data-label="Category"><?= e($r['category_name']) ?></td>
<td data-label="Date found" class="nowrap"><?= e($r['date_found']) ?></td>
<td data-label="Location"><?= e($r['location_found']) ?></td>
<td data-label="Status"><div><?= claim_pill($r['status']) ?><?= $r['released_at'] ? '<br><span class="small muted">Collected ' . e(substr($r['released_at'], 0, 10)) . '</span>' : '' ?></div></td>
<td data-label="Logged by"><?= e($r['logged_by']) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Claim</th><th>Item</th><th>Claimant</th><th>Status</th><th>Submitted</th><th>Decided</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td data-label="Claim">#<?= (int) $r['claim_id'] ?></td>
<td data-label="Item"><a href="<?= e(url('items/details.php?id=' . (int) $r['item_id'])) ?>"><?= e($r['category_name']) ?> #<?= (int) $r['item_id'] ?></a></td>
<td data-label="Claimant"><?= e($r['claimant']) ?></td>
<td data-label="Status"><?= claim_pill($r['verification_status']) ?></td>
<td data-label="Submitted" class="nowrap"><?= e(substr($r['date_submitted'], 0, 16)) ?></td>
<td data-label="Decided"><div><?= $r['date_verified'] ? e(substr($r['date_verified'], 0, 16)) . '<br><span class="small muted">by ' . e($r['verifier'] ?? '') . '</span>' : '<span class="muted">–</span>' ?></div></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<?= pager_links('admin/reports.php', $query, $page, $pages) ?>
<p class="small muted" style="margin-top:1rem">Privacy: proof-of-ownership text is never included in reports. The CSV of the claims report contains claimants' names and emails – keep it on a college computer and delete it when it is no longer needed (POPIA).</p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
