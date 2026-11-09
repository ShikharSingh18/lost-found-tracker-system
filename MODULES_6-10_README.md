# Lost & Found Tracker – Modules 6 to 10 (add-on pack)
Backend: Leandro Rughunath · Group 11 · IT Project 700

This pack holds ONLY the files added or changed for Modules 6–10. It goes on top of the
core site (Modules 1–5, backend_v5.zip). Copy the contents of this LostFoundTracker folder over
htdocs\LostFoundTracker and choose "Replace" when Windows asks.

## Contents (Modules 6–10 complete)
| Module | Status | Files |
|---|---|---|
| 6 Notifications inbox and live bell | Done and tested 4 Nov 2026 (8/8) | notifications.php, notifications_count.php, assets/js/notifications.js |
| 7 Manage Items | Done and tested 4 Nov 2026 (12/12) | items/manage.php, items/edit.php (notifications.php updated) |
| 8 User management | Done and tested 4 Nov 2026 (15/15) | admin/users.php, account.php, database/004_user_admin_log.sql |
| 9 Dashboard, reports, audit log | Done and tested 4 Nov 2026 (14/14) | admin/dashboard.php, admin/reports.php, admin/audit_log.php, includes/reports.php |
| 10 Privacy (POPIA/GDPR) | Done and tested 4 Nov 2026 (14/14) | privacy.php, my_data.php, admin/retention.php, includes/privacy.php |

Shared files changed by these modules (replace the core versions):
| File | Changed for | Change |
|---|---|---|
| includes/footer.php | Module 6 | Loads assets/js/notifications.js for logged-in users (live bell) |
| assets/css/photos.css | Modules 6, 7, 8 | Highlight for unread notifications; filter panel can be folded/unfolded at every screen size (BUG-06); Users page styles; row lines under the last table column on wide screens (NOTE-07); dashboard cards, report tabs |
| includes/bootstrap.php | Module 8 | Checks the account on every click: a deactivated user is logged out at once and a changed role takes effect at once (BUG-02). The 30-second bell check no longer keeps an idle session alive (DEV-03) |
| includes/header.php | Modules 8, 9 | Menu items "Users" (Admin) and "My account" (everyone); "Reports" (Admin) |
| includes/authentication.php | Module 8 | logUserAdmin() and generate_temp_password(); "account deactivated" message is no longer replaced by "Please log in" |
| admin/users.php | Modules 8, 10 | Deletion requests box; "Delete personal data" (anonymise) button |
| admin/dashboard.php | Modules 9, 10 | "Retention clean-up" button |
| includes/reports.php | Modules 9, 10 | Labels for the new privacy log entries |
| account.php | Modules 8, 10 | Link to My data & privacy |
| includes/footer.php | Modules 6, 10 | "Privacy notice" link on every page |
| claims/submit.php | Module 10 (core file) | Consent box links to the privacy notice |
| register.php | Module 10 (core file) | Short privacy note with link |
| notifications_count.php | Module 8 | Marked as a background request (DEV-03) |

No database changes for Module 6.

Fix after testing (4 Nov): admin alerts for claims that were already decided showed a yellow "Pending"
badge. They now show the claim's current status ("Claim reviewed" + Approved/Rejected). BUG-05.

## Module 6 – Notifications (group note 7: web-app based, in the browser, no email)
- Notifications page: each user sees only their own notifications, newest first; unread ones are
  highlighted with a "New" badge; "Mark as read" and "Mark all as read".
- Each message links to where it matters: My Claims (claimants) or Review Claims (admins).
- The red bell count in the menu and the tab title, e.g. "(2) Search found items", refresh
  themselves every 30 seconds without reloading the page (only while the tab is visible).
- "Turn on browser alerts": with permission, Windows shows a pop-up when a new notification arrives.

### Tests 56–63
56. As Student, the bell count equals the unread notifications. Logged out, open
    localhost/LostFoundTracker/notifications_count.php → {"unread":null} (not allowed without login).
57. Notifications page lists only your own messages, newest first; unread ones highlighted with "New".
58. "Mark as read" on one message → it turns plain, the bell count drops by 1.
59. "Mark all as read" → "You are all caught up."; the bell badge disappears.
60. LIVE BELL: Admin keeps Search open in Chrome. In an Incognito window (Ctrl+Shift+N) log in as
    Student and submit a claim. Within 30 seconds, WITHOUT reloading, the Admin's bell goes up by 1
    and the tab title shows "(n)".
61. Browser alerts: on Notifications click "Turn on browser alerts" → Allow. Repeat test 60:
    a Windows pop-up "Lost & Found Tracker" appears; clicking it opens the Notifications page.
62. Links: claimant message "View my claims" → My Claims; admin message "Review claim" → Review Claims.
63. Security (developer-tested): one user cannot mark another user's notification as read.

## Module 7 – Manage Items (Administrator) – Phase 4 Table 24, Section 8.5
- Manage Items lists ALL items (any status) with filters incl. Status, 20 per page.
- Edit: change category, description, date, location; remove photos and add new ones
  (max 5 in total; the first photo is the thumbnail). Saved with an ITEM_UPDATED audit row.
  Status is never edited directly – only through claims, Dispose or Mark collected.
- Dispose (instead of delete): only Found items. Status -> Disposed + ITEM_DISPOSED audit row.
  Pending claims on that item are rejected automatically and the claimants are told
  "The item is no longer available."
- Mark collected: when the owner physically picks up a Claimed item, an ITEM_RELEASED audit
  row is recorded (Phase 4 6.6) and the owner gets "You collected your …". Only once per item.
- No database changes. Dispose and Mark collected ask "Are you sure?" first.

### Tests 64–75 (log in as Admin unless stated)
64. Student or Staff opens items/manage.php or items/edit.php?id=1 → "You do not have permission".
65. Manage Items lists every item with status badges; Found items show Dispose, Claimed items
    show Mark collected, pending claims show "N pending claim(s)" linking to Review Claims.
66. Filters: Status = Claimed → only Claimed items; keyword/category work as on Search.
67. Edit item 2 (Black Wallet): change the description → "Item #2 updated."; Search shows the
    new text; audit_log has ITEM_UPDATED for item 2.
68. Edit validation: empty location, future date → errors; nothing changes.
69. Photos: on the Black Wallet add 2 photos → "2 photo(s) added"; thumbnail appears in Search.
70. Photos: try to add 4 more (would be 6) → "An item can have at most 5 photos"; nothing changes.
71. Photos: tick "Remove (thumbnail)" and save → the 2nd photo becomes the thumbnail; the removed
    file is gone from uploads/item_photos.
72. MARK COLLECTED on the Keys (item 1, Claimed) → confirm → "marked as collected"; status shows
    "Collected <time>"; button disappears; audit_log has ITEM_RELEASED; Staff (owner) gets
    "You collected your Keys (item #1)" in Notifications.
73. Dispose: log a new test item, have the Student claim it, then Dispose it → "marked Disposed.
    1 pending claim(s) were rejected automatically."; Student's My Claims shows Rejected and the
    notification "…The item is no longer available."; item disappears from student Search.
74. Dispose is not offered for Claimed items; Mark collected is not offered for Found/Disposed items.
75. Cancel: click Dispose and choose Cancel in the "Are you sure?" box → nothing changes.

## Module 8 – User management – Phase 4 Table 24 "Manage user accounts", Section 8.2
- Users page (Admin): list and search accounts (name/email, role, Active/Deactivated/Locked), 25 per page.
- Create an account (Student, Staff or Admin). Type a temporary password or leave it empty and the
  system makes one, e.g. Kp7w-Rx3m-Tq9b. It is shown ONCE with a Copy button and never stored readable.
- Change role, Deactivate / Reactivate (accounts are never deleted), New temporary password
  (password resets are disabled – group note 12 – so the admin gives a new one), Unlock an account
  locked after 5 failed logins.
- Safety rules: you can't change your own role, deactivate yourself or give yourself a temporary
  password; the last active Admin can never be demoted or deactivated.
- My account (everyone): see your details and change your own password (current + new twice, 8–72 characters).
- Every change goes to the new user_admin_log table (who, what, when, by whom – never the password)
  and the latest 10 are shown under "Recent account changes".
- BUG-02 fixed: a deactivated user who is still logged in is logged out on their next click with
  "Your account has been deactivated. Please contact an administrator."
- DEV-03 fixed: the bell check every 30 seconds used to keep the session alive forever, so the
  30-minute idle logout never happened while a tab was open. It no longer counts as activity.

### Set-up (once)
1. Copy this pack over htdocs\LostFoundTracker (Replace).
2. phpMyAdmin → your Lost & Found database (the DB_NAME in config\db_settings.php, default lost_found) → Import → database/004_user_admin_log.sql → Go.
   (New table only; nothing existing changes. Needs DB team approval like 001–003.)

### Tests 76–90 (log in as Admin unless stated; use Chrome + an Incognito window for the second user)
76. Menu shows "Users" and "My account" for Admin; Student/Staff see only "My account".
    Student opens admin/users.php → "You do not have permission".
77. Users lists all accounts with role, Active badge and created date; your row says "(you)" and has
    no role dropdown or action buttons.
78. Create an account: name "Test Staff Two", email staff2@test.com, role Staff, password empty →
    "Staff account created…" and a yellow box with the temporary password + Copy. WRITE IT DOWN.
79. Reload the page (F5) → the temporary password box is gone (shown only once).
80. Create again with staff2@test.com (or STAFF2@test.com) → "An account with this email already exists."
81. Incognito: log in as staff2@test.com with the temporary password → works, lands on Search.
82. Incognito, My account: wrong current password + "abc" → both errors; nothing changes.
83. Incognito, My account: correct temporary password + new "Staff2Pass!" twice → "Your password was
    changed." Log out and in again with the new password → works.
84. Admin: change staff2's role to Student → "…is now Student". Incognito: click any menu item →
    the menu now shows Student options (no Log Found Item) without logging out.
85. BUG-02 retest: Admin deactivates staff2 (confirm the box). Incognito: click any link → back on Login
    with "Your account has been deactivated. Please contact an administrator."
86. Incognito: log in as staff2 with the right password → "Invalid email or password." (blocked).
87. Admin: Reactivate staff2 → can log in again. Then "New temporary password" → a new password is shown;
    the old one ("Staff2Pass!") no longer works, the new one does.
88. Lockout + Unlock: Incognito, enter a wrong password for staff2 5 times → "Too many failed attempts".
    Admin: Users → Account = Locked → staff2 shows "Locked until hh:mm" + Unlock → click it →
    staff2 can log in straight away.
89. Recent account changes at the bottom lists every step above (Created, Role changed, Deactivated,
    Reactivated, Temporary password set, Unlocked, Changed own password) with who did it.
    phpMyAdmin: user_admin_log has the same rows and no passwords.
90. DEV-03 retest (idle timeout): log in as Student, leave the tab open and don't touch it for
    31 minutes, then click a link → "Your session expired" / Login page. (Before the fix the bell
    kept the session alive.)

## Module 9 – Dashboard, reports and audit log – FR8, FR10, Phase 4 Table 21/22
- Dashboard (Admin): cards for items (logged, Found, Claimed + collected, Disposed) and claims
  (Pending – highlighted when > 0, Approved, Rejected, total). Every card opens the matching report.
  Items by category (bar chart), claims waiting longest, Found items waiting longest, latest activity.
- Reports (new menu item): Items report (date found from/to, status, category) and Claims report
  (date submitted from/to, status). 25 per page, status totals, "Export to Excel" with the same filters
  (semicolon-separated, so it opens in columns in Excel on South African Windows – BUG-07), plus a
  "Comma-separated CSV" link for other programs. UTF-8. A cell that starts with = + - @ is saved with a ' in front, so Excel
  can't run it as a formula (CSV injection protection).
- Privacy: reports never include the proof-of-ownership text; claimant emails appear only in the
  claims CSV (needed to contact owners).
- Audit log: read-only (no edit/delete anywhere). Tabs "Items and claims" (audit_log) and
  "Account changes" (user_admin_log from Module 8). Filters: action, performed by, dates,
  item # / claim # or account. 50 per page, newest first, Export CSV. Click an item or claim number
  to see its whole history.
- No database changes.

### Tests 91–104 (log in as Admin unless stated)
91. Menu shows Dashboard, Reports and Audit Log. Staff/Student opening admin/dashboard.php,
    admin/reports.php or admin/audit_log.php → "You do not have permission".
92. Dashboard cards match the database: compare with phpMyAdmin (items by status, claims by status).
    "Claimed (n collected)" = items with ITEM_RELEASED.
93. Click each card → the right report opens already filtered (e.g. Disposed → Items report, status Disposed);
    Pending → Review Claims.
94. Items by category: bar lengths and numbers match; hover shows Found/Claimed.
95. Claims waiting longest / Found items waiting longest / Latest activity show the right entries
    (log a new item first so there is a Found item; submit a claim as Student so there is a pending one).
96. Items report: no filters → all items; status totals add up to the total.
97. Items report filters: Status = Claimed; Category = Keys; a date range. Set "from" after "to" →
    note "dates were swapped".
98. Export to Excel (Items) → items_report_<date>.csv opens in Excel with each heading in its own column
    (Item ID … Logged at) and the same rows as the screen (all pages); quotes/commas correct.
    "Comma-separated CSV" gives the standard comma file (for Google Sheets / other programs).
99. CSV injection: log an item with description  =1+1  → in the CSV/Excel it shows '=1+1 as text,
    not 2.
100. Claims report: all claims with claimant, status, submitted, decided by; Status = Rejected filter;
     Export to Excel includes the claimant email but NOT the proof text.
101. Audit log "Items and claims": newest first; Action filter (e.g. Claim approved); Performed by filter;
     item # filter; click an item number → its full history.
102. Audit log "Account changes": the Module 8 changes (Test Staff Two); filter by account; Export to Excel.
103. Read-only: there is no edit or delete on the audit log; bad values in the address bar
     (e.g. ?item=abc&from=2026-13-40) → "A date was not valid and was ignored.", no error page.
104. Phone (Chrome F12 → device toolbar, iPhone 12 Pro): dashboard, reports and audit log readable,
     no sideways scrolling; tables become cards.

## Module 10 – Privacy (POPIA + GDPR, group note 8, Phase 3 NFR3)
>>> The group must confirm two settings in includes/privacy.php before the demo:
>>> RETENTION_MONTHS (placeholder 12) and PRIVACY_CONTACT (Information Officer).
- Privacy notice (privacy.php, no login needed, linked in every footer, on Register and in the claim
  consent box): what is collected, why, legal basis, who sees it, retention, storage, rights, complaints.
- My data & privacy (my_data.php, from My account): summary of what is held; "Download my data" =
  JSON file (account, claims incl. own proof text, notifications, account changes, items logged, own
  audit actions; never the password hash); "Ask for my information to be deleted" (tick box to
  confirm) → admins get a notification (without the name) and a Deletion requests box on Users.
- Users page: "Delete personal data" (anonymise): name → "Deleted user #id", email →
  deleted-<id>@deleted.invalid, random unusable password, deactivated, proof text of their claims
  removed, their notifications and login attempts deleted. Claims, items and the audit trail stay
  (other records depend on them). Blocked for yourself, the last active Admin and anyone with Pending
  claims. Can't be undone; the account can't be reactivated or changed afterwards. "Decline" logs a reason.
- Retention clean-up (admin/retention.php, from the Dashboard): preview, then removes proof text of
  claims decided more than RETENTION_MONTHS ago (one CLAIM_PROOF_REMOVED audit row each) and deletes
  read notifications older than that. Pending claims are never touched.
- New log entries: DATA_EXPORTED, ERASURE_REQUESTED, ERASURE_REQUEST_DECLINED, USER_ANONYMISED
  (Account changes) and CLAIM_PROOF_REMOVED (Items and claims).
- No database changes.

### Set-up for testing
Use staff2@test.com (Test Staff Two) as the person who asks for deletion, so your main test accounts stay.
For test 116 (retention) one claim must look old: in phpMyAdmin → SQL run
  UPDATE claims SET date_verified = '2025-06-01 10:00:00' WHERE claim_id = 1;
(claim 1 is a Rejected claim from 3 Nov – only its date changes).

### Tests 105–118
105. Logged out: footer "Privacy notice" opens privacy.php without logging in; all sections shown;
     "12 months" and the contact text appear.
106. Register page shows the privacy note + link; claim form consent box links to the notice (new tab).
107. As Student: My account → "My data & privacy" shows account, claims, notifications counts.
108. Download my data → my_data_<date>.json; open in Notepad: account, claims with your proof text,
     notifications; no "password". Audit Log → Account changes shows "Downloaded own data".
109. Logged out, open localhost/LostFoundTracker/my_data.php → Login page.
110. As staff2 (Incognito): My data & privacy → "Ask for my information to be deleted" without ticking
     the box → browser asks to tick it; tick + submit → "Your request was sent"; page now shows
     "You asked for this on …"; button gone.
111. Admin: bell shows a new notification "A user asked for their personal information to be deleted…"
     (no name); Users page shows the yellow "Deletion requests (1)" box with Test Staff Two.
112. Pending claim blocks it: as staff2 claim the Phone (#16) first … OR use Test Student, who has
     claim #11 pending: Users → Test Student → Delete personal data → "has 1 pending claim(s)…";
     nothing changes.
113. Delete personal information (staff2, from the requests box) → confirm → message
     "…now 'Deleted user #5', closed…"; row shows "Personal data deleted"; no buttons; box gone.
114. staff2's Incognito tab: next click → Login with "account has been deactivated"; logging in with
     staff2@test.com / its password → "Invalid email or password."
115. phpMyAdmin: users row 5 = Deleted user #5 / deleted-5@deleted.invalid / is_active 0;
     notifications for user 5 gone; Account changes: "Personal information deleted"; staff2's old
     history still there but under "Deleted user #5".
116. Retention: run the UPDATE from Set-up → Dashboard → Retention clean-up shows 1 claim (#1) →
     Run clean-up now → "proof text removed from 1 claim(s)"; Audit Log filter "Proof text removed
     (privacy)" → 1 entry for claim #1; claims row 1 proof_description = [Proof text removed for privacy].
117. Safety: Users → your own row has no "Delete personal data"; Staff/Student opening
     admin/retention.php → no permission.
118. Phone (iPhone 12 Pro): privacy notice and My data & privacy readable, no sideways scrolling.
