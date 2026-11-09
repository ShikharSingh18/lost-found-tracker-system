# QA Test Log – Lost & Found Tracker
**Tester:** Leandro Rughunath (402313605) · Testing & QA · Distance Learning Group 11 · IT Project 700
**Environment:** XAMPP on Windows – Apache 2.4.65, PHP 8.3.36, MariaDB 10.11.16 · local only · Opera GX (Modules 1–2, early Module 3), Google Chrome from 3 Nov 2026 · 2–8 Nov 2026
**Evidence:** `Evidence/` – one folder per module, each with an INDEX.md (screenshot → test → result) and `Bug-xx` / `Note-xx` sub-folders. File names start with the test number (T15_…, T24a_…); bug/note screenshots B, L, P, A, F, N, C, M; set-up S; developer pre-tests DEV_.

## Summary – 131 / 131 PASS
| Area | Tests | Result | Bugs |
|---|---|---|---|
| 1 Login and accounts | 12 | 12 PASS | BUG-02 fixed in Module 8 and closed (test 85) |
| 2 Log found item and photos | 11 | 11 PASS | BUG-01 fixed |
| 3 Search and item details | 10 | 10 PASS | BUG-03, BUG-04 fixed; DEV-01 fixed before release |
| 4 Submit a claim and My Claims | 12 | 12 PASS | none (NOTE-03 cosmetic) |
| 5 Approve or reject claims | 11 | 11 PASS | none (NOTE-04, NOTE-05 cosmetic, fixed) |
| 6 Notifications and live bell | 8 | 8 PASS | BUG-05 fixed; DEV-02 fixed before release |
| 7 Manage Items | 12 | 12 PASS | BUG-06 fixed |
| 8 User management and My account | 15 | 15 PASS | BUG-02 closed; DEV-03 confirmed fixed; DEV-04 fixed before release; NOTE-06, NOTE-07 fixed |
| 9 Dashboard, reports and audit log | 14 | 14 PASS | BUG-07 fixed; NOTE-08, NOTE-09 fixed |
| 10 Privacy (POPIA/GDPR) | 14 | 14 PASS | none |
| QA – Backup and restore | 6 | 6 PASS | none |
| QA – Stress test | 6 | 6 PASS | NOTE-10 (tool) fixed |
| **Total** | **131** | **131 PASS** | 7 bugs + 4 developer-found issues fixed; NOTE-01 and NOTE-03 fixed and retested 9 Nov (R-1…R-5 PASS) |

## Module 1 – Login and accounts — 12/12 PASS
| # | Test | Expected | Result | Evidence |
|---|---|---|---|---|
| 1–2 | Register Student | Role = Student; password_hash starts with $2y$ (bcrypt) | PASS | T01-02 |
| 3 | Duplicate email | "An account with this email already exists"; name and email kept | PASS | T03 |
| 4 | Registration validation (server) | Short password, mismatch, invalid email refused; errors listed together | PASS | T04a–c |
| 5 | Generic login error | Wrong password and unknown email show identical message | PASS | T05a–b |
| 6 | Lockout | 5 failures lock 15 min; correct password refused while locked | PASS | T06a–d |
| 9 | Logout and Back button | Back/refresh leads to login | PASS | T09a–c |
| 10/13 | Student menu; admin page blocked | "You do not have permission" | PASS | T05-13 |
| 11 | Idle timeout | 30+ min → "Your session expired" | PASS | T11 |
| 12 | Deactivated account | is_active = 0 refused; = 1 works | PASS | T12a–d |
| – | Staff menu | Log Found Item and My Claims; no admin links | PASS | T06d |

## Module 2 – Log found item and photos — 11/11 PASS
| # | Test | Expected | Result | Evidence |
|---|---|---|---|---|
| S1 | Database setup | 8 tables on MariaDB; 7 categories | PASS | 00_Setup S10–S11 |
| 7–8/14 | Admin menu; form with DB categories | | PASS | T07-08_14 |
| 15 | Save without photos | Status Found, logged_by set, audit row | PASS | T15 |
| 16 | Save with 5 photos | "… and 5 photo(s)"; 5 random-named files | PASS (after BUG-01 fix) | T16a–b |
| 17a | 6th photo | Refused | PASS | T17a |
| 17c | Non-image file | Refused | PASS | T17c |
| 17b | 16 MB photo | Refused | Developer test only | – |
| 18a/b | Required fields (browser + server) | All 4 server errors; nothing saved | PASS | T18b1–2 |
| 19 | Direct photo URL | 403 Forbidden | PASS | T19 |
| 20 | photo.php access | Logged in shows; logged out redirects | PASS | T20a–b |
| 21 | Photo cleaned | ≤1600 px; no camera/date/GPS metadata | PASS | T21 |

## Module 3 – Search and item details — 10/10 PASS
| # | Test | Expected | Result | Evidence |
|---|---|---|---|---|
| 23 | Student/Staff see Found only, no Status filter | | PASS | T23, T23d |
| 24 | Keyword, Clear, Category, Location, combined | 2 / 3 / 1 / 2 / 1 found | PASS | T24a–e |
| 25 | Date range; from > to swapped with note | Swap note + 3 found; later range → 0 | PASS | T25a–b |
| 26 | Filters kept after Apply | | PASS | T24a, c, e |
| 27 | Paging (15 items) | 12 + 3 across 2 pages; filters respected | PASS | T27a–c |
| 28 | SQL injection: ', ' OR 1=1 --, % | 0 results, no error | PASS | T28a–c |
| 29 | Details gallery; full photo; Logged by hidden from students | | PASS (after BUG-04 fix) | T29a–c |
| 30 | Claimed item hidden from students; admin still sees it | "This item is not available" | PASS | T30a–d |
| 31 | Admin Status filter; thumbnails | | PASS | T31 |
| 32 | Claim button links to claims/submit.php?item_id=3 | | PASS | T32 |

## Module 4 – Submit a claim and My Claims — 12/12 PASS
| # | Test | Expected | Result | Evidence |
|---|---|---|---|---|
| 33 | Admin can't claim | No My Claims menu; claim page → "You do not have permission" | PASS | T33a–b |
| 34 | Claim form | Proof box, consent checkbox, item summary with photo | PASS | T34 |
| 35 | Short proof + no consent (server) | Both errors; text kept | PASS | T35a–b |
| 36 | Valid proof + no consent | Only consent error; nothing saved | PASS | T36 |
| 37 | Valid claim | My Claims, green message, Pending; F5 doesn't resubmit | PASS | T37, T37b |
| 38 | Database: claim row | Claimant from session (user 3), Pending, consent_given_at set; item still Found | PASS | T38-39 |
| 39 | Database: audit + notification | CLAIM_SUBMITTED row; one notification to active Admin only | PASS | T38-39, T33a |
| 40 | No duplicate pending claim | Details shows "pending claim" button; direct link → My Claims notice | PASS | T40a–b |
| 41 | Resubmit after rejection | Rejected note; button returns; new Pending claim accepted | PASS | T41a–c |
| 42 | Claimed item | "This item is not available to claim" | PASS | T42 |
| 43 | Staff claims; privacy | Staff can claim; doesn't see student's claims | PASS | T43a–b |
| 44 | Security | item_id=abc handled; forged CSRF token → 400, nothing saved | PASS | T44a–b |

Developer test (before release): a forged claimant_user_id field was ignored; a deactivated admin got no notification.

## Module 5 – Approve or reject claims — 11/11 PASS
| # | Test | Expected | Result | Evidence |
|---|---|---|---|---|
| 45 | Staff/Student blocked | "You do not have permission"; no menu link | PASS | T45 |
| 46 | Review queue | 3 pending; two-claims warning on Wallet; record + photo beside proof | PASS | T46a–b |
| 47 | No decision chosen (browser + server) | "Choose Approve or Reject"; nothing changed | PASS | T47a–b |
| 48 | Reject | Claim Rejected; item stays Found; admin alert read | PASS | T48 |
| 49 | Approve with two claims (Phase 4 Table 11) | Approved, item Claimed, other claim rejected automatically | PASS | T49 |
| 50 | Database: claims and items | #2 Approved / #4 Rejected in the same transaction; item 3 Claimed | PASS | T50-52 |
| 51 | Database: audit | CLAIM_REJECTED, CLAIM_APPROVED, ITEM_STATUS_CHANGED, CLAIM_REJECTED (Table 11 order) | PASS | T50-52 |
| 52 | Database: notifications | Approved / not-approved messages to claimants; admin alerts marked read | PASS | T50-52 |
| 53 | Claimants' My Claims | Student Approved + collect note; Staff Rejected; resubmit offer only for Found items | PASS | T53a–b |
| 54 | Claimed item hidden | Gone from search; direct link "not available" | PASS | T54a–b |
| 55 | Two admins, same claim | Stale tab → "already decided. Nothing was changed."; decision kept | PASS | T55a–b |

Developer test (before release): Table 11 reproduced exactly; forced audit failure rolled back everything (Table 10 step 8).

## Module 6 – Notifications inbox and live bell — 8/8 PASS
| # | Test | Expected | Result | Evidence |
|---|---|---|---|---|
| 56 | Bell count correct; count endpoint | Bell = unread in DB; JSON when logged in; {"unread":null} when logged out | PASS | T56a–d |
| 57 | Own messages only, newest first | Staff sees only Staff's 3, unread highlighted | PASS | T57 |
| 58 | Mark one as read | Highlight and button gone; bell 3 → 2 | PASS | T58 |
| 59 | Mark all as read | "All caught up"; badge gone | PASS | T59 |
| 60 | Live bell | Admin bell 1 → 2 without reload after a claim in another window | PASS | T60a–c |
| 61 | Browser pop-up alerts | Windows pop-up with the message; tab title shows count | PASS | T61a–e |
| 62 | Links | "View my claims", "Review claim", "See decision" go to the right pages | PASS (after BUG-05 fix) | T62a–c |
| 63 | Can't mark another user's notification | Unchanged | PASS (developer test) | – |

Environment note: Windows notifications for Google Chrome had to be switched on for test 61.

## Module 7 – Manage Items — 12/12 PASS
| # | Test | Expected | Result | Evidence |
|---|---|---|---|---|
| 64 | Staff blocked from Manage Items and Edit | "You do not have permission" | PASS | T64a–b |
| 65 | List | All statuses; right buttons per status; pending-claim link to Review Claims | PASS | T65a–b |
| 66 | Filters | Status Claimed → 2; keyword "keys" → 1 | PASS (after BUG-06 fix) | T66a–b |
| 67 | Edit details | "Item #2 updated."; Search shows new description; ITEM_UPDATED audit | PASS | T67a–c |
| 68 | Edit validation (server) | Empty location + future date refused; nothing saved | PASS | T68a–c |
| 69 | Add photos | "2 photo(s) added"; 2 of 5; first = thumbnail | PASS | T69a–b |
| 70 | 5-photo limit | Keeping 2 + adding 4 refused | PASS | T70 |
| 71 | Remove thumbnail | Next photo becomes thumbnail; Search updated; file deleted | PASS | T71a–c |
| 72 | Mark collected | ITEM_RELEASED (item 1, claim 5); "Collected" shown; button gone; owner notified | PASS | T72a–e |
| 73 | Dispose with pending claim | Disposed; claim auto-rejected; claimant told "no longer available"; hidden from Search | PASS | T73a–e |
| 74 | Actions only where allowed | No Dispose on Claimed; no Mark collected on Found/Disposed/collected | PASS | T65a, T72d, T73b |
| 75 | Confirmation | "Are you sure?" box with Cancel before Dispose / Mark collected | PASS | T75, T72a |

## Module 8 – User management and My account — 15/15 PASS
Set-up: database/004_user_admin_log.sql imported (T76-setup). Chrome (Admin) + Incognito (staff2@test.com, user 5).
| # | Test | Expected | Result | Evidence |
|---|---|---|---|---|
| 76 | Menus and access | Admin sees Users + My account; Student/Staff only My account; Student on Users → no permission | PASS | T76a–e |
| 77 | Users list | 3 accounts · 1 active admin; own row "(you)" with no role dropdown or actions | PASS | T76b2 |
| 78 | Create account, generated password | "Staff account created for staff2@test.com."; temporary password shown with Copy | PASS | T78 |
| 79 | Reload | Temporary password box gone; 4 accounts | PASS | T79 |
| 80 | Duplicate email | "An account with this email already exists."; nothing added | PASS | T80 |
| 81 | Log in with temporary password | Welcome, Test Staff Two (Staff menu) | PASS | T81 |
| 82 | Change password validation | Wrong current password → "Your current password is not correct." | PASS | T82 |
| 83 | Change own password | "Your password was changed."; new password works | PASS | T83a–b |
| 84 | Role change mid-session | Student → Staff applied on next click without logging out | PASS | T84a1, T84b2, T87b → T84c |
| 85 | BUG-02 retest | Logged-in staff2's next click after deactivation → "Your account has been deactivated…" | PASS | T85a1–a2, T85b |
| 86 | Deactivated login | "Invalid email or password." | PASS | T86 |
| 87 | Reactivate; new temporary password | Can log in; old password refused; new one works | PASS | T87a–e |
| 88 | Lockout + Unlock | Locked after 5 failures; Locked filter shows "Locked until 13:37" + Unlock; login works straight after unlock | PASS | T88a–d |
| 89 | Change history | 10 changes with who did each; user_admin_log 10 rows, no passwords | PASS | T89a–b |
| 90 | DEV-03 retest: idle timeout with tab open | Tab untouched 13:25 → 14:40 → "Your session expired." | PASS | T90 |

Developer test (before release): self role change, self deactivate and self temporary password blocked even with a forged form; bad CSRF → 400; Staff → 403; phone layout at 390 px.

## Module 9 – Dashboard, reports and audit log — 14/14 PASS
Set-up: item #16 (Phone) and #17 (Stationery, description starting "=1+1" for the CSV-injection test); Student claim #11 on #16 left Pending.
| # | Test | Expected | Result | Evidence |
|---|---|---|---|---|
| 91 | Menus and access | Admin: Dashboard, Reports, Audit Log; Student → no permission on all three | PASS | T91a–d |
| 92 | Dashboard = database | phpMyAdmin: items 2/2/1, claims 1/2/8, collected 1 – same as the cards | PASS | T92 |
| 93 | Cards open reports | Disposed → 1 item; Found → #16, #17; Pending → Review claims #11; Rejected → 8 claims | PASS | T93a–d |
| 94 | Items by category | Wallet 2, Keys 1, Phone 1, Stationery 1, others 0; tooltip with Found/Claimed | PASS | T94a–b |
| 95 | Dashboard lists | Claim #11 waiting; #17 before #16; latest activity correct | PASS (NOTE-08 fixed) | T95, Note-08 |
| 96 | Items report | 5 items · Found 2 · Claimed 2 · Disposed 1; Keys "Collected" | PASS | T96 |
| 97 | Items filters | Claimed → 2; Wallet → 2; from > to → dates swapped note, 2 items | PASS | T97a–c |
| 98 | Export to Excel | Opens in columns A–I with all 5 rows | PASS (after BUG-07 fix) | T98, Bug-07 |
| 99 | CSV injection | "=1+1 blue pen…" exported as '=1+1… – shown as text, not 2 | PASS | T99 |
| 100 | Claims report + export | 11 claims with decided by; Excel has claimant email but no proof text | PASS | T100a–b |
| 101 | Audit log items/claims | Newest first; Claim approved → 2; item #1 → full Keys history | PASS | T101a–c |
| 102 | Account changes | 10 Test Staff Two changes; filter + Excel 10 rows, no passwords | PASS | T102a–b |
| 103 | Bad address-bar values | "A date was not valid and was ignored."; all 35 entries; no error | PASS | T103 |
| 104 | Phone layout | Dashboard, Reports, Audit Log readable; tables become cards | PASS (NOTE-09 fixed) | T104a–c |

## Module 10 – Privacy (POPIA/GDPR) — 14/14 PASS
Settings still to be confirmed by the group: RETENTION_MONTHS = 12 (placeholder) and PRIVACY_CONTACT in includes/privacy.php.
| # | Test | Expected | Result | Evidence |
|---|---|---|---|---|
| 105 | Privacy notice logged out | Opens without login from the footer; all sections incl. 12 months and Information Regulator | PASS | T105a–c |
| 106 | Links on forms | Register privacy note; claim consent "(privacy notice)" opens in a new tab | PASS | T106a, T106b1–b2 |
| 107 | My data & privacy | Account, claims, notifications, changes; Download; Correct; Delete sections | PASS | T107a–b |
| 108 | Download my data | JSON with account, claims incl. own proof text, notifications; no password hash; "Downloaded own data" logged | PASS | T108a1–a4, T108b |
| 109 | Logged out | my_data.php → Login "Please log in to continue." | PASS | T109 |
| 110 | Ask for deletion | Tick box required; "Your request was sent" | PASS | T110a–b |
| 111 | Admin told | Notification without the name; Users "Deletion requests (1)" | PASS | T111a–b |
| 112 | Pending claim blocks deletion | Test Student (claim #11 Pending) → "has 1 pending claim(s)…"; nothing changed | PASS | T112 |
| 113 | Delete personal information | "…now 'Deleted user #5', closed…"; row shows "Personal data deleted", no buttons | PASS | T113a–b |
| 114 | Deleted user locked out | Old email + password → "Invalid email or password." (open-tab logout covered by T85b) | PASS | T114b |
| 115 | Database and log | users row 5 anonymised, is_active 0; "Personal information deleted"; history kept under "Deleted user #5" | PASS | T115a–b |
| 116 | Retention clean-up | Claim #1 (decided 2025-06-01) found; proof text removed; 1 CLAIM_PROOF_REMOVED audit row | PASS | T116a–d |
| 117 | Safety | Own row has no delete button; Staff → retention page no permission | PASS | T117a–b |
| 118 | Phone layout | Privacy notice and My data & privacy at 390 px, tables as cards | PASS | T118a1–a2, T118b |

## QA – Backup and restore (Phase 4 Section 8.6 / Table 26; NFR7) — 6/6 PASS · 8 Nov 2026
Tools: `QA_Tools/` (backup.bat, restore_test.bat, recover.bat). Evidence: `Evidence/QA_Backup_Restore`.
| ID | Test | Result |
|---|---|---|
| BR-0 | Dashboard before: 5 items (2 Found / 2 Claimed / 1 Disposed), claims 1 / 2 / 8 / 11 | recorded |
| BR-1 | backup.bat: mysqldump --single-transaction (21.9 KB, 0.26 s) + 6 photos + manifest with a fingerprint of all 9 tables, in <XAMPP>\backups\ (outside htdocs) | PASS |
| BR-2 | localhost/LostFoundTracker/tools/backup.php → 403 Forbidden | PASS |
| BR-3 | restore_test.bat into separate DB lost_found_restore: all 9 tables IDENTICAL, spot-check ok, 0.3 s; live DB untouched | PASS |
| BR-4 | Disaster drill: 1 photo deleted + 1 000 stress items present → recover.bat: all tables + photos IDENTICAL to the backup; recovery time 0.4 s (target < 4 h) | PASS |
| BR-5 | Site after recovery = BR-0 (stress data gone, photo back) | PASS |

## QA – Stress test (group note 6; NFR4, NFR5; Phase 4 Tables 10–11) — 6/6 PASS · 8 Nov 2026
Tool: `QA_Tools/stress_test.bat` (runs through the real website). Evidence: `Evidence/QA_Stress_Test`.
| ID | Test | Result |
|---|---|---|
| ST-1 | 10 admins approve 10 different claims on one item at the same moment | 1 Approved, 9 Rejected, item Claimed, audit 1/9/1, 10 notifications – PASS |
| ST-2 | 5 admins approve the same claim at once | approved once, 1 audit row, 1 notification – PASS |
| ST-3 | Approve + Dispose the same item at once (5 items) | 5/5 Claimed+Approved, never mixed – PASS |
| ST-4 | 1 012 items, 25 concurrent users × 8 rounds (200 requests) | 0 errors, avg 0.103 s, 95% 0.325 s, max 0.378 s (target < 3 s), 71 req/s – PASS |
| ST-5 | Dashboard, items report, Excel export (1 012 rows), audit export (1 082 rows) | 0.019–0.036 s – PASS |
| ST-6 | 20 simultaneous wrong logins; 50 guests on admin pages | account locked; 50/50 sent to Login – PASS |

## Bug log
| ID | Area | Description | Severity | Status | Evidence |
|---|---|---|---|---|---|
| BUG-01 | 2 | With exactly 5 photos, upload.js disables the file input; no photos sent | High | Fixed in admin/item_add.php; long-term fix in upload.js (frontend) | Module2/Bug-01 |
| BUG-02 | 1 | Deactivating a user doesn't end a session already logged in | Low | Closed: account re-checked on every request; role changes apply at once | T12; Module8 T85b, T84c |
| BUG-03 | 3 | Filter boxes overflow the 300 px sidebar on wide screens | Low | Fixed in assets/css/photos.css | Module3/Bug-03 |
| BUG-04 | 3 | Details page cropped portrait photos | Medium | Fixed in photos.css; CSS/JS links versioned (?v=) | Module3/Bug-04 |
| BUG-05 | 6 | Admin "new claim" alerts kept a Pending badge after the claim was decided | Low | Fixed: shows current status | Module6/Bug-05 |
| BUG-06 | 7 (and 3) | Filter panel header ignored clicks on wide screens | Low | Fixed: foldable at every width | Module7/Bug-06 |
| BUG-07 | 9 | CSV export opened in Excel as one column (South African Windows uses ;) | Medium | Fixed: "Export to Excel" uses ; – comma CSV link kept | Module9/Bug-07 |
| DEV-01 | 3 | Shared header reused $f and $page, wiping search filters and page numbers | High | Fixed before release | – |
| DEV-02 | 6 | "Turn on browser alerts" button visible when alerts were blocked | Low | Fixed before release | Module6 DEV_ |
| DEV-03 | 6/1 | 30-second bell check counted as activity, so idle logout never happened | Medium | Fixed before release; confirmed by test 90 | Module8 T90 |
| DEV-04 | 8 | "Please log in to continue." replaced "Your account has been deactivated…" | Low | Fixed before release | Module8 DEV_ |
| ENV-01 | Setup | MySQL80 Windows service held port 3306 | Medium | Fixed: stopped, set to Manual | 00_Setup S04–S09 |
| NOTE-01 | – | 403/404 pages show Apache/PHP versions | Low | Fixed 9 Nov: .htaccess (ServerSignature Off, no X-Powered-By, no folder listings) + styled error.php – retested 9 Nov R-1…R-3 PASS (Evidence/QA_Final_Retest) | T19, T32, BR-2 |
| NOTE-02 | 2 | Distinctive details in description; cards visible in wallet photo | Info | Staff guidance | T20a |
| NOTE-03 | 4 | CSRF-refused page is plain unstyled text | Cosmetic | Fixed 9 Nov: shown inside the site layout with Go back / Home – retested R-4 PASS | T44b |
| NOTE-04 | 5 | Empty review queue said "0 pending claims. Compare the proof…" | Cosmetic | Fixed | T49, T55 |
| NOTE-05 | 4/5 | Resubmit hint shown while a newer claim for the item is pending | Cosmetic | Fixed | T55-setup |
| NOTE-06 | 8 | Users list: Created date wrapped onto two lines | Cosmetic | Fixed | Module8/Note-06 |
| NOTE-07 | 8 (all tables) | Wide screens: last table column had no row lines | Cosmetic | Fixed in photos.css (frontend to fix at source) | Module8/Note-07 |
| NOTE-08 | 9 | Dashboard: item found today read "today ago" | Cosmetic | Fixed | Module9/Note-08 |
| NOTE-09 | 9 | Phone cards: two-part cells spread across the row | Cosmetic | Fixed | Module9/Note-09 |
| NOTE-10 | QA tool | stress_test.bat showed the admin password while typing | Low | Fixed (hidden input); evidence screenshot redacted | QA_Stress_Test |
