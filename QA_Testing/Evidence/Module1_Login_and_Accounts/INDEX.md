# Module 1 – Login and accounts (register.php, login.php, logout.php)

| File | Test | Result |
|---|---|---|
| T01-02 | Accounts have correct roles; passwords stored as bcrypt ($2y$), never plain text | PASS |
| T03 | Duplicate email refused; name and email kept | PASS |
| T04a | Short password refused (server) | PASS |
| T04b | Mismatched passwords refused (server) | PASS |
| T04c | Invalid email refused; several errors listed together; mobile menu layout | PASS |
| T05a | Wrong password → "Invalid email or password." | PASS |
| T05b | Unknown email → the identical message (no account enumeration) | PASS |
| T05-13 | Student menu has no admin links; admin page → "You do not have permission" | PASS |
| T06a | 5 failed logins → "Too many failed attempts" | PASS |
| T06b | Correct password refused while locked | PASS |
| T06c | login_attempts: failed_count 5, locked_until +15 min; unknown emails tracked too | PASS |
| T06d | Unlocked by deleting the row; Staff menu correct (Table 13) | PASS |
| T09a | Logout → logged-out page | PASS |
| T09b | Back button → login page "Please log in to continue." | PASS |
| T09c | Refresh → still logged out | PASS |
| T11 | Idle 30+ minutes → "Your session expired. Please log in again." | PASS |
| T12a | Test Student set to is_active = 0 | – |
| T12b | Inactive account refused with the generic message | PASS |
| T12c | Account reactivated (is_active = 1) | – |
| T12d | Reactivated account logs in | PASS |

Open bug: BUG-02 (low) – deactivating an account doesn't end a session that is already logged in.
