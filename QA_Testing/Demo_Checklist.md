# Demo checklist – Lost & Found Tracker
For the lecturer demonstration (local XAMPP; AnyDesk if the lecturer wants to watch remotely – group note 6).

## The day before
- [ ] Demo PC: latest code from GitHub main copied to `htdocs\LostFoundTracker`; own `config\db_settings.php` in place.
- [ ] phpMyAdmin: database has all tables incl. `login_attempts`, `item_photos`, `claims.consent_given_at`, `user_admin_log` (files 001–004).
- [ ] Run **tools\reset_demo.bat** → type RESET → "DEMO DATA READY". Note the demo password it prints.
- [ ] Have 2–3 photos of everyday objects (a pen, a bag, a key) on the desktop for the live upload.
- [ ] Chrome: Windows notifications for Chrome switched on (Settings → System → Notifications).
- [ ] AnyDesk installed and tested if needed; screen resolution readable when shared.

## 15 minutes before
- [ ] XAMPP Control Panel: **Apache** and **MySQL** green. (MySQL80 Windows service stays stopped – ENV-01.)
- [ ] Open `localhost/LostFoundTracker/login.php` – page loads.
- [ ] Log out everywhere. Window 1 (normal Chrome) = Admin; window 2 (Incognito) = Student.
- [ ] Close other tabs, mute notifications from other apps.

## Suggested demo order (about 15 minutes)
| # | Who | Show | Point to make |
|---|---|---|---|
| 1 | Guest | Login page, Privacy notice (footer) | POPIA/GDPR; finders are never recorded |
| 2 | Student (ayesha@demo.ac.za) | Search with filters, item details, My Claims (1 pending, 1 rejected), Notifications | Students only see Found items; proof text stays private |
| 3 | Staff (staff@demo.ac.za) | Log Found Item with 2 photos (live) | Up to 5 photos, 15 MB, location data removed from photos |
| 4 | Admin (admin@demo.ac.za) | Dashboard (yellow Pending card) → Review Claims → **approve the Phone claim** | One approved claim per item; audit trail; owner notified |
| 5 | Student window | Bell updates by itself; pop-up alert; My Claims shows Approved | Live in-browser notifications |
| 6 | Admin | Manage Items → Mark collected; Reports → Export to Excel | FR8 reports, opens in Excel |
| 7 | Admin | Audit Log → click an item number → full history | FR10 audit trail, read-only |
| 8 | Admin | Users → create account (temporary password shown once) | No password resets – admin sets a temporary one |
| 9 | Student | My data & privacy → Download my data | Right of access (POPIA s23 / GDPR Art. 15) |
| 10 | Tester | Show QA_Testing on GitHub: 131/131, stress test and recovery results | Testing evidence |

## If something goes wrong
| Problem | Fix |
|---|---|
| "Could not connect to the database" | XAMPP → start MySQL; check the MySQL80 Windows service is stopped |
| Page looks unstyled | Ctrl+F5 |
| "This form has expired" | Go back, F5, try again (normal security check) |
| Demo data got messy | tools\reset_demo.bat (takes seconds) |
| Something broke badly | tools\recover.bat restores the last backup |
| Account locked after wrong passwords | Admin → Users → Unlock, or wait 15 minutes |

## After the demo
- [ ] Don't leave demo accounts on a shared PC; reset or stop XAMPP.
- [ ] Keep the backups folder private (it contains personal information).
