<?php
/*
 * Module 8: User management (Administrator) – Phase 4 Table 24 "Manage user accounts",
 * Section 8.2 and group note 12 (password resets disabled: an administrator sets a
 * temporary password instead).
 *
 * - Create Student, Staff or Admin accounts (temporary password typed or generated).
 *   A typed password must pass validate_password_strength(). Every new account and every
 *   admin-set password gets must_change_password = TRUE, so the user picks their own at first login.
 * - Change role, deactivate / reactivate (accounts are never deleted – Section 8.2).
 * - Set a new temporary password; unlock an account locked after 5 failed logins.
 * - Safety rules: an admin can't change their OWN role or deactivate themselves, and
 *   the last active Admin can never be demoted or deactivated.
 * - Every change is written to user_admin_log (database/004_user_admin_log.sql).
 *   Passwords are never logged. A generated password is shown ONCE to the admin.
 * - A deactivated user is logged out on their next click (includes/bootstrap.php, BUG-02).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/privacy.php';   // Module 10: anonymise

$admin = requireRole('Admin');
$pdo = db();
const ROLES = ['Student', 'Staff', 'Admin'];
const USERS_PER_PAGE = 25;

function count_active_admins(PDO $pdo): int
{
    return (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Admin' AND is_active = TRUE")->fetchColumn();
}

$createErrors = [];
$createOld = ['full_name' => '', 'email' => '', 'role' => 'Staff'];

// ======================= Actions (POST) =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $back = is_string($_POST['back'] ?? null) && str_starts_with($_POST['back'], 'admin/users.php') ? $_POST['back'] : 'admin/users.php';

    // ---------- Create account ----------
    if ($action === 'create') {
        $name  = trim((string) ($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $role  = (string) ($_POST['role'] ?? '');
        $pass  = (string) ($_POST['password'] ?? '');
        $createOld = ['full_name' => $name, 'email' => $email, 'role' => $role];

        $n = mb_strlen($name);
        if ($n < 2 || $n > 150) $createErrors[] = 'Full name must be between 2 and 150 characters.';
        if ($email === '' || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) $createErrors[] = 'Enter a valid email address.';
        if (!in_array($role, ROLES, true)) $createErrors[] = 'Choose a role.';
        $generated = $pass === '';
        if ($generated) {
            $pass = generate_temp_password();
        } else {
            foreach (validate_password_strength($pass, $email, $name) as $problem) {
                $createErrors[] = 'Temporary password: ' . $problem;
            }
        }

        if (!$createErrors) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role, must_change_password) VALUES (:n, :e, :h, :r, TRUE)')
                    ->execute([':n' => $name, ':e' => $email, ':h' => password_hash($pass, PASSWORD_DEFAULT), ':r' => $role]);
                $newId = (int) $pdo->lastInsertId();
                logUserAdmin($pdo, $newId, 'USER_CREATED', $role, $admin['user_id']);
                $pdo->commit();
                $_SESSION['temp_password'] = ['email' => $email, 'password' => $pass, 'why' => 'New ' . $role . ' account'];
                flash('ok', "$role account created for $email.");
                redirect('admin/users.php');
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($ex->getCode() === '23000') {
                    $createErrors[] = 'An account with this email already exists.';
                } else {
                    error_log('user create failed: ' . $ex->getMessage());
                    $createErrors[] = DEV_MODE ? 'Saving failed: ' . $ex->getMessage() : 'The account could not be created.';
                }
            }
        }
        // fall through to show the page with the errors
    } else {
        // ---------- Actions on an existing account ----------
        $userId = filter_var(is_string($_POST['user_id'] ?? null) ? $_POST['user_id'] : '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$userId || !in_array($action, ['role', 'deactivate', 'reactivate', 'temp_password', 'unlock', 'anonymise', 'decline_erasure'], true)) {
            flash('bad', 'Unknown action.');
            redirect($back);
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT user_id, full_name, email, role, is_active FROM users WHERE user_id = :id FOR UPDATE');
            $stmt->execute([':id' => $userId]);
            $u = $stmt->fetch();
            if (!$u) {
                $pdo->rollBack();
                flash('bad', 'That account does not exist.');
                redirect($back);
            }
            $isSelf = (int) $u['user_id'] === $admin['user_id'];
            $who = $u['full_name'] . ' (' . $u['email'] . ')';
            $lastAdmin = $u['role'] === 'Admin' && $u['is_active'] && count_active_admins($pdo) <= 1;
            if (is_anonymised($u['email']) && $action !== 'unlock') {
                $pdo->rollBack();
                flash('info', "{$u['full_name']}'s personal information was deleted. The account can't be changed or reactivated.");
                redirect($back);
            }

            switch ($action) {
                case 'role':
                    $newRole = (string) ($_POST['role'] ?? '');
                    if (!in_array($newRole, ROLES, true) || $newRole === $u['role']) {
                        $pdo->rollBack();
                        flash('info', 'No change: choose a different role.');
                        redirect($back);
                    }
                    if ($isSelf) {
                        $pdo->rollBack();
                        flash('warn', "You can't change your own role. Ask another administrator.");
                        redirect($back);
                    }
                    if ($lastAdmin) {
                        $pdo->rollBack();
                        flash('warn', "$who is the last active Admin and can't be changed to $newRole.");
                        redirect($back);
                    }
                    $pdo->prepare('UPDATE users SET role = :r WHERE user_id = :id')->execute([':r' => $newRole, ':id' => $userId]);
                    logUserAdmin($pdo, $userId, 'ROLE_CHANGED', "{$u['role']} -> $newRole", $admin['user_id']);
                    $msg = "$who is now $newRole. This takes effect on their next click.";
                    break;

                case 'deactivate':
                    if ($isSelf) {
                        $pdo->rollBack();
                        flash('warn', "You can't deactivate your own account.");
                        redirect($back);
                    }
                    if ($lastAdmin) {
                        $pdo->rollBack();
                        flash('warn', "$who is the last active Admin and can't be deactivated.");
                        redirect($back);
                    }
                    if (!$u['is_active']) {
                        $pdo->rollBack();
                        flash('info', "$who is already deactivated.");
                        redirect($back);
                    }
                    $pdo->prepare('UPDATE users SET is_active = FALSE WHERE user_id = :id')->execute([':id' => $userId]);
                    logUserAdmin($pdo, $userId, 'USER_DEACTIVATED', null, $admin['user_id']);
                    $msg = "$who was deactivated. If they are logged in, they are logged out on their next click.";
                    break;

                case 'reactivate':
                    if ($u['is_active']) {
                        $pdo->rollBack();
                        flash('info', "$who is already active.");
                        redirect($back);
                    }
                    $pdo->prepare('UPDATE users SET is_active = TRUE WHERE user_id = :id')->execute([':id' => $userId]);
                    logUserAdmin($pdo, $userId, 'USER_REACTIVATED', null, $admin['user_id']);
                    $msg = "$who was reactivated and can log in again.";
                    break;

                case 'temp_password':
                    if ($isSelf) {
                        $pdo->rollBack();
                        flash('warn', 'To change your own password use My account.');
                        redirect($back);
                    }
                    $temp = generate_temp_password();
                    $pdo->prepare('UPDATE users SET password_hash = :h, must_change_password = TRUE WHERE user_id = :id')
                        ->execute([':h' => password_hash($temp, PASSWORD_DEFAULT), ':id' => $userId]);
                    $pdo->prepare('DELETE FROM login_attempts WHERE email = :e')->execute([':e' => $u['email']]);
                    logUserAdmin($pdo, $userId, 'PASSWORD_SET_BY_ADMIN', null, $admin['user_id']);
                    $_SESSION['temp_password'] = ['email' => $u['email'], 'password' => $temp, 'why' => 'New temporary password'];
                    $msg = "A new temporary password was set for $who. Their old password no longer works.";
                    break;

                case 'anonymise':   // Module 10: right to erasure
                    if ($isSelf) {
                        $pdo->rollBack();
                        flash('warn', "You can't delete your own personal information. Ask another administrator.");
                        redirect($back);
                    }
                    if ($lastAdmin) {
                        $pdo->rollBack();
                        flash('warn', "$who is the last active Admin and can't be removed.");
                        redirect($back);
                    }
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM claims WHERE claimant_user_id = :id AND verification_status = 'Pending'");
                    $stmt->execute([':id' => $userId]);
                    if ($pending = (int) $stmt->fetchColumn()) {
                        $pdo->rollBack();
                        flash('warn', "$who has $pending pending claim(s). Approve or reject them on Review Claims first, then delete the personal information.");
                        redirect($back);
                    }
                    $n = anonymise_user($pdo, $u, $admin['user_id']);
                    $msg = "Personal information of $who was deleted: the account is now \"Deleted user #$userId\", closed, and the proof text of $n claim(s) was removed. Claims and the audit trail are kept without their details.";
                    break;

                case 'decline_erasure':
                    logUserAdmin($pdo, $userId, 'ERASURE_REQUEST_DECLINED', mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 200) ?: null, $admin['user_id']);
                    $msg = "The deletion request from $who was marked as declined. Tell them why.";
                    break;

                case 'unlock':
                    $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE email = :e');
                    $stmt->execute([':e' => $u['email']]);
                    logUserAdmin($pdo, $userId, 'ACCOUNT_UNLOCKED', null, $admin['user_id']);
                    $msg = "$who was unlocked and can try logging in again.";
                    break;
            }

            $pdo->commit();
            flash('ok', $msg);
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('user admin failed: ' . $ex->getMessage());
            flash('bad', DEV_MODE ? 'Saving failed: ' . $ex->getMessage() : 'The change could not be saved.');
        }
        redirect($back);
    }
}

// ======================= List (GET) =======================
$get = fn (string $k): string => is_string($_GET[$k] ?? null) ? trim($_GET[$k]) : '';
$f = ['q' => mb_substr($get('q'), 0, 100), 'role' => $get('role'), 'state' => $get('state')];
if (!in_array($f['role'], array_merge([''], ROLES), true)) $f['role'] = '';
if (!in_array($f['state'], ['', 'active', 'inactive', 'locked'], true)) $f['state'] = '';

$where = [];
$params = [];
if ($f['q'] !== '') {
    $like = '%' . addcslashes($f['q'], '%_\\') . '%';
    $where[] = '(u.full_name LIKE :q1 OR u.email LIKE :q2)';
    $params[':q1'] = $params[':q2'] = $like;
}
if ($f['role'] !== '') { $where[] = 'u.role = :role'; $params[':role'] = $f['role']; }
if ($f['state'] === 'active') $where[] = 'u.is_active = TRUE';
if ($f['state'] === 'inactive') $where[] = 'u.is_active = FALSE';
if ($f['state'] === 'locked') $where[] = 'la.email IS NOT NULL';
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$from = 'FROM users u LEFT JOIN login_attempts la ON la.email = u.email AND la.locked_until > NOW(6)';

$stmt = $pdo->prepare("SELECT COUNT(*) $from $whereSql");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$pages = max(1, (int) ceil($total / USERS_PER_PAGE));
$page = min($pages, max(1, (int) $get('page')));

$stmt = $pdo->prepare(
    "SELECT u.user_id, u.full_name, u.email, u.role, u.is_active, u.created_at, la.locked_until
     $from $whereSql
     ORDER BY u.is_active DESC, u.full_name
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', USERS_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':offset', ($page - 1) * USERS_PER_PAGE, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

$recent = $pdo->query(
    'SELECT l.action, l.detail, l.occurred_at, t.full_name AS target, a.full_name AS actor
     FROM user_admin_log l JOIN users t ON t.user_id = l.target_user_id JOIN users a ON a.user_id = l.performed_by
     ORDER BY l.log_id DESC LIMIT 10'
)->fetchAll();

// Module 10: open deletion requests (asked, not yet anonymised or declined)
$requests = $pdo->query(
    "SELECT r.target_user_id, r.occurred_at, u.full_name, u.email, u.role,
            (SELECT COUNT(*) FROM claims c WHERE c.claimant_user_id = r.target_user_id AND c.verification_status = 'Pending') AS pending
     FROM user_admin_log r JOIN users u ON u.user_id = r.target_user_id
     WHERE r.action = 'ERASURE_REQUESTED'
       AND r.log_id = (SELECT MAX(x.log_id) FROM user_admin_log x WHERE x.target_user_id = r.target_user_id AND x.action IN ('ERASURE_REQUESTED','USER_ANONYMISED','ERASURE_REQUEST_DECLINED'))
     ORDER BY r.occurred_at"
)->fetchAll();

$activeAdmins = count_active_admins($pdo);
$query = array_filter($f, fn ($v) => $v !== '');
$here = 'admin/users.php' . ($query || $page > 1 ? '?' . http_build_query($query + ($page > 1 ? ['page' => $page] : [])) : '');

$temp = $_SESSION['temp_password'] ?? null;   // shown once, then forgotten
unset($_SESSION['temp_password']);

$actionLabels = [
    'USER_CREATED' => 'Account created', 'ROLE_CHANGED' => 'Role changed', 'USER_DEACTIVATED' => 'Deactivated',
    'USER_REACTIVATED' => 'Reactivated', 'PASSWORD_SET_BY_ADMIN' => 'Temporary password set',
    'ACCOUNT_UNLOCKED' => 'Unlocked', 'PASSWORD_CHANGED_BY_USER' => 'Changed own password',
    'USER_ANONYMISED' => 'Personal information deleted', 'DATA_EXPORTED' => 'Downloaded own data',
    'ERASURE_REQUESTED' => 'Asked for deletion', 'ERASURE_REQUEST_DECLINED' => 'Deletion request declined',
];

$pageTitle = 'Users';
$activeNav = 'users';
$extraCss  = ['assets/css/photos.css'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Users</h1><p class="muted">Create accounts, change roles, deactivate users and set temporary passwords. Accounts are never deleted.</p></div>

<?php if ($temp): ?>
<div class="alert alert-warn" role="status">
<b><?= e($temp['why']) ?> for <?= e($temp['email']) ?>:</b>
<code class="temp-pass" id="temp-pass"><?= e($temp['password']) ?></code>
<button class="btn btn-sm btn-outline" type="button" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('temp-pass').textContent).then(function(){this.textContent='Copied';}.bind(this))">Copy</button>
<div class="small" style="margin-top:.4rem">Give it to the person privately. It is shown <b>only once</b> and is not stored anywhere in readable form. They will be asked to choose their own password the first time they log in.</div>
</div>
<?php endif; ?>

<?php if ($requests): ?>
<section class="card card-warn" style="margin-bottom:1rem">
<h2 style="margin-top:0">Deletion requests (<?= count($requests) ?>)</h2>
<p class="small muted">The person asked for their personal information to be deleted (POPIA s24 / GDPR Art. 17). Deal with requests promptly.</p>
<ul class="dash-list">
<?php foreach ($requests as $rq): $rid = (int) $rq['target_user_id'];
    $h = csrf_field() . '<input type="hidden" name="user_id" value="' . $rid . '"><input type="hidden" name="back" value="' . e($here) . '">'; ?>
<li><b><?= e($rq['full_name']) ?></b> (<?= e($rq['email']) ?>, <?= e($rq['role']) ?>) <span class="small muted">· asked <?= e(substr($rq['occurred_at'], 0, 16)) ?></span>
<?php if ((int) $rq['pending']): ?><br><span class="small">Has <?= (int) $rq['pending'] ?> pending claim(s) – <a href="<?= e(url('admin/claim_review.php')) ?>">decide them first</a>.</span>
<?php else: ?>
<div class="btn-row" style="margin-top:.4rem">
<form method="post" action="<?= e(url('admin/users.php')) ?>" style="display:inline" onsubmit="return confirm('Delete the personal information of <?= e(addslashes($rq['full_name'])) ?>? Name, email and proof text are removed and the account is closed. This cannot be undone.');"><?= $h ?><input type="hidden" name="action" value="anonymise"><button class="btn btn-sm" type="submit">Delete personal information</button></form>
<form method="post" action="<?= e(url('admin/users.php')) ?>" style="display:inline" onsubmit="var r=prompt('Reason for declining (shown in the log):');if(r===null)return false;this.reason.value=r;return true;"><?= $h ?><input type="hidden" name="action" value="decline_erasure"><input type="hidden" name="reason" value=""><button class="btn btn-sm btn-ghost" type="submit">Decline</button></form>
</div>
<?php endif; ?></li>
<?php endforeach; ?>
</ul>
</section>
<?php endif; ?>

<details class="card filters"<?= $createErrors ? ' open' : '' ?>><summary>Create an account</summary>
<?php if ($createErrors): ?>
<div class="alert alert-bad" role="alert" style="margin-top:.75rem"><?php foreach ($createErrors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<form class="form form-2" action="<?= e(url('admin/users.php')) ?>" method="post" style="margin-top:.75rem">
<?= csrf_field() ?><input type="hidden" name="action" value="create">
<div class="field"><label for="full_name">Full name <span class="req">*</span></label><input id="full_name" name="full_name" minlength="2" maxlength="150" required value="<?= e($createOld['full_name']) ?>"></div>
<div class="field"><label for="email">Email <span class="req">*</span></label><input id="email" name="email" type="email" maxlength="254" required value="<?= e($createOld['email']) ?>"></div>
<div class="field"><label for="role">Role <span class="req">*</span></label><select id="role" name="role" required>
<?php foreach (ROLES as $r): ?><option<?= $createOld['role'] === $r ? ' selected' : '' ?>><?= $r ?></option><?php endforeach; ?>
</select></div>
<div class="field"><label for="password">Temporary password</label><input id="password" name="password" type="text" minlength="8" maxlength="72" autocomplete="off" placeholder="Leave empty to generate one"><div class="hint">8–72 characters with a letter, a number and a symbol. Leave empty and the system makes one for you. The user must change it at first login.</div></div>
<div class="btn-row full"><button class="btn" type="submit">Create account</button></div>
</form></details>

<details class="card filters" open style="margin-top:1rem"><summary>Find users</summary>
<form class="form form-2" action="<?= e(url('admin/users.php')) ?>" method="get" style="margin-top:.75rem">
<div class="field full"><label for="q">Name or email</label><input id="q" name="q" type="search" maxlength="100" value="<?= e($f['q']) ?>"></div>
<div class="field"><label for="frole">Role</label><select id="frole" name="role"><option value="">Any role</option>
<?php foreach (ROLES as $r): ?><option<?= $f['role'] === $r ? ' selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="state">Account</label><select id="state" name="state">
<?php foreach (['' => 'Any', 'active' => 'Active', 'inactive' => 'Deactivated', 'locked' => 'Locked (failed logins)'] as $v => $l): ?>
<option value="<?= $v ?>"<?= $f['state'] === $v ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
<div class="btn-row full"><button class="btn" type="submit">Apply filters</button><a class="btn btn-ghost" href="<?= e(url('admin/users.php')) ?>">Clear</a></div>
</form></details>

<p class="small muted" style="margin-top:1rem"><?= $total ?> account<?= $total === 1 ? '' : 's' ?> · <?= $activeAdmins ?> active admin<?= $activeAdmins === 1 ? '' : 's' ?></p>
<?php if (!$users): ?>
<div class="card"><p style="margin:0">No accounts match these filters.</p></div>
<?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Account</th><th>Created</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($users as $u):
    $id = (int) $u['user_id'];
    $self = $id === $admin['user_id'];
    $gone = is_anonymised($u['email']);   // Module 10: personal information deleted
    $hidden = csrf_field() . '<input type="hidden" name="user_id" value="' . $id . '"><input type="hidden" name="back" value="' . e($here) . '">'; ?>
<tr>
<td data-label="Name"><?= e($u['full_name']) ?><?= $self ? ' <span class="small muted">(you)</span>' : '' ?></td>
<td data-label="Email"><?= e($u['email']) ?></td>
<td data-label="Role">
<?php if ($self || $gone): ?><?= e($u['role']) ?>
<?php else: ?>
<form method="post" action="<?= e(url('admin/users.php')) ?>" class="inline-form"><?= $hidden ?><input type="hidden" name="action" value="role">
<select name="role" aria-label="Role for <?= e($u['full_name']) ?>" class="select-sm">
<?php foreach (ROLES as $r): ?><option<?= $u['role'] === $r ? ' selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select>
<button class="btn btn-sm btn-outline" type="submit">Change</button></form>
<?php endif; ?></td>
<td data-label="Account"><div><?php if ($gone): ?><span class="pill pill-disposed">Personal data deleted</span><?php else: ?><span class="pill pill-<?= $u['is_active'] ? 'found' : 'disposed' ?>"><?= $u['is_active'] ? 'Active' : 'Deactivated' ?></span><?php endif; ?>
<?php if ($u['locked_until']): ?><br><span class="pill pill-pending">Locked until <?= e(substr($u['locked_until'], 11, 5)) ?></span><?php endif; ?></div></td>
<td data-label="Created" class="nowrap"><?= e(substr($u['created_at'], 0, 10)) ?></td>
<td class="actions" data-label="Actions">
<?php if (!$self && !$gone): ?>
<?php if ($u['is_active']): ?>
<form method="post" action="<?= e(url('admin/users.php')) ?>" style="display:inline" onsubmit="return confirm('Deactivate <?= e(addslashes($u['full_name'])) ?>? They will be logged out and cannot log in until reactivated.');"><?= $hidden ?><input type="hidden" name="action" value="deactivate"><button class="btn btn-sm btn-ghost" type="submit">Deactivate</button></form>
<?php else: ?>
<form method="post" action="<?= e(url('admin/users.php')) ?>" style="display:inline"><?= $hidden ?><input type="hidden" name="action" value="reactivate"><button class="btn btn-sm btn-ok" type="submit">Reactivate</button></form>
<?php endif; ?>
<form method="post" action="<?= e(url('admin/users.php')) ?>" style="display:inline" onsubmit="return confirm('Delete the personal information of <?= e(addslashes($u['full_name'])) ?>? Name, email and claim proof text are removed and the account is closed for good. Only do this when the person asked (or the college requires it). This cannot be undone.');"><?= $hidden ?><input type="hidden" name="action" value="anonymise"><button class="btn btn-sm btn-ghost" type="submit">Delete personal data</button></form>
<form method="post" action="<?= e(url('admin/users.php')) ?>" style="display:inline" onsubmit="return confirm('Set a new temporary password for <?= e(addslashes($u['full_name'])) ?>? Their current password will stop working.');"><?= $hidden ?><input type="hidden" name="action" value="temp_password"><button class="btn btn-sm btn-outline" type="submit">New temporary password</button></form>
<?php endif; ?>
<?php if ($u['locked_until']): ?>
<form method="post" action="<?= e(url('admin/users.php')) ?>" style="display:inline"><?= $hidden ?><input type="hidden" name="action" value="unlock"><button class="btn btn-sm btn-outline" type="submit">Unlock</button></form>
<?php endif; ?>
</td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if ($pages > 1): ?>
<nav class="pager" aria-label="Pagination">
<?php for ($p = 1; $p <= $pages; $p++): ?>
<?php if ($p === $page): ?><span class="on" aria-current="page"><?= $p ?></span><?php else: ?><a href="<?= e(url('admin/users.php?' . http_build_query($query + ['page' => $p]))) ?>"><?= $p ?></a><?php endif; ?>
<?php endfor; ?>
</nav>
<?php endif; ?>
<?php endif; ?>

<?php if ($recent): ?>
<h2 style="margin-top:2rem">Recent account changes</h2>
<div class="table-wrap"><table><thead><tr><th>When</th><th>Account</th><th>Change</th><th>By</th></tr></thead><tbody>
<?php foreach ($recent as $r): ?>
<tr><td data-label="When"><?= e(substr($r['occurred_at'], 0, 16)) ?></td><td data-label="Account"><?= e($r['target']) ?></td>
<td data-label="Change"><?= e($actionLabels[$r['action']] ?? $r['action']) ?><?= $r['detail'] ? ' <span class="small muted">(' . e($r['detail']) . ')</span>' : '' ?></td>
<td data-label="By"><?= e($r['actor']) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
