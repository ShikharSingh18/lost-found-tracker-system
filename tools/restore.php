<?php
/*
 * QA tool: RESTORE (Phase 4 Section 8.6 – restore testing and recovery procedure).
 *
 *   tools\restore_test.bat   php restore.php            -> restores the LATEST backup into a SEPARATE
 *                                                          database "lost_found_restore" and proves it is
 *                                                          identical to the backup's manifest. Live data
 *                                                          is not touched. (Table 26 "Restore testing")
 *   tools\recover.bat        php restore.php --live     -> RECOVERY: replaces the live database
 *                                                          "lost_found" and the photos with the backup.
 *                                                          Asks you to type RESTORE first.
 *   Optional: --backup=2026-11-04_201500  to use a specific backup folder.
 */
require __DIR__ . '/common.php';

$live = in_array('--live', $argv, true);
$pick = null;
foreach ($argv as $a) if (str_starts_with($a, '--backup=')) $pick = substr($a, 9);

$dirs = glob(BACKUP_DIR . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
sort($dirs);
if (!$dirs) fail('No backups found in ' . BACKUP_DIR . '. Run backup.bat first.');
$dir = $pick ? BACKUP_DIR . DIRECTORY_SEPARATOR . $pick : end($dirs);
if (!is_file("$dir/lost_found.sql") || !is_file("$dir/manifest.json")) fail("Not a complete backup: $dir");
$manifest = json_decode(file_get_contents("$dir/manifest.json"), true);
$target = $live ? DB_NAME : RESTORE_DB;

head(($live ? 'RECOVERY (live database)' : 'RESTORE TEST (separate database)') . '  ' . date('Y-m-d H:i:s'));
out('Backup used : ' . basename($dir) . '  (made ' . $manifest['created'] . ')');
out('Restore into: ' . $target . ($live ? '  <-- the LIVE site database' : '  (live database "' . DB_NAME . '" is not touched)'));

if ($live) {
    out('');
    out('WARNING: everything changed in "' . DB_NAME . '" since this backup will be LOST');
    out('(new items, claims, accounts, photos). Take the site offline first: tell users / stop testing.');
    if (ask('Type RESTORE to continue') !== 'RESTORE') { out('Cancelled. Nothing was changed.'); exit(0); }
}

$t0 = microtime(true);
$server = pdo_for(null);
if (!$live) {
    // Only the separate restore-test database is ever dropped.
    $server->exec('DROP DATABASE IF EXISTS `' . RESTORE_DB . '`');
}
$server->exec('CREATE DATABASE IF NOT EXISTS `' . $target . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');

[$code, $err] = run(array_merge([mysql_bin('mysql')], mysql_args(), ['--default-character-set=utf8mb4', $target]), "$dir/lost_found.sql");
if ($code !== 0) fail("mysql import failed (exit $code): $err");
out('Database imported in ' . round(microtime(true) - $t0, 2) . ' s');

if ($live) {
    $photoDir = SITE_DIR . '/uploads/item_photos';
    $removed = 0;
    foreach (is_dir($photoDir) ? scandir($photoDir) : [] as $f) {
        if ($f !== '.' && $f !== '..' && $f !== '.htaccess' && is_file("$photoDir/$f")) { unlink("$photoDir/$f"); $removed++; }
    }
    $copied = copy_dir("$dir/item_photos", $photoDir);
    out("Photos: $removed current file(s) removed, $copied restored from the backup");
}
$rto = round(microtime(true) - $t0, 1);

// ---- Verify (recovery procedure step 4: check record counts and spot-check) ----
$fp = db_fingerprint(pdo_for($target));
out('');
out(sprintf('%-22s %10s %10s  %s', 'Table', 'Backup', 'Restored', 'Result'));
out(str_repeat('-', 60));
$ok = true;
foreach ($manifest['tables'] as $tbl => $m) {
    $r = $fp[$tbl] ?? null;
    $same = $r && $r['rows'] === $m['rows'] && $r['hash'] === $m['hash'];
    $ok = $ok && $same;
    out(sprintf('%-22s %10d %10s  %s', $tbl, $m['rows'], $r ? $r['rows'] : 'missing', $same ? 'IDENTICAL' : 'DIFFERENT'));
}
foreach (array_diff_key($fp, $manifest['tables']) as $tbl => $r) { $ok = false; out(sprintf('%-22s %10s %10d  EXTRA TABLE', $tbl, '-', $r['rows'])); }
if ($live) {
    $ph = photo_fingerprint();
    $same = $ph['hash'] === $manifest['photos']['hash'];
    $ok = $ok && $same;
    out(sprintf('%-22s %10d %10d  %s', '(photos)', $manifest['photos']['files'], $ph['files'], $same ? 'IDENTICAL' : 'DIFFERENT'));
}

// Spot-check: newest item, claim and audit entry (procedure step 4)
$p = pdo_for($target);
out('');
out('Spot-check of the restored data:');
$row = $p->query('SELECT i.item_id, c.category_name, i.status, i.date_found FROM items i JOIN categories c ON c.category_id = i.category_id ORDER BY i.item_id DESC LIMIT 1')->fetch();
out('  newest item  : ' . ($row ? "#{$row['item_id']} {$row['category_name']} ({$row['status']}, found {$row['date_found']})" : 'none'));
$row = $p->query('SELECT claim_id, item_id, verification_status FROM claims ORDER BY claim_id DESC LIMIT 1')->fetch();
out('  newest claim : ' . ($row ? "#{$row['claim_id']} on item #{$row['item_id']} ({$row['verification_status']})" : 'none'));
$row = $p->query('SELECT log_id, action, occurred_at FROM audit_log ORDER BY log_id DESC LIMIT 1')->fetch();
out('  newest audit : ' . ($row ? "#{$row['log_id']} {$row['action']} at " . substr($row['occurred_at'], 0, 19) : 'none'));
$row = $p->query("SELECT COUNT(*) FROM claims WHERE approved_item_id IS NOT NULL")->fetchColumn();
out("  approved claims (unique per item, generated column rebuilt): $row");

out('');
out('Recovery time (import + checks): ' . $rto . ' s   (target in Phase 4: under 4 hours)');
out($ok ? 'RESULT: PASS – the restored ' . ($live ? 'live database and photos are' : 'database is') . ' IDENTICAL to the backup.'
        : 'RESULT: FAIL – differences found (see table above).');
out('Log saved: ' . save_log($live ? 'recovery' : 'restore_test'));
exit($ok ? 0 : 2);
