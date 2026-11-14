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
<?php
    // admin-only pages go in one "Admin" dropdown so the bar does not get crowded
    $adminKeys = ['manage', 'claim_review', 'dashboard', 'reports', 'audit_log', 'users'];
    $navLink = function (string $key, array $item) use ($activeNav, $user): string {
        $html = '<a href="' . e(url($item[1])) . '"' . ($activeNav === $key ? ' aria-current="page"' : '') . '><span>' . e($item[0]) . '</span>';
        if ($key === 'notifications') {
            $unread = unread_notification_count($user['user_id']);
            if ($unread > 0) $html .= '<span class="badge-dot" aria-label="' . $unread . ' unread">' . $unread . '</span>';
        }
        return $html . '</a>';
    };
    $adminLinks = '';
    $adminShown = false;
    foreach ($menu as $navKey => $navItem):
        if (!in_array($user['role'], $navItem[2], true)) continue;
        if (in_array($navKey, $adminKeys, true)) { $adminLinks .= $navLink($navKey, $navItem); continue; }
        // first non-admin link after the admin ones (Notifications) is where the dropdown goes
        if ($adminLinks !== '' && !$adminShown):
            $adminShown = true; ?>
      <details class="nav-more"><summary<?= in_array($activeNav, $adminKeys, true) ? ' aria-current="page"' : '' ?>>Admin</summary><div class="nav-menu"><?= $adminLinks ?></div></details>
<?php   endif;
        echo '      ', $navLink($navKey, $navItem), "\n";
    endforeach; ?>
      <a href="<?= e(url('logout.php?token=' . urlencode(csrf_token()))) ?>">Logout</a>
<?php endif; ?>
    </nav>
  </div>
</header>
<main id="main">
<?php if ($flashMsg = take_flash()): ?>
<div class="alert alert-<?= e($flashMsg['type']) ?>" role="<?= $flashMsg['type'] === 'bad' ? 'alert' : 'status' ?>"><?= e($flashMsg['msg']) ?></div>
<?php endif; ?>
