/* Krono UI: custom dropdown (searchable, scrollable) and calendar for every <select> and <input type="date">.
   Plain JS, no library. The native element stays in the page (hidden) so forms, validation and "change" listeners keep working.
   Opt out on one element with data-native. Layout hooks: data-kauto (shrink to content), data-ksmall (compact trigger), data-search="off|on". */
(function () {
  'use strict';
  var MON = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  var WD = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
  var svg = function (p) { return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + p + '</svg>'; };
  var I = {
    chev: svg('<path d="m6 9 6 6 6-6"/>'), left: svg('<path d="m15 6-6 6 6 6"/>'), right: svg('<path d="m9 6 6 6-6 6"/>'),
    cal: svg('<rect x="3.5" y="5" width="17" height="15" rx="2.5"/><path d="M8 3v4M16 3v4M3.5 10h17"/>'), check: svg('<path d="m5 12.5 4.5 4.5L19 7.5"/>'), search: svg('<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4-4"/>')
  };
  var uid = 0, current = null;
  function el(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function closeCurrent() { if (current) { current.close(); } }

  /* place a fixed popover under (or over) its trigger, inside the viewport */
  function place(pop, trig, minW) {
    var r = trig.getBoundingClientRect(), vw = window.innerWidth, vh = window.innerHeight;
    pop.style.minWidth = Math.max(r.width, minW || 0) + 'px';
    pop.style.left = '0px'; pop.style.top = '0px'; pop.style.visibility = 'hidden';
    var w = pop.offsetWidth, h = pop.offsetHeight, left = Math.min(Math.max(8, r.left), Math.max(8, vw - w - 8));
    var below = vh - r.bottom - 8, above = r.top - 8, top;
    if (h <= below || below >= above) { top = r.bottom + 6; pop.style.maxHeight = Math.max(160, below - 6) + 'px'; }
    else { top = Math.max(8, r.top - h - 6); pop.style.maxHeight = Math.max(160, above - 6) + 'px'; }
    pop.style.left = left + 'px'; pop.style.top = top + 'px'; pop.style.visibility = '';
  }
  function bindOutside(api, pop, trig) {
    var down = function (e) { if (!pop.contains(e.target) && !trig.contains(e.target)) api.close(); };
    var scroll = function (e) { if (!pop.contains(e.target)) api.close(); };
    var esc = function (e) { if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); api.close(); trig.focus(); } };   // capture: works even if focus was lost, and the dialog behind does not close
    document.addEventListener('mousedown', down, true); document.addEventListener('keydown', esc, true); window.addEventListener('scroll', scroll, true); window.addEventListener('resize', api.close);
    return function () { document.removeEventListener('mousedown', down, true); document.removeEventListener('keydown', esc, true); window.removeEventListener('scroll', scroll, true); window.removeEventListener('resize', api.close); };
  }

  /* ---------- select ---------- */
  function enhanceSelect(sel) {
    if (sel._k || sel.multiple || sel.hasAttribute('data-native')) return; sel._k = 1;
    var wrap = el('div', 'kselect' + (sel.hasAttribute('data-kauto') ? ' kselect-auto' : '') + (sel.hasAttribute('data-ksmall') ? ' kselect-sm' : ''));
    var trig = el('button', 'kselect-trigger'); trig.type = 'button'; trig.setAttribute('aria-haspopup', 'listbox'); trig.setAttribute('aria-expanded', 'false');
    var lab = sel.getAttribute('aria-label'); var lf = sel.id && document.querySelector('label[for="' + sel.id + '"]'); if (lab || lf) trig.setAttribute('aria-label', lab || lf.textContent.replace('*', '').trim());
    sel.parentNode.insertBefore(wrap, sel); wrap.appendChild(trig); wrap.appendChild(sel); sel.hidden = true; sel.tabIndex = -1; sel._kfocus = trig;
    var popEl = null, items = [], active = -1, off = null, input = null, list = null;

    function optHTML(o) { return (o.dataset.dot ? '<i class="kdot ' + esc(o.dataset.dot) + '"></i>' : '') + '<span class="kselect-text">' + esc(o.textContent) + '</span>'; }
    function paint() {
      var o = sel.options[sel.selectedIndex];
      trig.innerHTML = '<span class="kselect-label">' + (o ? optHTML(o) : '') + '</span>' + I.chev; trig.disabled = sel.disabled;
      if (sel.getAttribute('aria-invalid') === 'true') trig.setAttribute('aria-invalid', 'true'); else trig.removeAttribute('aria-invalid');
    }
    function setActive(i, scroll) {
      if (active > -1 && items[active]) items[active].row.classList.remove('is-active');
      active = i; if (i > -1 && items[i]) { items[i].row.classList.add('is-active'); if (scroll !== false) items[i].row.scrollIntoView({ block: 'nearest' }); }
    }
    function visible() { return items.filter(function (x) { return !x.row.hidden; }); }
    function choose(i) { var o = sel.options[i]; if (!o || o.disabled) return; var ch = sel.selectedIndex !== i; sel.selectedIndex = i; paint(); api.close(); trig.focus(); if (ch) sel.dispatchEvent(new Event('change', { bubbles: true })); }
    var api = {
      sync: paint,
      open: function () {
        if (sel.disabled) return; closeCurrent(); current = api;
        popEl = el('div', 'kpop'); popEl.setAttribute('role', 'presentation');
        var many = sel.options.length > 7, want = sel.getAttribute('data-search');
        if (want === 'on' || (many && want !== 'off')) {
          var sw = el('div', 'kselect-search'), box = el('div', 'kselect-searchbox', I.search); input = el('input'); input.type = 'text'; input.placeholder = 'Search…'; input.setAttribute('aria-label', 'Search options'); input.autocomplete = 'off';
          box.appendChild(input); sw.appendChild(box); popEl.appendChild(sw);
        } else input = null;
        list = el('div', 'kselect-list'); list.setAttribute('role', 'listbox'); list.id = 'kl' + (++uid); items = [];
        [].forEach.call(sel.options, function (o, i) {
          var row = el('div', 'kselect-opt', optHTML(o) + (o.dataset.meta ? '<span class="kselect-meta">' + esc(o.dataset.meta) + '</span>' : '') + '<span class="kselect-check">' + I.check + '</span>');
          row.setAttribute('role', 'option'); row.setAttribute('aria-selected', i === sel.selectedIndex ? 'true' : 'false'); if (o.disabled) row.setAttribute('aria-disabled', 'true');
          row.addEventListener('mousedown', function (e) { e.preventDefault(); }); row.addEventListener('click', function () { choose(i); });
          row.addEventListener('mousemove', function () { var k = items.findIndex(function (x) { return x.row === row; }); if (k !== active) setActive(k, false); });
          list.appendChild(row); items.push({ row: row, text: o.textContent.toLowerCase(), i: i });
        });
        var none = el('div', 'kselect-empty', 'No results found'); none.hidden = true; list.appendChild(none); popEl.appendChild(list); wrap.appendChild(popEl);
        trig.setAttribute('aria-expanded', 'true'); place(popEl, trig, 180); off = bindOutside(api, popEl, trig);
        setActive(sel.selectedIndex); if (input) { input.focus(); input.addEventListener('input', filter); }
        popEl.addEventListener('keydown', key); trig.addEventListener('keydown', key);
      },
      close: function () {
        if (!popEl) return; off && off(); off = null; popEl.remove(); popEl = null; trig.setAttribute('aria-expanded', 'false'); trig.removeEventListener('keydown', key); if (current === api) current = null;
      }
    };
    function filter() {
      var q = input.value.trim().toLowerCase(), n = 0;
      items.forEach(function (x) { var show = !q || x.text.indexOf(q) > -1; x.row.hidden = !show; if (show) n++; });
      list.lastChild.hidden = n > 0; var v = visible(); setActive(v.length ? items.indexOf(v[0]) : -1);
    }
    function key(e) {
      if (!popEl) { if (e.target === trig && (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); api.open(); } return; }
      var v = visible(), pos = v.findIndex(function (x) { return items.indexOf(x) === active; });
      if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); api.close(); trig.focus(); }
      else if (e.key === 'ArrowDown') { e.preventDefault(); if (v.length) setActive(items.indexOf(v[Math.min(v.length - 1, pos + 1)])); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); if (v.length) setActive(items.indexOf(v[Math.max(0, pos - 1)])); }
      else if (e.key === 'Home') { if (!input) { e.preventDefault(); v.length && setActive(items.indexOf(v[0])); } }
      else if (e.key === 'End') { if (!input) { e.preventDefault(); v.length && setActive(items.indexOf(v[v.length - 1])); } }
      else if (e.key === 'Enter') { e.preventDefault(); if (active > -1) choose(items[active].i); }
      else if (e.key === 'Tab') api.close();
    }
    trig.addEventListener('click', function () { popEl ? api.close() : api.open(); });
    sel.addEventListener('change', paint); sel._k = api; paint();
  }

  /* ---------- date ---------- */
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function iso(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function parse(s) { var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || ''); if (!m) return null; var d = new Date(+m[1], +m[2] - 1, +m[3]); return d.getMonth() === +m[2] - 1 ? d : null; }
  function fmt(d) { return MON[d.getMonth()].slice(0, 3) + ' ' + pad(d.getDate()) + ', ' + d.getFullYear(); }

  function enhanceDate(inp) {
    if (inp._k || inp.hasAttribute('data-native')) return; inp._k = 1;
    var wrap = el('div', 'kdate'), trig = el('button', 'kdate-trigger'); trig.type = 'button'; trig.setAttribute('aria-haspopup', 'dialog'); trig.setAttribute('aria-expanded', 'false');
    var lf = inp.id && document.querySelector('label[for="' + inp.id + '"]'); if (lf) trig.setAttribute('aria-label', lf.textContent.replace('*', '').trim());
    inp.parentNode.insertBefore(wrap, inp); wrap.appendChild(trig); wrap.appendChild(inp); inp.type = 'hidden'; inp._kfocus = trig;
    var ph = inp.getAttribute('placeholder') || 'Select date', popEl = null, off = null, view = 'days', cur = null, yearBase = 0;

    function paint() {
      var d = parse(inp.value); trig.innerHTML = '<span class="kdate-label' + (d ? '' : ' is-empty') + '">' + (d ? fmt(d) : esc(ph)) + '</span>' + I.cal;
      if (inp.getAttribute('aria-invalid') === 'true') trig.setAttribute('aria-invalid', 'true'); else trig.removeAttribute('aria-invalid');
    }
    function lim() { return { min: parse(inp.getAttribute('min')), max: parse(inp.getAttribute('max')) }; }
    function pick(d) { var ch = inp.value !== iso(d); inp.value = iso(d); paint(); api.close(); trig.focus(); if (ch) inp.dispatchEvent(new Event('change', { bubbles: true })); }
    function render() {
      var L = lim(), today = new Date(), sel = parse(inp.value); popEl.innerHTML = '';
      var head = el('div', 'kcal-head'), title = el('button', 'kcal-title'), prev = el('button', 'kcal-nav', I.left), next = el('button', 'kcal-nav', I.right);
      [title, prev, next].forEach(function (b) { b.type = 'button'; });
      prev.setAttribute('aria-label', 'Previous'); next.setAttribute('aria-label', 'Next');
      if (view === 'days') { title.innerHTML = MON[cur.getMonth()] + ' ' + cur.getFullYear() + ' ' + I.chev; }
      else if (view === 'months') { title.innerHTML = cur.getFullYear() + ' ' + I.chev; }
      else { title.textContent = yearBase + ' – ' + (yearBase + 11); }
      title.addEventListener('click', function () { if (view === 'days') view = 'months'; else if (view === 'months') { view = 'years'; yearBase = cur.getFullYear() - 4; } else view = 'days'; render(); });
      prev.addEventListener('click', function () { if (view === 'days') cur = new Date(cur.getFullYear(), cur.getMonth() - 1, 1); else if (view === 'months') cur = new Date(cur.getFullYear() - 1, cur.getMonth(), 1); else yearBase -= 12; render(); });
      next.addEventListener('click', function () { if (view === 'days') cur = new Date(cur.getFullYear(), cur.getMonth() + 1, 1); else if (view === 'months') cur = new Date(cur.getFullYear() + 1, cur.getMonth(), 1); else yearBase += 12; render(); });
      head.appendChild(title); var nav = el('div', 'kcal-navs'); nav.appendChild(prev); nav.appendChild(next); head.appendChild(nav); popEl.appendChild(head);
      var body = el('div', 'kcal-body');
      if (view === 'days') {
        var g = el('div', 'kcal-grid'); WD.forEach(function (w) { g.appendChild(el('div', 'kcal-wd', w)); });
        var first = new Date(cur.getFullYear(), cur.getMonth(), 1), start = new Date(first); start.setDate(1 - first.getDay());
        for (var i = 0; i < 42; i++) {
          var d = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i), b = el('button', 'kday', String(d.getDate())); b.type = 'button';
          if (d.getMonth() !== cur.getMonth()) b.classList.add('is-dim');
          if (iso(d) === iso(today)) b.classList.add('is-today');
          if (sel && iso(d) === iso(sel)) { b.classList.add('is-sel'); b.setAttribute('aria-pressed', 'true'); }
          if ((L.min && d < L.min) || (L.max && d > L.max)) b.disabled = true;
          (function (dd) { b.addEventListener('click', function () { pick(dd); }); })(d); g.appendChild(b);
        }
        body.appendChild(g);
      } else {
        var g2 = el('div', 'kcal-pick');
        for (var k = 0; k < 12; k++) {
          (function (k) {
            var isM = view === 'months', val = isM ? k : yearBase + k, b = el('button', 'kcal-cell', isM ? MON[k].slice(0, 3) : String(val)); b.type = 'button';
            if (isM ? (sel && sel.getFullYear() === cur.getFullYear() && sel.getMonth() === k) : (sel && sel.getFullYear() === val)) b.classList.add('is-sel');
            b.addEventListener('click', function () { cur = isM ? new Date(cur.getFullYear(), k, 1) : new Date(val, cur.getMonth(), 1); view = isM ? 'days' : 'months'; render(); });
            g2.appendChild(b);
          })(k);
        }
        body.appendChild(g2);
      }
      popEl.appendChild(body);
      var foot = el('div', 'kcal-foot'), left = el('div', 'kcal-foot-left'), t = el('button', 'kcal-link', 'Today'); t.type = 'button';
      t.addEventListener('click', function () { var d = new Date(); d = new Date(d.getFullYear(), d.getMonth(), d.getDate()); if ((L.min && d < L.min) || (L.max && d > L.max)) { cur = d; view = 'days'; render(); } else pick(d); });
      left.appendChild(t);
      if (inp.value && !inp.hasAttribute('data-required')) { var c = el('button', 'kcal-link', 'Clear'); c.type = 'button'; c.addEventListener('click', function () { inp.value = ''; paint(); api.close(); trig.focus(); inp.dispatchEvent(new Event('change', { bubbles: true })); }); left.appendChild(c); }
      var done = el('button', 'btn btn-primary h-8', 'Done'); done.type = 'button'; done.addEventListener('click', function () { api.close(); trig.focus(); });
      foot.appendChild(left); foot.appendChild(done); popEl.appendChild(foot);
      place(popEl, trig, 296);
    }
    var api = {
      sync: paint,
      open: function () {
        closeCurrent(); current = api; popEl = el('div', 'kpop kcal'); popEl.setAttribute('role', 'dialog'); popEl.setAttribute('aria-label', 'Choose date');
        var d = parse(inp.value) || new Date(); cur = new Date(d.getFullYear(), d.getMonth(), 1); view = 'days';
        wrap.appendChild(popEl); trig.setAttribute('aria-expanded', 'true'); render(); off = bindOutside(api, popEl, trig); popEl.addEventListener('keydown', ekey);
      },
      close: function () { if (!popEl) return; off && off(); off = null; popEl.remove(); popEl = null; trig.setAttribute('aria-expanded', 'false'); if (current === api) current = null; }
    };
    function ekey(e) { if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); api.close(); trig.focus(); } }
    trig.addEventListener('click', function () { popEl ? api.close() : api.open(); });
    trig.addEventListener('keydown', function (e) { if (!popEl && (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); api.open(); } });
    inp.addEventListener('change', paint); inp._k = api; paint();
  }

  function init(root) {
    [].forEach.call((root || document).querySelectorAll('select'), enhanceSelect);
    [].forEach.call((root || document).querySelectorAll('input[type="date"]'), enhanceDate);
  }
  /* call after changing values from script (form.reset() is handled automatically) */
  function sync(root) { [].forEach.call((root || document).querySelectorAll('select,input'), function (e) { e._k && e._k.sync && e._k.sync(); }); }
  document.addEventListener('reset', function (e) { setTimeout(function () { sync(e.target); }, 0); }, true);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(); }); else init();
  window.KronoUI = { init: init, sync: sync };
})();
