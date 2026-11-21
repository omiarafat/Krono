/* Sign-in pages: turns the one-code input into six boxes (keyboard, paste and backspace work). The real field stays as the form value. */
(function () {
  document.querySelectorAll('[data-otp]').forEach(function (box) {
    var real = box.querySelector('input'); if (!real) return;
    var n = 6, cells = [], row = document.createElement('div');
    row.className = 'grid grid-cols-6 gap-2'; row.setAttribute('role', 'group'); row.setAttribute('aria-label', 'Six digit code');
    real.type = 'hidden'; real.removeAttribute('autofocus');
    function sync() { real.value = cells.map(function (c) { return c.value; }).join(''); }
    function fill(from, text) { text.replace(/\D/g, '').split('').slice(0, n - from).forEach(function (d, i) { cells[from + i].value = d; }); sync(); var last = Math.min(n - 1, from + text.replace(/\D/g, '').length); cells[last].focus(); }
    for (var i = 0; i < n; i++) (function (i) {
      var c = document.createElement('input'); c.className = 'auth-otp'; c.inputMode = 'numeric'; c.maxLength = 1; c.autocomplete = i ? 'off' : 'one-time-code'; c.setAttribute('aria-label', 'Digit ' + (i + 1));
      c.addEventListener('input', function () { c.value = c.value.replace(/\D/g, '').slice(-1); sync(); if (c.value && i < n - 1) cells[i + 1].focus(); });
      c.addEventListener('keydown', function (e) { if (e.key === 'Backspace' && !c.value && i) { cells[i - 1].value = ''; cells[i - 1].focus(); sync(); } if (e.key === 'ArrowLeft' && i) cells[i - 1].focus(); if (e.key === 'ArrowRight' && i < n - 1) cells[i + 1].focus(); });
      c.addEventListener('paste', function (e) { e.preventDefault(); fill(i, (e.clipboardData || window.clipboardData).getData('text')); });
      cells.push(c); row.appendChild(c);
    })(i);
    box.appendChild(row); cells[0].focus();
  });
})();
