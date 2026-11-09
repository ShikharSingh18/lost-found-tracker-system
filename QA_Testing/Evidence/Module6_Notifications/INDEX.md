# Module 6 – Notifications inbox and live bell (notifications.php, notifications_count.php, assets/js/notifications.js)
Result: 8/8 tests PASS · 1 bug found and fixed (BUG-05) · tested 4 Nov 2026 in Chrome on XAMPP
Shipped in: LostFoundTracker_Modules_6-10.zip

| File | Test | Result |
|---|---|---|
| T56a | Student bell = 1, inbox "You have 1 unread notification" | PASS |
| T56b | Database: exactly one unread notification for the Student | PASS |
| T56c | notifications_count.php logged in → {"unread":1,"latest":…} | PASS |
| T56d | notifications_count.php logged out → {"unread":null} | PASS |
| T57 | Staff inbox: only own messages, newest first, unread highlighted, bell = 3 | PASS |
| T58 | Mark one as read → plain, button gone, bell 3 → 2 | PASS |
| T59 | Mark all as read → "2 notification(s) marked as read", "You are all caught up", badge gone | PASS |
| T60a | LIVE: Admin on Search, bell = 1 | – |
| T60b | Staff (Incognito) submits a claim on item #2 | – |
| T60c | Admin bell = 2 WITHOUT reloading the page | PASS |
| T61a | Chrome asks "localhost wants to show notifications" → Allow | – |
| T61b | Admin rejects Staff's claim | – |
| T61c | Staff page: "Browser alerts are on." | PASS |
| T61d | Windows pop-up "Lost & Found Tracker – Your claim for Wallet (item #2) was not approved."; tab title "(4) …" | PASS |
| T61e | Bell 4 = inbox 4 unread (one notification per decision) | PASS |
| T62a | Staff "View my claims" → My Claims | PASS |
| T62b | Admin alerts: "Review claim" (pending) and "See decision" (decided) links | PASS (BUG-05 seen here) |
| T62c | "Review claim" → Review Claims showing claim #6 | PASS |
| 63 | One user can't mark another user's notification read | PASS (developer test) |

Note: test 61 needed Windows Settings → System → Notifications → Google Chrome turned ON (environment, not a bug).

## Bug-05_admin_alert_shows_pending (Low – FIXED 4 Nov)
Admin "new claim" alerts kept a yellow Pending badge after the claim was decided.
Fix: notifications.php shows the claim's CURRENT status ("Claim reviewed" + Approved/Rejected).
| File | Shows |
|---|---|
| A1 | Before: decided claims still labelled Pending |
| A2 | After (retest 4 Nov 11:08): Staff's item #2 claims show "Claim reviewed" + Rejected |
| A3 | After: only claim #6 shows Pending with "Review claim"; others Approved/Rejected with "See decision" |

## Developer test results (before release)
| File | Shows |
|---|---|
| DEV_inbox_unread_highlighted | Inbox with unread highlight |
| DEV_live_badge_after_30s | Badge 1 → 2 without reload |
| DEV_alerts_blocked_button_hidden / DEV_alerts_button_shown | DEV-02 fix: alerts button only when it can be used |
