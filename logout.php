<?php
/*
 * Module 1: Logout.
 * The Logout link carries the CSRF token (?token=...), so another website
 * can't log a user out by linking here.
 */
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user() !== null) {
    verify_csrf($_GET['token'] ?? '');

    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => $p['path'],
        'secure'   => $p['secure'],
        'httponly' => $p['httponly'],
        'samesite' => $p['samesite'],
    ]);
    session_destroy();
    session_start();   // fresh empty session so the header can build guest links
}

$pageTitle = 'Logged out';
require __DIR__ . '/includes/header.php';
?>
<div class="auth card stack" style="text-align:center"><h1>You have been logged out</h1><p class="muted">Thanks for using Lost &amp; Found Tracker.</p><a class="btn" href="<?= e(url('login.php')) ?>">Log in again</a></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
