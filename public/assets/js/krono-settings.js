/* Krono Settings: forms with an unsaved-changes guard. Plain JS.
   form[data-guard]      : Save buttons ([data-save], anywhere on the page) stay disabled until something changed.
                           Leaving by a page link while changed asks "Leave without saving?" (#leave-modal).
   data-required="a,b"   : fields that must be filled (inline "This field is required.").
   input[data-orig]      : e-mail field; while it differs from the saved address the [data-email-pw] row (current password) shows.
   [data-open=id] / [data-close] open and close a plain <dialog>.
   button[data-menu-toggle]: collapses the left Settings menu to icons only (remembered in this browser; clicking an icon opens that page and the menu stays as it is).
   input[data-photo-input]: choosing a photo uploads it at once (a photo over 2 MB is refused here, with a message, before it is sent). */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); }, all = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };

  all('[data-open]').forEach(function (b) { b.addEventListener('click', function () { var d = document.getElementById(b.dataset.open); d && d.showModal(); }); });
  all('dialog [data-close]').forEach(function (b) { b.addEventListener('click', function () { b.closest('dialog').close(); }); });

  var mt = $('[data-menu-toggle]');
  if (mt) {
    var root = document.documentElement, paint = function () { var c = root.dataset.settingsMenu === 'collapsed'; mt.setAttribute('aria-expanded', c ? 'false' : 'true'); mt.setAttribute('aria-label', c ? 'Open settings menu' : 'Collapse settings menu'); };
    paint();
    mt.addEventListener('click', function () { var c = root.dataset.settingsMenu === 'collapsed'; if (c) delete root.dataset.settingsMenu; else root.dataset.settingsMenu = 'collapsed'; try { localStorage.setItem('krono-settings-menu', c ? 'expanded' : 'collapsed'); } catch (x) {} paint(); });
  }
  var photo = $('[data-photo-input]');
  if (photo) photo.addEventListener('change', function () {
    var f = photo.files[0], msg = $('[data-photo-error]'); if (!f) return;
    if (f.size > 2 * 1024 * 1024) { msg.textContent = 'That photo is larger than 2 MB. Choose a smaller one.'; msg.classList.remove('hidden'); photo.value = ''; return; }
    msg.classList.add('hidden'); photo.form.submit();
  });

  /* Roles list: search box, delete dialog. */
  var rs = $('[data-role-search]');
  if (rs) rs.addEventListener('input', function () {
    var q = rs.value.trim().toLowerCase(), n = 0;
    all('[data-role-row]').forEach(function (r) { var on = !q || r.dataset.search.indexOf(q) > -1; r.hidden = !on; if (on) n++; });
    $('[data-role-none]').classList.toggle('hidden', n > 0);
  });
  all('[data-role-delete]').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = $('#delete-role-modal'), m = +b.dataset.members, i = +b.dataset.invites, used = m + i > 0, parts = [];
      d.querySelector('input[name=id]').value = b.dataset.roleDelete; $('[data-dr-name]', d).textContent = '“' + b.dataset.name + '”';
      if (m) parts.push(m + ' member' + (m === 1 ? '' : 's')); if (i) parts.push(i + ' pending invitation' + (i === 1 ? '' : 's'));
      $('[data-dr-count]', d).textContent = parts.join(' and ');
      $('[data-dr-free]', d).classList.toggle('hidden', used); $('[data-dr-used]', d).classList.toggle('hidden', !used); $('[data-dr-go]', d).hidden = used;
      d.showModal();
    });
  });
  /* Role form: a permission that needs another one turns it on; turning that one off turns the dependants off; live "x of N on". */
  var perms = all('input[data-perm]');
  if (perms.length) {
    var byKey = {}; perms.forEach(function (p) { byKey[p.dataset.perm] = p; });
    var count = function () { var n = perms.filter(function (p) { return p.checked; }).length, c = $('[data-perm-count]'); if (c) c.textContent = n; };
    var off = function (key) { perms.forEach(function (p) { if (p.dataset.needs === key && p.checked && !p.disabled) { p.checked = false; off(p.dataset.perm); } }); };
    perms.forEach(function (p) {
      p.addEventListener('change', function () {
        if (p.checked) { var n = p.dataset.needs; while (n && byKey[n]) { if (!byKey[n].disabled) byKey[n].checked = true; n = byKey[n].dataset.needs; } }
        else off(p.dataset.perm);
        count();
      });
    });
    count();
  }

  var form = $('form[data-guard]'); if (!form) return;
  var saves = all('[data-save]'), req = (form.dataset.required || '').split(',').filter(Boolean), saving = false;
  var ser = function () { return new URLSearchParams(new FormData(form)).toString(); }, snap = ser();
  var dirty = function () { return !saving && ser() !== snap; };
  var email = $('input[data-orig]', form), pwRow = $('[data-email-pw]', form);

  function refresh() {
    if (email && pwRow) {
      var on = email.value.trim().toLowerCase() !== email.dataset.orig.toLowerCase(), was = !pwRow.hidden; pwRow.hidden = !on;
      if (!on && was) { var p = $('input', pwRow); p.value = ''; }
    }
    var d = dirty(); saves.forEach(function (b) { b.disabled = !d; });
  }
  form.addEventListener('input', refresh); form.addEventListener('change', refresh);
  if (ser() !== snap || $('[aria-invalid]', form) || form.hasAttribute('data-new')) { snap = ''; }   // the server sent the form back with errors: keep Save available
  refresh();

  function mark(el, msg) {
    el.setAttribute('aria-invalid', 'true'); var p = document.createElement('p'); p.className = 'field-error'; p.textContent = msg; (el.closest('.kselect') || el).parentNode.appendChild(p);
  }
  form.addEventListener('submit', function (e) {
    if (saving) { e.preventDefault(); return; }
    all('.field-error', form).forEach(function (x) { x.remove(); }); all('[aria-invalid]', form).forEach(function (x) { x.removeAttribute('aria-invalid'); });
    var bad = false;
    req.forEach(function (n) { var el = form.elements[n]; if (el && !el.value.trim()) { bad = true; mark(el, 'This field is required.'); } });
    if (pwRow && !pwRow.hidden) { var pw = form.elements.current_password; if (!pw.value) { bad = true; mark(pw, 'This field is required.'); } }
    if (bad) { e.preventDefault(); var f = $('[aria-invalid]', form); f && f.focus(); return; }
    saving = true; saves.forEach(function (b) { b.disabled = true; b.textContent = 'Saving…'; });
  });

  /* leaving with unsaved changes */
  var leave = $('#leave-modal'), target = null;
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href]'); if (!a || !leave || !dirty() || a.target === '_blank' || e.metaKey || e.ctrlKey || e.shiftKey) return;
    if (a.getAttribute('href').charAt(0) === '#') return;
    e.preventDefault(); target = a.href; leave.showModal();
  });
  if (leave) {
    $('[data-leave-keep]', leave).addEventListener('click', function () { leave.close(); target = null; });
    $('[data-leave-yes]', leave).addEventListener('click', function () { saving = true; window.location.href = target; });
  }
  window.addEventListener('beforeunload', function (e) { if (dirty()) { e.preventDefault(); e.returnValue = ''; } });
})();
