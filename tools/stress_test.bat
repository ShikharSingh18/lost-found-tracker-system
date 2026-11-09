@echo off
rem Lost & Found Tracker QA tool - double-click to run (needs XAMPP MySQL and Apache running)
cd /d "%~dp0"
"..\..\..\php\php.exe" stress_test.php %*
echo.
pause
