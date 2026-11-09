<?php
/*
 * Shared helpers used by every page (Phase 4, Section 6.1 and Section 8).
 *
 *   e($text)                 escape output for HTML (XSS protection)
 *   url($path)               build a link that works from any folder
 *   redirect($path)          send the browser to another page and stop
 *   flash($type, $msg)       one-time message shown on the next page
 *   csrf_field()             hidden CSRF input for every POST form
 *   verify_csrf()            check the token at the top of every POST handler
 *   current_user()           logged-in user array, or null
 *   requireLogin()           stop guests
 *   requireRole('Admin',..)  stop users without one of the given roles
 *   writeAudit(...)          insert an audit_log row (call inside the same transaction)
 */

// ---------- Output and navigation -------------------------------------------

function e(?string $text): string
{
    return htmlspecialchars($text ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function flash(string $type, string $msg): void
{
    // $type is one of: ok, bad, warn, info  (matches alert-ok, alert-bad ... in style.css)
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function take_flash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// ---------- CSRF (Section 8.4) ----------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token = null): void
{
    $token ??= $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(400);
        exit('Your form expired or was not valid. Go back, refresh the page and try again.');
    }
}

// ---------- Logged-in user and role checks (Section 8.3) --------------------

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;   // ['user_id', 'full_name', 'email', 'role']
}

function requireLogin(): array
{
    $user = current_user();
    if ($user === null) {
        // keep a more specific message (e.g. "account deactivated" from bootstrap.php)
        if (empty($_SESSION['flash'])) flash('info', 'Please log in to continue.');
        redirect('login.php');
    }
    return $user;
}

function requireRole(string ...$roles): array
{
    $user = requireLogin();
    // The role always comes from the session, never from form input.
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        $pageTitle = 'Access denied';
        require __DIR__ . '/header.php';
        echo '<div class="alert alert-bad" role="alert">You do not have permission to open this page.</div>';
        require __DIR__ . '/footer.php';
        exit;
    }
    return $user;
}

function log_user_in(array $userRow): void
{
    session_regenerate_id(true);   // prevents session fixation (Section 8.2)
    $_SESSION['user'] = [
        'user_id'   => (int) $userRow['user_id'],
        'full_name' => $userRow['full_name'],
        'email'     => $userRow['email'],
        'role'      => $userRow['role'],
    ];
    unset($_SESSION['csrf_token']);   // fresh token after login
}

// ---------- Audit log (Section 5.5, 6.7) ------------------------------------

/**
 * Call this INSIDE the same transaction as the change it records,
 * so the change and its audit row are saved together or not at all.
 * At least one of $itemId / $claimId must be given (database CHECK).
 */
function writeAudit(PDO $pdo, string $action, ?int $itemId, ?int $claimId, int $performedBy): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO audit_log (item_id, claim_id, action, performed_by)
         VALUES (:item_id, :claim_id, :action, :performed_by)'
    );
    $stmt->execute([
        ':item_id'      => $itemId,
        ':claim_id'     => $claimId,
        ':action'       => $action,
        ':performed_by' => $performedBy,
    ]);
}

// ---------- Notifications ---------------------------------------------------

function unread_notification_count(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :u AND is_read = FALSE');
    $stmt->execute([':u' => $userId]);
    return (int) $stmt->fetchColumn();
}

// ---------- Asset links with cache-busting ----------------------------------

/**
 * Link to a CSS/JS file with ?v=<last-modified time> added, so browsers
 * download the new version as soon as the file changes (no Ctrl+F5 needed).
 */
function asset_url(string $path): string
{
    $file = __DIR__ . '/../' . ltrim($path, '/');
    $version = is_file($file) ? filemtime($file) : 0;
    return url($path) . '?v=' . $version;
}

// ---------- Module 8: account changes log -----------------------------------

/**
 * Records a change to a user account in user_admin_log (database/004_user_admin_log.sql).
 * Never pass a password in $detail.
 */
function logUserAdmin(PDO $pdo, int $targetUserId, string $action, ?string $detail, int $performedBy): void
{
    $pdo->prepare(
        'INSERT INTO user_admin_log (target_user_id, action, detail, performed_by)
         VALUES (:t, :a, :d, :p)'
    )->execute([':t' => $targetUserId, ':a' => $action, ':d' => $detail, ':p' => $performedBy]);
}

/** A readable random temporary password, e.g. "Kp7w-Rx3m-Tq9b" (no 0/O/1/l to avoid mix-ups). */
function generate_temp_password(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $groups = [];
    for ($g = 0; $g < 3; $g++) {
        $part = '';
        for ($i = 0; $i < 4; $i++) {
            $part .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $groups[] = $part;
    }
    return implode('-', $groups);
}
