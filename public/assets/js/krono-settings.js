/* Krono Settings: forms with an unsaved-changes guard. Plain JS.
   form[data-guard]      : Save buttons ([data-save], anywhere on the page) stay disabled until something changed.
                           Leaving by a page link while changed asks "Leave without saving?" (#leave-modal).
   data-required="a,b"   : fields that must be filled (inline "This field is required.").
   input[data-orig]      : e-mail field; while it differs from the saved address the [data-email-pw] row (current password) shows.
   [data-open=id] / [data-close] open and close a plain <dialog>. */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); }, all = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };

  all('[data-open]').forEach(function (b) { b.addEventListener('click', function () { var d = document.getElementById(b.dataset.open); d && d.showModal(); }); });
  all('dialog [data-close]').forEach(function (b) { b.addEventListener('click', function () { b.closest('dialog').close(); }); });

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
  if (ser() !== snap || $('[aria-invalid]', form)) { snap = ''; }   // the server sent the form back with errors: keep Save available
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
