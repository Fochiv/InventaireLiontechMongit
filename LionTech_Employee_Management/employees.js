/* ============================================================
   employees.js — LionTech Business Manager
   Handles: language, sidebar, modal, camera, role permissions,
            photo upload preview, pay type toggle
   ============================================================ */

/* ── TRANSLATIONS ── */
const translations = {
  en: {
    nav_main:'Main', nav_dashboard:'Dashboard', nav_employees:'Employees',
    nav_products:'Products', nav_stock_in:'Stock In', nav_stock_out:'Stock Out',
    nav_reports:'Reports', nav_settings:'Settings', nav_logout:'Logout',
    page_title:'Employee Management',
    page_subtitle:'Manage employees, roles, attendance, and access.',
    add_employee:'Add Employee',
    feature_locked_title:'Employee feature is not active',
    feature_locked_text:'This business does not have employee management enabled. Please contact LionTech.',
    expired_warning:'Subscription expired. Employee actions are disabled until renewal.',
    new_credentials:'New temporary credentials:',
    pin_notice:'Show this PIN to the employee once. They should change it after first login.',
    total_employees:'Total Employees', active_employees:'Active Employees',
    clocked_in:'Clocked In Today', inactive_employees:'Inactive Employees',
    search_placeholder:'Search employees...',
    all_roles:'All roles', all_statuses:'All statuses', active:'Active', inactive:'Inactive',
    employees_list:'Employees List', employee:'Employee', phone:'Phone',
    role:'Role', status:'Status', actions:'Actions',
    no_employees:'No employees yet. Add your first employee.',
    reset_pin:'Reset PIN', actions_disabled:'Actions disabled',
    today_attendance:"Today's Attendance",
    locked_times:'Clock times are locked after submission.',
    no_attendance:'No clock-in records for today yet.',
    add_employee_title:'Add Employee',
    section_photo:'📷 Profile Photo',
    photo_hint:'Click to upload or use camera',
    use_camera:'Take a photo',
    section_identity:'👤 Identity',
    first_name:'First Name', last_name:'Last Name',
    date_of_birth:'Date of Birth', gender:'Gender',
    male:'Male', female:'Female', other_gender:'Other',
    id_card:'ID Card / National ID Number',
    id_card_hint:'Optional — used for internal verification only',
    section_contact:'📞 Contact & Address',
    phone_required:'Phone', emergency_phone:'Emergency Phone', address:'Home Address',
    section_role:'💼 Role & Position',
    employee_role:'Role', job_title:'Job Title',
    role_employee:'Employee', role_cashier:'Cashier',
    role_stock:'Stock Manager', role_team_lead:'Team Lead',
    role_manager:'Manager', role_other:'Other',
    custom_role_ph:'Specify the role...',
    section_permissions:'🔐 Access & Permissions',
    permissions_hint:'Choose what this employee can do in the system.',
    perm_view_products:'View products',
    perm_stock_in:'Add stock in',
    perm_stock_out:'Add stock out',
    perm_view_reports:'View reports',
    perm_manage_employees:'Manage employees',
    perm_approve_stock:'Approve stock movements',
    perm_clock:'Clock in / Clock out',
    perm_notifications:'View notifications',
    section_pay:'💰 Pay (optional)',
    pay_type:'Pay Type', pay_monthly:'Monthly salary',
    pay_hourly:'Hourly rate', pay_daily:'Daily rate',
    pay_amount:'Amount (XAF)',
    username_preview:'Username preview:',
    auto_pin:'A 6-digit temporary PIN will be generated automatically.',
    cancel:'Cancel', create_employee:'Create Employee',
    snap_photo:'📸 Take photo', close_camera:'✕ Close',

    // In en: {}
btn_view: 'View',
btn_schedule: 'Schedule',
btn_reset_pin: 'Reset PIN',
btn_leave: 'Leave',
btn_deactivate: 'Deactivate',
btn_activate: 'Activate',
at_work: 'At work',
off_work: 'Off',
on_leave: 'On Leave',
col_clock: 'Clock',
col_status: 'Status',
col_actions: 'Actions',
col_employee: 'Employee',
col_phone: 'Phone',
col_role: 'Role',
  },
  fr: {
    nav_main:'Menu', nav_dashboard:'Tableau de bord', nav_employees:'Employés',
    nav_products:'Produits', nav_stock_in:'Stock entrant', nav_stock_out:'Stock sortant',
    nav_reports:'Rapports', nav_settings:'Paramètres', nav_logout:'Déconnexion',
    page_title:'Gestion des employés',
    page_subtitle:'Gérer les employés, rôles, présence et accès.',
    add_employee:'Ajouter employé',
    feature_locked_title:'Fonction employés non active',
    feature_locked_text:"Ce business n'a pas la gestion des employés activée. Contactez LionTech.",
    expired_warning:"Abonnement expiré. Actions désactivées jusqu'au renouvellement.",
    new_credentials:'Nouveaux identifiants temporaires :',
    pin_notice:"Montrez ce PIN à l'employé une seule fois. Il devra le changer après sa première connexion.",
    total_employees:'Total employés', active_employees:'Employés actifs',
    clocked_in:"Présents aujourd'hui", inactive_employees:'Employés inactifs',
    search_placeholder:'Rechercher un employé...',
    all_roles:'Tous les rôles', all_statuses:'Tous les statuts',
    active:'Actif', inactive:'Inactif',
    employees_list:'Liste des employés', employee:'Employé', phone:'Téléphone',
    role:'Rôle', status:'Statut', actions:'Actions',
    no_employees:'Aucun employé pour le moment. Ajoutez le premier employé.',
    reset_pin:'Réinitialiser PIN', actions_disabled:'Actions désactivées',
    today_attendance:'Présence du jour',
    locked_times:'Les heures de pointage sont verrouillées après soumission.',
    no_attendance:"Aucun pointage pour aujourd'hui.",
    add_employee_title:'Ajouter un employé',
    section_photo:'📷 Photo de profil',
    photo_hint:'Cliquez pour uploader ou utilisez la caméra',
    use_camera:'Prendre une photo',
    section_identity:'👤 Identité',
    first_name:'Prénom', last_name:'Nom',
    date_of_birth:'Date de naissance', gender:'Genre',
    male:'Homme', female:'Femme', other_gender:'Autre',
    id_card:"Numéro CNI / Carte d'identité",
    id_card_hint:'Optionnel — utilisé pour vérification interne uniquement',
    section_contact:'📞 Contact & Adresse',
    phone_required:'Téléphone', emergency_phone:"Téléphone d'urgence",
    address:'Adresse de résidence',
    section_role:'💼 Rôle & Poste',
    employee_role:'Rôle', job_title:'Titre du poste',
    role_employee:'Employé', role_cashier:'Caissier(ère)',
    role_stock:'Gestionnaire stock', role_team_lead:"Chef d'équipe",
    role_manager:'Manager', role_other:'Autre',
    custom_role_ph:'Précisez le rôle...',
    section_permissions:'🔐 Accès & Permissions',
    permissions_hint:'Choisissez ce que cet employé peut faire dans le système.',
    perm_view_products:'Voir les produits',
    perm_stock_in:'Ajouter stock entrant',
    perm_stock_out:'Ajouter stock sortant',
    perm_view_reports:'Voir les rapports',
    perm_manage_employees:'Gérer les employés',
    perm_approve_stock:'Approuver mouvements stock',
    perm_clock:'Clock in / Clock out',
    perm_notifications:'Voir les notifications',
    section_pay:'💰 Rémunération (optionnel)',
    pay_type:'Type de rémunération', pay_monthly:'Salaire mensuel',
    pay_hourly:'Taux horaire', pay_daily:'Taux journalier',
    pay_amount:'Montant (XAF)',
    username_preview:'Aperçu username :',
    auto_pin:'Un PIN à 6 chiffres sera généré automatiquement.',
    cancel:'Annuler', create_employee:"Créer l'employé",
    snap_photo:'📸 Prendre la photo', close_camera:'✕ Fermer',
    // In fr: {}
btn_view: 'Voir',
btn_schedule: 'Horaire',
btn_reset_pin: 'Réinitialiser PIN',
btn_leave: 'Congé',
btn_deactivate: 'Désactiver',
btn_activate: 'Activer',
at_work: 'Au travail',
off_work: 'Absent',
on_leave: 'En congé',
col_clock: 'Présence',
col_status: 'Statut',
col_actions: 'Actions',
col_employee: 'Employé',
col_phone: 'Téléphone',
col_role: 'Rôle',
  }
};

/* ── Default permissions by role ── */
const rolePermissions = {
  employee:      ['view_products','clock_inout'],
  cashier:       ['view_products','stock_out','clock_inout','view_notifications'],
  stock_manager: ['view_products','stock_in','stock_out','clock_inout','view_notifications'],
  team_lead:     ['view_products','stock_in','stock_out','view_reports','clock_inout','view_notifications','approve_stock'],
  manager:       ['view_products','stock_in','stock_out','view_reports','manage_employees','clock_inout','view_notifications','approve_stock'],
  other:         ['view_products','clock_inout'],
};

/* ── Language ── */
let lang = localStorage.getItem('lt_lang') || 'fr';

function applyLang() {
  document.documentElement.lang = lang;
  document.querySelectorAll('[data-i18n]').forEach(el => {
    const k = el.dataset.i18n;
    if (translations[lang][k]) el.textContent = translations[lang][k];
  });
  document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
    const k = el.dataset.i18nPlaceholder;
    if (translations[lang][k]) el.placeholder = translations[lang][k];
  });
  const btn = document.getElementById('em-lang');
  if (btn) btn.textContent = lang === 'en' ? 'FR' : 'EN';
  localStorage.setItem('lt_lang', lang);
}

applyLang();
document.getElementById('em-lang')?.addEventListener('click', () => {
  lang = lang === 'en' ? 'fr' : 'en';
  applyLang();
  
});

/* ── Sidebar ── */
const sidebar  = document.getElementById('em-sidebar') || document.getElementById('od-sidebar');
const overlay  = document.getElementById('em-overlay');

document.getElementById('em-hamburger')?.addEventListener('click', () => {
  sidebar?.classList.add('open');
  overlay?.classList.add('show');
});
document.getElementById('em-sidebar-close')?.addEventListener('click', () => {
  sidebar?.classList.remove('open');
  overlay?.classList.remove('show');
});
overlay?.addEventListener('click', () => {
  sidebar?.classList.remove('open');
  overlay?.classList.remove('show');
});

/* ── Modal ── */
const modal = document.getElementById('addEmployeeModal');
function openModal()  { modal?.classList.add('show');    modal?.setAttribute('aria-hidden','false'); }
function closeModal() { modal?.classList.remove('show'); modal?.setAttribute('aria-hidden','true'); }

document.getElementById('openAddModal')?.addEventListener('click', openModal);
document.getElementById('closeAddModal')?.addEventListener('click', closeModal);
document.getElementById('cancelAdd')?.addEventListener('click', closeModal);
modal?.addEventListener('click', e => { if (e.target === modal) closeModal(); });

/* ── Username preview ── */
function clean(v) {
  return (v||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]/g,'');
}
function updatePreview() {
  const f = clean(document.getElementById('firstName')?.value);
  const l = clean(document.getElementById('lastName')?.value);
  const el = document.getElementById('usernamePreview');
  if (el) el.textContent = (f||'prenom') + (l||'nom') + '###';
}
document.getElementById('firstName')?.addEventListener('input', updatePreview);
document.getElementById('lastName')?.addEventListener('input',  updatePreview);
updatePreview();

/* ── Role → auto-set permissions ── */
const roleSelect = document.getElementById('employeeRoleSelect');
const otherBox   = document.getElementById('otherRoleBox');

roleSelect?.addEventListener('change', function() {
  /* Show/hide "other" text input */
  if (otherBox) otherBox.style.display = this.value === 'other' ? 'block' : 'none';

  /* Auto-check permissions for this role */
  const perms = rolePermissions[this.value] || rolePermissions['employee'];
  document.querySelectorAll('.em-perm input[type=checkbox]').forEach(cb => {
    cb.checked = perms.includes(cb.value);
  });
});

/* ── Pay type toggle ── */
document.getElementById('payTypeSelect')?.addEventListener('change', function() {
  const field = document.getElementById('payAmountField');
  if (field) field.style.display = this.value ? 'block' : 'none';
});

/* ── Photo upload preview ── */
document.getElementById('profilePhotoInput')?.addEventListener('change', function() {
  const file = this.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    const preview = document.getElementById('photoPreview');
    const placeholder = document.getElementById('photoPlaceholder');
    if (preview) { preview.src = e.target.result; preview.style.display = 'block'; }
    if (placeholder) placeholder.style.display = 'none';
  };
  reader.readAsDataURL(file);
});

/* ── Camera ── */
let cameraStream = null;

document.getElementById('openCamera')?.addEventListener('click', async () => {
  const overlay = document.getElementById('cameraOverlay');
  const video   = document.getElementById('cameraVideo');
  try {
    cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
    video.srcObject = cameraStream;
    overlay?.classList.add('show');
  } catch (err) {
    alert('Impossible d\'accéder à la caméra. Veuillez vérifier les permissions.');
  }
});

document.getElementById('snapPhoto')?.addEventListener('click', () => {
  const video   = document.getElementById('cameraVideo');
  const canvas  = document.getElementById('cameraCanvas');
  const overlay = document.getElementById('cameraOverlay');
  if (!video || !canvas) return;

  canvas.width  = video.videoWidth;
  canvas.height = video.videoHeight;
  canvas.getContext('2d').drawImage(video, 0, 0);
  const dataUrl = canvas.toDataURL('image/jpeg', 0.85);

  /* Show preview */
  const preview     = document.getElementById('photoPreview');
  const placeholder = document.getElementById('photoPlaceholder');
  if (preview)     { preview.src = dataUrl; preview.style.display = 'block'; }
  if (placeholder) placeholder.style.display = 'none';

  /* Store base64 */
  document.getElementById('photoBase64').value = dataUrl;

  /* Clear file input so it doesn't override the camera photo */
  const fileInput = document.getElementById('profilePhotoInput');
  if (fileInput) fileInput.value = '';

  stopCamera();
  overlay?.classList.remove('show');
});

/* ── Employee table filter ── */
const search       = document.getElementById('employeeSearch');
const roleFilter   = document.getElementById('roleFilter');
const statusFilter = document.getElementById('statusFilter');

function filterRows() {
  const q = (search?.value || '').toLowerCase();
  const r = roleFilter?.value   || '';
  const s = statusFilter?.value || '';
  document.querySelectorAll('#employeesTable tbody tr').forEach(row => {
    if (row.querySelector('.em-empty')) return;
    const okQ = (row.dataset.name || '').includes(q) || row.textContent.toLowerCase().includes(q);
    const okR = !r || row.dataset.role   === r;
    const okS = !s || row.dataset.status === s;
    row.style.display = (okQ && okR && okS) ? '' : 'none';
  });
}

search?.addEventListener('input',  filterRows);
roleFilter?.addEventListener('change', filterRows);
statusFilter?.addEventListener('change', filterRows);

/* ── View employee schedule ── */
const scheduleModal = document.getElementById('scheduleModal');
const dayNames = {
  fr: { monday:'Lundi', tuesday:'Mardi', wednesday:'Mercredi', thursday:'Jeudi', friday:'Vendredi', saturday:'Samedi', sunday:'Dimanche' },
  en: { monday:'Monday', tuesday:'Tuesday', wednesday:'Wednesday', thursday:'Thursday', friday:'Friday', saturday:'Saturday', sunday:'Sunday' }
};

function viewSchedule(userId, name) {
  document.getElementById('scheduleModalTitle').textContent = (lang === 'fr' ? 'Horaire de ' : 'Schedule for ') + name;
  document.getElementById('scheduleModalBody').innerHTML = '<p style="color:#94A3B8;text-align:center">Chargement...</p>';
  scheduleModal?.classList.add('show');

  fetch('?get_schedule=' + userId)
    .then(r => r.json())
    .then(data => {
      const body = document.getElementById('scheduleModalBody');
      if (data.empty || !data.schedule_id) {
        body.innerHTML = '<div style="text-align:center;padding:20px;color:#94A3B8">' +
          (lang === 'fr' ? '📅 Aucun horaire défini par cet employé.' : '📅 No schedule set by this employee.') +
          '</div>';
        return;
      }

      const days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
      const names = dayNames[lang] || dayNames.fr;

      let pillsHtml = '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px">';
      days.forEach(d => {
        const active = data[d] == 1;
        pillsHtml += `<span style="padding:5px 12px;border-radius:50px;font-size:12px;font-weight:700;background:${active?'#0B1F3A':'#F1F5F9'};color:${active?'#fff':'#94A3B8'}">${names[d]}</span>`;
      });
      pillsHtml += '</div>';

      let timeHtml = '';
      if (data.start_time && data.end_time) {
        timeHtml = `<div style="display:flex;gap:20px;font-size:14px;margin-bottom:12px">
          <span>🕐 <strong>${lang==='fr'?'Début':'Start'}:</strong> ${data.start_time.slice(0,5)}</span>
          <span>🕔 <strong>${lang==='fr'?'Fin':'End'}:</strong> ${data.end_time.slice(0,5)}</span>
        </div>`;
      }

      let notesHtml = data.notes
        ? `<div style="background:#F8FAFC;border-radius:8px;padding:10px 14px;font-size:13px;color:#64748B">📝 ${data.notes}</div>`
        : '';

      body.innerHTML = pillsHtml + timeHtml + notesHtml;
    })
    .catch(() => {
      document.getElementById('scheduleModalBody').innerHTML =
        '<p style="color:#991B1B;text-align:center">Erreur de chargement.</p>';
    });
}

function closeScheduleModal() {
  scheduleModal?.classList.remove('show');
}
scheduleModal?.addEventListener('click', e => { if (e.target === scheduleModal) closeScheduleModal(); });