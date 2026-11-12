/* Krono modals and toasts (Projects, Project Details and the pages after them). Plain JS.
   Form modal  : <dialog data-form-modal> containing one <form>.
     form attrs: data-defaults='{"field":"value"}'  values for a NEW record
                 data-required="name,start_date"      fields that must be filled
                 data-date-order="start_date,due_date" second date cannot be before the first
   Open buttons: data-modal-open="dialog-id"  (+ data-fill='{"field":"value"}' to EDIT: fills the form and uses the data-edit texts)
   Texts       : [data-m-title] [data-m-sub] [data-m-submit] each with data-new="..." and data-edit="..."
                 [data-only="new|edit"] is shown in that mode only; [data-fill-text="key"] shows that key of data-fill (e.g. the locked project name)
   A field with data-dependent fires "change" after it is filled, so a field that depends on it (Assignee on Project) can rebuild first.
   Closing with unsaved input asks "Discard unsaved changes?" (#discard-modal). */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); }, all = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
  var sync = function (root) { window.KronoUI && window.KronoUI.sync(root); };

  /* toast: success fades after 6s, errors stay until closed */
  all('[data-toast]').forEach(function (x) {
    var kill = function () { x.parentNode && x.parentNode.remove(); };
    $('[data-toast-close]', x).addEventListener('click', kill); if (x.dataset.toast === 'auto') setTimeout(kill, 6000);
  });
  /* menus: close on outside click */
  document.addEventListener('click', function (e) { all('[data-menu][open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); }); });
  function closeMenus() { all('[data-menu][open]').forEach(function (d) { d.removeAttribute('open'); }); }

  var discard = $('#discard-modal'), asking = null;
  function ser(form) { return new URLSearchParams(new FormData(form)).toString(); }
  function clearErrors(form) {
    all('.field-error', form).forEach(function (x) { x.remove(); });
    all('[aria-invalid]', form).forEach(function (x) { x.removeAttribute('aria-invalid'); });
  }
  function mark(el, msg) {
    el.setAttribute('aria-invalid', 'true'); var p = document.createElement('p'); p.className = 'field-error'; p.textContent = msg; el.parentNode.appendChild(p);
  }
  function dueMin(form) {
    var o = (form.dataset.dateOrder || '').split(','); if (o.length !== 2) return;
    var a = form.elements[o[0]], b = form.elements[o[1]]; if (a.value) b.setAttribute('min', a.value); else b.removeAttribute('min');
  }
  function texts(dlg, edit, fill) {
    ['title', 'sub', 'submit'].forEach(function (k) {
      var el = $('[data-m-' + k + ']', dlg); if (el) el.textContent = el.getAttribute(edit ? 'data-edit' : 'data-new') || el.textContent;
    });
    all('[data-only]', dlg).forEach(function (el) { el.hidden = el.dataset.only !== (edit ? 'edit' : 'new'); });
    all('[data-fill-text]', dlg).forEach(function (el) { if (fill && fill[el.dataset.fillText] != null) el.textContent = fill[el.dataset.fillText]; });
  }
  function requestClose(dlg) {
    var form = $('form', dlg);
    if (form && ser(form) !== dlg._snap && !dlg.hasAttribute('data-saving') && discard) { asking = dlg; discard.showModal(); } else dlg.close();
  }

  all('dialog[data-form-modal]').forEach(function (dlg) {
    var form = $('form', dlg), submit = $('[data-m-submit]', dlg), req = (form.dataset.required || '').split(',').filter(Boolean);
    dlg._snap = ser(form);
    all('[data-modal-close]', dlg).forEach(function (b) { b.addEventListener('click', function () { requestClose(dlg); }); });
    dlg.addEventListener('cancel', function (e) { e.preventDefault(); requestClose(dlg); });
    dlg.addEventListener('mousedown', function (e) { if (e.target === dlg) requestClose(dlg); });
    var o = (form.dataset.dateOrder || '').split(','); if (o.length === 2) { form.elements[o[0]].addEventListener('change', function () { dueMin(form); }); dueMin(form); }
    form.addEventListener('submit', function (e) {
      if (dlg.hasAttribute('data-saving')) { e.preventDefault(); return; }
      clearErrors(form); var bad = false;
      req.forEach(function (n) { var el = form.elements[n]; if (!el.value.trim()) { bad = true; mark(el, 'This field is required.'); } });
      if (o.length === 2) { var a = form.elements[o[0]].value, b = form.elements[o[1]].value; if (a && b && b < a) { bad = true; mark(form.elements[o[1]], 'Due date cannot be before the start date.'); } }
      if (bad) { e.preventDefault(); sync(form); var f = $('[aria-invalid]', form); f && (f._kfocus || f).focus(); return; }
      dlg.setAttribute('data-saving', ''); submit.disabled = true; submit.textContent = 'Saving…';
    });
    if (dlg.hasAttribute('data-autoopen')) {   // the server sent the form back with errors: show it again with the values kept
      texts(dlg, !!(form.elements.id && form.elements.id.value));
      dlg.showModal(); var first = $('[aria-invalid]', form) || $('input:not([type=hidden]), textarea', form); first && (first._kfocus || first).focus();
    }
  });

  all('[data-modal-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var dlg = document.getElementById(btn.dataset.modalOpen), form = $('form', dlg), fill = btn.dataset.fill ? JSON.parse(btn.dataset.fill) : null;
      closeMenus(); form.reset(); clearErrors(form);
      var vals = Object.assign({}, JSON.parse(form.dataset.defaults || '{}'), fill || {});
      Object.keys(vals).forEach(function (n) {
        var el = form.elements[n]; if (!el) return; el.value = vals[n] == null ? '' : vals[n];
        if (el.hasAttribute('data-dependent')) el.dispatchEvent(new Event('change'));
      });
      dueMin(form); sync(form); texts(dlg, !!fill, fill);
      dlg.showModal(); dlg._snap = ser(form); var f = $('input:not([type=hidden]), textarea', form); f && f.focus();
    });
  });

  if (discard) {
    $('[data-discard-keep]', discard).addEventListener('click', function () { discard.close(); asking = null; });
    $('[data-discard-yes]', discard).addEventListener('click', function () { discard.close(); asking && asking.close(); asking = null; });
  }

  /* archive confirmation */
  var am = $('#archive-modal');
  if (am) {
    all('[data-project-archive]').forEach(function (b) {
      b.addEventListener('click', function () {
        closeMenus(); $('form', am).elements.id.value = b.dataset.projectArchive; $('[data-am-name]', am).textContent = b.dataset.name;
        var t = +b.dataset.tasks, l = +b.dataset.logs;
        $('[data-am-counts]', am).textContent = t + ' task' + (t === 1 ? '' : 's') + ' and ' + l + ' saved time log' + (l === 1 ? '' : 's') + ' are linked to this project.'; am.showModal();
      });
    });
    all('[data-modal-close]', am).forEach(function (b) { b.addEventListener('click', function () { am.close(); }); });
    $('form', am).addEventListener('submit', function () { var b = $('.btn-danger', am); b.disabled = true; b.textContent = 'Archiving…'; });
  }

  /* delete task: button carries data-task-delete (id), data-name, data-logs, data-time */
  var dt = $('#delete-task-modal');
  if (dt) {
    all('[data-task-delete]', document).forEach(function (b) {
      b.addEventListener('click', function () {
        var n = +b.dataset.logs, linked = n > 0; $('form', dt).elements.id.value = b.dataset.taskDelete; $('[data-dt-name]', dt).textContent = '“' + b.dataset.name + '”';
        all('[data-dt-free]', dt).forEach(function (x) { x.hidden = linked; }); all('[data-dt-linked]', dt).forEach(function (x) { x.hidden = !linked; });
        $('[data-dt-counts]', dt).textContent = n + ' saved time log' + (n === 1 ? '' : 's') + ' (' + b.dataset.time + ')'; $('[data-dt-link]', dt).href = b.dataset.href; dt.showModal();
      });
    });
    all('[data-modal-close]', dt).forEach(function (b) { b.addEventListener('click', function () { dt.close(); }); });
    $('form', dt).addEventListener('submit', function () { var b = $('.btn-danger', dt); b.disabled = true; b.textContent = 'Deleting…'; });
  }
})();
