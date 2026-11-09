# QA – Stress test (group note 6; Phase 3 NFR4 performance, NFR5 reliability; Phase 4 Tables 10–11)
Tool: tools\stress_test.bat (LostFoundTracker_QA_Tools.zip) · runs through the real website over HTTP
Result: **6/6 PASS** on XAMPP, 8 Nov 2026 (1012 items, 25 concurrent users, slowest page 0.378 s) · stress data removed afterwards by the BR-4 recovery

| ID | Test | Expected |
|---|---|---|
| ST-1 | 10 admins approve 10 different claims on the same item at the same moment | 1 Approved, 9 Rejected, item Claimed; audit 1/9/1; 10 notifications |
| ST-2 | 5 admins approve the same claim at the same moment | Approved once; 1 audit row; 1 notification |
| ST-3 | Approve + Dispose the same item at the same moment (5 items) | Each item Claimed+Approved or Disposed+Rejected, never mixed |
| ST-4 | 1 000 extra items; 25 users at once × 8 rounds (search, filters, details, manage, bell, dashboard, reports, audit) | 0 errors; every page < 3 s |
| ST-5 | Dashboard, items report, Excel export of all items, audit export | each < 3 s |
| ST-6 | 20 simultaneous wrong logins; 50 guests on admin pages | account locked; all 50 redirected to Login |

DEV_stress_test_dry_run_log.txt = developer run on the SQLite test copy (proves the tool works; timings
there are not representative). The real result is Leandro's XAMPP log (tools\results\stress_test_*.txt).

## Leandro's run (XAMPP)
| File | Test | Result |
|---|---|---|
| ST1_start_and_race_result_PASSWORD_HIDDEN.png | Start (1012 items, 25 sessions in 4.4 s); ST-1: 10× HTTP 302, 1 approved + 9 "already decided"; DB Approved 1 / Rejected 9 / Pending 0, audit 1/9/1, 10 notifications | PASS |
| ST2-ST4_results.png | ST-2 approved once, 1 audit row, 1 notification · ST-3 5/5 Claimed+Approved (never mixed) · ST-4 200 requests, 0 errors, avg 0.103 s, 95% 0.325 s, max 0.378 s, 71 req/s | PASS |
| ST5-ST6_summary_6of6.png | ST-5 dashboard 0.022 s, Excel export 1012 rows 0.036 s, audit export 1082 rows 0.020 s · ST-6 20 wrong logins → locked; 50 guests → 50 to Login · 6/6 | PASS |
| ST_dashboard_after_stress_1012_items.png | Dashboard after: 1012 items (1002 Found, 9 Claimed), claims 27 | recorded |

Notes: the password was visible in the console during the run (screenshot redacted) – NOTE-10, fixed in the
QA tools (password now hidden on Windows). In ST-3 the approval won all 5 races on this PC; both outcomes
are correct – the check is that no item ends up mixed.
