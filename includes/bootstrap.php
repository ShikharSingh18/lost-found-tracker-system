<?php
/*
 * Include this at the top of EVERY page:
 *     require_once __DIR__ . '/includes/bootstrap.php';          // pages in the root
 *     require_once __DIR__ . '/../includes/bootstrap.php';       // pages in a subfolder
 *
 * It loads the settings and database, starts a secure session and loads
 * the shared helpers in authentication.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (DEV_MODE) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');   // errors go to the server log only (Section 8.4)
}

date_default_timezone_set('Africa/Johannesburg');

// ---- Session (Section 8.2) -------------------------------------------------
const SESSION_IDLE_SECONDS = 30 * 60;   // log out after 30 minutes of inactivity

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('LFTSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL . '/',
        'secure'   => USE_HTTPS,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > SESSION_IDLE_SECONDS) {
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['flash'] = ['type' => 'warn', 'msg' => 'Your session expired. Please log in again.'];
}
// DEV-03: background requests (the bell's 30-second check) must not count as
// activity, otherwise the idle logout never happens while a tab is open.
// Such scripts define LFT_BACKGROUND_REQUEST before including this file.
if (!defined('LFT_BACKGROUND_REQUEST')) {
    $_SESSION['last_seen'] = time();
}

require_once __DIR__ . '/authentication.php';

// ---- BUG-02 / Module 8: re-check the account on EVERY request ---------------
// If an administrator deactivates a user (or the account was removed), the user
// is logged out on their next click. A role change takes effect immediately.
if (isset($_SESSION['user']['user_id'])) {
    $stmt = db()->prepare('SELECT full_name, email, role, is_active, must_change_password FROM users WHERE user_id = :id');
    $stmt->execute([':id' => $_SESSION['user']['user_id']]);
    $fresh = $stmt->fetch();

    if (!$fresh || !$fresh['is_active']) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['flash'] = ['type' => 'bad', 'msg' => 'Your account has been deactivated. Please contact an administrator.'];
    } else {
        $_SESSION['user']['full_name'] = $fresh['full_name'];
        $_SESSION['user']['email']     = $fresh['email'];
        $_SESSION['user']['role']      = $fresh['role'];

        // temporary password: only My account, logout and the bell check may load until it is changed
        $openPages = ['account.php', 'logout.php', 'notifications_count.php'];
        if ($fresh['must_change_password'] && !in_array(basename($_SERVER['SCRIPT_NAME']), $openPages, true)) {
            flash('warn', 'You are using a temporary password. Choose your own password to continue.');
            redirect('account.php');
        }
    }
    unset($stmt, $fresh);
}
