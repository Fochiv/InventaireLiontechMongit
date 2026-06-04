/* ============================================================
   add_business.js — Add Business interactions
   Updated version for manual owner username
   ============================================================ */

(function () {

  const $ = (sel, root = document) => root.querySelector(sel);

  const form = $('#add-business-form');

  const menuBtn = $('#ab-hamburger');
  const sidebar = $('#ab-sidebar');
  const overlay = $('#ab-overlay');

  const fields = {
    business_name: $('#business_name'),
    business_type: $('#business_type'),
    city: $('#city'),
    phone: $('#phone'),

    owner_name: $('#owner_name'),
    owner_username: $('#owner_username'),
    owner_phone: $('#owner_phone'),

    plan_name: $('#plan_name'),
    amount: $('#amount'),
    start_date: $('#start_date'),
    end_date: $('#end_date')
  };

  /* ============================================================
     Mobile menu
     ============================================================ */

  function openMenu() {
    sidebar?.classList.add('open');
    overlay?.classList.add('show');
  }

  function closeMenu() {
    sidebar?.classList.remove('open');
    overlay?.classList.remove('show');
  }

  menuBtn?.addEventListener('click', openMenu);
  overlay?.addEventListener('click', closeMenu);

  /* ============================================================
     Error handling
     ============================================================ */

  function setError(input, message) {

    if (!input) return;

    const field = input.closest('.ab-field');

    if (!field) return;

    let err = field.querySelector('.ab-error');

    if (!err) {

      err = document.createElement('div');

      err.className = 'ab-error';

      field.appendChild(err);
    }

    field.classList.add('invalid');

    err.textContent = message;
  }

  function clearError(input) {

    if (!input) return;

    const field = input.closest('.ab-field');

    if (!field) return;

    field.classList.remove('invalid');

    const err = field.querySelector('.ab-error');

    if (err) err.textContent = '';
  }

  /* ============================================================
     Validation
     ============================================================ */

  function required(input, label) {

    if (!input) return true;

    if (!input.value.trim()) {

      setError(input, `${label} is required.`);

      return false;
    }

    clearError(input);

    return true;
  }

  function validPhone(input, label) {

    if (!required(input, label)) return false;

    const value = input.value.replace(/\s+/g, '');

    if (!/^[+0-9()\-]{6,25}$/.test(value)) {

      setError(
        input,
        `${label} must be a valid phone number.`
      );

      return false;
    }

    clearError(input);

    return true;
  }

  function validUsername(input) {

    if (!required(input, 'Owner username')) return false;

    const value = input.value.trim();

    if (!/^[a-zA-Z0-9._-]{3,100}$/.test(value)) {

      setError(
        input,
        'Username can only contain letters, numbers, dot, dash, or underscore.'
      );

      return false;
    }

    clearError(input);

    return true;
  }

  function validAmount() {

    const input = fields.amount;

    if (!required(input, 'Monthly price')) return false;

    const amount = Number(input.value);

    if (Number.isNaN(amount) || amount < 0) {

      setError(
        input,
        'Monthly price must be a valid number.'
      );

      return false;
    }

    clearError(input);

    return true;
  }

  function validDates() {

    let ok = true;

    if (!required(fields.start_date, 'Start date'))
      ok = false;

    if (!required(fields.end_date, 'Expiration date'))
      ok = false;

    const start = fields.start_date?.value;
    const end = fields.end_date?.value;

    if (start && end && end < start) {

      setError(
        fields.end_date,
        'Expiration date must be after start date.'
      );

      ok = false;
    }

    return ok;
  }

  function validate() {

    let ok = true;

    ok = required(fields.business_name, 'Business name') && ok;

    ok = required(fields.business_type, 'Business type') && ok;

    ok = required(fields.city, 'City') && ok;

    ok = validPhone(fields.phone, 'Business phone') && ok;

    ok = required(fields.owner_name, 'Owner full name') && ok;

    ok = validUsername(fields.owner_username) && ok;

    ok = validPhone(fields.owner_phone, 'Owner phone') && ok;

    ok = required(fields.plan_name, 'Plan') && ok;

    ok = validAmount() && ok;

    ok = validDates() && ok;

    return ok;
  }

  /* ============================================================
     Submit form
     ============================================================ */

  if (form) {

    form.addEventListener('submit', function (e) {

      const isValid = validate();

      if (!isValid) {

        e.preventDefault();

        const firstInvalid = $(
          '.ab-field.invalid input, .ab-field.invalid select, .ab-field.invalid textarea'
        );

        if (firstInvalid) {
          firstInvalid.focus();
        }

        return false;
      }

      const btn = $('#btn-create-business');

      if (btn) {

        btn.disabled = true;

        btn.innerHTML = '⏳ Création du business...';
      }
    });
  }

  /* ============================================================
     Live input cleaning
     ============================================================ */

  Object.values(fields).forEach((input) => {

    if (!input) return;

    input.addEventListener('input', () => {

      clearError(input);

      /* Auto format username */

      if (input === fields.owner_username) {

        input.value = input.value
          .trim()
          .replace(/\s+/g, '.')
          .toLowerCase();
      }
    });

    input.addEventListener('change', () => {

      clearError(input);
    });
  });

  /* ============================================================
     Live preview
     ============================================================ */

  const previewMap = {

    business_name: '#pv-business',
    business_type: '#pv-type',
    city: '#pv-city',

    owner_name: '#pv-owner',
    owner_phone: '#pv-owner-phone',

    plan_name: '#pv-plan',
    amount: '#pv-price',
    end_date: '#pv-expire'
  };

  function updatePreview() {

    Object.entries(previewMap).forEach(([name, selector]) => {

      const input = document.querySelector(
        `[name="${name}"]`
      );

      const preview = $(selector);

      if (!input || !preview) return;

      let value = input.value || '—';

      if (name === 'amount' && input.value) {

        value =
          `${Number(input.value).toLocaleString()} XAF`;
      }

      preview.textContent = value;
    });
  }

  Object.keys(previewMap).forEach((name) => {

    const input = document.querySelector(
      `[name="${name}"]`
    );

    if (!input) return;

    input.addEventListener('input', updatePreview);

    input.addEventListener('change', updatePreview);
  });

  updatePreview();

  /* ============================================================
     Auto-fill subscription price
     ============================================================ */

  const planSelect = $('#plan_name');

  if (planSelect) {

    planSelect.addEventListener('change', function () {

      const price = $('#amount');

      if (!price) return;

      const prices = {
        Basic: 15000,
        Standard: 25000,
        Premium: 50000,
        Trial: 0
      };

      if (prices[this.value] !== undefined) {

        price.value = prices[this.value];
      }

      updatePreview();
    });
  }

})();