<?php
/*
 * Home page: centred welcome, live item search for everyone, and a "recently found"
 * spotlight for Staff and Admin (a bit like the "someone just bought..." notices in online shops).
 * The search and the spotlight read items/live_search.php and items/recent.php (JSON).
 */
require_once __DIR__ . '/includes/bootstrap.php';

$user = requireLogin();
$showRecent = $user['role'] !== 'Student';

$pageTitle = 'Home';
$activeNav = 'home';
$extraCss  = ['assets/css/home.css'];
$extraJs   = ['assets/js/home-search.js'];
require __DIR__ . '/includes/header.php';
?>
<div class="home-hero">
<h1>Welcome, <?= e($user['full_name']) ?></h1>
<p class="muted">You are logged in as <strong><?= e($user['role']) ?></strong>.</p>
</div>

<div class="home-grid<?= $showRecent ? ' has-recent' : '' ?>">
<section class="card hs" id="home-search"
 data-search-url="<?= e(url('items/live_search.php')) ?>"
 data-details-url="<?= e(url('items/details.php')) ?>"
 data-photo-url="<?= e(url('items/photo.php')) ?>"
 data-all-url="<?= e(url('items/search.php')) ?>">
<h2 class="hs-title"><label for="home-q">Search found items</label></h2>
<p class="small muted">Start typing a keyword, category, description, place or date. Results appear as you type.</p>
<form class="hs-form" action="<?= e(url('items/search.php')) ?>" method="get" role="search">
<input id="home-q" name="q" type="search" maxlength="100" autocomplete="off" placeholder="e.g. black wallet, library, 20 November">
<button class="btn" type="submit">Search</button>
</form>
<div id="home-results" class="hs-results" aria-live="polite" hidden></div>
</section>
<?php if ($showRecent): ?>

<aside class="card rc" id="recent-panel" data-url="<?= e(url('items/recent.php')) ?>"
 data-details-url="<?= e(url('items/details.php')) ?>" data-photo-url="<?= e(url('items/photo.php')) ?>" aria-label="Recently found items">
<h2 class="hs-title">Recently found</h2>
<div class="rc-stage"></div>
<div class="rc-dots" role="group" aria-label="Choose a recent item"></div>
<p class="small muted rc-empty" hidden>Nothing has been logged yet.</p>
<p class="small" style="margin:.75rem 0 0"><a href="<?= e(url('items/search.php')) ?>">Browse all found items &rarr;</a></p>
</aside>
<?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
