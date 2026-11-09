<?php
/*
 * Module 4: Submit a claim for an item (Phase 4, Section 6.5 and Table 17).
 *
 * Rules:
 * - Students and Staff only (Administrators verify claims, they don't make them).
 * - Only items with status Found can be claimed.
 * - The claimant is ALWAYS the logged-in user from the session, never a form field.
 * - Proof of ownership is required: at least 20 characters (max 2,000).
 * - Consent to the privacy notice is required (POPIA/GDPR) and its time is stored.
 * - A user can't have two Pending claims on the same item; after a Rejected
 *   claim they may try again.
 * - A new claim is Pending. Submitting doesn't change the item's status.
 * - The claim, its audit entry and the administrators' notifications are saved
 *   in ONE transaction. The item row is locked while checking, so two quick
 *   clicks can't create duplicate claims.
 *
 * Needs database/003_claim_consent.sql.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$user = requireRole('Student', 'Staff');
$pdo = db();

const PROOF_MIN = 20;
const PROOF_MAX = 2000;

$rawId = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['item_id'] ?? '') : ($_GET['item_id'] ?? '');
$itemId = filter_var(is_string($rawId) ? $rawId : '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

function load_item(PDO $pdo, int $id, bool $lock = false): array|false
{
    $stmt = $pdo->prepare(
        'SELECT i.item_id, i.description, i.date_found, i.location_found, i.status, c.category_name,
                (SELECT p.photo_id FROM item_photos p WHERE p.item_id = i.item_id ORDER BY p.sort_order LIMIT 1) AS thumb_id
         FROM items i JOIN categories c ON c.category_id = i.category_id
         WHERE i.item_id = :id' . ($lock ? ' FOR UPDATE' : '')
    );
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

function has_pending_claim(PDO $pdo, int $itemId, int $userId, bool $lock = false): bool
{
    $stmt = $pdo->prepare(
        "SELECT claim_id FROM claims
         WHERE item_id = :item AND claimant_user_id = :user AND verification_status = 'Pending'
         LIMIT 1" . ($lock ? ' FOR UPDATE' : '')
    );
    $stmt->execute([':item' => $itemId, ':user' => $userId]);
    return (bool) $stmt->fetchColumn();
}

function show_unavailable(string $message): never
{
    http_response_code(404);
    $pageTitle = 'Claim not possible';
    $activeNav = 'search';
    require __DIR__ . '/../includes/header.php';
    echo '<div class="alert alert-warn" role="status">' . e($message) . '</div>';
    echo '<p><a class="btn btn-ghost" href="' . e(url('items/search.php')) . '">Back to search</a></p>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$item = $itemId ? load_item($pdo, $itemId) : false;
if (!$item || $item['status'] !== 'Found') {
    show_unavailable('This item is not available to claim. It may already have been claimed.');
}
if (has_pending_claim($pdo, $itemId, $user['user_id'])) {
    flash('info', 'You already have a pending claim for this item. An administrator will review it.');
    redirect('claims/my_claims.php');
}

$errors = [];
$oldProof = '';
$oldConsent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $proof = trim(is_string($_POST['proof'] ?? null) ? $_POST['proof'] : '');
    $consent = ($_POST['consent'] ?? '') === 'yes';
    $oldProof = $proof;
    $oldConsent = $consent;

    // ---- Validation (Table 17 / Table 19) ----
    $len = mb_strlen($proof);
    if ($len < PROOF_MIN) {
        $errors[] = 'Proof of ownership must be at least ' . PROOF_MIN . ' characters. Describe details only the owner would know.';
    } elseif ($len > PROOF_MAX) {
        $errors[] = 'Proof of ownership must be ' . number_format(PROOF_MAX) . ' characters or fewer.';
    }
    if (!$consent) {
        $errors[] = 'Please tick the box to agree to how your claim details are used.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Lock the item and re-check it inside the transaction.
            $locked = load_item($pdo, $itemId, true);
            if (!$locked || $locked['status'] !== 'Found') {
                $pdo->rollBack();
                show_unavailable('Sorry, this item was claimed by someone else a moment ago.');
            }
            if (has_pending_claim($pdo, $itemId, $user['user_id'], true)) {
                $pdo->rollBack();
                flash('info', 'You already have a pending claim for this item.');
                redirect('claims/my_claims.php');
            }

            $pdo->prepare(
                'INSERT INTO claims (item_id, claimant_user_id, proof_description, consent_given_at)
                 VALUES (:item, :user, :proof, NOW(6))'
            )->execute([':item' => $itemId, ':user' => $user['user_id'], ':proof' => $proof]);
            $claimId = (int) $pdo->lastInsertId();

            writeAudit($pdo, 'CLAIM_SUBMITTED', $itemId, $claimId, $user['user_id']);

            // Tell every active administrator (Table 23). The message holds no proof details.
            $pdo->prepare(
                "INSERT INTO notifications (user_id, claim_id, message)
                 SELECT user_id, :claim, :msg FROM users WHERE role = 'Admin' AND is_active = TRUE"
            )->execute([
                ':claim' => $claimId,
                ':msg'   => "A new claim was submitted for item #$itemId ({$locked['category_name']}).",
            ]);

            $pdo->commit();

            flash('ok', "Your claim for the {$locked['category_name']} (item #$itemId) was submitted. An administrator will review it.");
            redirect('claims/my_claims.php');   // POST-redirect-GET: refresh won't submit twice
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('claim submit failed: ' . $ex->getMessage());
            $errors[] = DEV_MODE ? 'Saving failed: ' . $ex->getMessage() : 'Your claim could not be saved. Please try again.';
        }
    }
}

$icons = ['Keys' => '🔑', 'Wallet' => '👛', 'Phone' => '📱', 'ID Card' => '🪪', 'Clothing' => '🧥', 'Stationery' => '✏️', 'Other' => '📦'];

$pageTitle = 'Submit a claim';
$activeNav = 'search';
$extraCss  = ['assets/css/photos.css'];
require __DIR__ . '/../includes/header.php';
?>
<div class="crumbs"><a href="<?= e(url('items/search.php')) ?>">Search</a> › <a href="<?= e(url('items/details.php?id=' . $itemId)) ?>">Item</a> › Claim</div>
<div class="grid layout-2"><div class="stack"><div class="page-head"><h1>Submit a claim</h1><p class="muted">Provide details only the owner would know. An administrator will verify them before release.</p></div>
<?php if ($errors): ?>
<div class="alert alert-bad" role="alert"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<form class="card form" action="<?= e(url('claims/submit.php')) ?>" method="post">
<?= csrf_field() ?>
<input type="hidden" name="item_id" value="<?= (int) $itemId ?>">
<div class="field"><label for="proof">Proof of ownership <span class="req">*</span></label><textarea id="proof" name="proof" minlength="<?= PROOF_MIN ?>" maxlength="<?= PROOF_MAX ?>" required placeholder="E.g. number of keys, keyring charms, unique marks, contents…"><?= e($oldProof) ?></textarea><div class="hint">Minimum <?= PROOF_MIN ?> characters. Do not share passwords, PINs or full ID numbers.</div></div>
<div class="field"><label class="consent"><input type="checkbox" name="consent" value="yes" required<?= $oldConsent ? ' checked' : '' ?>> <span>I agree that my name, email and the details above are stored and checked by an administrator only to decide this claim, and are deleted after the retention period (<a href="<?= e(url('privacy.php')) ?>" target="_blank" rel="noopener">privacy notice</a>). <span class="req">*</span></span></label></div>
<div class="btn-row"><button class="btn" type="submit">Submit claim</button><a class="btn btn-ghost" href="<?= e(url('items/details.php?id=' . $itemId)) ?>">Cancel</a></div></form></div>
<aside class="card card-flat stack"><h3>You are claiming</h3><div class="item" style="box-shadow:none">
<div class="thumb"<?= $item['thumb_id'] ? '' : ' aria-hidden="true"' ?>><?php if ($item['thumb_id']): ?><img src="<?= e(url('items/photo.php?id=' . (int) $item['thumb_id'])) ?>" alt="Photo of the found <?= e(strtolower($item['category_name'])) ?>"><?php else: ?><?= $icons[$item['category_name']] ?? '📦' ?><?php endif; ?></div>
<div><b><?= e($item['category_name']) ?></b><p class="small" style="margin:0"><?= e(mb_strimwidth($item['description'], 0, 90, '…')) ?></p><div class="meta"><span>📍 <?= e($item['location_found']) ?></span><span>📅 <?= e((new DateTimeImmutable($item['date_found']))->format('j M Y')) ?></span></div><span class="pill pill-found">Found</span></div></div></aside></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
