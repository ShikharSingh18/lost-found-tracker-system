<?php
/*
 * Module 1: Login (Phase 4, Section 6.2 and Section 8.2).
 * - same error for unknown email and wrong password
 * - session ID regenerated after login
 * - 5 failed attempts -> 15 minute lockout (needs database/001_login_attempts.sql)
 * - deactivated accounts (is_active = FALSE) cannot log in
 * - password resets are disabled for now (group decision)
 */
require_once __DIR__ . '/includes/bootstrap.php';

const MAX_FAILED_LOGINS = 5;
const LOCKOUT_MINUTES   = 15;

if (current_user() !== null) {
    redirect('index.php');
}

$error = null;
$oldEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email    = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $oldEmail = $email;
    $pdo = db();

    // ---- Is this email currently locked out? ----
    $stmt = $pdo->prepare('SELECT locked_until FROM login_attempts WHERE email = :email AND locked_until > NOW(6)');
    $stmt->execute([':email' => $email]);

    if ($stmt->fetchColumn() !== false) {
        $error = 'Too many failed attempts. Please wait ' . LOCKOUT_MINUTES . ' minutes and try again.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT user_id, full_name, email, password_hash, role, is_active
             FROM users WHERE email = :email'
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        // Always run password_verify, even for unknown emails, so the response
        // time doesn't reveal whether the email is registered.
        $hash = $user['password_hash'] ?? '$2y$10$LIzoj8z9LSpCXR3LJo2jG.SzZKGMl33HuubKPOJNcwp.Noa4qEp/a';
        $passwordOk = password_verify($password, $hash);

        if ($user && $passwordOk && $user['is_active']) {
            // Success: clear failed attempts, upgrade the hash if PHP's default changed.
            $pdo->prepare('DELETE FROM login_attempts WHERE email = :email')->execute([':email' => $email]);
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $pdo->prepare('UPDATE users SET password_hash = :h WHERE user_id = :id')
                    ->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $user['user_id']]);
            }
            log_user_in($user);
            redirect('index.php');   // later: send Admins to admin/dashboard.php once it exists
        }

        // Failure: count it, lock after MAX_FAILED_LOGINS.
        $pdo->prepare(
            'INSERT INTO login_attempts (email, failed_count, last_failed_at)
             VALUES (:email, 1, NOW(6))
             ON DUPLICATE KEY UPDATE
               failed_count   = IF(locked_until IS NOT NULL AND locked_until <= NOW(6), 1, failed_count + 1),
               locked_until   = IF(locked_until IS NOT NULL AND locked_until <= NOW(6), NULL, locked_until),
               last_failed_at = NOW(6)'
        )->execute([':email' => $email]);

        $pdo->prepare(
            'UPDATE login_attempts
             SET locked_until = NOW(6) + INTERVAL ' . LOCKOUT_MINUTES . ' MINUTE
             WHERE email = :email AND failed_count >= :max'
        )->execute([':email' => $email, ':max' => MAX_FAILED_LOGINS]);

        $error = 'Invalid email or password.';   // one generic message (Section 7.3.1)
    }
}

$pageTitle = 'Login';
$activeNav = 'login';
require __DIR__ . '/includes/header.php';
?>
<div class="auth stack"><div class="page-head"><h1>Welcome back</h1><p class="muted">Log in to search items and manage your claims.</p></div>
<?php if ($error): ?>
<div class="alert alert-bad" role="alert"><?= e($error) ?></div>
<?php endif; ?>
<form class="card form" action="<?= e(url('login.php')) ?>" method="post">
<?= csrf_field() ?>
<div class="field"><label for="email">Email <span class="req">*</span></label><input id="email" name="email" type="email" maxlength="254" autocomplete="email" required value="<?= e($oldEmail) ?>"></div>
<div class="field"><label for="password">Password <span class="req">*</span></label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
<button class="btn btn-block" type="submit">Log in</button></form>
<p class="small muted" style="text-align:center">Forgot your password? Ask an administrator to reset it.</p>
<p class="small muted" style="text-align:center">New here? <a href="<?= e(url('register.php')) ?>">Create an account</a></p></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
