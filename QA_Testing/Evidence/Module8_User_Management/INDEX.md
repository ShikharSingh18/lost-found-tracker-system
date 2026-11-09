# Module 8 – User management (admin/users.php, account.php)
Result: **15/15 tests PASS** (76–90) · tested 4 Nov 2026 in Chrome on XAMPP · BUG-02 closed · DEV-03 confirmed fixed · NOTE-06, NOTE-07 fixed
Shipped in: LostFoundTracker_Modules_6-10.zip · needs database/004_user_admin_log.sql imported

## Developer pre-test (test copy, 4 Nov 2026) – DEV_ screenshots
| File | What it shows | Result |
|---|---|---|
| DEV_users_list.png | Users list, own row marked "(you)" with no actions | PASS |
| DEV_account_created_temp_password.png | Account created; generated temporary password shown once | PASS |
| DEV_my_account_page.png | My account: details + change password | PASS |
| DEV_BUG-02_deactivated_user_logged_out.png | Deactivated user logged out on next click with the right message | PASS |
| DEV_locked_filter_unlock.png | Account = Locked filter shows the locked user with Unlock | PASS |
| DEV_recent_account_changes.png | Every change logged with who did it | PASS |
| DEV_users_mobile_390px.png | Phone layout (390 px) | PASS |

Also checked by script: duplicate email rejected; reload hides the temporary password; wrong current
password / short password rejected; self role change, self deactivate, self temporary password blocked
(even with a forged form); bad CSRF token → 400; Staff → 403 and no Users menu.

Found and fixed during the pre-test:
- DEV-04: after BUG-02's logout, "Please log in to continue." overwrote "Your account has been
  deactivated…". requireLogin() now keeps the more specific message.

## Leandro's tests (XAMPP, Chrome + Incognito, 4 Nov 2026)
| File | Test | Result |
|---|---|---|
| T76-setup | 004_user_admin_log.sql imported into lost_found: 6 columns, keys on log_id / target_user_id / performed_by, occurred_at defaults to current_timestamp(6) | PASS |
| T76a | Admin menu shows Users (after Audit Log) and My account (after Notifications) | PASS |
| T76b1 | Users page opens for Admin; Create an account folded, Find users open | PASS |
| T76b2 (also T77) | List: 3 accounts · 1 active admin; own row "(you)" with no role dropdown or actions; others have Change, Deactivate, New temporary password | PASS |
| T76c | Student: My account page with details; menu has My account, no Users | PASS |
| T76d | Student opens admin/users.php → "You do not have permission to open this page." | PASS |
| T76e | Staff: My account page; menu has Log Found Item and My account, no Users | PASS |
| T78 | Create Test Staff Two (staff2@test.com, Staff, password empty) → "Staff account created…" + generated temporary password with Copy | PASS |
| T79 | After F5 the temporary password box is gone; list shows 4 accounts incl. Test Staff Two (Active, 2026-11-04) | PASS |
| T80 | Second account with staff2@test.com → "An account with this email already exists."; form stays open with the name kept; still 4 accounts | PASS |
| T81 | staff2 logs in (Incognito) with the temporary password → Welcome, Test Staff Two, Staff menu incl. My account, no Users | PASS |
| T82 | My account: wrong current password → "Your current password is not correct."; nothing changed | PASS |
| T83a | Correct temporary password + new "Staff2Pass!" twice → "Your password was changed." | PASS |
| T83b | Log out, log in with Staff2Pass! → Welcome, Test Staff Two | PASS |
| T84a1 | Admin changes Test Staff Two Staff → Student → "…is now Student. This takes effect on their next click." | PASS |
| T84b2 | staff2 (Incognito) clicks Home → "logged in as Student"; Student menu, no Log Found Item | PASS (fully proven by T87b → T84c) |
| T85a1 | Deactivate asks "Deactivate Test Staff Two? They will be logged out and cannot log in until reactivated." with OK / Cancel | PASS |
| T85a2 | "Test Staff Two (staff2@test.com) was deactivated. If they are logged in, they are logged out on their next click." | PASS |
| T85b | BUG-02 RETEST: staff2 still logged in (Incognito) clicks a link → Login page with "Your account has been deactivated. Please contact an administrator." | PASS – BUG-02 closed |
| T86 | Deactivated staff2 logs in with the right password → "Invalid email or password." (same generic message, account not revealed) | PASS |
| T87a | Reactivate → "Test Staff Two (staff2@test.com) was reactivated and can log in again." | PASS |
| T87b | staff2 logs in again with Staff2Pass! → logged in as Student (also the "before" for T84c) | PASS |
| T84c | Admin changes role Student → Staff; staff2 clicks Home WITHOUT logging out → "logged in as Staff", Log Found Item back in menu | PASS – role change applies mid-session |
| T87c | New temporary password → "…Their old password no longer works." + yellow box with new password (shown once) | PASS |
| T87d | Old password Staff2Pass! → "Invalid email or password." | PASS |
| T87e | New temporary password → Welcome, Test Staff Two (Staff) | PASS |
| T88a | 5 wrong passwords, then the correct one → "Too many failed attempts. Please wait 15 minutes and try again." | PASS |
| T88b | Account = Locked filter → 1 account: Test Staff Two, Active + "Locked until 13:37", Unlock button | PASS |
| T88c | Unlock → "Test Staff Two (staff2@test.com) was unlocked and can try logging in again." | PASS |
| T88d | staff2 logs in with the correct password immediately (before 13:37) → Welcome (Staff) | PASS |
| T89a | Recent account changes: all 10 changes newest first, with detail (Staff -> Student etc.) and By; "Changed own password" by Test Staff Two, the rest by Leandro Admin | PASS |
| T89b | phpMyAdmin user_admin_log: 10 rows (USER_CREATED … ACCOUNT_UNLOCKED), target 5, performed_by 1 (5 for own password change), detail only role names or NULL – no passwords | PASS |
| T90 | DEV-03 RETEST: staff2 tab left open (bell polling) and untouched 13:25 → 14:40, then a click → "Your session expired. Please log in again." | PASS – DEV-03 confirmed fixed |

## Issues found in Module 8
| ID | Description | Status | Evidence |
|---|---|---|---|
| NOTE-06 | Users list: Created date wrapped onto two lines ("2026-11-" / "02") | Cosmetic – fixed 4 Nov (nowrap on the Created column; users.php + photos.css) | Note-06_created_date_wraps N1 (before), N2 (after) |
| NOTE-07 | Wide screens: the last table column (Actions, By) had no row lines – the frontend's mobile rule td:last-child{border-bottom:0} also applied on desktop. Affects every table, not just Users | Cosmetic – fixed 4 Nov in photos.css (desktop only) | Note-07_last_column_row_lines N1 (before), N2 (old photos.css still in place – file not yet replaced), N3 (after) |
| BUG-02 | (from Module 1) Deactivated user stayed logged in | Closed – T85b | T85b |
| DEV-03 | Bell polling kept idle sessions alive | Confirmed fixed – T90 | T90 |
| DEV-04 | Deactivated message replaced by "Please log in" | Fixed before release (developer test) | DEV_BUG-02 |
