<?php
/*
 * Module 8: My account (all logged-in users).
 * - Shows your name, email and role (only an administrator can change these).
 * - Change your own password: current password + new password twice (rules in
 *   validate_password_strength()). Used after an administrator gives you a temporary
 *   password (password resets by email are disabled - group note 12).
 * - If the account still has a temporary password (must_change_password), bootstrap.php
 *   sends the user here and nothing else opens until the password is changed.
 * - The change is written to user_admin_log as PASSWORD_CHANGED_BY_USER (no password stored).
 * - The session ID is regenerated after the change.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$user = requireLogin();
$pdo = db();
$errors = [];

$stmt = $pdo->prepare('SELECT created_at, must_change_password FROM users WHERE user_id = :id');
$stmt->execute([':id' => $user['user_id']]);
$row = $stmt->fetch();
$created = (string) ($row['created_at'] ?? '');
$mustChange = (bool) ($row['must_change_password'] ?? false);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $current = (string) ($_POST['current_password'] ?? '');
    $new     = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = :id');
    $stmt->execute([':id' => $user['user_id']]);
    $hash = (string) $stmt->fetchColumn();

    if ($current === '' || !password_verify($current, $hash)) $errors[] = 'Your current password is not correct.';
    if ($new !== $confirm) {
        $errors[] = 'The two new passwords do not match.';
    } elseif ($current !== '' && $new === $current) {
        $errors[] = 'The new password must be different from the current one.';
    } else {
        $errors = array_merge($errors, validate_password_strength($new, $user['email'], $user['full_name']));
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE users SET password_hash = :h, must_change_password = FALSE WHERE user_id = :id')
                ->execute([':h' => password_hash($new, PASSWORD_DEFAULT), ':id' => $user['user_id']]);
            logUserAdmin($pdo, $user['user_id'], 'PASSWORD_CHANGED_BY_USER', null, $user['user_id']);
            $pdo->commit();
            session_regenerate_id(true);
            flash('ok', 'Your password was changed. Use the new password next time you log in.');
            // a forced change should drop the user into the site, not back on this form
            redirect($mustChange ? 'index.php' : 'account.php');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('password change failed: ' . $ex->getMessage());
            $errors[] = DEV_MODE ? 'Saving failed: ' . $ex->getMessage() : 'Your password could not be changed.';
        }
    }
}

$pageTitle = 'My account';
$activeNav = 'account';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><h1>My account</h1></div>

<?php if ($mustChange): ?>
<div class="alert alert-warn" role="alert"><b>Choose your own password.</b> You are using a temporary password from an administrator. Enter it below as the current password, then pick a new one. You can't use the rest of the site until you do.</div>
<?php endif; ?>

<div class="card">
<h2 style="margin-top:0">Your details</h2>
<p><b>Name:</b> <?= e($user['full_name']) ?><br><b>Email:</b> <?= e($user['email']) ?><br><b>Role:</b> <?= e($user['role']) ?><br><b>Account created:</b> <?= e(substr($created, 0, 10)) ?></p>
<p class="small muted" style="margin-bottom:0">To change your name, email or role, contact an administrator. · <a href="<?= e(url('my_data.php')) ?>">My data &amp; privacy</a> (download or delete your information)</p>
</div>

<div class="card" style="margin-top:1rem">
<h2 style="margin-top:0">Change password</h2>
<?php if ($errors): ?>
<div class="alert alert-bad" role="alert"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<form class="form" action="<?= e(url('account.php')) ?>" method="post" autocomplete="off">
<?= csrf_field() ?>
<div class="field"><label for="current_password">Current password <span class="req">*</span></label><input id="current_password" name="current_password" type="password" required autocomplete="current-password"></div>
<div class="field"><label for="new_password">New password <span class="req">*</span></label><input id="new_password" name="new_password" type="password" minlength="8" maxlength="72" required autocomplete="new-password"><div class="hint">8–72 characters with a letter, a number and a symbol. Not a common password.</div></div>
<div class="field"><label for="confirm_password">Repeat new password <span class="req">*</span></label><input id="confirm_password" name="confirm_password" type="password" minlength="8" maxlength="72" required autocomplete="new-password"></div>
<div class="btn-row"><button class="btn" type="submit">Change password</button></div>
</form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
