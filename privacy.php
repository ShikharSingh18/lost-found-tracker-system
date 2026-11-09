<?php
/*
 * Module 10: Privacy notice (POPIA s18 notification / GDPR Art. 13). Public: no login needed,
 * linked from the footer of every page, the Register page and the claim consent box.
 * Wording is plain-language; the group should check it against the college's own policy.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/privacy.php';

$pageTitle = 'Privacy notice';
$activeNav = 'privacy';
$extraCss  = ['assets/css/photos.css'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><h1>Privacy notice</h1><p class="muted">How the Lost &amp; Found Tracker uses your personal information (POPIA and GDPR).</p></div>

<article class="card prose">
<h2>Who is responsible</h2>
<p>The Lost &amp; Found Tracker is run by the campus Lost &amp; Found office. Questions or requests about your information go to <?= e(PRIVACY_CONTACT) ?>.</p>

<h2>What we collect and why</h2>
<div class="table-wrap"><table><thead><tr><th>Information</th><th>Why</th><th>Who can see it</th></tr></thead><tbody>
<tr><td data-label="Information">Your name, email, role and password (stored only as a secure hash)</td><td data-label="Why">To give you an account and check who is using the system</td><td data-label="Who can see it">You and administrators</td></tr>
<tr><td data-label="Information">Claims: the item, your proof of ownership, dates, the decision</td><td data-label="Why">To decide whether the item is yours before it is handed over</td><td data-label="Who can see it">You and administrators. Proof text is never included in reports or exports.</td></tr>
<tr><td data-label="Information">Your consent time on each claim</td><td data-label="Why">To show that you agreed before your claim was stored</td><td data-label="Who can see it">Administrators</td></tr>
<tr><td data-label="Information">Notifications to you</td><td data-label="Why">To tell you what happened to your claim</td><td data-label="Who can see it">Only you</td></tr>
<tr><td data-label="Information">Failed login attempts (email and time)</td><td data-label="Why">To lock an account for 15 minutes after 5 wrong passwords</td><td data-label="Who can see it">Administrators</td></tr>
<tr><td data-label="Information">Activity records (what was done, by whom, when)</td><td data-label="Why">An audit trail so every item and claim decision can be checked</td><td data-label="Who can see it">Administrators</td></tr>
</tbody></table></div>
<p><b>People who hand in found items are not recorded.</b> They don't need an account and their name is never stored.</p>
<p>Photos of found items are cleaned when uploaded: location (GPS), camera and date information is removed. Staff are asked not to photograph cards or documents in a way that shows personal details.</p>

<h2>Legal basis</h2>
<p>Your account and activity records are needed to run the service the college offers (POPIA s11(1)(b)/(f), GDPR Art. 6(1)(b)/(f)). Claims are stored with your consent, which you give with the tick box on the claim form (POPIA s11(1)(a), GDPR Art. 6(1)(a)). We only collect what is needed for these purposes (minimality).</p>

<h2>How long we keep it</h2>
<ul>
<li>Proof-of-ownership text is removed <b><?= RETENTION_MONTHS ?> months</b> after the claim was decided. The fact that a claim was made, its result and dates stay in the audit trail.</li>
<li>Notifications you have read are deleted after <?= RETENTION_MONTHS ?> months.</li>
<li>Your account stays until you ask for it to be deleted, or the college closes it.</li>
</ul>

<h2>Where it is stored and who it is shared with</h2>
<p>Everything is stored on the college's own computer (this system runs locally, not in the cloud). It is not sold or shared with anyone outside the Lost &amp; Found office, and no email or marketing is sent. The site only uses one cookie, which keeps you logged in; there are no tracking or advertising cookies.</p>

<h2>Your rights</h2>
<ul>
<li><b>See and download your information</b> – <?php if (current_user()): ?><a href="<?= e(url('my_data.php')) ?>">My data &amp; privacy</a><?php else: ?>log in and open My account → My data &amp; privacy<?php endif; ?> (POPIA s23, GDPR Art. 15 and 20).</li>
<li><b>Correct it</b> – ask an administrator to fix your name or email (POPIA s24, GDPR Art. 16).</li>
<li><b>Have it deleted</b> – ask from My data &amp; privacy. An administrator removes your name, email and proof text. Records that other people's items and claims depend on stay, but without your details (POPIA s24, GDPR Art. 17).</li>
<li><b>Withdraw consent / object</b> – contact <?= e(PRIVACY_CONTACT) ?>. Withdrawing consent does not affect decisions already made.</li>
<li><b>Complain</b> – to the Information Regulator (South Africa), inforegulator.org.za.</li>
</ul>
<p class="small muted" style="margin-bottom:0">Security: passwords are hashed, every page checks your role, forms are protected against forgery, and accounts lock after repeated wrong passwords.</p>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>
