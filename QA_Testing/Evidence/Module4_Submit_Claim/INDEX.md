# Module 4 – Submit a claim and My Claims (claims/submit.php, claims/my_claims.php)
Result: 12/12 tests PASS · 0 bugs · tested 3 Nov 2026 in Chrome on XAMPP
Schema addition used: database/003_claim_consent.sql (claims.consent_given_at)

| File | Test | Result |
|---|---|---|
| T33a | Admin menu has no "My Claims"; bell shows 3 unread (one per claim submitted) | PASS |
| T33b | Admin opening claims/submit.php?item_id=3 → "You do not have permission" | PASS |
| T34 | Claim form: proof box, consent checkbox, item summary with photo, breadcrumbs | PASS |
| T35a | Browser validation switched off in DevTools | – |
| T35b | Proof "my keys" + no consent → both server errors; text kept | PASS |
| T36 | Valid proof + no consent → only the consent error; nothing saved | PASS |
| T37 | Valid claim → My Claims, green message, Pending, "Awaiting review" | PASS |
| T37b | F5 on My Claims → still one claim (no double submit) | PASS |
| T38-39 | Database: claim (user 3, Pending, consent time), item still Found, CLAIM_SUBMITTED audit row, one notification to the Admin only | PASS |
| T40a | Item page shows "You have a pending claim – view it" | PASS |
| T40b | Typing the claim address again → My Claims "You already have a pending claim" | PASS |
| T41a | Claim set to Rejected → red badge, decision time, "You can submit a new claim" | PASS |
| T41b | Claim button returns after rejection | PASS |
| T41c | New claim accepted: Pending above Rejected | PASS |
| T42 | Claimed item → "This item is not available to claim" (no form) | PASS |
| T43a | Staff's My Claims doesn't show the student's claims | PASS |
| T43b | Staff can claim (Keys, Pending) | PASS |
| T44a | item_id=abc → "not available to claim", no PHP/database error | PASS |
| T44b | Forged CSRF token → 400 Bad Request "Your form expired or was not valid"; no claim saved | PASS |

## Developer test results (SQLite test copy, before release)
All passed, including: a form field claimant_user_id=2 was ignored (claim saved for the logged-in
user), and a deactivated admin received no notification.

| File | Shows |
|---|---|
| DEV_claim_form_testdata | Claim form on test data |
| DEV_my_claims_pending_rejected_testdata | My Claims with Pending and Rejected rows on test data |

## Note
NOTE-03 (cosmetic): the CSRF-refused page (T44b) is plain unstyled text. Works correctly; could be
shown inside the normal page layout later.

Cleanup: UPDATE items SET status = 'Found' WHERE item_id = 1;  (if not already done after T42)
