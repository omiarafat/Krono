/* Sign-in pages: turns the one-code input into six boxes (keyboard, paste and backspace work). The real field stays as the form value. */
(function () {
  document.querySelectorAll('[data-otp]').forEach(function (box) {
    var real = box.querySelector('input'); if (!real) return;
    var n = 6, cells = [], row = document.createElement('div');
    row.className = 'auth-otp-row'; row.setAttribute('role', 'group'); row.setAttribute('aria-label', 'Six digit code');
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

  /* Password fields get a show / hide eye button on the right. */
  var EYE = '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>';
  var EYE_OFF = '<path d="M3 3l18 18M10.6 5.1A10.4 10.4 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6A16.6 16.6 0 0 0 2 12s3.5 7 10 7a9.7 9.7 0 0 0 4.4-1M9.9 9.9a3 3 0 0 0 4.2 4.2"/>';
  function svg(inner) { return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + inner + '</svg>'; }
  document.querySelectorAll('input[type="password"]').forEach(function (input) {
    if (input.closest('.pw-field')) return;
    var wrap = document.createElement('div'); wrap.className = 'pw-field';
    input.parentNode.insertBefore(wrap, input); wrap.appendChild(input);
    var b = document.createElement('button'); b.type = 'button'; b.className = 'pw-toggle'; b.setAttribute('aria-pressed', 'false'); b.setAttribute('aria-label', 'Show password'); b.innerHTML = svg(EYE);
    b.addEventListener('click', function () {
      var show = input.type === 'password'; input.type = show ? 'text' : 'password';
      b.setAttribute('aria-pressed', show ? 'true' : 'false'); b.setAttribute('aria-label', show ? 'Hide password' : 'Show password'); b.innerHTML = svg(show ? EYE_OFF : EYE);
      input.focus();
    });
    wrap.appendChild(b);
  });
})();
