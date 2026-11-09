# QA – Backup and restore (Phase 4 Section 8.6 Table 26 + recovery procedure; Phase 3 NFR7)
Tools: tools\backup.bat, restore_test.bat, recover.bat (LostFoundTracker_QA_Tools.zip)
Result: **6/6 PASS** (BR-0 to BR-5) · XAMPP, 8 Nov 2026 · backup 0.26 s, restore test 0.3 s, recovery 0.4 s

| ID | Test | Expected |
|---|---|---|
| BR-0 | Dashboard before | numbers to compare with BR-5 |
| BR-1 | backup.bat | BACKUP OK; folder with lost_found.sql ("Dump completed"), item_photos, manifest.json |
| BR-2 | Backups/tools not reachable from the browser | tools → 403; localhost/backups → 404 |
| BR-3 | restore_test.bat → lost_found_restore | every table IDENTICAL; live database untouched |
| BR-4 | Delete one photo + stress data present → recover.bat (type RESTORE) | all tables + photos IDENTICAL; recovery time shown |
| BR-5 | Site after recovery | Dashboard = BR-0; stress data gone; deleted photo back; login works |

## Leandro's run (XAMPP)
| File | Test | Result |
|---|---|---|
| BR0_dashboard_before.png | BR-0 Dashboard before: items 5 (Found 2, Claimed 2, Disposed 1), claims 1 / 2 / 8 / 11 | recorded |
| BR1_backup_ok.png | BR-1 backups\2026-11-08_225316: lost_found.sql 21.9 KB in 0.26 s (MariaDB 10.11.16), 6 photos, 9 tables fingerprinted | PASS |
| BR2_tools_403_forbidden.png | BR-2 localhost/LostFoundTracker/tools/backup.php → 403 Forbidden | PASS (shows Apache version – NOTE-01, still open) |
| BR3_restore_test_identical.png | BR-3 restore into lost_found_restore: all 9 tables IDENTICAL, spot-check item #17 / claim #11 / audit #36, 0.3 s | PASS |
| BR5a_dashboard_after_recovery_same_as_BR0.png | BR-5 Dashboard after recover.bat: 5 items (2 / 2 / 1), claims 1 / 2 / 8 / 11 – identical to BR-0; the 1 000+ stress items are gone | PASS |
| BR4b_recovery_log_identical_pass.png | BR-4 recover.bat (recovery log): one photo had been deleted ("5 current file(s) removed, 7 restored"); all 9 tables + photos (6/6) IDENTICAL; spot-check item #17 / claim #11 / audit #36; recovery time 0.4 s (target < 4 h) | PASS |
