/* Invitation link page: copy the link with one click; the field stays selectable as a fallback. */
(function () {
  var box = document.querySelector('[data-invite-link]'); if (!box) return;
  var input = box.querySelector('[data-link-input]'), btn = box.querySelector('[data-copy]'), label = box.querySelector('[data-copy-label]'), note = box.querySelector('[data-copy-note]');
  input.addEventListener('focus', function () { input.select(); });
  function done(ok) {
    label.textContent = ok ? 'Copied' : 'Copy failed';
    if (!ok) { input.focus(); input.select(); note.textContent = 'Copy did not work here. The link is selected, press Ctrl+C (or Cmd+C).'; }
    setTimeout(function () { label.textContent = 'Copy link'; }, 2000);
  }
  btn.addEventListener('click', function () {
    if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(input.value).then(function () { done(true); }, function () { done(false); }); return; }
    input.focus(); input.select(); var ok = false; try { ok = document.execCommand('copy'); } catch (e) {} done(ok);
  });
})();
