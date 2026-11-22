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
 *   validate_password_strength(...)  password rules, used by register, account and admin/users
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
        // NOTE-03: show the refusal inside the normal site layout (was plain text)
        http_response_code(400);
        $pageTitle = 'Form expired';
        require __DIR__ . '/header.php';
        echo '<div class="alert alert-warn" role="alert"><b>This form has expired or was not sent from this site.</b> '
           . 'Nothing was saved. This protects you against forged requests.</div>'
           . '<p><a class="btn" href="javascript:history.back()">Go back</a> '
           . '<a class="btn btn-ghost" href="' . e(url('index.php')) . '">Home</a></p>'
           . '<p class="small muted">Tip: go back, refresh the page (F5) and try again. This happens if the page was open for a long time or you logged out in another tab.</p>';
        require __DIR__ . '/footer.php';
        exit;
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

// ---------- Password rules --------------------------------------------------

/**
 * One place for the password rules so register, My account and the admin
 * create form all behave the same. Returns a list of problems, empty = OK.
 * $email and $name are only used to stop people putting them in the password.
 */
function validate_password_strength(string $pw, string $email = '', string $name = ''): array
{
    // bcrypt ignores everything after 72 bytes, so longer ones are refused
    if (strlen($pw) < 8 || strlen($pw) > 72) {
        return ['Password must be 8–72 characters.'];
    }

    $errors = [];
    if (!preg_match('/[A-Za-z]/', $pw))     $errors[] = 'Include at least one letter.';
    if (!preg_match('/\d/', $pw))           $errors[] = 'Include at least one number.';
    if (!preg_match('/[^A-Za-z0-9]/', $pw)) $errors[] = 'Include at least one symbol, for example ! @ # $ % -';

    $lower = strtolower($pw);

    // Swap the usual look-alikes back and drop everything that is not a letter,
    // so "P@ssw0rd1!" ends up as "password" and gets caught by the list below.
    $folded = preg_replace(
        '/[^a-z]/', '',
        strtr($lower, ['@' => 'a', '0' => 'o', '1' => 'i', '3' => 'e', '$' => 's', '5' => 's', '7' => 't', '!' => 'i'])
    );
    $common = [
        'password', 'passw0rd', 'qwerty', 'qwertyuiop', 'letmein', 'welcome', 'admin', 'administrator',
        'iloveyou', 'monkey', 'dragon', 'abc', 'abcdef', 'login', 'changeme', 'temp', 'temporary',
        'lostfound', 'lostandfound', 'student', 'staff', 'school', 'football', 'master', 'sunshine',
    ];
    foreach ($common as $word) {
        // the +3 allows for a few extra letters stuck on the end, like "password123"
        if ($folded === $word || ($folded !== '' && strlen($folded) <= strlen($word) + 3 && str_contains($folded, $word))) {
            $errors[] = 'That password is too common or easy to guess.';
            break;
        }
    }

    if (preg_match('/^(.)\1+$/', $pw)) {
        $errors[] = 'Password cannot be one character repeated.';
    }
    // running sequences (12345678, abcdefgh, qwertyui) - checked forwards and backwards
    foreach (['0123456789', 'abcdefghijklmnopqrstuvwxyz', 'qwertyuiopasdfghjklzxcvbnm'] as $run) {
        if (str_contains($run, $lower) || str_contains(strrev($run), $lower)) {
            $errors[] = 'Password cannot be a simple sequence like 12345678 or abcdefgh.';
            break;
        }
    }

    $local = strtolower((string) strstr($email, '@', true));
    if (strlen($local) >= 4 && str_contains($lower, $local)) {
        $errors[] = 'Password must not contain your email name.';
    }
    foreach (preg_split('/\s+/', strtolower(trim($name))) ?: [] as $part) {
        if (strlen($part) >= 4 && str_contains($lower, $part)) {
            $errors[] = 'Password must not contain your name.';
            break;
        }
    }

    return array_values(array_unique($errors));
}

/**
 * Random temporary password for an admin to hand over, e.g. "k7#Tmw-Pq4xZ9b".
 * Always has an upper, lower, digit and symbol so it passes validate_password_strength().
 * Left out look-alikes (0/O, 1/l/I) so it can be read out or typed without mistakes.
 */
function generate_temp_password(): string
{
    $upper  = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower  = 'abcdefghjkmnpqrstuvwxyz';
    $digits = '23456789';
    $syms   = '!@#$%&*?-';
    $pick = fn (string $set): string => $set[random_int(0, strlen($set) - 1)];

    // one of each type first, then fill up to 14
    $chars = [$pick($upper), $pick($lower), $pick($digits), $pick($syms)];
    $all = $upper . $lower . $digits . $syms;
    while (count($chars) < 14) {
        $chars[] = $pick($all);
    }

    // shuffle so the first four are not always upper/lower/digit/symbol
    for ($i = count($chars) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
    }
    return implode('', $chars);
}
