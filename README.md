# Lost & Found Tracker

A white-label web app for reporting, searching and claiming lost property at a college. Staff log items that are handed in, students search and claim them, and admins verify ownership before anything is released.

Built with PHP and MySQL. Runs on XAMPP.

## Features

**For students and staff**
- Search found items by keyword, category, location and date, with photo thumbnails and a gallery on each item.
- Submit a claim with proof of ownership and consent. One pending claim per item.
- Track claim status in My Claims, with an in-browser notification bell that updates every 30 seconds.
- Download your own data or ask for your personal information to be deleted.

**For staff**
- Log a found item with up to 5 photos (JPG/PNG, 15 MB each). Photos are re-encoded and stripped of metadata.

**For admins**
- Review claims: approve or reject. Approval runs in one transaction that auto-rejects competing claims and writes the audit trail.
- Manage items: edit, dispose, and mark as collected.
- Manage users: create accounts, change roles, deactivate, unlock, issue temporary passwords, and anonymise personal data.
- Dashboard and reports with switchable charts (bar, stacked, horizontal bar, line, area, pie, doughnut), PNG download, and a print layout (use "Save as PDF" in the print dialog).
- CSV export for items, claims and the audit log, using the same filters as the screen.
- Read-only audit log for items, claims and account changes.
- Retention clean-up that removes old proof-of-ownership text.

**For everyone**
- Light and dark theme that follows the computer's setting and can be switched from the menu.
- Layout that works on phones, with tables that turn into cards.

## Roles

| Role | Can do |
|---|---|
| Student | Search, claim, view own claims, notifications, own data |
| Staff | Everything a student can, plus log found items |
| Admin | Everything above, plus review claims, manage items and users, dashboard, reports, audit log, retention |

## Security

- Passwords are hashed with `password_hash`. Rules: 8-72 characters with a letter, a number and a symbol. Common passwords, sequences and passwords containing the user's name or email are rejected.
- Accounts created or reset by an admin must change their password at first login.
- Five failed logins lock the account for 15 minutes. Admins can unlock it.
- Sessions time out after 30 minutes idle. Deactivated users are logged out on their next click.
- Prepared statements throughout, CSRF tokens on forms, and role checks on every page.
- Uploaded photos are not directly accessible on the web and are only served to logged-in users with the right role.
- CSV exports are protected against formula injection. Reports never include proof-of-ownership text.

## Privacy and compliance (for your college to complete)

This project is white-label. It ships with privacy tooling, but the privacy policy, the contact details and the retention period belong to the college that runs it. Complete the following before going live.

**What you need to fill in**
- `PRIVACY_CONTACT` in `includes/privacy.php`: the person or office that handles privacy requests (for example your Information Officer). The default is a placeholder.
- `RETENTION_MONTHS` in `includes/privacy.php`: how long proof-of-ownership text and read notifications are kept after a claim is decided. The default of 12 is a placeholder, so set the period your own policy requires.
- The wording of `privacy.php` (the public privacy notice): review it against your own policy and the laws that apply to you. It is a starting template, not legal advice.
- The regulator and legal references in the notice (it currently cites POPIA, GDPR and South Africa's Information Regulator): replace these if your college is in another jurisdiction.

**What the app provides**
- A public privacy notice, linked from every page footer, the registration page and the claim consent box.
- Consent recorded on every claim.
- Data minimisation: people who hand in items are never recorded, reports exclude proof text, and photo metadata (GPS, camera, date) is removed.
- Download my data: users can export their own data as a JSON file.
- Deletion requests: users can ask for deletion and admins can anonymise an account while keeping the records other items and claims depend on.
- Retention clean-up: admins can preview and run the removal of old proof text and read notifications.
- An audit trail of privacy actions (exports, deletion requests, anonymisation, proof removal).

## Quick start (XAMPP, Windows)

**Requirements:** XAMPP with PHP 8+, MySQL 8.0.16+, and the `gd` and `fileinfo` PHP extensions enabled.

1. Copy the project into `C:\xampp\htdocs\LostFoundTracker\`.
2. In `php.ini` set `upload_max_filesize = 15M`, `post_max_size = 80M` and `max_file_uploads = 20`. Make sure `extension=gd` and `extension=fileinfo` are not commented out. Restart Apache.
3. Start Apache and MySQL.
4. In phpMyAdmin, create the database:
   ```sql
   CREATE DATABASE lost_found CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
5. Import these files, in this order:
   1. `database/lost_found_schema.sql`
   2. `database/001_login_attempts.sql`
   3. `database/002_item_photos.sql`
   4. `database/003_claim_consent.sql`
   5. `database/004_user_admin_log.sql`
   6. `database/005_force_password_change.sql`
6. Copy `config/db_settings.example.php` to `config/db_settings.php` and check the values. This file is git-ignored.
7. Create the first accounts, once for an Admin and once for a Staff account:
   ```
   C:\xampp\php\php.exe database\create_admin.php
   ```
8. Open `http://localhost/LostFoundTracker/login.php`.

## Project structure

| Path | Contents |
|---|---|
| `admin/` | Dashboard, reports, audit log, claim review, users, log item, retention |
| `items/` | Search, item details, photos, manage and edit items |
| `claims/` | Submit a claim, My Claims |
| `includes/` | Bootstrap, auth helpers, layout, reports, chart data, privacy and photo handling |
| `assets/` | CSS, JavaScript (charts, theme, notifications, upload) and images |
| `config/` | Database settings and the PDO connection |
| `database/` | Schema, numbered migrations and the admin-creation script |

## Testing

`BACKEND_SETUP.md` and `MODULES_6-10_README.md` hold the manual test scripts (tests 1-118) for every module, including security, concurrency and mobile checks.

## Documentation

- [Setup and tests for the core modules](BACKEND_SETUP.md)
- [Notifications, manage items, users, dashboard and reports, privacy](MODULES_6-10_README.md)

## Licence

See [LICENSE](LICENSE).
