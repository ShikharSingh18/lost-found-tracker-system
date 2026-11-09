# QA Testing – Lost & Found Tracker
**Testing & QA:** Leandro Rughunath (402313605) · Distance Learning Group 11 · IT Project 700
**Environment:** XAMPP on Windows – Apache 2.4.65, PHP 8.3.36, MariaDB 10.11.16 · Google Chrome (+ Incognito for second users) · local only
**Dates:** 2–8 November 2026

## Result: 131 / 131 tests passed
| Area | Tests | Result | Bugs found → fixed |
|---|---|---|---|
| Module 1 – Login and accounts | 12 | 12 PASS | BUG-02 (closed in Module 8) |
| Module 2 – Log found item and photos | 11 | 11 PASS | BUG-01 |
| Module 3 – Search and item details | 10 | 10 PASS | BUG-03, BUG-04, DEV-01 |
| Module 4 – Submit a claim / My Claims | 12 | 12 PASS | – |
| Module 5 – Approve or reject claims | 11 | 11 PASS | – |
| Module 6 – Notifications and live bell | 8 | 8 PASS | BUG-05, DEV-02 |
| Module 7 – Manage Items | 12 | 12 PASS | BUG-06 |
| Module 8 – User management / My account | 15 | 15 PASS | BUG-02, DEV-03, DEV-04, NOTE-06, NOTE-07 |
| Module 9 – Dashboard, reports, audit log | 14 | 14 PASS | BUG-07, NOTE-08, NOTE-09 |
| Module 10 – Privacy (POPIA / GDPR) | 14 | 14 PASS | – |
| QA – Backup and restore (Phase 4 §8.6) | 6 | 6 PASS | – |
| QA – Stress test (25 concurrent users, race conditions) | 6 | 6 PASS | NOTE-10 (tool) |
| **Total** | **131** | **131 PASS** | 7 bugs + 4 developer-found issues fixed |

Open (cosmetic, planned): NOTE-01 – error pages show the Apache/PHP version; NOTE-03 – the "form expired" (CSRF) page is unstyled.

## Highlights
- **Concurrency:** 10 admins approving claims on the same item at the same moment → exactly one approved, the rest rejected; audit trail and notifications consistent.
- **Performance (NFR4):** 1 012 items, 25 users at once → 0 errors, slowest page 0.378 s (target < 3 s).
- **Recovery (NFR7):** backup restored into a separate database and into the live site – every table and photo identical, recovery time 0.4 s (target < 4 h).
- **Security:** role checks on every page, CSRF on every form, login lockout (also under 20 simultaneous attempts), CSV/Excel formula injection blocked, deactivated users logged out on their next click.
- **Privacy:** privacy notice, download-my-data, deletion on request (anonymising), retention clean-up of proof text.

## Folders
| Folder | Contents |
|---|---|
| `Evidence/` | One folder per module + QA_Backup_Restore + QA_Stress_Test. Each has an **INDEX.md** (screenshot → test → result) and `Bug-xx_…` / `Note-xx_…` sub-folders with before/after screenshots. `EVIDENCE_INDEX.md` lists all folders. |
| `QA_Tools/` | backup / restore / recovery / stress-test tools (run from the command line on the XAMPP PC only – see README_QA_TOOLS.md). To use them, copy into `htdocs/LostFoundTracker/tools/`. |

## Naming
`T##` = test screenshot (e.g. `T85b_…` = test 85, step b) · `S##` = set-up · `B/L/P/A/F/N/C/M#` = bug or note screenshots (before/after) · `DEV_` = developer pre-test on a test copy · `BR-`/`ST-` = backup/restore and stress tests.

All accounts, items and claims in the screenshots are test data. Passwords never appear (one screenshot was redacted).
