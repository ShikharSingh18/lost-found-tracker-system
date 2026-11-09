# QA Evidence – Leandro Rughunath (Backend & Testing)
Lost & Found Tracker · IT Project 700 · Group 11
Environment: XAMPP (Apache 2.4.65, PHP 8.3.36, MariaDB 10.11.16), local only · Browser: Opera GX (Modules 1–2), Google Chrome from Module 3 on

One folder per module. Each module folder has its own INDEX.md listing every
screenshot, the test it proves and the result, plus a sub-folder for each bug
found in that module.

| Folder | Contents |
|---|---|
| 00_Setup | XAMPP install, MySQL port problem (ENV-01) and fix, database import |
| Module1_Login_and_Accounts | Register, login, lockout, logout, idle timeout, deactivated accounts, role menus |
| Module2_Log_Found_Item | Logging items, photo upload and protection; Bug-01 (five photos not saved) |
| Module3_Search_and_Details | Search, filters, item details, gallery; Bug-03 (filter layout), Bug-04 (photo cropped) |
| Module4_Submit_Claim | Submitting claims, consent, duplicates, My Claims, access and CSRF checks |
| Module5_Approve_Reject_Claims | Review queue, approve/reject, Table 11 two-claims example, database checks, two-admin test |
| Module6_Notifications | Inbox, mark read, live bell, Windows pop-up alerts, links; Bug-05 |
| Module7_Manage_Items | Manage list, edit + photos, Mark collected, Dispose; Bug-06 |
| Module8_User_Management | Users page (create, role, deactivate/reactivate, temporary password, unlock), My account password change, BUG-02 + DEV-03 retests; Note-06, Note-07 |
| Module9_Dashboard_Reports_Audit | Dashboard cards and lists, items/claims reports, Excel export, CSV-injection check, audit log + account changes, phone layout; Bug-07, Note-08, Note-09 |
| Module10_Privacy_POPIA_GDPR | Privacy notice, download my data, deletion requests and anonymising, retention clean-up |
| QA_Backup_Restore | Backup, restore test into a separate database, disaster recovery (Phase 4 Section 8.6) |
| QA_Stress_Test | Concurrency races, 25-user load test, heavy pages, lockout under load |

File names starting with DEV_ come from the developer's test database, not from XAMPP.
