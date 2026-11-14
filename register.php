<?php
/*
 * Module 1: Registration (Phase 4, Section 6.2 and Table 14).
 * Self-registration ALWAYS creates a Student. Staff/Admin accounts are
 * created or promoted by an administrator.
 */
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user() !== null) {
    redirect('index.php');
}

$errors = [];
$old = ['full_name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email    = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm'] ?? '');
    $old = ['full_name' => $fullName, 'email' => $email];

    // ---- Validation (Table 14 / Table 19) ----
    $nameLen = mb_strlen($fullName);
    if ($nameLen < 2 || $nameLen > 150) {
        $errors[] = 'Full name must be between 2 and 150 characters.';
    }
    if ($email === '' || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    // length, letter + number + symbol and common-password rules all live in authentication.php
    $errors = array_merge($errors, validate_password_strength($password, $email, $fullName));
    if ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }

    if (!$errors) {
        $stmt = db()->prepare(
            "INSERT INTO users (full_name, email, password_hash, role)
             VALUES (:full_name, :email, :password_hash, 'Student')"
        );
        try {
            $stmt->execute([
                ':full_name'     => $fullName,
                ':email'         => $email,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            flash('ok', 'Account created. You can now log in.');
            redirect('login.php');
        } catch (PDOException $ex) {
            // 23000 = the UNIQUE index on email was hit (uniqueness check)
            if ($ex->getCode() === '23000') {
                $errors[] = 'An account with this email already exists. Try logging in instead.';
            } else {
                throw $ex;
            }
        }
    }
}

$pageTitle = 'Register';
$activeNav = 'register';
require __DIR__ . '/includes/header.php';
?>
<div class="auth stack"><div class="page-head"><h1>Create your account</h1><p class="muted">Registration creates a Student account. Staff and admin access is granted by an administrator.</p></div>
<?php if ($errors): ?>
<div class="alert alert-bad" role="alert"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<form class="card form" action="<?= e(url('register.php')) ?>" method="post" novalidate>
<?= csrf_field() ?>
<div class="field"><label for="full_name">Full name <span class="req">*</span></label><input id="full_name" name="full_name" minlength="2" maxlength="150" autocomplete="name" required value="<?= e($old['full_name']) ?>"></div>
<div class="field"><label for="email">Email <span class="req">*</span></label><input id="email" name="email" type="email" maxlength="254" autocomplete="email" required value="<?= e($old['email']) ?>"></div>
<div class="field"><label for="password">Password <span class="req">*</span></label><input id="password" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required><div class="hint">8–72 characters with a letter, a number and a symbol. Not a common password.</div></div>
<div class="field"><label for="confirm">Confirm password <span class="req">*</span></label><input id="confirm" name="confirm" type="password" minlength="8" maxlength="72" autocomplete="new-password" required></div>
<p class="small muted" style="margin:0">Your name and email are used only to run your account and your claims. See the <a href="<?= e(url('privacy.php')) ?>" target="_blank" rel="noopener">privacy notice</a>.</p>
<button class="btn btn-block" type="submit">Register</button></form>
<p class="small muted" style="text-align:center">Already registered? <a href="<?= e(url('login.php')) ?>">Log in</a></p></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
