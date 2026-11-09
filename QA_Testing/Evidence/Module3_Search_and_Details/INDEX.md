# Module 3 – Search and item details (items/search.php, items/details.php)
Result: 10/10 tests PASS · 2 bugs found and fixed (BUG-03, BUG-04) · tested 3 Nov 2026 (Opera GX, then Chrome)

| File | Test | Result |
|---|---|---|
| T23 | Staff: only Found items, no Status filter, Staff menu | PASS |
| T23d | Student: only Found items, no Status filter, Student menu | PASS |
| T24a | Keyword "wallet" → 2 found; keyword kept | PASS |
| T24b | Clear → all 3 back, filters empty | PASS |
| T24c | Category Keys → 1 found; selection kept | PASS |
| T24d | Location "benches" → 2 found | PASS |
| T24e | Keyword "black" + category Wallet → 1 found | PASS |
| T25a | From 2026-11-05 / to 2026-10-01 → swapped with yellow note; 3 found | PASS |
| T25b | Range 2026-11-03 to 2026-11-10 → 0 found | PASS |
| T27a | 15 items → page 1 shows 12 with pager "1 2 Next" | PASS |
| T27b | Page 2 shows the 3 oldest with "Previous 1 2" | PASS |
| T27c | Keyword "PAGING" → 12 found on one page, no pager (filters respected) | PASS |
| T28a | Keyword ' (single quote) → 0 found, no database error | PASS |
| T28b | Keyword ' OR 1=1 -- → 0 found, no error (SQL injection blocked) | PASS |
| T28c | Keyword % → 0 found (wildcard escaped) | PASS |
| T29a | Details: 5-photo gallery, claim button, no "Logged by" for students | PASS |
| T29b | Whole portrait photo fits the frame (after BUG-04 fix) | PASS |
| T29c | Clicking the 2nd thumbnail swaps the main photo and highlights it | PASS |
| T30a | Item 2 set to Claimed in phpMyAdmin | – |
| T30b | Student search hides the Claimed item (2 found) | PASS |
| T30c | Student opening details.php?id=2 directly → "This item is not available" | PASS |
| T30d | Admin still sees all 3, Claimed badge on item 2 | PASS |
| T31 | Admin: Status filter shown; thumbnails from first photo | PASS |
| T32 | Claim button opens claims/submit.php?item_id=3 (page arrives in Module 4) | PASS |
| DEV_filter_layout_fix_* | Developer test data: fixed sidebar at desktop and phone width | – |

## Bug-03_filter_layout (Low – FIXED)
On wide screens the 300 px filter sidebar used two columns, so boxes spilled out of the card
(also present in the frontend mockup). Fix: assets/css/photos.css stacks the filters in one column.

| File | Shows |
|---|---|
| L1 | Before: Keyword, Location, Date and Status boxes overflow the card |
| L2 | After: all fields inside the card |

## Bug-04_photo_cropped (Medium – FIXED)
The details-page frame let tall phone photos spill past its bottom edge, so only the top showed.
Fix: photos.css pins the image inside the frame (object-fit: contain). Follow-up: CSS/JS links now
carry ?v=<file time> so browsers fetch changed files without Ctrl+F5.

| File | Shows |
|---|---|
| P1 | Before: only the top of the wallet photo visible |
| P2 | After: whole photo visible with dark side bars |
| DEV_photo_frame_before / after | Developer reproduction with a TOP/BOTTOM test image |

Cleanup after T27: DELETE FROM items WHERE description LIKE 'PAGING TEST%';  and set item 2 back to Found.
