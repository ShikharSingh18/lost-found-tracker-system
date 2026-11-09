@echo off
rem Lost & Found Tracker - reset the database to demo data (makes a backup first)
cd /d "%~dp0"
"..\..\..\php\php.exe" reset_demo.php %*
echo.
pause
