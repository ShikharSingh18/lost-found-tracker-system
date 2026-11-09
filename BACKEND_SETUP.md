# Backend – setup and test (Leandro)

## Files in this pack
| File | What it does |
|---|---|
| config/db_settings.example.php | Template for your own database settings (copy to db_settings.php) |
| config/database.php | `db()` – one shared PDO connection, exceptions on, emulated prepares off |
| includes/bootstrap.php | First line of every page: settings, secure session, 30-min idle timeout |
| includes/authentication.php | `e()`, `url()`, `redirect()`, `flash()`, CSRF, `requireLogin()`, `requireRole()`, `writeAudit()` |
| includes/header.php / footer.php | Shared layout with the role-based menu (Table 13), using Simphiwe's markup |
| includes/photos.php | Photo checks and re-encoding (5 photos, 15 MB each, JPG/PNG, metadata removed) |
| register.php / login.php / logout.php | Module 1 (Section 6.2) |
| admin/item_add.php | Module 2 – staff/admin log a handed-in item. No finder details. |
| items/photo.php | Shows a photo only to logged-in users with the right role |
| uploads/item_photos/.htaccess | Blocks direct web access to uploaded photos |
| items/search.php | Module 3 – search and filter (keyword, category, location, dates; admins also status), 12 per page |
| items/details.php | Module 3 – item details with photo gallery and claim button |
| claims/submit.php | Module 4 – submit a claim (proof ≥ 20 chars, POPIA/GDPR consent, one transaction with audit + admin notification) |
| claims/my_claims.php | Module 4 – a user's own claims with status, decision date and note |
| database/003_claim_consent.sql | PROPOSED column claims.consent_given_at – needs database team OK |
| admin/claim_review.php | Module 5 – approve/reject claims (Table 10 transaction, auto-reject others, audit, notifications) |
| assets/css/photos.css | Small add-on so real photos fit Simphiwe's thumbnail and photo boxes |
| index.php | Temporary home page for testing login |
| database/001_login_attempts.sql | PROPOSED table for the 5-failure lockout – needs database team OK |
| database/002_item_photos.sql | PROPOSED table for up to 5 photos per item – needs database team OK |
| database/create_admin.php | Terminal script to create the first Admin / Staff accounts |
| .gitignore | Keeps db_settings.php, uploaded photos and backups out of GitHub |

## Setup on XAMPP
1. Copy these files into `C:\xampp\htdocs\LostFoundTracker\` next to Simphiwe's `assets` folder
   (item_add.php uses his `assets/css/upload.css` and `assets/js/upload.js`).
2. Edit `C:\xampp\php\php.ini` (XAMPP Control Panel → Apache → Config → PHP (php.ini)):
   `upload_max_filesize = 15M`, `post_max_size = 80M`, `max_file_uploads = 20`,
   and make sure `extension=gd` and `extension=fileinfo` are not commented out. Restart Apache.
3. Start Apache and MySQL in the XAMPP Control Panel.
4. phpMyAdmin → SQL tab:
   `CREATE DATABASE lost_found CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
5. Select `lost_found` → Import `lost_found_schema.sql`, then `database/001_login_attempts.sql`,
   then `database/002_item_photos.sql`.
6. Copy `config/db_settings.example.php` to `config/db_settings.php` and check the values.
7. Create accounts: `C:\xampp\php\php.exe database\create_admin.php` (once for Admin, once for Staff).
8. Open http://localhost/LostFoundTracker/login.php

## Quick tests – login (record PASS/FAIL)
1. Register a new account → redirected to login with "Account created".
2. phpMyAdmin → users: role is Student, password_hash starts with `$2y$`.
3. Register the same email again → "already exists" error.
4. Short password / mismatched passwords / bad email → correct error, name and email kept in the form.
5. Wrong password and unknown email → the SAME "Invalid email or password." message.
6. 5 wrong passwords → lockout message; correct password also refused for 15 minutes.
7. Log in → Home shows name and role; menu matches the role (Student has no admin links).
8. Admin account → admin menu items appear.
9. Logout → logged-out page; pressing Back and refreshing Home sends you to login.
10. Open index.php without logging in → redirected to login.
11. Idle for 30+ minutes → next click asks you to log in again.
12. Set `is_active = 0` for a user in phpMyAdmin → that user can't log in.

## Quick tests – log found item
13. As Student, open admin/item_add.php → "You do not have permission".
14. As Staff and as Admin → the form opens; categories come from the database.
15. Save with no photos → "Item #N saved"; items row has status Found and logged_by = your user ID; audit_log has ITEM_LOGGED.
16. Save with 5 photos → 5 rows in item_photos (sort_order 1–5), 5 .jpg files with random names in uploads/item_photos/, items.photo_storage_key = the first one.
17. Try 6 photos, a 16 MB photo, and a .txt renamed to .jpg → each refused with a clear message, nothing saved.
18. Date in the future, empty description/location, no category → errors shown, typed values kept.
19. Browse to http://localhost/LostFoundTracker/uploads/item_photos/<file>.jpg → 403 Forbidden.
20. items/photo.php?id=1 logged in → photo shows; logged out → redirected to login.
21. Check a stored photo's properties: no camera/GPS data, longest side 1600 px or less.
22. Refresh the page after saving → no duplicate item.

## Quick tests – search and item details (Module 3)
23. Student → Search Found Items: only status Found items listed, newest first; items with photos show the first photo as thumbnail.
24. Keyword "wallet" → only matching items; category filter → only that category; location filter works.
25. Dates: from/to range filters; a "from" after "to" is swapped with a yellow note.
26. Filters stay filled in after Apply; Clear resets them.
27. More than 12 items → page numbers appear and keep the filters.
28. Type `%` or `' OR 1=1 --` as the keyword → no error, no extra results (SQL injection test).
29. Open an item → photos in a gallery; clicking a small photo swaps the big one; "Logged by" NOT shown to students.
30. Student opens details.php?id=<a Claimed item> (change status in phpMyAdmin) → "This item is not available."
31. Admin → search has a Status filter; details show item number and "Logged by".
32. Student item page shows "This is mine – submit a claim" (the claim page itself comes in Module 4).

## Module 4 setup (once)
phpMyAdmin → lost_found → Import `database/003_claim_consent.sql` (adds claims.consent_given_at).

## Quick tests – submit a claim and My Claims (Module 4)
33. Admin opens claims/submit.php?item_id=3 → "You do not have permission" (admins verify, they don't claim).
34. Student: item page → "This is mine – submit a claim" → form shows the item on the right.
35. Proof "my keys" and no tick → both errors shown; the text you typed is kept.
36. Proof of 20+ characters but no tick → "Please tick the box…"; nothing saved.
37. Valid proof + tick → redirected to My Claims with green "Your claim … was submitted"; status Pending.
38. phpMyAdmin: claims row has claimant_user_id = the student, verification_status Pending, consent_given_at filled; items row still Found.
39. audit_log has CLAIM_SUBMITTED with item_id and claim_id; notifications has one row per ACTIVE admin ("A new claim was submitted for item #…").
40. Open the same item again → "You have a pending claim – view it"; claims/submit.php?item_id=<same> → redirected to My Claims ("You already have a pending claim").
41. Set the claim to Rejected in phpMyAdmin (verified_by = 1, date_verified = NOW()) → My Claims shows Rejected + "You can submit a new claim"; a new claim is then allowed.
42. Item set to Claimed → claims/submit.php?item_id=<that item> → "This item is not available to claim".
43. Staff can also submit a claim; Staff's My Claims does NOT show the student's claims.
44. Security: change item_id in the address to a non-number → "not available"; form with a wrong CSRF token → "Your form expired…".

## Quick tests – approve or reject claims (Module 5)
Set-up for the two-claims test: log in as Staff and also claim the Wallet (item 3), so item 3 has TWO pending claims (Student and Staff).

45. Student or Staff opens admin/claim_review.php → "You do not have permission".
46. Admin → Review Claims: one card per pending claim with item record, photo, claimant name and email, proof; item 3 cards show "2 people have claimed this item".
47. Click Save decision without choosing → "Choose Approve or Reject before saving"; nothing changes.
48. Reject the Staff claim on the Keys (item 1) → "Claim #… rejected. The Keys (item #1) stays Found."; item 1 still Found.
49. APPROVE the Student's Wallet claim (Table 11) → "Claim #… approved. The Wallet (item #3) is now Claimed. 1 other pending claim(s) … rejected automatically."
50. phpMyAdmin: approved claim Approved + verified_by = admin + date_verified; the other wallet claim Rejected; item 3 Claimed.
51. audit_log: CLAIM_APPROVED (item 3 + claim), ITEM_STATUS_CHANGED (item 3), CLAIM_REJECTED (other claim) – plus CLAIM_REJECTED from test 48.
52. Notifications: Student "…was approved. Please collect it…"; Staff "…was not approved. The item was released to its owner." Admin's alerts for these claims now is_read = 1 (bell count drops).
53. Student's My Claims → Approved, "Collect it from the lost-and-found office"; Staff's My Claims → Rejected (no "submit a new claim" note, because the item is no longer Found).
54. Review Claims "Recently decided" lists each decision with the admin's name and time; the Wallet no longer appears in student search.
55. Concurrency (two tabs): open Review Claims in two tabs, approve a claim in tab 1, then try to decide the SAME claim in tab 2 → "Claim #… was already decided. Nothing was changed."

Menu links to pages not built yet (search, claims, admin…) will show "Not Found" for now – that's expected.
