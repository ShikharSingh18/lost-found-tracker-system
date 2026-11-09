# Module 9 – Dashboard, reports and audit log (admin/dashboard.php, admin/reports.php, admin/audit_log.php)
Result: **14/14 tests PASS** (91–104) · tested 4 Nov 2026 in Chrome on XAMPP · BUG-07 fixed · NOTE-08, NOTE-09 fixed
Shipped in: LostFoundTracker_Modules_6-10.zip · no database changes

## Developer pre-test (test copy with 33 seeded items, 4 Nov 2026) – DEV_ screenshots
| File | What it shows | Result |
|---|---|---|
| DEV_dashboard.png | Cards 33 / 30 / 2 (1 collected) / 1 and claims 1 / 2 / 4 / 7 match the data; category bars; lists | PASS |
| DEV_items_report.png | 33 items, 25 per page + pager, status totals | PASS |
| DEV_claims_report.png | 7 claims with claimant, status, decided by | PASS |
| DEV_audit_log.png | 43 entries, labels + codes, item/claim links | PASS |
| DEV_account_changes.png | user_admin_log tab | PASS |
| DEV_mobile_*.png | 390 px phone layout | PASS |

Also checked by script: swapped dates note; CSV = all rows (33) not just one page; quotes and commas
escaped; =HYPERLINK(...) description exported as '=HYPERLINK(...) (CSV injection blocked); claims CSV has
email but no proof text; junk in the address bar ignored; Staff gets 403 on all three pages and both CSV
exports; no browser errors.

Found and fixed during the pre-test:
- Dashboard lists: missing space before "· waiting …" (PHP removes the newline after ?>).
- Audit log sorted by log number; now sorted by date/time (then log number).

## Leandro's tests (XAMPP, Chrome, 4 Nov 2026)
| File | Test | Result |
|---|---|---|
| T91a | Admin menu: Dashboard, Reports, Audit Log; dashboard opens (5 items, Found 2, Pending 1 in yellow) | PASS |
| T91b | Student → admin/dashboard.php → "You do not have permission to open this page." | PASS |
| T91c | Student → admin/reports.php → no permission | PASS |
| T91d | Student → admin/audit_log.php → no permission | PASS |
| T92 | phpMyAdmin counts: Items Found 2 / Claimed 2 / Disposed 1; Claims Pending 1 / Approved 2 / Rejected 8; Collected 1 – identical to the dashboard cards | PASS |
| T93a | Disposed card → Items report, 1 item (#2 Wallet, Disposed), Export CSV (1 row) | PASS |
| T93b | Found card → Items report, 2 items (#16 Phone, #17 Stationery) | PASS |
| T93c | Pending card → Review claims: 1 pending claim (#11, Phone item #16, Test Student) | PASS |
| T93d | Rejected card → Claims report, 8 claims, all Rejected, decided by Leandro Admin | PASS |
| T94a | Items by category: Wallet 2, Keys 1, Phone 1, Stationery 1, Clothing/ID Card/Other 0; bars to scale | PASS |
| T94b | Hover Wallet → "Wallet: 2 item(s), 0 Found, 1 Claimed" (the other wallet is Disposed) | PASS |
| T95 | Claims waiting longest: claim #11 (Phone #16, Test Student, waiting today). Found items waiting longest: #17 (2026-11-03) before #16. Latest activity: Claim submitted #16/#11 16:01, Item logged #17, #16 … | PASS (wording issue NOTE-08) |
| T96 | Items report, no filters: 5 items · Found 2 · Claimed 2 · Disposed 1; Keys shows "Collected 2026-11-04" | PASS |
| T97a | Status = Claimed → 2 items (#3 Wallet, #1 Keys) | PASS |
| T97b | Category = Wallet → 2 items (#3 Claimed, #2 Disposed) · Found 0 · Claimed 1 · Disposed 1 | PASS |
| T97c1–c2 | From 2026-11-04, To 2026-11-03 → "dates were swapped" note; fields show 11/03 → 11/04; 2 Found items | PASS |
| T98 (1st try) | Export CSV opened in Excel with every row in column A (comma file, Excel on en-ZA Windows expects ;) | FAIL → BUG-07 |
| T98 (retest) | Export to Excel (5 rows): headings in columns A–I, 5 rows, Keys has Collected 2026/11/04 12:05 | PASS (after BUG-07 fix) |
| T99 | CSV injection: pen description exported as '=1+1 blue pen… – Excel shows text, not 2 | PASS |
| T100a | Claims report: 11 claims · Pending 1 · Approved 2 · Rejected 8; decided date + "by Leandro Admin"; Pending shows – | PASS |
| T100b | Claims Excel export: 11 rows, columns A–I incl. Claimant email; no proof-of-ownership text anywhere | PASS |
| T101a | Audit log (35 entries) newest first: #35 Claim submitted item #16 claim #11 by Test Student 16:01:49, then Item logged #17, #16 | PASS |
| T101b | Action = Claim approved → 2 entries (#13 item 1 / claim 5, #9 item 3 / claim 2) | PASS |
| T101c | Click item #1 → full Keys history in order: logged → claim 3 submitted/rejected → claim 5 submitted/approved → status changed → collected (ITEM_RELEASED) | PASS |
| T102a | Account changes tab: the 10 Test Staff Two changes, newest first, with detail and By | PASS |
| T102b1–b2 | Account = Test Staff Two → 10 entries; Export to Excel → 10 rows, columns A–I, Detail only role names, no passwords | PASS |
| T103 | ?item=abc&from=2026-13-40&action=DROP → "A date was not valid and was ignored."; all 35 entries; no error page | PASS |
| T104a | Narrow window (~720 px): dashboard cards wrap, menu becomes ☰ | PASS |
| T104b | Reports: table becomes one card per item, Export buttons wrap | PASS |
| T104c (1st) | Audit log on phone: cards readable but "Claim submitted / CLAIM_SUBMITTED" and "Test Student / (Student)" were split across the row | see T104c below |
| T104c | Retest at iPhone 12 Pro (390 px) after NOTE-09 fix: "Claim submitted" with CLAIM_SUBMITTED under it, "Test Student (Student)" together; Export buttons stack | PASS |

## Issues found in Module 9
| ID | Description | Severity / Status | Evidence |
|---|---|---|---|
| NOTE-08 | Found items waiting longest: an item found today read "found 2026-11-04, today ago" | Cosmetic – fixed 4 Nov in admin/dashboard.php (now "…, today") | Note-08_today_ago N1 (before), N2 (after) |
| BUG-07 | Export CSV opened in Excel as one column: South African Windows uses ; as the list separator, the file used , | Medium – fixed 4 Nov: "Export to Excel" uses ; (opens in columns); "Comma-separated CSV" link kept for other programs (includes/reports.php, reports.php, audit_log.php) | Bug-07_csv_one_column C1 (before), C2 (after) |
| NOTE-09 | Phone cards: cells with two parts (Action + code, By + role, Status + Collected, Decided + by) were spread across the row | Cosmetic – fixed 4 Nov: parts grouped in one block (audit_log.php, reports.php) | Note-09_phone_cards_split M1 (before), DEV_M2 (after, developer), M3 (after, Leandro) |
