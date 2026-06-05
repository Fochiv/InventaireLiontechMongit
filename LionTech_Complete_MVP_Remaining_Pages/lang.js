/* ============================================================
   lang.js — LionTech Business Manager EN / FR Translations
   ============================================================ */
window.LT_LANG = {

/* ─────────────── ENGLISH ─────────────── */
en: {
  toggle        : 'FR',
  app_name      : 'LionTech Business Manager',
  app_subtitle  : 'Manage your business, inventory, employees, and reports — all in one place.',

  /* Left panel */
  welcome       : 'Welcome to LionTech Business Manager',
  welcome_sub   : 'The all-in-one platform designed for African businesses of every size.',
  feat1         : 'Real-time inventory tracking',
  feat2         : 'Employee attendance & payroll',
  feat3         : 'Detailed business reports',
  feat4         : 'Multi-business & multi-branch support',
  feat5         : 'Role-based secure access',

  /* Form */
  login_title   : 'Sign In to Your Account',
  login_id_label: 'Login ID',
  login_id_ph   : 'Email, phone number, username, or employee code',
  password_label: 'Password / PIN',
  password_ph   : 'Enter your password or PIN',
  btn_login     : 'Login',
  btn_loading   : 'Signing in…',
  forgot        : 'Forgot password?',
  support       : 'Contact LionTech for support',

  /* Demo credentials */
  demo_title    : 'Demo credentials',
  demo_hint     : 'Click any row to auto-fill the form',
  demo_cols     : ['Role', 'Login ID', 'Password'],
  demo_rows: [
    ['Super Admin',     'admin@liontech.com', 'Admin@123'],
    ['Business Owner',  '675100001',          'Owner@123'],
    ['Expired Owner',   'bernard@beta.com',   'Owner@123'],
    ['Manager',         'manager@acme.com',   'Mgr@123'],
    ['Employee',        'EMP001',             'Emp@1234'],
    ['Inactive',        'EMP099',             'Emp@1234'],
  ],

  /* Errors & messages */
  err_empty       : 'Please fill in both fields.',
  err_invalid     : 'Invalid login ID or password.',
  err_inactive    : 'Your account is inactive. Please contact LionTech.',
  err_expired_own : 'Your subscription has expired. Redirecting to payment page…',
  err_expired_emp : 'Your business subscription has expired. Contact your business owner.',
  err_attempts    : 'Too many failed attempts. Please wait 15 minutes.',
  err_network     : 'Network error. Please check your connection.',
  warn_trial      : 'You are on a trial plan. Upgrade to avoid service interruption.',
  success_msg     : 'Login successful! Redirecting…',

  /* Role display names */
  role_super_admin    : 'Super Admin',
  role_business_owner : 'Business Owner',
  role_manager        : 'Manager',
  role_employee       : 'Employee',

  /* Footer */
  footer_copy : '© 2025 LionTech. All rights reserved.',
  footer_privacy : 'Privacy Policy',
  footer_terms   : 'Terms of Service',
},

/* ─────────────── FRANÇAIS ─────────────── */
fr: {
  toggle        : 'EN',
  app_name      : 'LionTech Business Manager',
  app_subtitle  : 'Gérez votre entreprise, votre stock, vos employés et vos rapports — en un seul endroit.',

  /* Left panel */
  welcome       : 'Bienvenue sur LionTech Business Manager',
  welcome_sub   : 'La plateforme tout-en-un conçue pour les entreprises africaines de toute taille.',
  feat1         : 'Suivi des stocks en temps réel',
  feat2         : 'Présences des employés et paie',
  feat3         : 'Rapports détaillés d\'activité',
  feat4         : 'Support multi-entreprises et multi-succursales',
  feat5         : 'Accès sécurisé par rôle',

  /* Form */
  login_title   : 'Connectez-vous à votre compte',
  login_id_label: 'Identifiant',
  login_id_ph   : 'Email, téléphone, nom d\'utilisateur ou code employé',
  password_label: 'Mot de passe / PIN',
  password_ph   : 'Entrez votre mot de passe ou PIN',
  btn_login     : 'Se connecter',
  btn_loading   : 'Connexion en cours…',
  forgot        : 'Mot de passe oublié ?',
  support       : 'Contacter le support LionTech',

  /* Demo credentials */
  demo_title    : 'Identifiants de démo',
  demo_hint     : 'Cliquez sur une ligne pour remplir le formulaire',
  demo_cols     : ['Rôle', 'Identifiant', 'Mot de passe'],
  demo_rows: [
    ['Super Admin',        'admin@liontech.com', 'Admin@123'],
    ['Propriétaire',       '675100001',          'Owner@123'],
    ['Propriétaire expiré','bernard@beta.com',   'Owner@123'],
    ['Gérant',             'manager@acme.com',   'Mgr@123'],
    ['Employé',            'EMP001',             'Emp@1234'],
    ['Inactif',            'EMP099',             'Emp@1234'],
  ],

  /* Errors & messages */
  err_empty       : 'Veuillez remplir les deux champs.',
  err_invalid     : 'Identifiant ou mot de passe invalide.',
  err_inactive    : 'Votre compte est inactif. Contactez LionTech.',
  err_expired_own : 'Votre abonnement a expiré. Redirection vers la page de paiement…',
  err_expired_emp : 'L\'abonnement de votre entreprise a expiré. Contactez votre propriétaire.',
  err_attempts    : 'Trop de tentatives. Veuillez attendre 15 minutes.',
  err_network     : 'Erreur réseau. Vérifiez votre connexion.',
  warn_trial      : 'Vous êtes en période d\'essai. Passez à un plan payant pour éviter une interruption.',
  success_msg     : 'Connexion réussie ! Redirection en cours…',

  /* Role display names */
  role_super_admin    : 'Super Administrateur',
  role_business_owner : 'Propriétaire',
  role_manager        : 'Gérant',
  role_employee       : 'Employé',

  /* Footer */
  footer_copy    : '© 2025 LionTech. Tous droits réservés.',
  footer_privacy : 'Politique de confidentialité',
  footer_terms   : 'Conditions d\'utilisation',
},

}; /* end LT_LANG */
