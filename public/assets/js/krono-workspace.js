/* Create / upgrade workspace: show only the fields that belong to the chosen option. */
(function () {
  var box = document.querySelector('[data-ws-form]'); if (!box) return;
  function apply() {
    var r = box.querySelector('input[name=mode]:checked'), mode = r ? r.value : 'new';
    box.querySelectorAll('[data-when]').forEach(function (el) { el.hidden = el.getAttribute('data-when') !== mode; });
  }
  box.addEventListener('change', function (e) { if (e.target.name === 'mode') apply(); });
  apply();
})();
