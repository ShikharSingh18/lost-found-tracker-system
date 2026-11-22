/*
 * Admin charts: small dependency-free SVG chart module (no CDN, no libraries).
 * Mount points: <div class="chart-mount" data-chart='{...json...}'> (built by includes/chart_data.php).
 * Types: bar (grouped), stacked, hbar, line, area, pie, doughnut. PNG download + print button.
 */
(function () {
  'use strict';
  var NS = 'http://www.w3.org/2000/svg';
  var FONT = 'system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif';
  var INK = '#5d6b7e', GRID = '#e6ebf2', TEXT = '#16202e';
  var PALETTE = ['#2f5bea', '#157347', '#e0a100', '#b3261e', '#7a4bd1', '#0b9fb3', '#e26d1f', '#6b7a90'];
  var NAMES = { bar: 'Bar', stacked: 'Stacked bar', hbar: 'Horizontal bar', line: 'Line', area: 'Area', pie: 'Pie', doughnut: 'Doughnut' };
  var W = 640;

  function el(name, attrs, text) {
    var n = document.createElementNS(NS, name);
    for (var k in attrs || {}) n.setAttribute(k, attrs[k]);
    if (text != null) n.textContent = text;
    return n;
  }
  function h(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }
  function T(x, y, s, o) {
    o = o || {};
    var t = el('text', { x: x, y: y, fill: o.fill || INK, 'font-size': o.size || 11, 'font-family': FONT, 'text-anchor': o.anchor || 'start' }, s);
    if (o.weight) t.setAttribute('font-weight', o.weight);
    if (o.rot) t.setAttribute('transform', 'rotate(' + o.rot + ' ' + x + ' ' + y + ')');
    return t;
  }
  function fmt(v) { return Number(v).toLocaleString('en-ZA'); }
  function trunc(s, n) { s = String(s); return s.length > n ? s.slice(0, n - 1) + '\u2026' : s; }
  function tipOn(node, text) { node.setAttribute('data-tip', text); return node; }

  function scale(max) {
    if (!(max > 0)) return { top: 1, step: 1 };
    var raw = max / 5, p = Math.pow(10, Math.floor(Math.log10(raw))), f = raw / p;
    var m = f <= 1 ? 1 : f <= 2 ? 2 : f <= 5 ? 5 : 10;
    var step = Math.max(1, m * p);
    return { top: Math.ceil(max / step) * step, step: step };
  }
  function seriesColor(cfg, si) { return cfg.series[si].color || PALETTE[si % PALETTE.length]; }
  function pointColor(cfg, si, li) {
    if (cfg.series.length === 1 && cfg.colors && cfg.colors[li]) return cfg.colors[li];
    return seriesColor(cfg, si);
  }
  function sliceColor(cfg, li) { return (cfg.colors && cfg.colors[li]) || PALETTE[li % PALETTE.length]; }
  function num(cfg, s, i) { return +cfg.series[s].data[i] || 0; }
  function totals(cfg) {
    return cfg.labels.map(function (_, i) {
      return cfg.series.reduce(function (a, s, si) { return a + num(cfg, si, i); }, 0);
    });
  }

  function cartesian(svg, cfg, type, tip) {
    var n = cfg.labels.length, S = cfg.series.length;
    var horiz = type === 'hbar', stacked = type === 'stacked' || (horiz && S > 1);
    var isLine = type === 'line' || type === 'area';
    var maxLab = Math.min(18, Math.max.apply(null, cfg.labels.map(function (l) { return String(l).length; }).concat([1])));
    var ml = horiz ? 14 + maxLab * 6.3 : 44, mr = 20, mt = 14, mb = horiz ? 30 : (n > 6 ? 66 : 40);
    var H = horiz ? Math.max(240, n * 32 + mt + mb) : 320;
    var pw = W - ml - mr, ph = H - mt - mb;
    var tot = totals(cfg);
    var max = stacked ? Math.max.apply(null, tot.concat([0])) : Math.max.apply(null, cfg.series.map(function (s, si) {
      return Math.max.apply(null, cfg.labels.map(function (_, i) { return num(cfg, si, i); }).concat([0]));
    }).concat([0]));
    var sc = scale(max), v, i, s;
    var G = el('g', {});
    svg.appendChild(G);

    if (horiz) {
      var X = function (val) { return ml + val / sc.top * pw; }, band = ph / n;
      for (v = 0; v <= sc.top + 1e-9; v += sc.step) {
        G.appendChild(el('line', { x1: X(v), x2: X(v), y1: mt, y2: mt + ph, stroke: GRID }));
        G.appendChild(T(X(v), mt + ph + 16, fmt(v), { anchor: 'middle' }));
      }
      for (i = 0; i < n; i++) {
        var cy = mt + band * i, acc = 0, bh = band * 0.62;
        G.appendChild(T(ml - 8, cy + band / 2 + 4, trunc(cfg.labels[i], 18), { anchor: 'end', fill: TEXT }));
        for (s = 0; s < S; s++) {
          var val = num(cfg, s, i);
          if (val <= 0) continue;
          var r = el('rect', { x: X(acc), y: cy + (band - bh) / 2, width: Math.max(1, X(acc + val) - X(acc)), height: bh, rx: 3, fill: pointColor(cfg, s, i) });
          G.appendChild(tipOn(r, cfg.labels[i] + (S > 1 ? ' \u00b7 ' + cfg.series[s].name : '') + ': ' + fmt(val) + (S > 1 ? ' (total ' + fmt(tot[i]) + ')' : '')));
          acc += val;
        }
        if (S === 1 && acc > 0) G.appendChild(T(X(acc) + 6, cy + band / 2 + 4, fmt(acc), { fill: TEXT, weight: 600 }));
      }
      G.appendChild(el('line', { x1: ml, x2: ml, y1: mt, y2: mt + ph, stroke: '#b9c3d3' }));
    } else {
      var Y = function (val) { return mt + ph - val / sc.top * ph; }, bandX = pw / n;
      for (v = 0; v <= sc.top + 1e-9; v += sc.step) {
        G.appendChild(el('line', { x1: ml, x2: ml + pw, y1: Y(v), y2: Y(v), stroke: GRID }));
        G.appendChild(T(ml - 6, Y(v) + 4, fmt(v), { anchor: 'end' }));
      }
      var every = Math.ceil(n / 12);
      for (i = 0; i < n; i++) {
        if (i % every === 0) {
          var lx = ml + bandX * (i + 0.5), ly = mt + ph + 16;
          G.appendChild(n > 6 ? T(lx + 4, ly, trunc(cfg.labels[i], 14), { anchor: 'end', rot: -35 }) : T(lx, ly, trunc(cfg.labels[i], 14), { anchor: 'middle' }));
        }
      }
      if (isLine) {
        for (s = 0; s < S; s++) {
          var pts = [];
          for (i = 0; i < n; i++) pts.push([ml + bandX * (i + 0.5), Y(num(cfg, s, i))]);
          var d = pts.map(function (p, k) { return (k ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1); }).join(' ');
          if (type === 'area') G.appendChild(el('path', { d: d + ' L' + pts[n - 1][0].toFixed(1) + ' ' + Y(0) + ' L' + pts[0][0].toFixed(1) + ' ' + Y(0) + ' Z', fill: seriesColor(cfg, s), 'fill-opacity': 0.15 }));
          G.appendChild(el('path', { d: d, fill: 'none', stroke: seriesColor(cfg, s), 'stroke-width': 2.5, 'stroke-linejoin': 'round' }));
          for (i = 0; i < n; i++) {
            G.appendChild(tipOn(el('circle', { cx: pts[i][0], cy: pts[i][1], r: n > 24 ? 3 : 4.5, fill: seriesColor(cfg, s), stroke: '#fff', 'stroke-width': 1.5 }),
              cfg.labels[i] + (S > 1 ? ' \u00b7 ' + cfg.series[s].name : '') + ': ' + fmt(num(cfg, s, i))));
          }
        }
      } else {
        for (i = 0; i < n; i++) {
          var acc2 = 0;
          for (s = 0; s < S; s++) {
            var val2 = num(cfg, s, i);
            if (val2 <= 0) continue;
            var bw, bx;
            if (stacked) { bw = bandX * 0.62; bx = ml + bandX * i + bandX * 0.19; }
            else { bw = bandX * 0.72 / S; bx = ml + bandX * i + bandX * 0.14 + s * bw; }
            var top = stacked ? Y(acc2 + val2) : Y(val2), ht = stacked ? Y(acc2) - Y(acc2 + val2) : Y(0) - Y(val2);
            var rc = el('rect', { x: bx, y: top, width: Math.max(1, bw - (stacked ? 0 : 2)), height: Math.max(1, ht), rx: 3, fill: pointColor(cfg, s, i) });
            G.appendChild(tipOn(rc, cfg.labels[i] + (S > 1 ? ' \u00b7 ' + cfg.series[s].name : '') + ': ' + fmt(val2) + (stacked ? ' (total ' + fmt(tot[i]) + ')' : '')));
            if (stacked) acc2 += val2;
          }
        }
      }
      G.appendChild(el('line', { x1: ml, x2: ml + pw, y1: Y(0), y2: Y(0), stroke: '#b9c3d3' }));
    }
    svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
  }

  function pie(svg, cfg, donut) {
    var H = 320, cx = W / 2, cy = H / 2, R = 130, r = donut ? 78 : 0;
    var vals = totals(cfg), total = vals.reduce(function (a, b) { return a + b; }, 0);
    svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
    if (!total) { svg.appendChild(T(cx, cy, 'No data', { anchor: 'middle', size: 14 })); return; }
    var pt = function (rad, a) { return (cx + rad * Math.cos(a)).toFixed(2) + ' ' + (cy + rad * Math.sin(a)).toFixed(2); };
    var a0 = -Math.PI / 2;
    vals.forEach(function (v, i) {
      if (v <= 0) return;
      var tip = cfg.labels[i] + ': ' + fmt(v) + ' (' + Math.round(v / total * 100) + '%)', node;
      if (v === total) {
        var d = 'M' + (cx - R) + ' ' + cy + 'a' + R + ' ' + R + ' 0 1 0 ' + 2 * R + ' 0a' + R + ' ' + R + ' 0 1 0 ' + (-2 * R) + ' 0';
        if (donut) d += 'M' + (cx - r) + ' ' + cy + 'a' + r + ' ' + r + ' 0 1 1 ' + 2 * r + ' 0a' + r + ' ' + r + ' 0 1 1 ' + (-2 * r) + ' 0';
        node = el('path', { d: d + 'Z', 'fill-rule': 'evenodd', fill: sliceColor(cfg, i) });
      } else {
        var a1 = a0 + v / total * Math.PI * 2, large = (a1 - a0) > Math.PI ? 1 : 0, p;
        p = donut
          ? 'M' + pt(R, a0) + 'A' + R + ' ' + R + ' 0 ' + large + ' 1 ' + pt(R, a1) + 'L' + pt(r, a1) + 'A' + r + ' ' + r + ' 0 ' + large + ' 0 ' + pt(r, a0) + 'Z'
          : 'M' + cx + ' ' + cy + 'L' + pt(R, a0) + 'A' + R + ' ' + R + ' 0 ' + large + ' 1 ' + pt(R, a1) + 'Z';
        node = el('path', { d: p, fill: sliceColor(cfg, i), stroke: '#fff', 'stroke-width': 2 });
        a0 = a1;
      }
      svg.appendChild(tipOn(node, tip));
    });
    if (donut) {
      svg.appendChild(T(cx, cy + 6, fmt(total), { anchor: 'middle', size: 26, weight: 700, fill: TEXT }));
      svg.appendChild(T(cx, cy + 26, 'total', { anchor: 'middle', size: 12 }));
    }
  }

  function legendItems(cfg, type) {
    if (type === 'pie' || type === 'doughnut') {
      var t = totals(cfg), sum = t.reduce(function (a, b) { return a + b; }, 0);
      return cfg.labels.map(function (l, i) { return { label: l, color: sliceColor(cfg, i), note: fmt(t[i]) + (sum ? ' (' + Math.round(t[i] / sum * 100) + '%)' : '') }; });
    }
    if (cfg.series.length === 1 && cfg.colors) return cfg.labels.map(function (l, i) { return { label: l, color: pointColor(cfg, 0, i) }; });
    if (cfg.series.length > 1) return cfg.series.map(function (s, i) { return { label: s.name, color: seriesColor(cfg, i) }; });
    return [];
  }

  function savePng(cfg, svg, items, type) {
    var vb = svg.viewBox.baseVal, w = vb.width, hh = vb.height, k = 2;
    var clone = svg.cloneNode(true);
    clone.setAttribute('xmlns', NS); clone.setAttribute('width', w); clone.setAttribute('height', hh);
    var img = new Image();
    img.onload = function () {
      var c = document.createElement('canvas'), ctx;
      var probe = c.getContext('2d'); probe.font = '13px ' + FONT;
      var rows = 1, x = 16;
      items.forEach(function (it) {
        var wd = probe.measureText(it.label + (it.note ? ' ' + it.note : '')).width + 34;
        if (x + wd > w - 16) { rows++; x = 16; }
        x += wd;
      });
      var head = 46, foot = items.length ? rows * 24 + 12 : 12;
      c.width = w * k; c.height = (head + hh + foot) * k;
      ctx = c.getContext('2d'); ctx.scale(k, k);
      ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, w, head + hh + foot);
      ctx.fillStyle = TEXT; ctx.font = '700 18px ' + FONT; ctx.fillText(cfg.title, 16, 30);
      ctx.drawImage(img, 0, head, w, hh);
      ctx.font = '13px ' + FONT;
      var lx = 16, ly = head + hh + 18;
      items.forEach(function (it) {
        var txt = it.label + (it.note ? ' ' + it.note : ''), wd = ctx.measureText(txt).width + 34;
        if (lx + wd > w - 16) { lx = 16; ly += 24; }
        ctx.fillStyle = it.color; ctx.fillRect(lx, ly - 10, 12, 12);
        ctx.fillStyle = TEXT; ctx.fillText(txt, lx + 18, ly);
        lx += wd;
      });
      c.toBlob(function (b) {
        var a = document.createElement('a');
        a.href = URL.createObjectURL(b);
        a.download = 'chart-' + (cfg.id || 'export') + '-' + new Date().toISOString().slice(0, 10) + '.png';
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
      }, 'image/png');
    };
    img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(new XMLSerializer().serializeToString(clone));
  }

  function build(mount) {
    var cfg;
    try { cfg = JSON.parse(mount.getAttribute('data-chart')); } catch (e) { return; }
    var multi = cfg.series.length > 1;
    var all = multi ? ['stacked', 'bar', 'hbar', 'line', 'area', 'doughnut', 'pie'] : ['bar', 'hbar', 'line', 'area', 'doughnut', 'pie'];
    var types = (cfg.types && cfg.types.length ? cfg.types : all).filter(function (t) { return NAMES[t]; });
    var type = types.indexOf(cfg.type) > -1 ? cfg.type : types[0];
    mount.textContent = '';

    var head = h('div', 'chart-head'), tools = h('div', 'chart-tools');
    head.appendChild(h('h3', null, cfg.title));
    var sel = h('select');
    sel.setAttribute('aria-label', 'Chart type for ' + cfg.title);
    types.forEach(function (t) { var o = h('option', null, NAMES[t]); o.value = t; if (t === type) o.selected = true; sel.appendChild(o); });
    var dl = h('button', 'btn btn-ghost btn-sm', 'PNG'); dl.type = 'button'; dl.title = 'Download this chart as a PNG image';
    if (types.length > 1) tools.appendChild(sel);
    tools.appendChild(dl); head.appendChild(tools); mount.appendChild(head);

    var box = h('div', 'chart-box'), tipEl = h('div', 'chart-tip');
    var legend = h('ul', 'chart-legend');
    mount.appendChild(box); mount.appendChild(legend);
    var svg, items = [];

    function draw() {
      box.textContent = ''; legend.textContent = '';
      if (!cfg.labels.length || totals(cfg).every(function (v) { return !v; })) {
        box.appendChild(h('p', 'chart-empty', 'No data to chart for these filters.'));
        dl.disabled = true; svg = null; return;
      }
      dl.disabled = false;
      svg = el('svg', { role: 'img', 'aria-label': cfg.title, preserveAspectRatio: 'xMidYMid meet' });
      svg.appendChild(el('title', {}, cfg.title));
      var t = totals(cfg);
      svg.appendChild(el('desc', {}, cfg.labels.map(function (l, i) { return l + ': ' + t[i]; }).join(', ')));
      if (type === 'pie' || type === 'doughnut') pie(svg, cfg, type === 'doughnut'); else cartesian(svg, cfg, type);
      box.appendChild(svg); box.appendChild(tipEl);
      items = legendItems(cfg, type);
      items.forEach(function (it) {
        var li = h('li'), sw = h('span', 'sw'); sw.style.background = it.color;
        li.appendChild(sw); li.appendChild(h('span', null, it.label));
        if (it.note) li.appendChild(h('b', null, it.note));
        legend.appendChild(li);
      });
    }
    box.addEventListener('pointermove', function (e) {
      var tgt = e.target.closest ? e.target.closest('[data-tip]') : null;
      if (!tgt) { tipEl.classList.remove('on'); return; }
      var r = box.getBoundingClientRect();
      tipEl.textContent = tgt.getAttribute('data-tip');
      tipEl.style.left = (e.clientX - r.left) + 'px'; tipEl.style.top = (e.clientY - r.top) + 'px';
      tipEl.classList.add('on');
    });
    box.addEventListener('pointerleave', function () { tipEl.classList.remove('on'); });
    sel.addEventListener('change', function () { type = sel.value; draw(); });
    dl.addEventListener('click', function () { if (svg) savePng(cfg, svg, items, type); });
    draw();
  }

  function init() {
    Array.prototype.forEach.call(document.querySelectorAll('.chart-mount[data-chart]'), build);
    Array.prototype.forEach.call(document.querySelectorAll('[data-print]'), function (b) {
      b.addEventListener('click', function () { window.print(); });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
