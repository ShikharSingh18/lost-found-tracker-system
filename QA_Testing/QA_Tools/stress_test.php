<?php
/*
 * QA tool: STRESS TEST (group note 6 "stress testing"; Phase 3 NFR4 performance, NFR5 reliability;
 * Phase 4 Table 10/11 concurrency-safe approval).
 *
 * Run:  tools\stress_test.bat      (make a backup first: tools\backup.bat)
 *       php tools\stress_test.php --cleanup     removes all stress-test data afterwards
 *
 * What it does (all through the real website over HTTP, like many browsers at once):
 *   ST-1  10 admins approve 10 DIFFERENT claims on the SAME item at the same moment
 *         -> exactly 1 approved, 9 rejected, item Claimed, audit + notifications consistent.
 *   ST-2  5 admins approve the SAME claim at the same moment (double-click / two tabs)
 *         -> approved once, 1 audit row, 1 notification.
 *   ST-3  Approve and Dispose the same item at the same moment (5 pairs)
 *         -> each item ends either Claimed+Approved or Disposed+Rejected, never mixed.
 *   ST-4  Load: 1 000 extra items, 25 users browsing at the same time (search, filters,
 *         details, dashboard, reports, bell) -> no errors, every page under 3 s (NFR4).
 *   ST-5  Heavy pages: dashboard, items report, Excel export of every item.
 *   ST-6  20 wrong-password logins for one email at the same moment -> account locked
 *         (lockout works under concurrency); 50 guests hitting admin pages -> all sent to Login.
 *
 * Test data is marked: users *@stress.test, items "STRESS TEST …". Nothing else is changed
 * except normal side effects (login sessions). Remove it with --cleanup or by restoring the backup.
 */
require __DIR__ . '/common.php';

const CLAIMANTS = 10;
const BULK_ITEMS = 1000;
const LOAD_SESSIONS = 25;
const LOAD_ROUNDS = 8;
const PAGE_LIMIT_S = 3.0;   // NFR4 target

if (!function_exists('curl_multi_init')) fail('The PHP curl extension is off. In xampp\\php\\php.ini remove the ; before extension=curl and try again.');

$pdo = pdo_for(DB_NAME);

// ======================= --cleanup =======================
function stress_ids(PDO $pdo): array
{
    $items = $pdo->query("SELECT item_id FROM items WHERE description LIKE 'STRESS TEST%'")->fetchAll(PDO::FETCH_COLUMN);
    $users = $pdo->query("SELECT user_id FROM users WHERE email LIKE '%@stress.test'")->fetchAll(PDO::FETCH_COLUMN);
    $in = fn (array $a) => $a ? implode(',', array_map('intval', $a)) : '0';
    $claims = $pdo->query('SELECT claim_id FROM claims WHERE item_id IN (' . $in($items) . ') OR claimant_user_id IN (' . $in($users) . ')')->fetchAll(PDO::FETCH_COLUMN);
    return [$in($items), $in($users), $in($claims), count($items), count($users), count($claims)];
}

if (in_array('--cleanup', $argv, true)) {
    head('STRESS TEST – CLEANUP');
    [$i, $u, $c, $ni, $nu, $nc] = stress_ids($pdo);
    $pdo->beginTransaction();
    $n1 = $pdo->exec("DELETE FROM notifications WHERE claim_id IN ($c) OR user_id IN ($u)");
    $n2 = $pdo->exec("DELETE FROM audit_log WHERE item_id IN ($i) OR claim_id IN ($c)");
    $pdo->exec("DELETE FROM user_admin_log WHERE target_user_id IN ($u)");
    $pdo->exec("DELETE FROM claims WHERE claim_id IN ($c)");
    $pdo->exec("DELETE FROM item_photos WHERE item_id IN ($i)");
    $pdo->exec("DELETE FROM items WHERE item_id IN ($i)");
    $pdo->exec("DELETE FROM users WHERE user_id IN ($u)");
    $pdo->exec("DELETE FROM login_attempts WHERE email LIKE '%@stress.test'");
    $pdo->commit();
    out("Removed $ni item(s), $nu user(s), $nc claim(s), $n1 notification(s), $n2 audit row(s).");
    out('Log saved: ' . save_log('stress_cleanup'));
    exit(0);
}

// ======================= HTTP helpers =======================
$BASE = 'http://localhost' . BASE_URL;

function handle(string $url, ?string $cookie, ?array $post = null): CurlHandle
{
    $h = curl_init($url);
    curl_setopt_array($h, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_USERAGENT => 'LFT-StressTest',
        CURLOPT_HTTPHEADER => $cookie ? ['Cookie: LFTSESSID=' . $cookie] : [],
    ]);
    if ($post !== null) {
        curl_setopt($h, CURLOPT_POST, true);
        curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    return $h;
}

function parse(CurlHandle $h, string $raw): array
{
    $hs = curl_getinfo($h, CURLINFO_HEADER_SIZE);
    $headers = substr($raw, 0, $hs);
    preg_match_all('/^Set-Cookie:\s*LFTSESSID=([^;]+)/mi', $headers, $m);
    preg_match('/^Location:\s*(\S+)/mi', $headers, $loc);
    return ['code' => curl_getinfo($h, CURLINFO_HTTP_CODE), 'time' => curl_getinfo($h, CURLINFO_TOTAL_TIME),
            'body' => substr($raw, $hs), 'cookie' => $m[1] ? end($m[1]) : null, 'location' => $loc[1] ?? null,
            'error' => curl_error($h)];
}

function req(string $url, ?string $cookie = null, ?array $post = null): array
{
    $h = handle($url, $cookie, $post);
    $r = parse($h, (string) curl_exec($h));
    curl_close($h);
    return $r;
}

/** Sends all requests at the SAME moment. $reqs = [[url, cookie, post|null], ...] */
function parallel(array $reqs): array
{
    $mh = curl_multi_init();
    $hs = [];
    foreach ($reqs as $k => [$url, $cookie, $post]) {
        $hs[$k] = handle($url, $cookie, $post);
        curl_multi_add_handle($mh, $hs[$k]);
    }
    do {
        $st = curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh, 1.0);
    } while ($running && $st === CURLM_OK);
    $out = [];
    foreach ($hs as $k => $h) {
        $out[$k] = parse($h, (string) curl_multi_getcontent($h));
        curl_multi_remove_handle($mh, $h);
        curl_close($h);
    }
    curl_multi_close($mh);
    return $out;
}

function token(string $body): ?string
{
    return preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $body, $m) ? $m[1] : null;
}

function flash_text(string $body): string
{
    return preg_match('/<div class="alert alert-\w+"[^>]*>(.*?)<\/div>/s', $body, $m) ? html_entity_decode(trim(strip_tags($m[1]))) : '(no message)';
}

function login(string $base, string $email, string $pass): array
{
    $g = req("$base/login.php");
    $tok = token($g['body']);
    if (!$g['cookie'] || !$tok) fail("Could not open $base/login.php (HTTP {$g['code']}). Is Apache running and is BASE_URL right?");
    $p = req("$base/login.php", $g['cookie'], ['csrf_token' => $tok, 'email' => $email, 'password' => $pass]);
    $cookie = $p['cookie'] ?? $g['cookie'];
    $chk = req("$base/admin/users.php", $cookie);   // always has a form, so always a CSRF token
    if ($chk['code'] !== 200 || !($t = token($chk['body']))) fail("Login as $email failed or the account is not an Admin (" . flash_text($p['body']) . ').');
    return [$cookie, $t];
}

$results = [];
function verdict(string $id, string $name, bool $ok, string $detail): void
{
    $GLOBALS['results'][] = [$id, $name, $ok];
    out(($ok ? 'PASS' : 'FAIL') . " – $detail");
}

// ======================= Start =======================
head('Lost & Found Tracker – STRESS TEST  ' . date('Y-m-d H:i:s'));
out("Site: $BASE   Database: " . DB_NAME . '   PHP ' . PHP_VERSION);
[, , , $ni, $nu] = stress_ids($pdo);
if ($ni || $nu) fail("Stress-test data from an earlier run is still there ($ni items, $nu users). Run:  stress_test.bat --cleanup   (or restore the backup) and start again.");
out('This adds test data (users *@stress.test, items "STRESS TEST …"). Make a backup first (backup.bat).');
if (strtolower(ask('Continue? (yes/no)', 'no')) !== 'yes') { out('Cancelled.'); exit(0); }
$email = ask('Admin email', 'admin@test.com');
$pass = ask_hidden('Admin password');
$adminId = (int) $pdo->query('SELECT user_id FROM users WHERE email = ' . $pdo->quote(strtolower($email)) . " AND role = 'Admin' AND is_active = TRUE")->fetchColumn();
if (!$adminId) fail("$email is not an active Admin.");
login($BASE, $email, $pass);   // check the password once before creating data

// ======================= Test data =======================
head('Setting up test data');
$cats = $pdo->query('SELECT category_id FROM categories ORDER BY category_id')->fetchAll(PDO::FETCH_COLUMN);
$hash = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
$pdo->beginTransaction();
$claimants = [];
$insU = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (:n, :e, :h, 'Student')");
for ($k = 1; $k <= CLAIMANTS; $k++) {
    $insU->execute([':n' => sprintf('Stress Claimant %02d', $k), ':e' => sprintf('claimant%02d@stress.test', $k), ':h' => $hash]);
    $claimants[] = (int) $pdo->lastInsertId();
}
$insI = $pdo->prepare("INSERT INTO items (category_id, description, date_found, location_found, logged_by) VALUES (:c, :d, :df, :l, :u)");
$insA = $pdo->prepare("INSERT INTO audit_log (item_id, claim_id, action, performed_by) VALUES (:i, :c, :a, :u)");
$insC = $pdo->prepare("INSERT INTO claims (item_id, claimant_user_id, proof_description, consent_given_at) VALUES (:i, :u, :p, NOW(6))");
$newItem = function (string $desc, string $date) use ($insI, $insA, $cats, $adminId, $pdo): int {
    $insI->execute([':c' => $cats[array_rand($cats)], ':d' => $desc, ':df' => $date, ':l' => 'Stress test location ' . random_int(1, 20), ':u' => $adminId]);
    $id = (int) $pdo->lastInsertId();
    $insA->execute([':i' => $id, ':c' => null, ':a' => 'ITEM_LOGGED', ':u' => $adminId]);
    return $id;
};
$newClaim = function (int $item, int $user) use ($insC, $insA, $pdo): int {
    $insC->execute([':i' => $item, ':u' => $user, ':p' => 'STRESS TEST proof of ownership text for a concurrency test']);
    $id = (int) $pdo->lastInsertId();
    $insA->execute([':i' => $item, ':c' => $id, ':a' => 'CLAIM_SUBMITTED', ':u' => $user]);
    return $id;
};
$today = date('Y-m-d');
$itemA = $newItem('STRESS TEST race item A – 10 claims', $today);
$claimsA = array_map(fn ($u) => $newClaim($itemA, $u), $claimants);
$itemC = $newItem('STRESS TEST race item C – same claim approved 5 times', $today);
$claimC = $newClaim($itemC, $claimants[0]);
$pairs = [];
for ($k = 0; $k < 5; $k++) {
    $b = $newItem("STRESS TEST race item B" . ($k + 1) . ' – approve vs dispose', $today);
    $pairs[] = [$b, $newClaim($b, $claimants[$k + 1])];
}
$words = ['black wallet', 'blue phone', 'keys with red tag', 'student card', 'grey hoodie', 'calculator', 'water bottle', 'umbrella', 'earphones', 'laptop charger'];
for ($k = 1; $k <= BULK_ITEMS; $k++) {
    $newItem("STRESS TEST bulk item $k – " . $words[$k % 10], date('Y-m-d', strtotime('-' . random_int(0, 365) . ' days')));
}
$pdo->commit();
out(sprintf('Created %d claimant accounts, %d race items with %d claims, %d bulk items.', CLAIMANTS, 7, CLAIMANTS + 6, BULK_ITEMS));
out('Items in database now: ' . $pdo->query('SELECT COUNT(*) FROM items')->fetchColumn());

head('Logging in ' . LOAD_SESSIONS . ' admin browser sessions');
$sessions = [];
$t = microtime(true);
for ($k = 0; $k < LOAD_SESSIONS; $k++) $sessions[] = login($BASE, $email, $pass);
out(LOAD_SESSIONS . ' sessions ready in ' . round(microtime(true) - $t, 1) . ' s (each login = real password check).');

$count = fn (string $sql) => (int) $pdo->query($sql)->fetchColumn();
$flashes = function (array $sessIdx) use ($BASE, $sessions): array {
    $m = [];
    foreach ($sessIdx as $s) $m[] = flash_text(req("$BASE/admin/claim_review.php", $sessions[$s][0])['body']);
    return $m;
};

// ======================= ST-1 =======================
head('ST-1  10 admins approve 10 different claims on the same item at the same moment');
$reqs = [];
foreach ($claimsA as $k => $cid) $reqs[] = ["$BASE/admin/claim_review.php", $sessions[$k][0], ['csrf_token' => $sessions[$k][1], 'claim_id' => $cid, 'decision' => 'Approve']];
$r = parallel($reqs);
$codes = array_count_values(array_map(fn ($x) => $x['code'], $r));
$msgs = $flashes(range(0, CLAIMANTS - 1));
$won = count(array_filter($msgs, fn ($m) => (bool) preg_match('/^Claim #\\d+ approved/', $m)));
$lost = count(array_filter($msgs, fn ($m) => str_contains($m, 'already decided') || str_contains($m, 'no longer Found')));
$ap = $count("SELECT COUNT(*) FROM claims WHERE item_id = $itemA AND verification_status = 'Approved'");
$rj = $count("SELECT COUNT(*) FROM claims WHERE item_id = $itemA AND verification_status = 'Rejected'");
$pe = $count("SELECT COUNT(*) FROM claims WHERE item_id = $itemA AND verification_status = 'Pending'");
$st = $pdo->query("SELECT status FROM items WHERE item_id = $itemA")->fetchColumn();
$auA = $count("SELECT COUNT(*) FROM audit_log WHERE item_id = $itemA AND action = 'CLAIM_APPROVED'");
$auR = $count("SELECT COUNT(*) FROM audit_log WHERE item_id = $itemA AND action = 'CLAIM_REJECTED'");
$auS = $count("SELECT COUNT(*) FROM audit_log WHERE item_id = $itemA AND action = 'ITEM_STATUS_CHANGED'");
$no = $count('SELECT COUNT(*) FROM notifications WHERE claim_id IN (' . implode(',', $claimsA) . ')');
out('HTTP responses: ' . json_encode($codes) . "   Messages: $won \"approved\", $lost \"already decided\"");
foreach (array_count_values(array_map(fn ($m) => preg_replace('/#\\d+/', '#n', $m), $msgs)) as $m => $c) out("  {$c}x  $m");
out("Database: Approved $ap · Rejected $rj · Pending $pe · item $st · audit APPROVED $auA / REJECTED $auR / STATUS_CHANGED $auS · notifications $no");
verdict('ST-1', 'Concurrent approvals, one item', $ap === 1 && $rj === CLAIMANTS - 1 && $pe === 0 && $st === 'Claimed'
    && $auA === 1 && $auR === CLAIMANTS - 1 && $auS === 1 && $no === CLAIMANTS && !isset($codes[500]) && $won === 1,
    'exactly one claim approved, all others rejected once, audit and notifications match');

// ======================= ST-2 =======================
head('ST-2  5 admins approve the SAME claim at the same moment');
$reqs = [];
for ($k = 0; $k < 5; $k++) $reqs[] = ["$BASE/admin/claim_review.php", $sessions[10 + $k][0], ['csrf_token' => $sessions[10 + $k][1], 'claim_id' => $claimC, 'decision' => 'Approve']];
$r = parallel($reqs);
$msgs = $flashes(range(10, 14));
$won = count(array_filter($msgs, fn ($m) => (bool) preg_match('/^Claim #\\d+ approved/', $m)));
$auA = $count("SELECT COUNT(*) FROM audit_log WHERE claim_id = $claimC AND action = 'CLAIM_APPROVED'");
$no = $count("SELECT COUNT(*) FROM notifications WHERE claim_id = $claimC");
$cs = $pdo->query("SELECT verification_status FROM claims WHERE claim_id = $claimC")->fetchColumn();
out("Messages: $won \"approved\", " . (5 - $won) . " other.   Database: claim $cs · CLAIM_APPROVED audit rows $auA · notifications $no");
verdict('ST-2', 'Same claim approved 5 times at once', $cs === 'Approved' && $auA === 1 && $no === 1 && $won === 1,
    'approved exactly once – no duplicate audit row or notification');

// ======================= ST-3 =======================
head('ST-3  Approve and Dispose the same item at the same moment (5 items)');
$reqs = [];
foreach ($pairs as $k => [$item, $cid]) {
    $reqs[] = ["$BASE/admin/claim_review.php", $sessions[15 + $k][0], ['csrf_token' => $sessions[15 + $k][1], 'claim_id' => $cid, 'decision' => 'Approve']];
    $reqs[] = ["$BASE/items/manage.php", $sessions[20 + $k][0], ['csrf_token' => $sessions[20 + $k][1], 'item_id' => $item, 'action' => 'dispose']];
}
parallel($reqs);
$ok = true;
$outcomes = [];
foreach ($pairs as [$item, $cid]) {
    $is = $pdo->query("SELECT status FROM items WHERE item_id = $item")->fetchColumn();
    $cs = $pdo->query("SELECT verification_status FROM claims WHERE claim_id = $cid")->fetchColumn();
    $good = ($is === 'Claimed' && $cs === 'Approved') || ($is === 'Disposed' && $cs === 'Rejected');
    $ok = $ok && $good;
    $outcomes[] = "#$item $is/$cs" . ($good ? '' : ' <-- INCONSISTENT');
}
out('Outcomes: ' . implode(', ', $outcomes));
verdict('ST-3', 'Approve vs Dispose race', $ok, 'every item is either Claimed+Approved or Disposed+Rejected – never mixed');

// ======================= ST-4 =======================
head('ST-4  Load: ' . LOAD_SESSIONS . ' users at the same time × ' . LOAD_ROUNDS . ' rounds, ' . $pdo->query('SELECT COUNT(*) FROM items')->fetchColumn() . ' items');
$anyItem = (int) $pdo->query("SELECT MAX(item_id) FROM items WHERE status = 'Found'")->fetchColumn();
$pages = [
    'items/search.php', 'items/search.php?q=wallet', 'items/search.php?q=student+card&category=' . $cats[0],
    'items/search.php?from=' . date('Y-m-d', strtotime('-90 days')) . '&to=' . $today, 'items/search.php?page=20',
    "items/details.php?id=$anyItem", 'items/manage.php?status=Found&page=5', 'notifications_count.php',
    'admin/dashboard.php', 'admin/reports.php?status=Found', 'admin/audit_log.php',
];
$times = [];
$errors = [];
$byPage = [];
$t = microtime(true);
for ($round = 0; $round < LOAD_ROUNDS; $round++) {
    $reqs = [];
    for ($s = 0; $s < LOAD_SESSIONS; $s++) $reqs[] = ["$BASE/" . $pages[($s + $round) % count($pages)], $sessions[$s][0], null];
    foreach (parallel($reqs) as $k => $x) {
        $p = strtok($pages[($k + $round) % count($pages)], '?');
        $times[] = $x['time'];
        $byPage[$p][] = $x['time'];
        if ($x['code'] !== 200) $errors[] = "{$pages[($k + $round) % count($pages)]} -> HTTP {$x['code']} {$x['error']}";
    }
}
$wall = microtime(true) - $t;
sort($times);
$n = count($times);
$p95 = $times[(int) floor(0.95 * ($n - 1))];
out(sprintf('Requests %d · errors %d · average %.3f s · median %.3f s · 95%% %.3f s · slowest %.3f s · %.1f requests/s',
    $n, count($errors), array_sum($times) / $n, $times[intdiv($n, 2)], $p95, end($times), $n / $wall));
out('Slowest per page:');
foreach ($byPage as $p => $ts) out(sprintf('  %-28s %3d requests  avg %.3f s  max %.3f s', $p, count($ts), array_sum($ts) / count($ts), max($ts)));
foreach (array_slice($errors, 0, 5) as $e) out('  error: ' . $e);
verdict('ST-4', 'Load test (NFR4)', !$errors && end($times) < PAGE_LIMIT_S, 'no errors and every page under ' . PAGE_LIMIT_S . ' s with ' . LOAD_SESSIONS . ' users at once');

// ======================= ST-5 =======================
head('ST-5  Heavy pages with ' . $pdo->query('SELECT COUNT(*) FROM items')->fetchColumn() . ' items');
$ok = true;
foreach (['admin/dashboard.php' => 'Dashboard', 'admin/reports.php' => 'Items report (page 1)', 'admin/reports.php?export=csv' => 'Export to Excel - all items',
          'admin/audit_log.php?export=csv' => 'Audit log export - all entries', 'items/search.php?q=STRESS' => 'Search matching 1 000 items'] as $u => $label) {
    $x = req("$BASE/$u", $sessions[0][0]);
    $rows = str_contains($u, 'export') ? max(0, substr_count($x['body'], "\n") - 1) : null;
    $good = $x['code'] === 200 && $x['time'] < PAGE_LIMIT_S;
    $ok = $ok && $good;
    out(sprintf('  %-34s HTTP %d  %.3f s%s  %s', $label, $x['code'], $x['time'], $rows !== null ? "  ($rows rows)" : '', $good ? 'ok' : 'SLOW/ERROR'));
}
verdict('ST-5', 'Heavy pages and exports', $ok, 'all under ' . PAGE_LIMIT_S . ' s');

// ======================= ST-6 =======================
head('ST-6  Security under load');
$guests = parallel(array_fill(0, 20, ["$BASE/login.php", null, null]));
$reqs = [];
foreach ($guests as $g) $reqs[] = ["$BASE/login.php", $g['cookie'], ['csrf_token' => token($g['body']), 'email' => 'locktest@stress.test', 'password' => 'wrong-' . random_int(1, 9999)]];
parallel($reqs);
$lock = $pdo->query("SELECT failed_count, locked_until, locked_until > NOW(6) AS locked FROM login_attempts WHERE email = 'locktest@stress.test'")->fetch();
out('20 wrong passwords at once for locktest@stress.test → failed_count ' . ($lock['failed_count'] ?? 0) . ', locked until ' . ($lock['locked_until'] ?? '-'));
$anon = parallel(array_fill(0, 50, ["$BASE/admin/dashboard.php", null, null]));
$redir = count(array_filter($anon, fn ($x) => $x['code'] === 302 && str_contains((string) $x['location'], 'login.php')));
out("50 guests open admin/dashboard.php at once → $redir sent to Login, " . (50 - $redir) . ' other');
verdict('ST-6', 'Lockout and access control under load', $lock && (int) $lock['locked'] === 1 && $redir === 50, 'account locked; every guest redirected to Login');

// ======================= Summary =======================
head('SUMMARY');
$pass = 0;
foreach ($results as [$id, $name, $ok]) { out(sprintf('%-5s %-42s %s', $id, $name, $ok ? 'PASS' : 'FAIL')); $pass += $ok ? 1 : 0; }
out('');
out("RESULT: $pass/" . count($results) . ' passed.');
out('Test data is still in the database. Next: run recover.bat to restore the backup (this also proves');
out('recovery), or run  stress_test.bat --cleanup  to delete only the stress-test data.');
out('Log saved: ' . save_log('stress_test'));
exit($pass === count($results) ? 0 : 2);
