# Module 7 – Manage Items (items/manage.php, items/edit.php)
Result: 12/12 tests PASS · 1 bug found and fixed (BUG-06) · tested 4 Nov 2026 in Chrome on XAMPP
Shipped in: LostFoundTracker_Modules_6-10.zip

| File | Test | Result |
|---|---|---|
| T64a / T64b | Staff opening Manage Items / Edit → "You do not have permission" | PASS |
| T65a | List: all statuses, right buttons per status, "1 pending claim" link | PASS |
| T65b | "1 pending claim" link opens Review Claims (claim #6) | PASS |
| T66a | Filter Status = Claimed → 2 items | PASS |
| T66b | Keyword "keys" → 1 item | PASS |
| T67a | Edit form loads current details (photos 0 of 5) | – |
| T67b | Description changed → "Item #2 updated." | PASS |
| T67c | Search shows "Black wallet with brown stitching" | PASS |
| T68a | Browser checks off: empty location + future date → both server errors | PASS |
| T68b | Refresh after an error asks "Confirm Form Resubmission" – normal browser behaviour for a posted form | – |
| T68c | Fresh page: original location and date – nothing saved | PASS |
| T69a / T69b | 2 photos added → "2 photo(s) added", "Current photos (2 of 5)", first = thumbnail | PASS |
| T70 | Adding 4 more (6 total) → "at most 5 photos. You are keeping 2 and adding 4." | PASS |
| T71a / T71b | Remove thumbnail → "1 photo(s) removed"; former 2nd photo is now the thumbnail (1 of 5) | PASS |
| T71c | Search shows the new thumbnail | PASS |
| T72a | Mark collected asks "Record that the owner has collected item #1?" | – |
| T72b | "The Keys (item #1) was marked as collected by its owner." | PASS |
| T72c | Database: ITEM_RELEASED, item 1, claim 5, by admin 1, 12:05:17 | PASS |
| T72d | Row shows "Collected 2026-11-04 12:05"; Mark collected button gone (can't record twice) | PASS |
| T72e | Owner (Staff) gets "Item collected – You collected your Keys (item #1)…" | PASS |
| T74 | Dispose only on Found; Mark collected only on uncollected Claimed (seen in T65a, T72d, T73b) | PASS |
| T75 | Dispose asks "Dispose of item #2? Its pending claims will be rejected…" with Cancel | PASS |
| T73a | Dispose #2 → "marked Disposed. 1 pending claim(s) were rejected automatically." | PASS |
| T73b | Row #2 Disposed, only Edit left | PASS |
| T73c | Student's My Claims: item #2 claim Rejected 12:14 | PASS |
| T73d | Student notification "…The item is no longer available." | PASS |
| T73e | Student Search: disposed item hidden (0 items found) | PASS |

## Bug-06_filters_wont_open (Low – FIXED 4 Nov)
On wide screens style.css showed a "−" on the filter panel but made its header ignore clicks, so the
panel could not be folded away (or opened when closed). Also affected Search Found Items.
Fix: manage.php opens the panel by default; photos.css makes the panel foldable at every width.
| File | Shows |
|---|---|
| F1 | Before: panel closed and could not be opened |
| F2 | Before: panel open, "−" does nothing |
| DEV_F3 | Developer test: folded panel shows "+" |
| F4 / F5 | After (XAMPP): folds to "+", opens to "−" |

## Developer test results (before release)
About 20 checks passed, incl. dispose with 2 pending claims, refusals, photo renumbering and file deletion.
| File | Shows |
|---|---|
| DEV_manage_items_list, DEV_edit_item_photos, DEV_owner_collected_notification | Test-data screenshots |

Data note after Module 7: no items are Found any more (#1 and #3 Claimed, #2 Disposed). Log a new item
before tests that need a Found item.
