<?php
/*
 * Shared page header and role-based menu (Phase 4, Section 7.2, Table 13).
 * Markup copied from Simphiwe's HTML pages so style.css still applies.
 *
 * Before including, a page can set:
 *   $pageTitle   text for the <title> tag
 *   $activeNav   key of the current menu item, e.g. 'search' (highlights it)
 *   $extraCss    extra stylesheets, e.g. ['assets/css/upload.css']
 *   $extraJs     extra scripts loaded by footer.php, e.g. ['assets/js/upload.js']
 */

$pageTitle ??= 'Lost & Found Tracker';
$activeNav ??= '';
$user = current_user();

// key => [label, page, roles allowed]   S = Student, T = Staff, A = Admin
$menu = [
    'home'          => ['Home',               'index.php',              ['Student', 'Staff', 'Admin']],
    'search'        => ['Search Found Items', 'items/search.php',       ['Student', 'Staff', 'Admin']],
    'my_claims'     => ['My Claims',          'claims/my_claims.php',   ['Student', 'Staff']],
    'item_add'      => ['Log Found Item',     'admin/item_add.php',     ['Staff', 'Admin']],
    'manage'        => ['Manage Items',       'items/manage.php',       ['Admin']],
    'claim_review'  => ['Review Claims',      'admin/claim_review.php', ['Admin']],
    'dashboard'     => ['Dashboard',          'admin/dashboard.php',    ['Admin']],
    'reports'       => ['Reports',            'admin/reports.php',      ['Admin']],   // Module 9
    'audit_log'     => ['Audit Log',          'admin/audit_log.php',    ['Admin']],
    'users'         => ['Users',              'admin/users.php',        ['Admin']],   // Module 8
    'notifications' => ['Notifications',      'notifications.php',      ['Student', 'Staff', 'Admin']],
    'account'       => ['My account',         'account.php',            ['Student', 'Staff', 'Admin']],   // Module 8
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#2f5bea">
<title><?= e($pageTitle) ?> | Lost &amp; Found Tracker</title>
<link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>">
<?php foreach ($extraCss ?? [] as $css): ?>
<link rel="stylesheet" href="<?= e(asset_url($css)) ?>">
<?php endforeach; ?>
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<header class="site-header">
  <div class="header-wrap">
    <div class="bar">
      <a class="brand" href="<?= e(url('index.php')) ?>"><span class="brand-mark" aria-hidden="true">🔎</span>Lost &amp; Found</a>
      <label class="nav-btn" for="nav-toggle"><span aria-hidden="true">☰</span><span class="sr-only">Toggle menu</span></label>
    </div>
    <input type="checkbox" id="nav-toggle" class="nav-toggle" aria-label="Toggle menu">
    <nav class="nav" aria-label="Main">
<?php if ($user === null): ?>
      <a href="<?= e(url('login.php')) ?>"<?= $activeNav === 'login' ? ' aria-current="page"' : '' ?>>Login</a>
      <a href="<?= e(url('register.php')) ?>"<?= $activeNav === 'register' ? ' aria-current="page"' : '' ?>>Register</a>
<?php else: ?>
<?php foreach ($menu as $navKey => [$navLabel, $navPage, $navRoles]):
        if (!in_array($user['role'], $navRoles, true)) continue; ?>
      <a href="<?= e(url($navPage)) ?>"<?= $activeNav === $navKey ? ' aria-current="page"' : '' ?>><span><?= e($navLabel) ?></span><?php
        if ($navKey === 'notifications'):
            $navUnread = unread_notification_count($user['user_id']);
            if ($navUnread > 0): ?><span class="badge-dot" aria-label="<?= $navUnread ?> unread"><?= $navUnread ?></span><?php endif;
        endif; ?></a>
<?php endforeach; ?>
      <a href="<?= e(url('logout.php?token=' . urlencode(csrf_token()))) ?>">Logout</a>
<?php endif; ?>
    </nav>
  </div>
</header>
<main id="main">
<?php if ($flashMsg = take_flash()): ?>
<div class="alert alert-<?= e($flashMsg['type']) ?>" role="<?= $flashMsg['type'] === 'bad' ? 'alert' : 'status' ?>"><?= e($flashMsg['msg']) ?></div>
<?php endif; ?>
