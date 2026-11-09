<?php
/*
 * Database connection (Phase 4, Section 6.1 global rules).
 * - PDO with ERRMODE_EXCEPTION
 * - emulated prepares OFF, so values are always sent separately from SQL
 * - one shared connection per request
 */

$settingsFile = __DIR__ . '/db_settings.php';
if (!is_file($settingsFile)) {
    http_response_code(500);
    exit('Missing config/db_settings.php. Copy config/db_settings.example.php and fill it in.');
}
require_once $settingsFile;

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $ex) {
            // Log the real reason, show the user a generic message (Section 8.4).
            error_log('DB connection failed: ' . $ex->getMessage());
            http_response_code(500);
            exit(DEV_MODE ? 'Database connection failed: ' . $ex->getMessage()
                          : 'The system is temporarily unavailable. Please try again later.');
        }
    }
    return $pdo;
}
