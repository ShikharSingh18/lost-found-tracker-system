/*
 * Home page: live search (everyone) and the "recently found" spotlight (Staff and Admin).
 * Plain JavaScript, no libraries. Results are built with textContent, so item text can never run as HTML.
 */
(function () {
  'use strict';

  var ICONS = { 'Keys': '\uD83D\uDD11', 'Wallet': '\uD83D\uDC5B', 'Phone': '\uD83D\uDCF1', 'ID Card': '\uD83E\uDEAA',
                'Clothing': '\uD83E\uDDE5', 'Stationery': '\u270F\uFE0F', 'Other': '\uD83D\uDCE6' };

  function node(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }
  function icon(category) { return ICONS[category] || '\uD83D\uDCE6'; }
  function thumb(item, photoUrl, cls) {
    var box = node('span', cls || 'hs-thumb');
    box.setAttribute('aria-hidden', 'true');
    if (item.thumb && photoUrl) {
      var img = document.createElement('img');
      img.src = photoUrl + '?id=' + item.thumb;
      img.alt = '';
      img.loading = 'lazy';
      box.appendChild(img);
    } else {
      box.textContent = icon(item.category);
    }
    return box;
  }
  function niceDate(ymd) {
    var d = new Date(ymd + 'T00:00:00');
    return isNaN(d) ? ymd : d.toLocaleDateString('en-ZA', { day: 'numeric', month: 'short', year: 'numeric' });
  }
  function timeAgo(iso) {
    var secs = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 1000));
    if (isNaN(secs)) return '';
    if (secs < 60) return 'just now';
    var m = Math.round(secs / 60);
    if (m < 60) return m + (m === 1 ? ' minute ago' : ' minutes ago');
    var h = Math.round(m / 60);
    if (h < 24) return h + (h === 1 ? ' hour ago' : ' hours ago');
    var d = Math.round(h / 24);
    if (d < 14) return d + (d === 1 ? ' day ago' : ' days ago');
    return new Date(iso).toLocaleDateString('en-ZA', { day: 'numeric', month: 'short' });
  }
  function getJson(url, signal) {
    return fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' }, signal: signal }).then(function (r) {
      var type = r.headers.get('Content-Type') || '';
      if (!r.ok || type.indexOf('json') === -1) throw new Error('bad response');
      return r.json();
    });
  }

  /* ------------------------------------------------------------ live search */
  function initSearch() {
    var root = document.getElementById('home-search');
    if (!root) return;
    var input = document.getElementById('home-q');
    var out = document.getElementById('home-results');
    var searchUrl = root.getAttribute('data-search-url');
    var detailsUrl = root.getAttribute('data-details-url');
    var photoUrl = root.getAttribute('data-photo-url');
    var allUrl = root.getAttribute('data-all-url');
    var timer = null, controller = null, seq = 0;

    function hide() { out.hidden = true; out.textContent = ''; }
    function message(text) { out.textContent = ''; out.appendChild(node('p', 'hs-msg', text)); out.hidden = false; }

    function render(data, q) {
      out.textContent = '';
      if (!data.items.length) {
        message('No items match \u201C' + q + '\u201D. Try fewer words, or a different spelling.');
        return;
      }
      out.appendChild(node('p', 'hs-count small muted', data.total + (data.total === 1 ? ' item matches' : ' items match') + ' \u201C' + q + '\u201D'));
      var list = node('ul', 'hs-list');
      data.items.forEach(function (it) {
        var li = document.createElement('li');
        var a = node('a', 'hs-item');
        a.href = detailsUrl + '?id=' + it.id;
        a.appendChild(thumb(it, photoUrl));
        var body = node('span', 'hs-body');
        var title = node('strong', 'hs-name', it.category);
        if (it.status && it.status !== 'Found') title.appendChild(node('span', 'pill pill-' + it.status.toLowerCase(), it.status));
        body.appendChild(title);
        body.appendChild(node('span', 'hs-text', it.text));
        body.appendChild(node('span', 'hs-meta small muted', niceDate(it.date) + ' \u00B7 ' + it.location));
        a.appendChild(body);
        a.appendChild(node('span', 'hs-go', '\u203A'));
        li.appendChild(a);
        list.appendChild(li);
      });
      out.appendChild(list);
      if (data.total > data.items.length) {
        var more = node('a', 'hs-all small', 'See all ' + data.total + ' results \u2192');
        more.href = allUrl + '?q=' + encodeURIComponent(q);
        out.appendChild(more);
      }
      out.hidden = false;
    }

    function run(q) {
      if (controller) controller.abort();
      controller = typeof AbortController === 'function' ? new AbortController() : null;
      var mine = ++seq;
      out.classList.add('is-loading');
      getJson(searchUrl + '?q=' + encodeURIComponent(q), controller ? controller.signal : undefined)
        .then(function (data) { if (mine === seq) { out.classList.remove('is-loading'); render(data, q); } })
        .catch(function (err) {
          if (err && err.name === 'AbortError') return;
          if (mine === seq) { out.classList.remove('is-loading'); message('Search is not available right now. Press Search, or reload the page and log in again.'); }
        });
    }

    input.addEventListener('input', function () {
      clearTimeout(timer);
      var q = input.value.trim();
      if (q.length < 2) { seq++; if (controller) controller.abort(); hide(); return; }
      timer = setTimeout(function () { run(q); }, 250);
    });

    // keyboard: Down from the box into the results, Up/Down between them, Esc clears
    root.addEventListener('keydown', function (e) {
      var links = Array.prototype.slice.call(out.querySelectorAll('a.hs-item'));
      if (e.key === 'Escape') { input.value = ''; hide(); input.focus(); return; }
      if (!links.length || (e.key !== 'ArrowDown' && e.key !== 'ArrowUp')) return;
      var i = links.indexOf(document.activeElement);
      if (document.activeElement === input) { if (e.key === 'ArrowDown') { links[0].focus(); e.preventDefault(); } return; }
      if (i === -1) return;
      e.preventDefault();
      if (e.key === 'ArrowDown') { (links[i + 1] || links[i]).focus(); }
      else if (i === 0) { input.focus(); } else { links[i - 1].focus(); }
    });
  }

  /* ------------------------------------------------------- recently found */
  function initRecent() {
    var panel = document.getElementById('recent-panel');
    if (!panel) return;
    var stage = panel.querySelector('.rc-stage');
    var dots = panel.querySelector('.rc-dots');
    var empty = panel.querySelector('.rc-empty');
    var url = panel.getAttribute('data-url');
    var detailsUrl = panel.getAttribute('data-details-url');
    var photoUrl = panel.getAttribute('data-photo-url');
    var items = [], index = 0, rotate = null, paused = false;
    var still = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

    function show(i) {
      if (!items.length) return;
      index = (i + items.length) % items.length;
      var it = items[index];
      var card = node('a', 'rc-card');
      card.href = detailsUrl + '?id=' + it.id;
      card.appendChild(thumb(it, photoUrl, 'rc-thumb'));
      var body = node('span', 'rc-body');
      body.appendChild(node('strong', 'rc-title', it.category + ' found at ' + it.location));
      body.appendChild(node('span', 'rc-text', it.text));
      var when = node('span', 'rc-when small muted', 'Logged ' + timeAgo(it.logged));
      when.setAttribute('data-logged', it.logged);
      body.appendChild(when);
      card.appendChild(body);
      stage.textContent = '';
      stage.appendChild(card);
      requestAnimationFrame(function () { card.classList.add('in'); });
      Array.prototype.forEach.call(dots.children, function (d, k) {
        d.setAttribute('aria-current', k === index ? 'true' : 'false');
        d.classList.toggle('on', k === index);
      });
    }

    function buildDots() {
      dots.textContent = '';
      items.forEach(function (it, k) {
        var b = node('button', 'rc-dot');
        b.type = 'button';
        b.setAttribute('aria-label', 'Show ' + it.category + ' found at ' + it.location);
        b.addEventListener('click', function () { show(k); });
        dots.appendChild(b);
      });
    }

    function start() {
      clearInterval(rotate);
      if (still || items.length < 2) return;
      rotate = setInterval(function () { if (!paused && !document.hidden) show(index + 1); }, 5000);
    }

    function load() {
      getJson(url).then(function (data) {
        var firstId = items.length ? items[index].id : null;
        items = data.items || [];
        empty.hidden = items.length > 0;
        dots.hidden = items.length < 2;
        buildDots();
        var keep = firstId == null ? -1 : items.map(function (x) { return x.id; }).indexOf(firstId);
        show(keep >= 0 ? keep : 0);
        start();
      }).catch(function () {
        if (!items.length) { empty.textContent = 'Recent items are not available right now.'; empty.hidden = false; }
      });
    }

    panel.addEventListener('mouseenter', function () { paused = true; });
    panel.addEventListener('mouseleave', function () { paused = false; });
    panel.addEventListener('focusin', function () { paused = true; });
    panel.addEventListener('focusout', function () { paused = false; });
    setInterval(function () { if (!document.hidden) load(); }, 60000);
    load();
  }

  function init() { initSearch(); initRecent(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
