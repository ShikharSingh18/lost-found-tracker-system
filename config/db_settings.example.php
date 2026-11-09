<?php
/*
 * Copy this file to config/db_settings.php and fill in your own values.
 * db_settings.php is listed in .gitignore, so each member keeps their own
 * copy and passwords never go into the GitHub repo.
 */

const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'lost_found';
const DB_USER = 'root';     // XAMPP default; use a limited user later (Phase 4, 8.5)
const DB_PASS = '';         // XAMPP default root password is empty

// Folder the site lives in under htdocs, with no trailing slash.
// http://localhost/LostFoundTracker/  ->  '/LostFoundTracker'
const BASE_URL = '/LostFoundTracker';

// true only when the site is served over HTTPS (it is local only for now)
const USE_HTTPS = false;

// Show PHP errors on screen while developing. Set to false for the demo.
const DEV_MODE = true;
