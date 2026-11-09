# Module 10 – Privacy, POPIA and GDPR (privacy.php, my_data.php, admin/retention.php, Users → Delete personal data)
Result: **14/14 tests PASS** (105–118) · tested 4 Nov 2026 in Chrome on XAMPP · no bugs found (T114a covered by Module 8 T85b)
Shipped in: LostFoundTracker_Modules_6-10.zip · no database changes
Settings to confirm with the group: RETENTION_MONTHS = 12 (placeholder), PRIVACY_CONTACT (Information Officer) – includes/privacy.php

## Developer pre-test (test copy, 4 Nov 2026) – DEV_ screenshots
| File | What it shows | Result |
|---|---|---|
| DEV_privacy_notice.png | Notice opens logged out; footer link | PASS |
| DEV_my_data.png | Summary of what is held + Download + Delete request | PASS |
| DEV_deletion_requested.png | "Your request was sent"; "You asked for this on …"; button gone | PASS |
| DEV_users_deletion_requests_box.png | Deletion requests (1) with "has 1 pending claim – decide them first" | PASS |
| DEV_user_anonymised.png | Row "Deleted user #3 / deleted-3@deleted.invalid / Personal data deleted", no buttons | PASS |
| DEV_retention_preview.png | 2 old claims due, cut-off date, Run clean-up | PASS |
| DEV_mobile_privacy.png | Notice at 390 px | PASS |

Also checked by script: JSON export has account, claims (4), notifications, account changes, items, own
audit actions and no password hash; guest on my_data.php → Login; anonymise blocked while a claim is
Pending, allowed after it was decided; anonymised user logged out on next click; 4 proof texts removed,
notifications deleted; self-anonymise and reactivating an anonymised account refused (forged form);
retention removed 2 proof texts with 2 CLAIM_PROOF_REMOVED audit rows; Staff → 403 on retention;
consent box and Register link to the notice; no browser errors.

## Leandro's tests (XAMPP, Chrome + Incognito, 4 Nov 2026)
| File | Test | Result |
|---|---|---|
| T105a–c | Logged out (menu Login/Register only): privacy notice opens; who is responsible, what/why/who table, finders not recorded, legal basis, 12 months retention, storage, rights, Information Regulator | PASS |
| T106a | Register page: "Your name and email are used only to run your account and your claims. See the privacy notice." | PASS |
| T106b1–b2 | Claim form (staff, item #17): consent box ends "(privacy notice)"; link opens privacy.php in a new tab, claim not submitted | PASS |
| T107a–b | My data & privacy (Test Student): account, 4 claims, 2 notifications, 0 account changes; Download button; Correct your details; Delete my personal information with tick box | PASS |
| T108a1–a4 | my_data JSON in Notepad: account, claims with own proof text (claim 1 already shows the test date 2025-06-01), notifications; "password" found only in the note sentence – no hash | PASS |
| T108b | Audit Log → Account changes: #11 Test Student "Downloaded own data" 18:46:14 by Test Student | PASS |
| T109 | Logged out → my_data.php → Login with "Please log in to continue." | PASS |
| T110a | staff2: submit without ticking → Chrome "Please check this box if you want to proceed." | PASS |
| T110b | Tick + submit → "Your request was sent. An administrator will delete your personal information…"; account changes 10 → 11 | PASS |
| T111a | Admin notification "A user asked for their personal information to be deleted. Open Users → Deletion requests." – no name | PASS |
| T111b | Users: "Deletion requests (1)" – Test Staff Two (staff2@test.com, Staff) asked 18:55, Delete personal information / Decline | PASS |
| T112 | Delete personal data on Test Student (claim #11 Pending) → "has 1 pending claim(s). Approve or reject them … first"; nothing changed | PASS |
| T113a | Delete personal information → "…the account is now "Deleted user #5", closed, and the proof text of 0 claim(s) was removed…" | PASS |
| T113b | Row: Deleted user #5 · deleted-5@deleted.invalid · Staff · "Personal data deleted" · no buttons | PASS |
| T114a | staff2's open tab, next click → logged out | Not captured – the tab sat idle > 30 min, so the idle timeout logged it out first ("Your session expired"). Same per-click account check already proven in Module 8 T85b |
| T114b | Log in with staff2@test.com + old password → "Invalid email or password." | PASS |
| T115a | phpMyAdmin users row 5: Deleted user #5 · deleted-5@deleted.invalid · new random hash · is_active 0 | PASS |
| T115b | Account changes: #13 Personal information deleted (0 claim proof text(s) removed) by Leandro Admin; #12 Asked for deletion; staff2's Module 8 history now under "Deleted user #5" | PASS |
| T116a | Retention clean-up: 1 decided claim older than 12 months (claim #1, Rejected, decided 2025-06-01), cut-off 2025-11-04 | PASS |
| T116b | Run clean-up → "proof text removed from 1 claim(s), 0 old read notification(s) deleted"; cards 0 / 0 / 2025-11-04 / 1 | PASS |
| T116c | Audit Log, Action = Proof text removed (privacy) → 1 entry: #36 item #3 claim #1 by Leandro Admin | PASS |
| T116d | phpMyAdmin claims row 1: proof_description "[Proof text removed for privacy]"; status, dates, consent kept; claim 2 untouched | PASS |
| T117a | Users: own row (Leandro Admin, you) has no actions – no "Delete personal data"; 4 accounts · 1 active admin | PASS |
| T117b | Staff (TestLee) → admin/retention.php → "You do not have permission to open this page." | PASS |
| T118a1–a2 | iPhone 12 Pro (390 px): privacy notice readable, ☰ menu, "What we collect" table shown as one card per row, no sideways scrolling | PASS |
| T118b | iPhone 12 Pro: My data & privacy (TestLee) – summary and Download button fit the screen | PASS |

## Issues found in Module 10
| ID | Description | Severity / Status | Evidence |
|---|---|---|---|
| – | No bugs found in Module 10 testing | – | – |
