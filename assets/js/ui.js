/*
 * Small site-wide enhancements (no libraries):
 *  1. Show/hide password: every password box gets an eye button.
 *  2. Filter panels (<details class="filters">) start collapsed unless a filter is in use
 *     (the address has filter values) or the panel holds an error message.
 */
(function () {
  'use strict';

  var EYE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
  var EYE_OFF = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a19.8 19.8 0 0 1 5.06-5.94"/><path d="M9.9 4.24A10.9 10.9 0 0 1 12 4c7 0 11 7 11 7a19.7 19.7 0 0 1-3.17 4.19"/><path d="M14.12 14.12A3 3 0 1 1 9.88 9.88"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

  var css = document.createElement('style');
  css.textContent =
    '.pw-wrap{position:relative;display:block}' +
    '.pw-wrap input{padding-right:3rem}' +
    '.pw-toggle{position:absolute;top:50%;right:.35rem;transform:translateY(-50%);width:2.4rem;height:2.4rem;' +
    'display:grid;place-items:center;padding:0;border:0;border-radius:8px;background:transparent;' +
    'color:var(--muted,#5d6b7e);cursor:pointer}' +
    '.pw-toggle:hover{color:var(--ink,#16202e);background:rgba(127,127,127,.15)}' +
    '.pw-toggle svg{width:20px;height:20px;pointer-events:none}';
  document.head.appendChild(css);

  function setupPasswords() {
    var boxes = document.querySelectorAll('input[type="password"]');
    Array.prototype.forEach.call(boxes, function (input) {
      if (input.getAttribute('data-pw-toggle') === 'off' || (input.parentNode && input.parentNode.classList.contains('pw-wrap'))) return;

      var wrap = document.createElement('span');
      wrap.className = 'pw-wrap';
      input.parentNode.insertBefore(wrap, input);
      wrap.appendChild(input);

      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'pw-toggle';
      btn.innerHTML = EYE;
      function label(show) {
        var text = show ? 'Hide password' : 'Show password';
        btn.setAttribute('aria-label', text);
        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        btn.title = text;
        btn.innerHTML = show ? EYE_OFF : EYE;
      }
      label(false);
      btn.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        label(show);
        input.focus();
      });
      wrap.appendChild(btn);

      // never leave a password visible after submitting or coming back with the Back button
      if (input.form) input.form.addEventListener('submit', function () { input.type = 'password'; label(false); });
    });
    window.addEventListener('pageshow', function () {
      Array.prototype.forEach.call(document.querySelectorAll('.pw-wrap input'), function (i) { i.type = 'password'; });
      Array.prototype.forEach.call(document.querySelectorAll('.pw-toggle'), function (b) { b.innerHTML = EYE; b.setAttribute('aria-pressed', 'false'); b.setAttribute('aria-label', 'Show password'); b.title = 'Show password'; });
    });
  }

  function collapseFilters() {
    var params = new URLSearchParams(window.location.search);
    ['page', 'log', 'type'].forEach(function (k) { params.delete(k); });
    var inUse = false;
    params.forEach(function (v) { if (v !== '') inUse = true; });
    if (inUse) return;
    Array.prototype.forEach.call(document.querySelectorAll('details.filters[open]'), function (d) {
      if (d.querySelector('.alert')) return;
      d.removeAttribute('open');
    });
  }

  function init() { setupPasswords(); collapseFilters(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
