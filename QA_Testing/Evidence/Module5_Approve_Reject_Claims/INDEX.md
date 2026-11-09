# Module 5 – Approve or reject claims (admin/claim_review.php)
Result: 11/11 tests PASS · 0 bugs · 2 cosmetic notes fixed · tested 3 Nov 2026 in Chrome on XAMPP
Implements Phase 4 Section 6.6, Table 10 (transaction sequence) and Table 11 (worked example).

| File | Test | Result |
|---|---|---|
| S01 | Set-up: Staff also claims the Wallet → item 3 has two pending claims (Table 11 situation) | – |
| T45 | Staff opening Review Claims → "You do not have permission"; no menu link | PASS |
| T46a | Queue: 3 pending; both Wallet cards warn "2 people have claimed this item"; item record + photo beside proof | PASS |
| T46b | Approve/Reject options per card; "Recently decided" table with admin and time | PASS |
| T47a | Save without a decision → browser "Please select one of these options" | PASS |
| T47b | Browser check off → server "Choose Approve or Reject before saving"; still 3 pending | PASS |
| T48 | Reject Keys claim #3 → "stays Found"; 2 pending left; bell 4 → 3 | PASS |
| T49 | APPROVE Wallet claim #2 → item Claimed, claim #4 rejected automatically; queue empty; bell 3 → 1 | PASS |
| T50-52 | Database: #2 Approved / #4 Rejected 2 ms apart (one transaction); item 3 Claimed, item 1 Found; audit 8–11 = CLAIM_REJECTED, CLAIM_APPROVED, ITEM_STATUS_CHANGED, CLAIM_REJECTED (Table 11 order); claimant notifications; admin alerts marked read | PASS |
| T53a | Student My Claims: Approved + "Collect it from the lost-and-found office"; item no longer a link | PASS |
| T53b | Staff My Claims: Wallet Rejected (no resubmit offer, item Claimed); Keys Rejected + resubmit offer (item Found) | PASS |
| T54a | Claimed wallet gone from search (2 items found) | PASS |
| T54b | Claimed wallet via direct link → "This item is not available" | PASS |
| T55-setup | Staff claims the Keys again → claim #5 Pending | – |
| T55a | Tab 1: approve claim #5 → Keys Claimed | PASS |
| T55b | Tab 2 (stale page): reject claim #5 → "Claim #5 was already decided. Nothing was changed."; #5 stays Approved | PASS |

## Developer test results (SQLite test copy, before release)
Table 11 reproduced exactly (item 12, claims 31 and 32). ROLLBACK check: with the audit write forced to
fail, the claim stayed Pending, the item stayed Found and no notification was sent (Table 10 step 8).

| File | Shows |
|---|---|
| DEV_review_queue_two_claims_one_item | Review page on test data |

## Cosmetic notes found during testing (both FIXED in backend_v5, re-issued 3 Nov 23:40)
- NOTE-04: empty queue said "0 pending claims. Compare the proof…" → now "All claims have been decided." (T49, T55)
- NOTE-05: My Claims showed "You can submit a new claim" on a rejected claim even when a newer claim for the same item was already pending → hint now hidden in that case (T55-setup)
