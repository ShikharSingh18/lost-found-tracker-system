<?php
/*
 * Module 10: My data & privacy (all logged-in users).
 * - Shows what the system holds about you (counts + your claims).
 * - "Download my data" (right of access / data portability – POPIA s23, GDPR Art. 15 & 20):
 *   a JSON file with your account, claims (including your own proof text), notifications,
 *   account changes about you and items you logged. Logged as DATA_EXPORTED.
 * - "Ask for my personal information to be deleted" (POPIA s24, GDPR Art. 17): logged as
 *   ERASURE_REQUESTED and every active admin gets a notification (without your name, so the
 *   notification itself doesn't keep your details). The admin anonymises on the Users page.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/privacy.php';

$user = requireLogin();
$uid = $user['user_id'];
$pdo = db();

function pending_erasure_request(PDO $pdo, int $uid): ?string
{
    $stmt = $pdo->prepare(
        "SELECT occurred_at FROM user_admin_log WHERE target_user_id = :id AND action = 'ERASURE_REQUESTED'
           AND log_id > COALESCE((SELECT MAX(log_id) FROM user_admin_log WHERE target_user_id = :id2 AND action IN ('USER_ANONYMISED','ERASURE_REQUEST_DECLINED')), 0)
         ORDER BY log_id DESC LIMIT 1"
    );
    $stmt->execute([':id' => $uid, ':id2' => $uid]);
    $v = $stmt->fetchColumn();
    return $v === false ? null : (string) $v;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'download') {
        $q = function (string $sql) use ($pdo, $uid): array {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $uid]);
            return $stmt->fetchAll();
        };
        $data = [
            'exported_at' => date('c'),
            'system' => 'Lost & Found Tracker (IT Project 700, Group 11)',
            'note' => 'Your personal information held by the system (POPIA s23 / GDPR Art. 15 and 20). Your password is stored only as a hash and is not included.',
            'account' => $q('SELECT user_id, full_name, email, role, is_active, created_at FROM users WHERE user_id = :id')[0] ?? null,
            'claims' => $q('SELECT cl.claim_id, cl.item_id, c.category_name AS item_category, i.description AS item_description,
                                   cl.proof_description, cl.consent_given_at, cl.verification_status, cl.date_submitted, cl.date_verified
                            FROM claims cl JOIN items i ON i.item_id = cl.item_id JOIN categories c ON c.category_id = i.category_id
                            WHERE cl.claimant_user_id = :id ORDER BY cl.claim_id'),
            'notifications' => $q('SELECT notification_id, claim_id, message, is_read, created_at FROM notifications WHERE user_id = :id ORDER BY notification_id'),
            'account_changes' => $q('SELECT action, detail, occurred_at FROM user_admin_log WHERE target_user_id = :id ORDER BY log_id'),
            'items_logged_by_you' => $q('SELECT i.item_id, c.category_name, i.date_found, i.location_found, i.status, i.created_at
                                         FROM items i JOIN categories c ON c.category_id = i.category_id WHERE i.logged_by = :id ORDER BY i.item_id'),
            'your_actions_in_audit_trail' => $q('SELECT action, item_id, claim_id, occurred_at FROM audit_log WHERE performed_by = :id ORDER BY log_id'),
        ];
        logUserAdmin($pdo, $uid, 'DATA_EXPORTED', null, $uid);

        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="my_data_' . date('Y-m-d') . '.json"');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'erase_request') {
        if (($_POST['confirm'] ?? '') !== 'yes') {
            flash('warn', 'Tick the box to confirm that you want your personal information deleted.');
            redirect('my_data.php');
        }
        if (pending_erasure_request($pdo, $uid)) {
            flash('info', 'Your request was already sent. An administrator will deal with it.');
            redirect('my_data.php');
        }
        $pdo->beginTransaction();
        logUserAdmin($pdo, $uid, 'ERASURE_REQUESTED', null, $uid);
        $pdo->prepare(
            "INSERT INTO notifications (user_id, claim_id, message)
             SELECT user_id, NULL, :m FROM users WHERE role = 'Admin' AND is_active = TRUE AND user_id <> :me"
        )->execute([':m' => 'A user asked for their personal information to be deleted. Open Users → Deletion requests.', ':me' => $uid]);
        $pdo->commit();
        flash('ok', 'Your request was sent. An administrator will delete your personal information and you will then be logged out.');
        redirect('my_data.php');
    }
    flash('bad', 'Unknown action.');
    redirect('my_data.php');
}

$count = function (string $sql) use ($pdo, $uid): int {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $uid]);
    return (int) $stmt->fetchColumn();
};
$nClaims = $count('SELECT COUNT(*) FROM claims WHERE claimant_user_id = :id');
$nNotes = $count('SELECT COUNT(*) FROM notifications WHERE user_id = :id');
$nItems = $count('SELECT COUNT(*) FROM items WHERE logged_by = :id');
$nChanges = $count('SELECT COUNT(*) FROM user_admin_log WHERE target_user_id = :id');
$requested = pending_erasure_request($pdo, $uid);
$isAdmin = $user['role'] === 'Admin';

$pageTitle = 'My data & privacy';
$activeNav = 'account';
$extraCss  = ['assets/css/photos.css'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><h1>My data &amp; privacy</h1><p class="muted">What the system holds about you, and your rights under POPIA and GDPR. <a href="<?= e(url('privacy.php')) ?>">Read the privacy notice</a>.</p></div>

<div class="card">
<h2 style="margin-top:0">What we hold about you</h2>
<ul class="dash-list">
<li><b>Account:</b> <?= e($user['full_name']) ?> · <?= e($user['email']) ?> · <?= e($user['role']) ?> <span class="small muted">(password stored only as a secure hash)</span></li>
<li><b>Claims you submitted:</b> <?= $nClaims ?> <span class="small muted">– including your proof-of-ownership text, removed <?= RETENTION_MONTHS ?> months after each decision</span></li>
<li><b>Notifications:</b> <?= $nNotes ?></li>
<?php if ($nItems): ?><li><b>Items you logged:</b> <?= $nItems ?> <span class="small muted">– your name is recorded as the person who logged them</span></li><?php endif; ?>
<li><b>Changes to your account:</b> <?= $nChanges ?> <span class="small muted">(created, role, password changes …)</span></li>
</ul>
<form method="post" action="<?= e(url('my_data.php')) ?>" style="margin-top:.75rem">
<?= csrf_field() ?><input type="hidden" name="action" value="download">
<button class="btn" type="submit">Download my data (JSON)</button>
<span class="small muted" style="margin-left:.5rem">A file you can open in any text editor or import into another system.</span>
</form>
</div>

<div class="card" style="margin-top:1rem">
<h2 style="margin-top:0">Correct your details</h2>
<p style="margin-bottom:0">To correct your name or email, contact an administrator. You can change your password under <a href="<?= e(url('account.php')) ?>">My account</a>.</p>
</div>

<div class="card" style="margin-top:1rem">
<h2 style="margin-top:0">Delete my personal information</h2>
<?php if ($isAdmin): ?>
<p style="margin-bottom:0">Administrator accounts are removed by another administrator on the Users page.</p>
<?php elseif ($requested): ?>
<div class="alert alert-info" role="status" style="margin:0">You asked for this on <?= e(substr($requested, 0, 16)) ?>. An administrator will deal with it; you will then be logged out and can no longer log in.</div>
<?php else: ?>
<p>An administrator will remove your name, email and proof-of-ownership text and close your account. Claims and items stay in the records other people depend on, but without your details. <b>This can't be undone</b>, and pending claims are decided first.</p>
<form method="post" action="<?= e(url('my_data.php')) ?>">
<?= csrf_field() ?><input type="hidden" name="action" value="erase_request">
<div class="field"><label class="consent"><input type="checkbox" name="confirm" value="yes" required> <span>I understand and want my personal information deleted.</span></label></div>
<button class="btn btn-outline" type="submit">Ask for my information to be deleted</button>
</form>
<?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
