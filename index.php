<?php
/*
 * Temporary home page so login can be tested end to end.
 * Simphiwe's index.html content can replace the body later.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$user = requireLogin();

$pageTitle = 'Home';
$activeNav = 'home';
require __DIR__ . '/includes/header.php';
?>
<div class="stack"><div class="page-head"><h1>Welcome, <?= e($user['full_name']) ?></h1>
<p class="muted">You are logged in as <strong><?= e($user['role']) ?></strong>.</p></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
