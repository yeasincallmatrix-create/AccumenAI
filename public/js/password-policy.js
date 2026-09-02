/**
 * Lightweight client-side password policy UX.
 * UX only — backend validation via PasswordPolicy is authoritative.
 * No plaintext persistence, no localStorage, no console.log, no extra requests.
 */
(function () {
  function score(pwd) {
    let s = 0;
    if (pwd.length >= 8) s++;
    if (/[A-Z]/.test(pwd)) s++;
    if (/[a-z]/.test(pwd)) s++;
    if (/[0-9]/.test(pwd)) s++;
    if (/[^A-Za-z0-9]/.test(pwd)) s++;
    return s;
  }
  function labelAndWidth(scoreVal, pwdLen) {
    if (!pwdLen) return { label: 'Enter a password', width: 0, cls: 'bg-secondary' };
    if (scoreVal <= 2) return { label: 'Weak', width: 33, cls: 'bg-danger' };
    if (scoreVal <= 4) return { label: 'Fair', width: 66, cls: 'bg-warning' };
    return { label: 'Strong', width: 100, cls: 'bg-success' };
  }
  document.querySelectorAll('[data-password-policy]').forEach(function (wrap) {
    var field = wrap.getAttribute('data-field') || 'password';
    var confirmField = wrap.getAttribute('data-confirm') || 'password_confirmation';
    var form = wrap.closest('form');
    if (!form) return;
    var pwdEl = form.querySelector('[name="' + field + '"]');
    var confirmEl = form.querySelector('[name="' + confirmField + '"]');
    if (!pwdEl) return;
    var matchLi = wrap.querySelector('[data-req="match"]');
    var bar = wrap.querySelector('[data-strength-bar]');
    var lab = wrap.querySelector('[data-strength-label]');
    // Show match row only when confirm field exists
    if (confirmEl && matchLi) matchLi.classList.remove('d-none');
    function update() {
      var v = pwdEl.value || '';
      var checks = {
        length: v.length >= 8,
        upper: /[A-Z]/.test(v),
        lower: /[a-z]/.test(v),
        number: /[0-9]/.test(v),
        symbol: /[^A-Za-z0-9]/.test(v),
      };
      Object.keys(checks).forEach(function (k) {
        var li = wrap.querySelector('[data-req="' + k + '"]');
        if (!li) return;
        var icon = li.querySelector('.req-icon');
        if (checks[k]) {
          li.classList.add('text-success'); li.classList.remove('text-muted');
          if (icon) { icon.textContent = '✓'; icon.className = 'req-icon text-success fw-bold'; }
        } else {
          li.classList.remove('text-success'); li.classList.add('text-muted');
          if (icon) { icon.textContent = '○'; icon.className = 'req-icon'; }
        }
      });
      if (matchLi && confirmEl) {
        var matched = v !== '' && confirmEl.value !== '' && v === confirmEl.value;
        var mIcon = matchLi.querySelector('.req-icon');
        if (matched) {
          matchLi.classList.add('text-success'); matchLi.classList.remove('text-muted');
          if (mIcon) { mIcon.textContent = '✓'; mIcon.className = 'req-icon text-success fw-bold'; }
        } else {
          matchLi.classList.remove('text-success'); matchLi.classList.add('text-muted');
          if (mIcon) { mIcon.textContent = '○'; mIcon.className = 'req-icon'; }
        }
      }
      var s = score(v);
      var info = labelAndWidth(s, v.length);
      if (bar) {
        bar.style.width = info.width + '%';
        bar.className = 'progress-bar ' + info.cls;
        bar.setAttribute('aria-valuenow', info.width);
      }
      if (lab) lab.textContent = info.label;
    }
    pwdEl.addEventListener('input', update);
    if (confirmEl) confirmEl.addEventListener('input', update);
    update();
  });
})();
