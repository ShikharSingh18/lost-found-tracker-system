<?php
/*
 * NOTE-01: friendly error page for Apache errors (403 Forbidden, 404 Not Found, 500).
 * Used by .htaccess (ErrorDocument). Replaces Apache's default page, which shows the
 * Apache / OpenSSL / PHP versions to anyone (information an attacker could use).
 */
require_once __DIR__ . '/includes/bootstrap.php';

$code = (int) ($_SERVER['REDIRECT_STATUS'] ?? ($_GET['code'] ?? 404));
if (!in_array($code, [403, 404, 500], true)) $code = 404;
http_response_code($code);

$texts = [
    403 => ['Access denied', 'You are not allowed to open this address.'],
    404 => ['Page not found', 'This page does not exist or has been moved.'],
    500 => ['Something went wrong', 'The server could not finish your request. Please try again in a moment.'],
];
[$title, $msg] = $texts[$code];

$pageTitle = $title;
require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><h1><?= e($title) ?></h1><p class="muted">Error <?= $code ?></p></div>
<div class="card">
<p><?= e($msg) ?></p>
<p style="margin-bottom:0"><a class="btn" href="<?= e(url('index.php')) ?>">Go to Home</a>
<a class="btn btn-ghost" href="<?= e(url('items/search.php')) ?>">Search found items</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
