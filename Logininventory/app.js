/* ============================================================
   app.js — LionTech Business Manager
   Fix: read JSON body before throwing on HTTP error status
   ============================================================ */

const LT = { lang: localStorage.getItem('lt_lang') || 'fr' };

const $  = id  => document.getElementById(id);
const $$ = sel => document.querySelectorAll(sel);

function applyLang(lang) {
  LT.lang = lang;
  localStorage.setItem('lt_lang', lang);
  const T = window.LT_LANG[lang];

  $$('[data-lt]').forEach(el => {
    const key = el.dataset.lt;
    if (T[key] !== undefined) {
      if (el.tagName === 'INPUT') el.placeholder = T[key];
      else el.textContent = T[key];
    }
  });

  const langBtn = $('lt-lang-btn');
  if (langBtn) langBtn.textContent = T.toggle;

  rebuildDemoTable(T);

  const btn = $('lt-btn-login');
  if (btn && !btn.classList.contains('loading'))
    btn.querySelector('span').textContent = T.btn_login;
}

function rebuildDemoTable(T) {
  const tbody = $('lt-demo-tbody');
  const thead = $('lt-demo-thead');
  if (!tbody || !thead) return;

  thead.innerHTML = '<tr>' + T.demo_cols.map(c => `<th>${c}</th>`).join('') + '</tr>';
  tbody.innerHTML = T.demo_rows.map(([role, lid, pwd]) => `
    <tr data-lid="${lid}" data-pwd="${pwd}">
      <td>${role}</td><td>${lid}</td><td>${pwd}</td>
    </tr>`).join('');

  tbody.querySelectorAll('tr').forEach(row => {
    row.addEventListener('click', () => {
      const li = $('lt-login-id'), pw = $('lt-password');
      if (li) { li.value = row.dataset.lid; li.classList.remove('error'); }
      if (pw) { pw.value = row.dataset.pwd; pw.classList.remove('error'); }
      clearAlert();
    });
  });

  const hint  = $('lt-demo-hint');
  const title = $('lt-demo-title-text');
  if (hint)  hint.textContent  = T.demo_hint;
  if (title) title.textContent = T.demo_title;
}

function showAlert(type, message) {
  const el = $('lt-alert');
  if (!el) return;
  el.className = `lt-alert ${type} visible`;
  const icons = { error:'⚠️', success:'✅', warning:'⚡' };
  el.innerHTML = `<span class="lt-alert-icon">${icons[type]||'•'}</span><span>${message}</span>`;
}
function clearAlert() {
  const el = $('lt-alert');
  if (el) el.className = 'lt-alert';
}

function initPasswordToggle() {
  const eyeBtn = $('lt-eye-btn'), pwdInput = $('lt-password');
  if (!eyeBtn || !pwdInput) return;

  const eyeOpen   = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>`;
  const eyeClosed = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>`;

  eyeBtn.innerHTML = eyeOpen;
  eyeBtn.addEventListener('click', () => {
    const hide = pwdInput.type === 'password';
    pwdInput.type    = hide ? 'text' : 'password';
    eyeBtn.innerHTML = hide ? eyeClosed : eyeOpen;
    eyeBtn.setAttribute('aria-label', hide ? 'Hide password' : 'Show password');
  });
}

function initDemo() {
  const demoEl = $('lt-demo'), header = $('lt-demo-header');
  if (!demoEl || !header) return;
  header.addEventListener('click', () => demoEl.classList.toggle('open'));
  if (window.innerWidth > 768) demoEl.classList.add('open');
}

function validateForm() {
  const loginId  = $('lt-login-id')?.value.trim() || '';
  const password = $('lt-password')?.value.trim()  || '';
  const T = window.LT_LANG[LT.lang];
  if (!loginId || !password) {
    showAlert('error', T.err_empty);
    if (!loginId)  $('lt-login-id')?.classList.add('error');
    if (!password) $('lt-password')?.classList.add('error');
    return false;
  }
  return true;
}

/* ─── LOGIN — fixed network error handling ─── */
async function handleLogin(e) {
  e.preventDefault();
  clearAlert();
  $$('.lt-input').forEach(el => el.classList.remove('error'));
  if (!validateForm()) return;

  const T        = window.LT_LANG[LT.lang];
  const btn      = $('lt-btn-login');
  const loginId  = $('lt-login-id').value.trim();
  const password = $('lt-password').value.trim();

  btn.classList.add('loading');
  btn.disabled = true;
  btn.querySelector('span').textContent = T.btn_loading;

  const resetBtn = () => {
    btn.classList.remove('loading');
    btn.disabled = false;
    btn.querySelector('span').textContent = T.btn_login;
  };

  try {
    const res = await fetch('auth.php', {
      method : 'POST',
      headers: { 'Content-Type': 'application/json' },
      body   : JSON.stringify({ login_id: loginId, password }),
    });

    /* ── KEY FIX: always read JSON body first, even on HTTP 4xx/5xx ── */
    let data;
    try {
      data = await res.json();
    } catch {
      /* auth.php returned non-JSON — PHP fatal error or wrong path */
      showAlert('error',
        res.status === 404
          ? 'auth.php not found. Check that the file exists.'
          : res.status >= 500
  ? 'Server error in auth.php. Open auth.php directly or check the XAMPP PHP error log.'
  : T.err_network
      );
      resetBtn();
      return;
    }

    if (data.success) {
      if (data.subscription_warning) {
        showAlert('warning', data.subscription_warning);
        await delay(1800);
      }
      showAlert('success', T.success_msg + ' (' + (T['role_' + data.role] || data.role) + ')');
      await delay(1200);
      window.location.href = data.redirect;

    } else {
      const msgMap = {
        empty_fields        : T.err_empty,
        invalid_credentials : T.err_invalid,
        account_inactive    : T.err_inactive,
        subscription_expired: data.redirect ? T.err_expired_own : T.err_expired_emp,
        too_many_attempts   : T.err_attempts,
      };
      /* Show the real server message if no mapping matches */
      showAlert('error', msgMap[data.code] || data.message || T.err_invalid);

      if (data.code === 'subscription_expired' && data.redirect) {
        await delay(2500);
        window.location.href = data.redirect;
      }
      resetBtn();
    }

  } catch (err) {
    /* True network failure — device offline, DNS failure, etc. */
    showAlert('error', T.err_network);
    resetBtn();
  }
}

function delay(ms) { return new Promise(res => setTimeout(res, ms)); }

document.addEventListener('DOMContentLoaded', () => {
  $('lt-lang-btn')?.addEventListener('click', () => {
    applyLang(LT.lang === 'en' ? 'fr' : 'en');
  });
  applyLang(LT.lang);
  initPasswordToggle();
  initDemo();
  $('lt-form')?.addEventListener('submit', handleLogin);
  $$('.lt-input').forEach(el => {
    el.addEventListener('input', () => { el.classList.remove('error'); clearAlert(); });
  });
});