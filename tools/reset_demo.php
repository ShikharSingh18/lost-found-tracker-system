<?php
/*
 * QA tool: RESET TO DEMO DATA (for the lecturer demo).
 * Run: tools\reset_demo.bat on the DEMO computer only.
 *
 * 1. Makes a backup first (backup.php) so nothing is lost.
 * 2. Empties every table except categories, and deletes uploaded photos.
 * 3. Loads a small, realistic demo story: 5 accounts, 11 items, claims in every state
 *    (pending, approved + collected, rejected), a disposed item, matching audit trail
 *    and notifications – so every page of the site has something to show.
 * All names are invented. The demo password is printed at the end.
 */
require __DIR__ . '/common.php';

const DEMO_PASSWORD = 'LostFound#2026';

head('Lost & Found Tracker – RESET TO DEMO DATA  ' . date('Y-m-d H:i:s'));
out('This DELETES all users, items, claims, notifications, logs and photos in "' . DB_NAME . '"');
out('and replaces them with demo data. A backup is made first.');
out('Log out of the site in every browser first.');
if (ask('Type RESET to continue') !== 'RESET') { out('Cancelled. Nothing was changed.'); exit(0); }

// 1. Backup first
out('');
out('Step 1/3 – backup');
[$code, $text] = run([PHP_BINARY, __DIR__ . '/backup.php']);
foreach (explode("\n", $text) as $line) if (str_contains($line, 'Backup folder') || str_contains($line, 'RESULT')) out('  ' . trim($line));
if ($code !== 0) fail('The backup failed, so nothing was reset. Check that MySQL is running.');

// 2. Empty
out('Step 2/3 – emptying tables and photos');
$pdo = pdo_for(DB_NAME);
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['notifications', 'audit_log', 'user_admin_log', 'item_photos', 'claims', 'items', 'login_attempts', 'users'] as $t) {
    $pdo->exec("TRUNCATE TABLE `$t`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
$photoDir = SITE_DIR . '/uploads/item_photos';
$removed = 0;
foreach (is_dir($photoDir) ? scandir($photoDir) : [] as $f) {
    if ($f !== '.' && $f !== '..' && $f !== '.htaccess' && is_file("$photoDir/$f")) { unlink("$photoDir/$f"); $removed++; }
}
out("  tables emptied (categories kept), $removed photo file(s) deleted");

// 3. Demo data
out('Step 3/3 – loading demo data');
$at = fn (int $daysAgo, string $time) => date('Y-m-d', strtotime("-$daysAgo days")) . " $time";
$cat = array_column($pdo->query('SELECT category_id, category_name FROM categories')->fetchAll(), 'category_id', 'category_name');
$need = ['Keys', 'Wallet', 'Phone', 'ID Card', 'Clothing', 'Stationery', 'Other'];
foreach ($need as $n) if (!isset($cat[$n])) fail("Category \"$n\" is missing – run the schema's INSERT INTO categories first.");

$pdo->beginTransaction();
$hash = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);
$u = [];
$insU = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role, created_at) VALUES (:n, :e, :h, :r, :c)');
foreach ([
    ['admin',  'Thandi Mokoena',     'admin@demo.ac.za',   'Admin',   30],
    ['staff',  'Sipho Dlamini',      'staff@demo.ac.za',   'Staff',   30],
    ['staff2', 'Naledi Khumalo',     'naledi@demo.ac.za',  'Staff',   20],
    ['ayesha', 'Ayesha Patel',       'ayesha@demo.ac.za',  'Student', 18],
    ['liam',   'Liam van der Merwe', 'liam@demo.ac.za',    'Student', 16],
] as [$key, $name, $email, $role, $days]) {
    $insU->execute([':n' => $name, ':e' => $email, ':h' => $hash, ':r' => $role, ':c' => $at($days, '08:00:00')]);
    $u[$key] = (int) $pdo->lastInsertId();
}
$logU = $pdo->prepare("INSERT INTO user_admin_log (target_user_id, action, detail, performed_by, occurred_at) VALUES (:t, 'USER_CREATED', :d, :by, :at)");
foreach (['staff' => 'Staff', 'staff2' => 'Staff'] as $k => $r) $logU->execute([':t' => $u[$k], ':d' => $r, ':by' => $u['admin'], ':at' => $at($k === 'staff' ? 30 : 20, '08:30:00')]);

$insI = $pdo->prepare('INSERT INTO items (category_id, description, date_found, location_found, status, logged_by, created_at, updated_at) VALUES (:c, :d, :df, :l, :s, :by, :at, :at2)');
$audit = $pdo->prepare('INSERT INTO audit_log (item_id, claim_id, action, performed_by, occurred_at) VALUES (:i, :c, :a, :by, :at)');
$a = fn (?int $i, ?int $c, string $act, int $by, string $when) => $audit->execute([':i' => $i, ':c' => $c, ':a' => $act, ':by' => $by, ':at' => $when]);
$item = function (string $c, string $desc, int $days, string $loc, string $by, string $status = 'Found') use ($insI, $pdo, $cat, $u, $at, $a): int {
    $when = $at($days, '10:15:00');
    $insI->execute([':c' => $cat[$c], ':d' => $desc, ':df' => date('Y-m-d', strtotime("-$days days")), ':l' => $loc,
                    ':s' => $status, ':by' => $u[$by], ':at' => $when, ':at2' => $when]);
    $id = (int) $pdo->lastInsertId();
    $a($id, null, 'ITEM_LOGGED', $u[$by], $when);
    return $id;
};
$insC = $pdo->prepare('INSERT INTO claims (item_id, claimant_user_id, proof_description, consent_given_at, verification_status, date_submitted, verified_by, date_verified) VALUES (:i, :u, :p, :s, :st, :s2, :vb, :dv)');
$claim = function (int $itemId, string $who, string $proof, string $sub, string $status = 'Pending', ?string $dec = null) use ($insC, $pdo, $u, $a): int {
    $insC->execute([':i' => $itemId, ':u' => $u[$who], ':p' => $proof, ':s' => $sub, ':st' => $status, ':s2' => $sub,
                    ':vb' => $status === 'Pending' ? null : $u['admin'], ':dv' => $status === 'Pending' ? null : $dec]);
    $id = (int) $pdo->lastInsertId();
    $a($itemId, $id, 'CLAIM_SUBMITTED', $u[$who], $sub);
    return $id;
};
$note = $pdo->prepare('INSERT INTO notifications (user_id, claim_id, message, is_read, created_at) VALUES (:u, :c, :m, :r, :at)');
$n = fn (int $user, ?int $c, string $m, bool $read, string $when) => $note->execute([':u' => $user, ':c' => $c, ':m' => $m, ':r' => (int) $read, ':at' => $when]);

// Items (oldest first)
$umbrella = $item('Other', 'Black folding umbrella with a wooden handle', 40, 'Reception desk', 'staff');
$earph    = $item('Other', 'White wired earphones in a small round case', 15, 'Computer lab 2', 'staff');
$keys     = $item('Keys', 'Set of 3 keys on a red lanyard with a small torch keyring', 12, 'Cafeteria', 'staff', 'Claimed');
$wallet   = $item('Wallet', 'Brown leather wallet, no cash inside, has a gym membership card', 9, 'Sports field – near the benches', 'staff2');
$calc     = $item('Stationery', 'Casio scientific calculator in a black slide cover', 6, 'Exam hall', 'staff');
$idcard   = $item('ID Card', 'Student card found at the entrance turnstile (details hidden)', 5, 'Main entrance', 'staff2');
$charger  = $item('Other', 'Laptop charger, 65 W, USB-C, grey cable', 4, 'Library – ground floor', 'staff');
$hoodie   = $item('Clothing', 'Grey hoodie, size M, college logo on the front', 3, 'Lecture room B12', 'staff2');
$phone    = $item('Phone', 'Black Samsung phone with a cracked screen protector and a blue silicone cover', 2, 'Library – second floor', 'staff');
$bottle   = $item('Other', 'Blue 750 ml water bottle covered in stickers', 1, 'Parking area', 'staff2');
$carkey   = $item('Keys', 'Car key with a silver ring and a small leather tag', 0, 'Parking area', 'staff2');

// Keys: claimed by Liam, approved, collected
$c1 = $claim($keys, 'liam', 'Three keys: a gold house key, a silver gate key and a small padlock key. The torch keyring has a broken clip.', $at(11, '09:05:00'), 'Approved', $at(10, '14:20:00'));
$n($u['admin'], $c1, "A new claim was submitted for item #$keys (Keys).", true, $at(11, '09:05:00'));
$a($keys, $c1, 'CLAIM_APPROVED', $u['admin'], $at(10, '14:20:00'));
$a($keys, null, 'ITEM_STATUS_CHANGED', $u['admin'], $at(10, '14:20:00'));
$n($u['liam'], $c1, "Your claim for Keys (item #$keys) was approved. Please collect it from the lost-and-found office.", true, $at(10, '14:20:00'));
$a($keys, $c1, 'ITEM_RELEASED', $u['admin'], $at(9, '11:40:00'));
$n($u['liam'], $c1, "You collected your Keys (item #$keys). Thank you for using Lost & Found Tracker.", true, $at(9, '11:40:00'));

// Wallet: claim by Ayesha rejected (details didn't match) – still Found
$c2 = $claim($wallet, 'ayesha', 'Black wallet with my bank card and a library card inside.', $at(8, '16:30:00'), 'Rejected', $at(7, '10:05:00'));
$n($u['admin'], $c2, "A new claim was submitted for item #$wallet (Wallet).", true, $at(8, '16:30:00'));
$a($wallet, $c2, 'CLAIM_REJECTED', $u['admin'], $at(7, '10:05:00'));
$n($u['ayesha'], $c2, "Your claim for Wallet (item #$wallet) was not approved.", false, $at(7, '10:05:00'));

// Phone: pending claim by Ayesha – for the live demo (approve it in front of the lecturer)
$c3 = $claim($phone, 'ayesha', 'My phone has a photo of my dog as the lock screen, and a small sticker of a cat inside the blue cover.', $at(1, '12:10:00'));
$n($u['admin'], $c3, "A new claim was submitted for item #$phone (Phone).", false, $at(1, '12:10:00'));

// Umbrella: disposed after nobody claimed it
$pdo->prepare("UPDATE items SET status = 'Disposed', updated_at = :at WHERE item_id = :i")->execute([':at' => $at(2, '15:00:00'), ':i' => $umbrella]);
$a($umbrella, null, 'ITEM_DISPOSED', $u['admin'], $at(2, '15:00:00'));

$pdo->commit();

$count = fn (string $t) => (int) $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
out(sprintf('  %d users, %d items, %d claims, %d notifications, %d audit entries', $count('users'), $count('items'), $count('claims'), $count('notifications'), $count('audit_log')));
out('');
out('Demo accounts (all use the password  ' . DEMO_PASSWORD . ' ):');
out('  Admin   : admin@demo.ac.za   (Thandi Mokoena)');
out('  Staff   : staff@demo.ac.za   (Sipho Dlamini)   ·  naledi@demo.ac.za (Naledi Khumalo)');
out('  Student : ayesha@demo.ac.za  (Ayesha Patel – 1 pending, 1 rejected claim)');
out('  Student : liam@demo.ac.za    (Liam van der Merwe – collected his keys)');
out('Story: claim #' . $c3 . ' on the Phone (item #' . $phone . ') is waiting for a decision – approve it live in the demo.');
out('Photos: none yet – add 1–2 photos live with Log Found Item or Manage Items → Edit.');
out('');
out('RESULT: DEMO DATA READY. Log out in every browser and log in again with a demo account.');
out('Log saved: ' . save_log('reset_demo'));
