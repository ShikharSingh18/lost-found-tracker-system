# Test Summary Report – Lost & Found Tracker
**Project:** IT Project 700 · Distance Learning Group 11 · Implementation phase
**Prepared by:** Leandro Rughunath (402313605) – Backend Development & Testing / QA
**Test period:** 2–8 November 2026 · **Report date:** 9 November 2026
**Detailed results:** [QA_Test_Log.md](QA_Test_Log.md) · **Evidence:** [Evidence/](Evidence/) (one folder per module, each with INDEX.md)

---

## 1. Purpose
This report summarises the testing of the Lost & Found Tracker web application against the requirements in the Analysis phase (FR1–FR10, NFR1–NFR7), the System Design phase (Phase 4) and the group's implementation notes, and states whether the system is ready for the demonstration.

## 2. Scope
**Tested:** all ten backend modules (accounts and login, logging found items with photos, search, claims, claim approval, notifications, item management, user management, dashboard/reports/audit log, privacy), plus backup/restore and a stress test.
**Not tested (out of scope):** the Android app (deferred by the group); HTTPS and hosting (the system runs locally only, group note 5); browsers other than Chrome (Opera GX used for early modules).

## 3. Test environment
| Item | Detail |
|---|---|
| Server | XAMPP on Windows – Apache 2.4.65, PHP 8.3.36, MariaDB 10.11.16, local only |
| Browser | Google Chrome (normal + Incognito window for a second user), Chrome device mode iPhone 12 Pro (390 px) for mobile; Opera GX for Modules 1–2 |
| Test accounts | Admin, Staff and Student test accounts; extra accounts created during testing (e.g. staff2) |
| Developer pre-tests | Each module was first checked on a separate test copy by the developer (DEV_ screenshots) before the tester's run |

## 4. Approach
- **Functional testing per module:** step-by-step test cases with expected results, executed manually in the browser; every step evidenced with a screenshot (T## files).
- **Negative and security testing:** invalid input, forged forms (CSRF), wrong roles, direct URLs, SQL injection strings, Excel formula injection, locked and deactivated accounts.
- **Database verification:** phpMyAdmin checks that the screen matches the database (claims, audit trail, notifications, anonymised users).
- **Regression:** every fix was retested, and earlier modules were rechecked when shared files changed.
- **Non-functional:** stress test with concurrent users and race conditions (NFR4, NFR5), backup/restore and disaster recovery (NFR7), mobile layout (NFR1).

## 5. Results
| Area | Tests | Passed | Failed |
|---|---|---|---|
| Modules 1–5 (core site) | 56 | 56 | 0 |
| Modules 6–10 (add-on) | 63 | 63 | 0 |
| Backup and restore (BR-0…BR-5) | 6 | 6 | 0 |
| Stress test (ST-1…ST-6) | 6 | 6 | 0 |
| **Total** | **131** | **131** | **0** |
| Final retest (R-1…R-5, section 8) | 5 | 5 | 0 |

Final status: all tests pass after the fixes listed in section 6. Tests that failed on the first run (e.g. test 98, Excel export) were fixed and passed on retest; the final result is recorded.

### Key measurements
| Measure | Target | Result |
|---|---|---|
| Page response with 1 012 items, 25 users at once (NFR4) | < 3 s | slowest 0.378 s, average 0.103 s, 0 errors in 200 requests |
| Excel export of all 1 012 items | < 3 s | 0.036 s |
| Concurrent approvals of claims on one item (Phase 4 Table 11) | exactly 1 approved | 1 approved, 9 rejected, audit and notifications consistent |
| Recovery from backup (Phase 4 §8.6) | < 4 hours | 0.4 s; every table and photo identical |
| Login lockout under 20 simultaneous wrong passwords | account locked | locked |

### Requirements coverage
| Requirement | Covered by |
|---|---|
| FR1 accounts and login, roles | Modules 1, 8 |
| FR2/FR7 log found items with photos | Module 2, 7 |
| FR3 search and filter | Module 3 |
| FR4 update/delete items (Disposed) | Module 7 |
| FR5 submit claims | Module 4 |
| FR6 verify, approve or reject | Module 5, stress ST-1–ST-3 |
| FR8 reports and export | Module 9 |
| FR9 notifications | Module 6 |
| FR10 audit trail | Modules 5, 7, 9 |
| NFR1 usability / mobile | phone layout tests in Modules 9, 10 |
| NFR2 security | Modules 1, 4, 8, 9; ST-6 |
| NFR3 POPIA (+ GDPR, group note 8) | Modules 4, 10 |
| NFR4 performance | ST-4, ST-5 |
| NFR5 reliability / no duplicates | Module 5, ST-1–ST-3 |
| NFR7 backup | BR-0–BR-5 |

## 6. Defects
| Type | Found | Fixed | Open |
|---|---|---|---|
| Bugs (BUG-01…07) | 7 | 7 | 0 |
| Issues found in developer pre-tests (DEV-01…04) | 4 | 4 | 0 |
| Environment (ENV-01, MySQL port clash) | 1 | 1 | 0 |
| Cosmetic / notes (NOTE-01…10) | 10 | 10 | 0 |

Most important defects:
- **BUG-01 (High):** with exactly 5 photos selected, no photos were saved. Fixed in the backend; the permanent fix in the frontend upload.js is with the frontend developer.
- **BUG-02 (Low → closed):** a deactivated user stayed logged in. The account is now checked on every request.
- **BUG-07 (Medium):** report exports opened in Excel as a single column on South African Windows settings. Fixed with an Excel-friendly export.
- **DEV-03 (Medium):** the notification bell kept idle sessions alive, so the 30-minute logout never happened. Fixed and confirmed (test 90).

NOTE-01 (server versions on error pages) and NOTE-03 (unstyled "form expired" page) were fixed on 9 Nov 2026 after the main test run; see the retest in section 8.

## 7. Risks and recommendations
1. **Database changes 001–004** must be approved and merged into the official schema by the database team before hand-in.
2. **Retention period and privacy contact** (includes/privacy.php) are placeholders (12 months) – the group must confirm them.
3. **Frontend fixes** for BUG-01 (upload.js) and NOTE-07 (style.css) should be made at the source by the frontend developer.
4. **Demo data:** reset the demo computer with tools/reset_demo.bat so the demo shows clean, realistic data.
5. **Backups:** keep a copy of the backups folder in a second place; it contains personal information.

## 8. Retest of the final fixes (9 Nov 2026) – 5/5 PASS, NOTE-01 and NOTE-03 closed
Evidence: [Evidence/QA_Final_Retest](Evidence/QA_Final_Retest)
| ID | Test | Expected | Result |
|---|---|---|---|
| R-1 | Open a page that doesn't exist | Styled "Page not found" page, no Apache/PHP version | PASS |
| R-2 | Open tools/backup.php in the browser | Styled "Access denied" (403), no version | PASS |
| R-3 | Open the assets/ folder in the browser | Access denied – no file listing | PASS |
| R-4 | Submit a form after logging out/in in another tab | Styled "This form has expired…" page inside the site, nothing saved | PASS |
| R-5 | reset_demo.bat | Backup made, demo data loaded (5 users, 11 items, 3 claims) | PASS |

## 9. Conclusion
All 131 planned tests passed. Every bug found was fixed and retested, the system performs well within the targets under load, concurrent claim decisions stay consistent, and the data can be recovered from a backup. Subject to the group items in section 7, the system is **ready for the demonstration**.

_Signed: Leandro Rughunath, Testing & QA – 9 November 2026_
