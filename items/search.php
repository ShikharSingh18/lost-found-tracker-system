<?php
/*
 * Module 3: Search and filter found items (Phase 4, Section 6.4, Table 16, Table 20).
 *
 * - Any logged-in user. Filters are optional and can be combined:
 *   keyword (q), category, location, date found from/to.
 * - Students and Staff only ever see items with status Found.
 *   Administrators can also filter by status.
 * - The SQL is built from a FIXED list of conditions. User input is only
 *   ever passed as bound values, so SQL injection is not possible.
 * - Field names match Simphiwe's search form: q, category, location, from, to.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$user = requireLogin();
$isAdmin = $user['role'] === 'Admin';
$pdo = db();

const ITEMS_PER_PAGE = 12;
const CATEGORY_ICONS = ['Keys' => '🔑', 'Wallet' => '👛', 'Phone' => '📱', 'ID Card' => '🪪',
                        'Clothing' => '🧥', 'Stationery' => '✏️', 'Other' => '📦'];

$categories = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_id')->fetchAll();
$categoryIds = array_map('intval', array_column($categories, 'category_id'));

// ---- Read and check the filters (Table 16). Bad values are ignored with a warning. ----
$warnings = [];
// Reads one text filter; anything that isn't plain text (e.g. ?q[]=x) becomes ''.
$get = fn (string $key): string => is_string($_GET[$key] ?? null) ? trim($_GET[$key]) : '';
$f = [
    'q'        => $get('q'),
    'category' => $get('category'),
    'location' => $get('location'),
    'from'     => $get('from'),
    'to'       => $get('to'),
    'status'   => $isAdmin ? $get('status') : 'Found',
];

if (mb_strlen($f['q']) > 100) {
    $f['q'] = mb_substr($f['q'], 0, 100);
    $warnings[] = 'Keyword was shortened to 100 characters.';
}
if (mb_strlen($f['location']) > 200) {
    $f['location'] = mb_substr($f['location'], 0, 200);
}
if ($f['category'] !== '' && !in_array((int) $f['category'], $categoryIds, true)) {
    $f['category'] = '';
    $warnings[] = 'Unknown category was ignored.';
}
foreach (['from' => 'From', 'to' => 'To'] as $key => $label) {
    if ($f[$key] !== '') {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $f[$key]);
        if (!$d || $d->format('Y-m-d') !== $f[$key]) {
            $warnings[] = "$label date was not valid and was ignored.";
            $f[$key] = '';
        }
    }
}
if ($f['from'] !== '' && $f['to'] !== '' && $f['from'] > $f['to']) {
    $warnings[] = '"From" date is after the "To" date, so the dates were swapped.';
    [$f['from'], $f['to']] = [$f['to'], $f['from']];
}
if ($isAdmin && !in_array($f['status'], ['', 'Found', 'Claimed', 'Disposed'], true)) {
    $f['status'] = '';
}

// ---- Build the WHERE clause from a fixed list of conditions ----
$where = [];
$params = [];

if ($f['status'] !== '') {
    $where[] = 'i.status = :status';
    $params[':status'] = $f['status'];
}
if ($f['q'] !== '') {
    // Escape LIKE wildcards so "%" or "_" typed by a user are treated as normal characters.
    $like = '%' . addcslashes($f['q'], '%_\\') . '%';
    $where[] = '(i.description LIKE :q1 OR c.category_name LIKE :q2 OR i.location_found LIKE :q3)';
    $params[':q1'] = $params[':q2'] = $params[':q3'] = $like;
}
if ($f['category'] !== '') {
    $where[] = 'i.category_id = :category';
    $params[':category'] = (int) $f['category'];
}
if ($f['location'] !== '') {
    $where[] = 'i.location_found LIKE :location';
    $params[':location'] = '%' . addcslashes($f['location'], '%_\\') . '%';
}
if ($f['from'] !== '') {
    $where[] = 'i.date_found >= :from';
    $params[':from'] = $f['from'];
}
if ($f['to'] !== '') {
    $where[] = 'i.date_found <= :to';
    $params[':to'] = $f['to'];
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ---- Count, then fetch one page ----
$stmt = $pdo->prepare("SELECT COUNT(*) FROM items i JOIN categories c ON c.category_id = i.category_id $whereSql");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

$pages = max(1, (int) ceil($total / ITEMS_PER_PAGE));
$page = min($pages, max(1, (int) $get('page')));

$stmt = $pdo->prepare(
    "SELECT i.item_id, i.description, i.date_found, i.location_found, i.status, c.category_name,
            (SELECT p.photo_id FROM item_photos p WHERE p.item_id = i.item_id ORDER BY p.sort_order LIMIT 1) AS thumb_id
     FROM items i JOIN categories c ON c.category_id = i.category_id
     $whereSql
     ORDER BY i.date_found DESC, i.item_id DESC
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue(':limit', ITEMS_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':offset', ($page - 1) * ITEMS_PER_PAGE, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll();

// Keeps the current filters when moving between pages.
function page_link(array $f, int $page, bool $isAdmin): string
{
    $query = array_filter($f, fn ($v) => $v !== '');
    if (!$isAdmin) {
        unset($query['status']);
    }
    $query['page'] = $page;
    return url('items/search.php?' . http_build_query($query));
}

$pageTitle = 'Search found items';
$activeNav = 'search';
$extraCss  = ['assets/css/photos.css'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Search found items</h1><p class="muted"><?= $isAdmin ? 'As an administrator you can also filter by status.' : 'Only items with status Found are shown.' ?></p></div>
<?php if ($warnings): ?>
<div class="alert alert-warn" role="status"><?php foreach ($warnings as $w): ?><div><?= e($w) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<div class="grid layout-side"><details class="card filters" open><summary>Search &amp; filters</summary>
<form class="form form-2" action="<?= e(url('items/search.php')) ?>" method="get" style="margin-top:.75rem">
<div class="field full"><label for="q">Keyword</label><input id="q" name="q" type="search" maxlength="100" placeholder="e.g. black, lanyard" value="<?= e($f['q']) ?>"></div>
<div class="field"><label for="category">Category</label><select id="category" name="category"><option value="">All categories</option>
<?php foreach ($categories as $c): ?>
<option value="<?= (int) $c['category_id'] ?>"<?= (string) $c['category_id'] === $f['category'] ? ' selected' : '' ?>><?= e($c['category_name']) ?></option>
<?php endforeach; ?>
</select></div>
<div class="field"><label for="location">Location found</label><input id="location" name="location" maxlength="200" value="<?= e($f['location']) ?>"></div>
<div class="field"><label for="from">Date found: from</label><input id="from" name="from" type="date" value="<?= e($f['from']) ?>"></div>
<div class="field"><label for="to">Date found: to</label><input id="to" name="to" type="date" value="<?= e($f['to']) ?>"></div>
<?php if ($isAdmin): ?>
<div class="field full"><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option>
<?php foreach (['Found', 'Claimed', 'Disposed'] as $s): ?>
<option<?= $f['status'] === $s ? ' selected' : '' ?>><?= $s ?></option>
<?php endforeach; ?>
</select></div>
<?php else: ?>
<div class="full"></div>
<?php endif; ?>
<div class="btn-row full"><button class="btn" type="submit">Apply filters</button><a class="btn btn-ghost" href="<?= e(url('items/search.php')) ?>">Clear</a></div></form></details>
<div><p class="small muted"><?= $total ?> item<?= $total === 1 ? '' : 's' ?> found</p>
<?php if (!$items): ?>
<div class="card"><p style="margin:0">No items match your search. Try fewer filters or a different keyword.</p></div>
<?php else: ?>
<div class="items">
<?php foreach ($items as $it):
    $desc = $it['description'];
    $short = mb_strlen($desc) > 90 ? mb_substr($desc, 0, 90) . '…' : $desc; ?>
<a class="item" href="<?= e(url('items/details.php?id=' . (int) $it['item_id'])) ?>">
<div class="thumb"<?= $it['thumb_id'] ? '' : ' aria-hidden="true"' ?>><?php if ($it['thumb_id']): ?><img src="<?= e(url('items/photo.php?id=' . (int) $it['thumb_id'])) ?>" alt="Photo of the found <?= e(strtolower($it['category_name'])) ?>" loading="lazy"><?php else: ?><?= CATEGORY_ICONS[$it['category_name']] ?? '📦' ?><?php endif; ?></div>
<div><h3><?= e($it['category_name']) ?></h3><p class="small" style="margin:0"><?= e($short) ?></p>
<div class="meta"><span>📅 <?= e($it['date_found']) ?></span><span>📍 <?= e($it['location_found']) ?></span></div>
<span class="pill pill-<?= e(strtolower($it['status'])) ?>"><?= e($it['status']) ?></span></div></a>
<?php endforeach; ?>
</div>
<?php if ($pages > 1): ?>
<nav class="pager" aria-label="Pagination">
<?php if ($page > 1): ?><a href="<?= e(page_link($f, $page - 1, $isAdmin)) ?>">Previous</a><?php endif; ?>
<?php for ($p = 1; $p <= $pages; $p++): ?>
<?php if ($p === $page): ?><span class="on" aria-current="page"><?= $p ?></span><?php else: ?><a href="<?= e(page_link($f, $p, $isAdmin)) ?>"><?= $p ?></a><?php endif; ?>
<?php endfor; ?>
<?php if ($page < $pages): ?><a href="<?= e(page_link($f, $page + 1, $isAdmin)) ?>">Next</a><?php endif; ?>
</nav>
<?php endif; ?>
<?php endif; ?>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
