/* Krono live filters: change a filter and only the results area is replaced. No page reload, no framework.
   Filter form : <form data-live data-live-target="results-id">  (selects fire at once, text inputs wait until typing stops)
   Results area: an element with that id. Links inside it marked data-live (tabs, pages, "Clear filters") replace the area too.
   The server still renders whole pages, so without JavaScript everything works as a normal page load (and a failed request falls back to one).
   After a swap the page fires "krono:content" (detail = the new area) so scripts can re-attach their buttons. */
(function () {
  'use strict';
  var all = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
  var busy = null, timer = 0;

  function load(url, targetId, mode) {
    if (busy) busy.abort(); busy = new AbortController(); var mine = busy;
    var area = document.getElementById(targetId); if (!area) { location.href = url; return; }
    area.setAttribute('aria-busy', 'true');
    fetch(url, { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin', signal: mine.signal })
      .then(function (r) { if (!r.ok && r.status !== 422) throw new Error('http ' + r.status); return r.text(); })
      .then(function (html) {
        var fresh = new DOMParser().parseFromString(html, 'text/html').getElementById(targetId); if (!fresh) throw new Error('no area');
        area.replaceWith(fresh);
        if (mode !== 'none') history[mode === 'replace' ? 'replaceState' : 'pushState']({ live: 1 }, '', url);
        if (/\/tasks$/.test(url.split('?')[0])) all('input[name=back]').forEach(function (b) { b.value = url.replace(/^.*?\/tasks/, '/tasks'); });   // forms outside the area (add / delete) return to the same filtered list
        document.dispatchEvent(new CustomEvent('krono:content', { detail: fresh }));
      })
      .catch(function (e) { if (e.name !== 'AbortError') location.href = url; });
  }

  function formUrl(form) {
    var p = new URLSearchParams();
    new FormData(form).forEach(function (v, k) { v = String(v).trim(); if (v !== '' && v !== 'all') p.append(k, v); });
    all('input[data-default]', form).forEach(function (i) { if (i.value === i.dataset.default) p.delete(i.name); });   // a default date range keeps the address short
    var q = p.toString(); return form.getAttribute('action') + (q ? '?' + q : '');
  }

  /* put the filter controls back to what the URL says (after a click on "Clear filters" or Back) */
  function fillForm(form, url) {
    var p = new URL(url, location.href).searchParams;
    all('select, input[name]', form).forEach(function (el) {
      if (el.type === 'hidden' && !el.hasAttribute('data-default')) return; var v = p.get(el.name);
      if (el.hasAttribute('data-default')) { el.value = v !== null ? v : el.dataset.default; return; }
      el.value = v !== null ? v : (el.tagName === 'SELECT' ? el.options[0].value : '');
    });
    (form._narrow || []).forEach(function (f) { f(false); });
    window.KronoUI && window.KronoUI.sync(form);
  }

  all('form[data-live]').forEach(function (form) {
    var target = form.dataset.liveTarget;
    form.addEventListener('submit', function (e) { e.preventDefault(); load(formUrl(form), target, 'push'); });
    /* a select that depends on another (Task depends on Project): show only the matching options, and reset it when the other one changes */
    all('select[data-depends]', form).forEach(function (sel) {
      var opts = [].slice.call(sel.options), master = form.elements[sel.dataset.depends];
      function narrow(reset) {
        var v = master.value, keep = reset ? '' : sel.value; sel.innerHTML = '';
        opts.forEach(function (o) { if (!o.value || !v || o.dataset[sel.dataset.depends] === v) sel.appendChild(o); });
        sel.value = [].some.call(sel.options, function (o) { return o.value === keep; }) ? keep : '';
        window.KronoUI && window.KronoUI.sync(form);
      }
      form.addEventListener('change', function (e) { if (e.target === master) narrow(true); }); narrow(false); (form._narrow = form._narrow || []).push(narrow);
    });
    form.addEventListener('change', function (e) { if (e.target.tagName === 'SELECT' || e.target.hasAttribute('data-live-change')) { clearTimeout(timer); load(formUrl(form), target, 'push'); } });
    form.addEventListener('input', function (e) {
      if (e.target.tagName !== 'INPUT') return; clearTimeout(timer);
      timer = setTimeout(function () { load(formUrl(form), target, 'replace'); }, 300);
    });
    form.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.tagName === 'INPUT') e.preventDefault(), clearTimeout(timer), load(formUrl(form), target, 'replace'); });
  });

  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[data-live]'); if (!a || e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.button) return;
    var area = a.closest('[data-live-area]'); if (!area) return; e.preventDefault();
    var form = document.querySelector('form[data-live][data-live-target="' + area.id + '"]'); if (form) fillForm(form, a.href);
    load(a.href, area.id, 'push');
  });

  window.addEventListener('popstate', function () { if (document.querySelector('[data-live-area]')) location.reload(); });
})();
