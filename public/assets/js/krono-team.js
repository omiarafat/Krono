/* Krono Teams: row actions menu, member drawer, conditional fields. Plain JS, works on rows that live filters swap in.
   Rows / cards carry data-member='{"kind","id","name","email","role","manager_id","reports","can":{...},...}'.
   Menu button: [data-team-menu]. Modals are opened with KronoModals.open (krono-modals.js). */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); }, all = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
  var drawer = $('#member-drawer'), menu = null, menuFor = null;
  var data = function (el) { var h = el.closest('[data-member]'); try { return h ? JSON.parse(h.getAttribute('data-member')) : null; } catch (e) { return null; } };

  function actions(m) {
    var c = m.can || {}, a = [];
    if (m.kind === 'invite') { if (c.invite) a.push({ k: 'resend', l: 'Resend invitation' }, { k: 'cancel', l: 'Cancel invitation', danger: 1 }); return a; }
    if (c.role) a.push({ k: 'role', l: 'Change role' });
    if (c.manager) a.push({ k: 'manager', l: m.manager_id ? 'Change manager' : 'Assign manager' });
    if (c.reactivate) a.push({ k: 'reactivate', l: 'Reactivate' });
    if (c.deactivate) a.push({ k: 'deactivate', l: 'Deactivate', danger: 1 });
    return a;
  }

  /* the "what happens to their reports" select: other managers + "leave unassigned" */
  function prep(dlg, m, show) {
    var form = $('form', dlg), wrap = $('[data-reports-wrap]', form), sel = wrap && $('select', wrap); if (!sel) return;
    var mg = JSON.parse(form.dataset.managers || '{}'); sel.innerHTML = '';
    sel.add(new Option('Choose…', '')); sel.add(new Option('Leave them without a manager', 'unassign'));
    Object.keys(mg).forEach(function (id) { if (String(id) !== String(m.id)) sel.add(new Option('Move to ' + mg[id], id)); });
    wrap.hidden = !show; sel.disabled = !show; sel.value = '';
    form._reportsNeed = !!show;
    window.KronoUI && window.KronoUI.sync(form);
  }
  /* role modal: only needed while a manager with reports moves to another role */
  function baseOf(sel) { var o = sel && sel.options && sel.options[sel.selectedIndex]; return (o && o.dataset.base) || (sel ? sel.value : ''); }
  function roleWrap(dlg) {
    var form = $('form', dlg), m = dlg._m; if (!m) return;
    var need = m.role === 'manager' && +m.reports > 0 && baseOf(form.elements.role) !== 'manager';
    var wrap = $('[data-reports-wrap]', form), sel = $('select', wrap); wrap.hidden = !need; sel.disabled = !need; window.KronoUI && window.KronoUI.sync(form);
  }

  function run(act, m) {
    var M = window.KronoModals; if (!M) return;
    closeMenu();
    if (act === 'role') { var d = $('#role-modal'); d._m = m; prep(d, m, false); M.open('role-modal', { user_id: m.id, name: m.name, role: m.role_token || m.role }); roleWrap(d); }
    else if (act === 'manager') M.open('assign-modal', { user_id: m.id, manager_id: m.manager_id || '' });
    else if (act === 'deactivate') { var e = $('#deactivate-modal'); prep(e, m, m.role === 'manager' && +m.reports > 0); M.open('deactivate-modal', { user_id: m.id, name: m.name }); }
    else if (act === 'reactivate') M.open('reactivate-modal', { user_id: m.id, name: m.name });
    else if (act === 'cancel') M.open('cancel-invite-modal', { id: m.id, name: m.email });
    else if (act === 'resend') { var f = $('#resend-form'); f.elements.id.value = m.id; f.submit(); }
  }

  /* ---- floating actions menu ---- */
  function closeMenu() { if (menu) { menu.remove(); menu = null; menuFor = null; } }
  function openMenu(btn) {
    var m = data(btn); if (!m) return; var was = menuFor === btn; closeMenu(); if (was) return;
    var list = actions(m); menuFor = btn;
    menu = document.createElement('div'); menu.setAttribute('role', 'menu');
    menu.className = 'fixed z-50 min-w-48 rounded-xl border border-line bg-surface p-1.5 shadow-modal';
    var add = function (k, l, danger) { var b = document.createElement('button'); b.type = 'button'; b.setAttribute('role', 'menuitem'); b.className = 'menu-item' + (danger ? ' text-danger' : ''); b.textContent = l; b.addEventListener('click', function () { k === 'view' ? (closeMenu(), openDrawer(m)) : run(k, m); }); menu.appendChild(b); };
    add('view', 'View details'); list.forEach(function (a) { add(a.k, a.l, a.danger); });
    document.body.appendChild(menu);
    var r = btn.getBoundingClientRect(), h = menu.offsetHeight, w = menu.offsetWidth;
    menu.style.left = Math.max(8, Math.min(window.innerWidth - w - 8, r.right - w)) + 'px';
    menu.style.top = (r.bottom + 6 + h > window.innerHeight ? Math.max(8, r.top - h - 6) : r.bottom + 6) + 'px';
    $('[role=menuitem]', menu).focus();
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-team-menu]');
    if (b) { e.stopPropagation(); openMenu(b); return; }
    if (menu && !menu.contains(e.target)) closeMenu();
    var row = e.target.closest && e.target.closest('[data-member]');
    if (row && !e.target.closest('a, button, select, input, [role=menu]')) { var m = data(row); m && openDrawer(m); }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeMenu();
    if (menu && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) { e.preventDefault(); var it = all('[role=menuitem]', menu), i = it.indexOf(document.activeElement); it[(i + (e.key === 'ArrowDown' ? 1 : -1) + it.length) % it.length].focus(); }
    if (e.key === 'Enter' && e.target.matches && e.target.matches('tr[data-member]')) { var m = data(e.target); m && openDrawer(m); }
  });
  window.addEventListener('resize', closeMenu); window.addEventListener('scroll', closeMenu, true);

  /* ---- drawer ---- */
  function put(k, v) { var el = $('[data-d="' + k + '"]', drawer); if (el) el.textContent = v; }
  function openDrawer(m) {
    if (!drawer) return;
    var ini = m.name.split(/\s+/).filter(Boolean).slice(0, 2).map(function (x) { return x[0]; }).join('').toUpperCase();
    put('initials', ini); put('name', m.name + (m.self ? ' (you)' : '')); put('email', m.email);
    var tone = m.status === 'active' ? 'green' : m.status === 'invited' ? 'amber' : 'gray';
    $('[data-d="status"]', drawer).innerHTML = '<span class="tag tag-' + tone + '">' + m.status.charAt(0).toUpperCase() + m.status.slice(1) + '</span>';
    put('role', m.role_name || m.role_label); put('manager', m.manager || '—'); put('reports', m.role === 'manager' ? String(m.reports) : '—');
    put('joined', m.kind === 'invite' ? 'Not yet' : m.joined_label); put('last', m.kind === 'invite' ? '—' : m.last_label);
    var ex = $('[data-d="extra"]', drawer).parentNode; ex.hidden = m.kind !== 'invite';
    if (m.kind === 'invite') put('extra', 'Sent ' + m.joined_label + ' · expires ' + m.expires_label);
    var notes = JSON.parse(drawer.dataset.notes || '{}'); put('note', notes[m.role_token || m.role] || '');
    var box = $('[data-d-actions]', drawer); box.innerHTML = '';
    actions(m).forEach(function (a) {
      var b = document.createElement('button'); b.type = 'button'; b.className = 'btn' + (a.danger ? ' btn-danger' : ''); b.textContent = a.l;
      b.addEventListener('click', function () { drawer.close(); run(a.k, m); }); box.appendChild(b);
    });
    drawer.showModal();
  }
  if (drawer) {
    $('[data-drawer-close]', drawer).addEventListener('click', function () { drawer.close(); });
    drawer.addEventListener('mousedown', function (e) { if (e.target === drawer) drawer.close(); });
  }

  /* ---- fields that depend on another field: data-show-if="field:v1,v2" ---- */
  function showIf(form) {
    all('[data-show-if]', form).forEach(function (w) {
      var p = w.dataset.showIf.split(':'), src = form.elements[p[0]], on = !!src && p[1].split(',').indexOf(baseOf(src)) > -1;
      w.hidden = !on; all('select, input', w).forEach(function (x) { x.disabled = !on; }); window.KronoUI && window.KronoUI.sync(w);
    });
  }
  all('dialog[data-form-modal]').forEach(function (d) {
    var f = $('form', d); if (!f) return;
    f.addEventListener('change', function (e) { showIf(f); if (d.id === 'role-modal' && e.target.name === 'role') roleWrap(d); });
    d.addEventListener('krono:modal-open', function () { showIf(f); });
    showIf(f);
  });
})();
