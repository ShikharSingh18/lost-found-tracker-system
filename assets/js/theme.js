/* Light / dark switch.
   Loaded in <head> so the theme is on <html> before the page is drawn (no white flash). */
(function () {
  var root = document.documentElement;

  var theme = null;
  try { theme = localStorage.getItem('lft-theme'); } catch (e) { /* storage blocked, fall through */ }
  if (theme !== 'dark' && theme !== 'light') {
    // nothing saved yet: follow the computer's setting
    theme = window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  root.setAttribute('data-theme', theme);

  document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('theme-toggle');
    if (!btn) return;
    var icon = btn.querySelector('span');

    function paint() {
      var dark = root.getAttribute('data-theme') === 'dark';
      btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
      btn.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
      icon.textContent = dark ? '\u2600\uFE0F' : '\uD83C\uDF19';
    }

    btn.addEventListener('click', function () {
      var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem('lft-theme', next); } catch (e) { /* not saved, still switches for this page */ }
      paint();
    });

    paint();
  });
})();
