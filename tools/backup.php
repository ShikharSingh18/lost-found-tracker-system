<?php
/*
 * QA tool: BACKUP (Phase 4 Section 8.6, Table 26).
 * Run:  tools\backup.bat   (or  ..\..\..\php\php.exe tools\backup.php)
 *
 * Creates <XAMPP>\backups\<date_time>\ with
 *   lost_found.sql   mysqldump --single-transaction --routines (safe while the site is in use)
 *   item_photos\     copy of uploads\item_photos
 *   manifest.json    row count + fingerprint of every table and of the photos,
 *                    so a restore can be PROVED identical (compare.php / restore.php)
 * The backups folder is outside htdocs, so a backup can never be downloaded through the site.
 * Backups contain personal information: keep them on this computer or an encrypted drive.
 */
require __DIR__ . '/common.php';

head('Lost & Found Tracker – BACKUP  ' . date('Y-m-d H:i:s'));
$stamp = date('Y-m-d_His');
$dir = BACKUP_DIR . DIRECTORY_SEPARATOR . $stamp;
if (!is_dir($dir) && !mkdir($dir, 0775, true)) fail("Can't create $dir");
out("Backup folder: $dir");

// 1. Fingerprint first (what the backup must contain)
try {
    $pdo = pdo_for(DB_NAME);
    $fp = db_fingerprint($pdo);
} catch (Throwable $e) {
    fail('Cannot connect to the database "' . DB_NAME . '". Is MySQL running in XAMPP? (' . $e->getMessage() . ')');
}
$version = $pdo->query('SELECT VERSION()')->fetchColumn();

// 2. mysqldump
$sqlFile = $dir . DIRECTORY_SEPARATOR . 'lost_found.sql';
$t = microtime(true);
[$code, $err] = run(array_merge([mysql_bin('mysqldump')], mysql_args(),
    ['--single-transaction', '--routines', '--triggers', '--default-character-set=utf8mb4', DB_NAME]), null, $sqlFile);
$secs = round(microtime(true) - $t, 2);
if ($code !== 0) fail("mysqldump failed (exit $code): $err");
$tail = file_get_contents($sqlFile, false, null, max(0, filesize($sqlFile) - 200));
if (!str_contains((string) $tail, 'Dump completed')) fail('The dump file is incomplete (no "Dump completed" line).');
out(sprintf('Database dump: lost_found.sql  %s KB in %s s  (%s)', number_format(filesize($sqlFile) / 1024, 1), $secs, $version));

// 3. Photos
$photos = photo_fingerprint();
$copied = copy_dir(SITE_DIR . '/uploads/item_photos', $dir . DIRECTORY_SEPARATOR . 'item_photos');
out("Photos copied: $copied file(s) (incl. .htaccess)  ·  " . $photos['files'] . ' photo(s)');

// 4. Manifest
file_put_contents($dir . DIRECTORY_SEPARATOR . 'manifest.json', json_encode([
    'created' => date('c'), 'database' => DB_NAME, 'server' => $version,
    'tables' => $fp, 'photos' => $photos,
], JSON_PRETTY_PRINT));

out('');
out(sprintf('%-22s %8s  %s', 'Table', 'Rows', 'Fingerprint'));
out(str_repeat('-', 50));
foreach ($fp as $tbl => $f) out(sprintf('%-22s %8d  %s', $tbl, $f['rows'], $f['hash']));
out(sprintf('%-22s %8d  %s', '(photos)', $photos['files'], $photos['hash']));

$all = glob(BACKUP_DIR . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
out('');
out('RESULT: BACKUP OK. Backups kept in ' . BACKUP_DIR . ': ' . count($all) . ' (policy: keep the last 7 daily + 4 weekly; delete older ones by hand).');
out('Reminder: also copy this folder to a second place (external drive / OneDrive) – Phase 4 Table 26.');
out('Log saved: ' . save_log('backup'));
