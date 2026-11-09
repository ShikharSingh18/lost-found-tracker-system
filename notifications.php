<?php
/*
 * Module 6: Notifications inbox (Phase 4, Table 20 "Notifications inbox", Table 23;
 * group note 7: web-app based notifications shown in the browser, no email).
 *
 * - Every logged-in user sees ONLY their own notifications (user_id from the session).
 * - Newest first, unread ones highlighted, 30 per page.
 * - "Mark as read" per message and "Mark all as read" (POST + CSRF).
 * - Each message links to the page it is about: My Claims for claimants,
 *   Review Claims for administrators.
 * - The bell count in the menu refreshes itself every 30 seconds
 *   (assets/js/notifications.js + notifications_count.php).
 */
require_once __DIR__ . '/includes/bootstrap.php';

$user = requireLogin();
$pdo = db();
const NOTES_PER_PAGE = 30;

// ---------- Mark as read (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (($_POST['action'] ?? '') === 'all') {
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = TRUE WHERE user_id = :me AND is_read = FALSE');
        $stmt->execute([':me' => $user['user_id']]);
        flash('ok', $stmt->rowCount() . ' notification(s) marked as read.');
    } else {
        $id = filter_var(is_string($_POST['id'] ?? null) ? $_POST['id'] : '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id) {
            // "AND user_id = :me" means nobody can mark someone else's message.
            $pdo->prepare('UPDATE notifications SET is_read = TRUE WHERE notification_id = :id AND user_id = :me')
                ->execute([':id' => $id, ':me' => $user['user_id']]);
        }
    }
    $page = max(1, (int) ($_POST['page'] ?? 1));
    redirect('notifications.php' . ($page > 1 ? '?page=' . $page : ''));
}

// ---------- List (GET) ----------
$stmt = $pdo->prepare('SELECT COUNT(*), COALESCE(SUM(is_read = FALSE), 0) FROM notifications WHERE user_id = :me');
$stmt->execute([':me' => $user['user_id']]);
[$total, $unread] = array_map('intval', $stmt->fetch(PDO::FETCH_NUM));

$pages = max(1, (int) ceil($total / NOTES_PER_PAGE));
$page = min($pages, max(1, (int) (is_string($_GET['page'] ?? null) ? $_GET['page'] : 1)));

$stmt = $pdo->prepare(
    'SELECT n.notification_id, n.claim_id, n.message, n.is_read, n.created_at, cl.verification_status
     FROM notifications n
     LEFT JOIN claims cl ON cl.claim_id = n.claim_id
     WHERE n.user_id = :me
     ORDER BY n.created_at DESC, n.notification_id DESC
     LIMIT :limit OFFSET :offset'
);
$stmt->bindValue(':me', $user['user_id'], PDO::PARAM_INT);
$stmt->bindValue(':limit', NOTES_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':offset', ($page - 1) * NOTES_PER_PAGE, PDO::PARAM_INT);
$stmt->execute();
$notes = $stmt->fetchAll();

/** Friendly time: "Today, 09:12", "Yesterday, 18:40" or "27 Oct 2026, 14:03". */
function when(string $ts): string
{
    $d = new DateTimeImmutable($ts);
    $day = $d->format('Y-m-d');
    if ($day === date('Y-m-d')) {
        return 'Today, ' . $d->format('H:i');
    }
    if ($day === date('Y-m-d', strtotime('-1 day'))) {
        return 'Yesterday, ' . $d->format('H:i');
    }
    return $d->format('j M Y, H:i');
}

/** Where a notification should take the user. */
function note_link(array $n, string $role): ?array
{
    if (!$n['claim_id']) {
        return null;
    }
    if ($role === 'Admin') {
        return $n['verification_status'] === 'Pending'
            ? ['admin/claim_review.php', 'Review claim']
            : ['admin/claim_review.php', 'See decision'];
    }
    return ['claims/my_claims.php', 'View my claims'];
}

/** Short title from the message, for the bold first line. */
function note_title(array $n): array
{
    $m = $n['message'];
    if (str_contains($m, 'was approved')) {
        return ['Claim approved', 'approved'];
    }
    if (str_contains($m, 'not approved')) {
        return ['Claim not approved', 'rejected'];
    }
    if (str_starts_with($m, 'You collected')) {
        return ['Item collected', 'approved'];   // Module 7: owner picked the item up
    }
    if (str_starts_with($m, 'A new claim')) {
        // Show the claim's CURRENT status, not the status when the alert was sent.
        $status = strtolower((string) ($n['verification_status'] ?? 'pending'));
        return [$status === 'pending' ? 'New claim to review' : 'Claim reviewed', $status];
    }
    return ['Update', null];
}

$pageTitle = 'Notifications';
$activeNav = 'notifications';
$extraCss  = ['assets/css/photos.css'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><h1>Notifications</h1><p class="muted"><?= $unread ? "You have $unread unread notification" . ($unread === 1 ? '' : 's') . '.' : 'You are all caught up.' ?></p></div>

<div class="btn-row" style="margin-bottom:1rem">
<?php if ($unread): ?>
<form method="post" action="<?= e(url('notifications.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="all"><input type="hidden" name="page" value="<?= $page ?>"><button class="btn btn-outline btn-sm" type="submit">Mark all as read</button></form>
<?php endif; ?>
<button class="btn btn-ghost btn-sm" type="button" id="browser-alerts" style="display:none">Turn on browser alerts</button>
<span class="small muted" id="browser-alerts-state"></span>
</div>

<?php if (!$notes): ?>
<div class="card"><p style="margin:0">No notifications yet. You'll be told here when something happens to your claims<?= $user['role'] === 'Admin' ? ' or when a new claim needs review' : '' ?>.</p></div>
<?php else: ?>
<div class="card"><ul class="notes">
<?php foreach ($notes as $n):
    [$title, $pill] = note_title($n);
    $link = note_link($n, $user['role']);
    $isUnread = !$n['is_read']; ?>
<li<?= $isUnread ? ' class="note-unread"' : '' ?>>
<div style="display:flex;justify-content:space-between;gap:.75rem;align-items:flex-start;flex-wrap:wrap">
<div><b><?= e($title) ?></b><?php if ($pill): ?> <span class="pill pill-<?= e($pill) ?>"><?= e(ucfirst($pill)) ?></span><?php endif; ?><?php if ($isUnread): ?> <span class="badge-dot" aria-label="unread">New</span><?php endif; ?>
<br><span class="small<?= $isUnread ? '' : ' muted' ?>"><?= e($n['message']) ?></span>
<br><span class="small muted"><?= e(when($n['created_at'])) ?><?php if ($link): ?> · <a href="<?= e(url($link[0])) ?>"><?= e($link[1]) ?></a><?php endif; ?></span></div>
<?php if ($isUnread): ?>
<form method="post" action="<?= e(url('notifications.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $n['notification_id'] ?>"><input type="hidden" name="page" value="<?= $page ?>"><button class="btn btn-ghost btn-sm" type="submit">Mark as read</button></form>
<?php endif; ?>
</div></li>
<?php endforeach; ?>
</ul></div>
<?php if ($pages > 1): ?>
<nav class="pager" aria-label="Pagination">
<?php for ($p = 1; $p <= $pages; $p++): ?>
<?php if ($p === $page): ?><span class="on" aria-current="page"><?= $p ?></span><?php else: ?><a href="<?= e(url('notifications.php?page=' . $p)) ?>"><?= $p ?></a><?php endif; ?>
<?php endfor; ?>
</nav>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
