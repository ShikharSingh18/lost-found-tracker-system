</main>
<footer class="site-footer"><div class="inner"><span>Lost &amp; Found Tracker &middot; IT Project 700 &middot; Group 11</span><span>Report found items promptly. Ownership is verified before release. · <a href="<?= e(url('privacy.php')) ?>">Privacy notice</a></span></div></footer>
<?php foreach ($extraJs ?? [] as $js): ?>
<script src="<?= e(asset_url($js)) ?>"></script>
<?php endforeach; ?>
<?php if (current_user() !== null): /* Module 6: live bell count for logged-in users */ ?>
<script src="<?= e(asset_url('assets/js/notifications.js')) ?>" data-count-url="<?= e(url('notifications_count.php')) ?>" data-inbox-url="<?= e(url('notifications.php')) ?>"></script>
<?php endif; ?>
</body>
</html>
