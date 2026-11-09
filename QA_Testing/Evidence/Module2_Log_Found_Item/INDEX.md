# Module 2 – Log found item and photos (admin/item_add.php, items/photo.php)

| File | Test | Result |
|---|---|---|
| T07-08_14 | Admin menu; Log Found Item form with categories from the database | PASS |
| T15 | Item #1 saved: status Found, logged_by 1, no photo | PASS |
| T16a | Item #3 saved "… and 5 photo(s)" | PASS |
| T16b | 5 random-named .jpg files in uploads/item_photos | PASS |
| T17a | 6th photo refused: "Maximum 5 photos" | PASS |
| T17c | Text file refused: "is not a JPG or PNG" | PASS |
| T18b1 | Browser validation switched off in DevTools | – |
| T18b2 | Server shows all 4 errors (category, future date, location, description) | PASS |
| T19 | Direct photo URL → 403 Forbidden | PASS |
| T20a | photo.php shows the photo when logged in | PASS |
| T20b | photo.php redirects to login when logged out | PASS |
| T21 | Photo resized to 899 × 1599; camera/date/GPS metadata removed | PASS |

## Bug-01_five_photos_not_saved (High – FIXED)
upload.js disables the file input at 5 photos; browsers don't send disabled inputs.
Fix: item_add.php re-enables the input on submit.

| File | Shows |
|---|---|
| B1 | Photo folder empty after upload |
| B2 | "Item #2 saved" with no photo count |
| B3 | Database query: no item_photos rows |
| B4 | Folder still empty |
| B5 | Cause: re-enable script missing from item_add.php |
| B6 | After the fix: "Item #3 saved … and 5 photo(s)" |
