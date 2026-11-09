# Lost & Found Tracker – QA tools: backup, restore and stress test
Testing & QA: Leandro Rughunath · Group 11 · IT Project 700

These tools run on the XAMPP computer only (double-click the .bat files). They can't be opened
from a browser: the folder has `Require all denied` and every script stops if it is not run
from the command line.

| File | What it does |
|---|---|
| backup.bat | Backup (Phase 4 Table 26): mysqldump --single-transaction of lost_found + copy of the photos + manifest with a fingerprint of every table, into `<XAMPP>\backups\<date_time>\` (outside htdocs, so it can never be downloaded). |
| restore_test.bat | Restores the latest backup into a SEPARATE database `lost_found_restore` and proves every table is identical (Table 26 "restore testing"). The live database is not touched. |
| recover.bat | Disaster recovery: puts the latest backup back into the LIVE database `lost_found` and the photos folder, then proves it is identical. You must type RESTORE. Everything changed since the backup is lost. |
| stress_test.bat | Stress test through the real website (many logged-in users at the same time). Adds test data marked `@stress.test` / `STRESS TEST`. `stress_test.bat --cleanup` removes it. |

Each run prints PASS/FAIL and saves a log in `tools\results\` – put those logs in the evidence pack.

Before you start: XAMPP Apache and MySQL running; `config\db_settings.php` correct (the tools use
the same settings as the site). The PHP curl extension must be on (it is on by default in XAMPP).
Don't use the site while a backup or recovery is running.

## Test order (one run proves everything – about 20 minutes)
BR = backup/restore (Phase 4 Section 8.6, Phase 3 NFR7) · ST = stress (group note 6, NFR4, NFR5)

**BR-0 Before** – Dashboard screenshot (the numbers to compare with at the end).

**BR-1 Backup** – double-click `backup.bat` → "RESULT: BACKUP OK", table list with row counts.
In File Explorer open `<XAMPP>\backups\<newest>\`: lost_found.sql, item_photos\, manifest.json.
Open lost_found.sql in Notepad: last line "-- Dump completed on …".

**BR-2 Backups are not public** – in Chrome open
`localhost/LostFoundTracker/tools/backup.php` and `localhost/LostFoundTracker/tools/results/`
→ 403 Forbidden. `localhost/backups/` → 404 Not Found (the folder is outside htdocs).

**BR-3 Restore test** – double-click `restore_test.bat` → every table "IDENTICAL",
spot-check lines, "RESULT: PASS". phpMyAdmin shows a new database `lost_found_restore` with the
same tables; `lost_found` is unchanged.

**ST-1 … ST-6 Stress test** – double-click `stress_test.bat`, type `yes`, admin email and password.
The password is hidden while you type (NOTE-10 fix). It takes 1–3 minutes.
Expected: 6/6 PASS.
- ST-1 10 admins approve 10 different claims on the same item at once → exactly 1 approved, 9 rejected.
- ST-2 5 admins approve the same claim at once → approved once, 1 audit row, 1 notification.
- ST-3 approve and dispose the same item at once (5 items) → never a mixed result.
- ST-4 1 000 extra items, 25 users at once × 8 rounds → 0 errors, every page under 3 s (NFR4).
- ST-5 dashboard, items report, Excel export of every item, audit export → under 3 s each.
- ST-6 20 wrong passwords at once → account locked; 50 guests on admin pages → all sent to Login.
Then screenshot the Dashboard (now 1 000+ items) and Search (STRESS TEST items).

**BR-4 Disaster recovery** – simulate a loss first: in `htdocs\LostFoundTracker\uploads\item_photos\`
delete ONE photo file (an item now shows a broken photo – screenshot it). Then double-click
`recover.bat`, type `RESTORE` → all tables and "(photos)" IDENTICAL, recovery time in seconds,
"RESULT: PASS".

**BR-5 Site after recovery** – Ctrl+F5: Dashboard numbers are the same as BR-0, no STRESS TEST
items in Search, the deleted photo shows again, you can still log in. The stress-test data is gone
because it was made after the backup – that is what "data since the last backup is lost" means
(Phase 4: maximum data loss 24 hours with daily backups).

After testing: you may drop `lost_found_restore` in phpMyAdmin. Keep the backups folder private
(it contains personal information) and copy one backup to a second place, e.g. OneDrive.
