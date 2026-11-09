# QA – Final retest of NOTE-01, NOTE-03 and the demo reset (9 Nov 2026, XAMPP, Chrome)
| File | Test | Result |
|---|---|---|
| R1_404_styled_no_version.png | R-1 localhost/LostFoundTracker/nothing.php → styled "Page not found" (Error 404), no Apache/PHP version | PASS |
| R2_tools_403_styled.png | R-2 tools/backup.php → styled "Access denied" (Error 403) | PASS |
| R3_assets_no_listing_403.png | R-3 assets/ → "Access denied", no file listing | PASS |
| R4_form_expired_styled.png | R-4 Log Found Item submitted after logging out/in in another tab → "This form has expired or was not sent from this site. Nothing was saved." inside the site layout | PASS |
| R5_reset_demo_ready.png | R-5 reset_demo.bat → backup 2026-11-09_214737, tables emptied, 5 users / 11 items / 3 claims / 6 notifications / 19 audit entries, "DEMO DATA READY" | PASS |
NOTE-01 and NOTE-03 closed.
