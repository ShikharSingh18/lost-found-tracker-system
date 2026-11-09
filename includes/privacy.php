<?php
/*
 * Module 10: privacy settings and helpers (POPIA + GDPR, group note 8; Phase 3 NFR3).
 *
 * >>> THE GROUP MUST CONFIRM THESE TWO SETTINGS BEFORE THE DEMO <<<
 *   RETENTION_MONTHS  how long proof-of-ownership text and read notifications are kept
 *                     after a claim is decided (placeholder: 12 months).
 *   PRIVACY_CONTACT   who people contact about their personal information.
 */
const RETENTION_MONTHS = 12;
const PRIVACY_CONTACT  = 'the Lost & Found office (Information Officer: to be confirmed by Group 11)';

const ANONYMISED_DOMAIN = 'deleted.invalid';   // ".invalid" can never be a real email domain
const PROOF_REMOVED_TEXT = '[Proof text removed for privacy]';

function is_anonymised(string $email): bool
{
    return str_ends_with($email, '@' . ANONYMISED_DOMAIN);
}

/** First moment that is still inside the retention period, as 'Y-m-d H:i:s'. */
function retention_cutoff(): string
{
    return (new DateTimeImmutable('now'))->modify('-' . RETENTION_MONTHS . ' months')->format('Y-m-d H:i:s');
}

/**
 * Right to erasure (POPIA s24, GDPR Art. 17). Call INSIDE a transaction, with the user row locked.
 * Keeps the rows that other records depend on (claims, items, audit trail – foreign keys
 * and FR10 need them) but removes everything that identifies the person:
 *   users: name -> "Deleted user #id", email -> deleted-<id>@deleted.invalid,
 *          unusable random password, deactivated
 *   claims: proof-of-ownership text removed
 *   notifications: deleted;  login_attempts: deleted
 * @return int number of claims whose proof text was removed
 */
function anonymise_user(PDO $pdo, array $u, int $performedBy): int
{
    $id = (int) $u['user_id'];
    $pdo->prepare('DELETE FROM login_attempts WHERE email = :e')->execute([':e' => $u['email']]);
    $pdo->prepare('UPDATE users SET full_name = :n, email = :e, password_hash = :h, is_active = FALSE WHERE user_id = :id')
        ->execute([
            ':n'  => 'Deleted user #' . $id,
            ':e'  => 'deleted-' . $id . '@' . ANONYMISED_DOMAIN,
            ':h'  => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
            ':id' => $id,
        ]);
    $stmt = $pdo->prepare('UPDATE claims SET proof_description = :t WHERE claimant_user_id = :id AND proof_description <> :t2');
    $stmt->execute([':t' => PROOF_REMOVED_TEXT, ':t2' => PROOF_REMOVED_TEXT, ':id' => $id]);
    $claims = $stmt->rowCount();
    $pdo->prepare('DELETE FROM notifications WHERE user_id = :id')->execute([':id' => $id]);
    logUserAdmin($pdo, $id, 'USER_ANONYMISED', $claims . ' claim proof text(s) removed', $performedBy);
    return $claims;
}
