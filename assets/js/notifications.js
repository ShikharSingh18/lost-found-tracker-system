/*
 * Module 6: keeps the Notifications bell up to date without reloading the page
 * (group note 7: web-app based notifications in the browser).
 *
 * - Every 30 seconds (only while the tab is visible) it asks notifications_count.php
 *   for the number of unread notifications and updates the red badge in the menu
 *   and the tab title, e.g. "(2) Search found items | Lost & Found Tracker".
 * - If the user has turned on browser alerts (button on the Notifications page),
 *   a pop-up system notification is shown when the count goes up.
 * Loaded by includes/footer.php for logged-in users only.
 */
(function () {
  var script = document.currentScript;
  var countUrl = script.dataset.countUrl;
  var inboxUrl = script.dataset.inboxUrl;
  var EVERY_MS = 30000;
  var baseTitle = document.title.replace(/^\(\d+\)\s*/, '');
  var link = document.querySelector('.nav a[href^="' + inboxUrl + '"]');
  var last = null;
  var timer = null;

  function setBadge(n) {
    if (!link) return;
    var badge = link.querySelector('.badge-dot');
    if (n > 0) {
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'badge-dot';
        link.appendChild(badge);
      }
      badge.textContent = n;
      badge.setAttribute('aria-label', n + ' unread');
    } else if (badge) {
      badge.remove();
    }
    document.title = (n > 0 ? '(' + n + ') ' : '') + baseTitle;
  }

  function alertUser(n, latest) {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    var note = new Notification('Lost & Found Tracker', {
      body: latest || ('You have ' + n + ' unread notification' + (n === 1 ? '' : 's') + '.'),
      tag: 'lft-unread'
    });
    note.onclick = function () { window.focus(); window.location.href = inboxUrl; };
  }

  function check() {
    fetch(countUrl, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) {
        if (r.status === 401) { stop(); return null; }
        return r.ok ? r.json() : null;
      })
      .then(function (data) {
        if (!data || typeof data.unread !== 'number') return;
        if (last !== null && data.unread > last) alertUser(data.unread, data.latest);
        last = data.unread;
        setBadge(data.unread);
      })
      .catch(function () { /* offline or server stopped: try again next time */ });
  }

  function start() { if (!timer) { check(); timer = setInterval(check, EVERY_MS); } }
  function stop() { clearInterval(timer); timer = null; }

  document.addEventListener('visibilitychange', function () {
    document.hidden ? stop() : start();
  });
  if (!document.hidden) start();

  // "Turn on browser alerts" button (only exists on the Notifications page).
  var btn = document.getElementById('browser-alerts');
  var state = document.getElementById('browser-alerts-state');
  function showState() {
    if (!btn) return;
    if (!('Notification' in window)) { state.textContent = 'This browser does not support pop-up alerts.'; return; }
    // style.display, not "hidden": the .btn style would override the hidden attribute.
    if (Notification.permission === 'granted') { btn.style.display = 'none'; state.textContent = 'Browser alerts are on.'; }
    else if (Notification.permission === 'denied') { btn.style.display = 'none'; state.textContent = 'Browser alerts are blocked in your browser settings.'; }
    else { btn.style.display = ''; state.textContent = ''; }
  }
  if (btn) {
    btn.addEventListener('click', function () {
      Notification.requestPermission().then(showState);
    });
    showState();
  }
})();
