/* ================================================================
   global_i18n.js — Universal EN/FR translation engine
   Loaded via Sidebar.php — covers ALL pages automatically.
   Works by:
     1. Translating [data-i18n] and [data-lt] elements (standard approach)
     2. Translating h1/h2/h3/th by text-content lookup (catches hardcoded text)
     3. Intercepting localStorage('lt_lang') so any page's lang toggle
        also fires the global engine — no per-page JS edits needed.
   ================================================================ */
(function () {
  'use strict';

  /* ── Master dictionary (French → English) ── */
  const FR_EN = {
    /* Page titles & subtitles */
    'Tableau de bord'                    : 'Dashboard',
    'Centre de Validation'               : 'Validation Center',
    'Approuvez ou refusez les demandes de stock des employés'
                                         : 'Approve or reject employee stock requests',
    'Abonnement & Paiement'              : 'Subscription & Payment',
    'Plan actuel, paiements et renouvellement'
                                         : 'Current plan, payments and renewal',
    'Produits'                           : 'Products',
    'Inventaire non activé'              : 'Inventory not activated',
    'Stock entrant'                      : 'Stock In',
    'Stock sortant'                      : 'Stock Out',
    'Employés'                           : 'Employees',
    'Présences'                          : 'Attendance',
    'Rapports'                           : 'Reports',
    'Paramètres'                         : 'Settings',
    'Notifications'                      : 'Notifications',
    'Activité'                           : 'Activity',
    "Journaux d'activité"                : 'Activity Logs',
    'Validations'                        : 'Validations',
    'Profil'                             : 'Profile',

    /* Section headings */
    'Demandes Stock Entrant'             : 'Incoming Stock Requests',
    'Demandes Stock Sortant'             : 'Outgoing Stock Requests',
    'Plan actuel'                        : 'Current Plan',
    'Statut de votre abonnement'         : 'Your subscription status',
    'Fonctionnalités incluses'           : 'Included Features',
    'Soumettre un paiement'              : 'Submit a Payment',
    'Historique des paiements'           : 'Payment History',
    'Liste des produits'                 : 'Product List',
    'Ajouter un produit'                 : 'Add a Product',
    'Détails du produit'                 : 'Product Details',
    'Aperçu'                             : 'Overview',
    'Alertes'                            : 'Alerts',
    'Activité récente'                   : 'Recent Activity',

    /* Table headers */
    'Produit'                            : 'Product',
    'Quantité'                           : 'Quantity',
    'Qté'                                : 'Qty',
    'Fournisseur'                        : 'Supplier',
    'Demandé par'                        : 'Requested by',
    'Date'                               : 'Date',
    'Preuve'                             : 'Proof',
    'Actions'                            : 'Actions',
    'Action'                             : 'Action',
    'Raison'                             : 'Reason',
    'Motif'                              : 'Reason',
    'Statut'                             : 'Status',
    'Montant'                            : 'Amount',
    'Méthode'                            : 'Method',
    'Référence'                          : 'Reference',
    'Caissier'                           : 'Cashier',
    'N° Facture'                         : 'Invoice #',
    'Facture origine'                    : 'Original Invoice',
    'Nom'                                : 'Name',
    'Catégorie'                          : 'Category',
    'Prix'                               : 'Price',
    'Prix unitaire'                      : 'Unit Price',
    'Seuil'                              : 'Threshold',
    'Expiration'                         : 'Expiry',
    'N° Ref'                             : 'Ref #',
    'Note'                               : 'Note',
    'Rôle'                               : 'Role',
    'Téléphone'                          : 'Phone',
    'Durée'                              : 'Duration',
    'Détail'                             : 'Detail',
    'Désignation'                        : 'Description',
    'Business'                           : 'Business',
    'Abonnement'                         : 'Subscription',
    'Abonnements'                        : 'Subscriptions',
    'Employé'                            : 'Employee',
    'Tous les utilisateurs'              : 'All Users',
    'Paiements en attente'               : 'Pending Payments',
    'Paramètres plateforme'              : 'Platform Settings',
    'Demandes'                           : 'Requests',
    'Résumé'                             : 'Summary',

    /* Sidebar navigation sections */
    'Principal'                          : 'Main',
    'Registre'                           : 'Register',
    'Inventaire'                         : 'Inventory',
    'Équipe'                             : 'Team',
    'Finance'                            : 'Finance',
    'Gestion'                            : 'Management',
    'Compte'                             : 'Account',
    'Plateforme'                         : 'Platform',
    'Déconnexion'                        : 'Logout',

    /* Status pills */
    'Actif'                              : 'Active',
    'Inactif'                            : 'Inactive',
    'En attente'                         : 'Pending',
    'Approuvé'                           : 'Approved',
    'Refusé'                             : 'Rejected',
    'Expiré'                             : 'Expired',
    'Suspendu'                           : 'Suspended',
    'Essai'                              : 'Trial',
    'Faible'                             : 'Low',
    'Archivé'                            : 'Archived',

    /* Buttons / actions */
    'Ajouter produit'                    : 'Add Product',
    '+ Ajouter produit'                  : '+ Add Product',
    'Archiver'                           : 'Archive',
    'Restaurer'                          : 'Restore',
    'Sauvegarder'                        : 'Save',
    'Annuler'                            : 'Cancel',
    'Fermer'                             : 'Close',
    'Modifier'                           : 'Edit',
    'Supprimer'                          : 'Delete',
    'Voir'                               : 'View',
    'Exporter'                           : 'Export',
    'Export'                             : 'Export',
    'Imprimer'                           : 'Print',
    'Créer'                              : 'Create',
    'Enregistrer'                        : 'Save',
    'Télécharger'                        : 'Download',
    'Réinitialiser'                      : 'Reset',
    'Rechercher'                         : 'Search',
    'Renouveler'                         : 'Renew',
    'Valider'                            : 'Validate',
    'Approuver'                          : 'Approve',
    'Refuser'                            : 'Reject',
    'Soumettre'                          : 'Submit',

    /* Empty states */
    'Aucun remboursement'                : 'No refunds',
    'Aucun produit abîmé signalé'        : 'No damaged products reported',
    'Aucune demande de stock entrant en attente.'
                                         : 'No pending incoming stock requests.',
    'Aucune demande de stock sortant en attente.'
                                         : 'No pending outgoing stock requests.',

    /* Misc */
    'Total'                              : 'Total',
    'Sous-total'                         : 'Subtotal',
    'TVA'                                : 'VAT',
    'Remise'                             : 'Discount',
    'Oui'                                : 'Yes',
    'Non'                                : 'No',
    'Tous'                               : 'All',
    'Tous rôles'                         : 'All roles',
    'Tous statuts'                       : 'All statuses',
  };

  /* Reverse map EN → FR for switching back */
  const EN_FR = {};
  Object.entries(FR_EN).forEach(([k, v]) => { EN_FR[v] = k; });

  /* WeakMap stores the *original French* text for each translated element */
  const origCache = new WeakMap();

  /* Return true if an element's text content is safe to replace
     (only text nodes + harmless inline tags — no data-bound children) */
  function isSafeEl(el) {
    for (const c of el.childNodes) {
      if (c.nodeType === Node.ELEMENT_NODE &&
          !['SPAN','B','EM','STRONG','I','SVG','SMALL'].includes(c.tagName)) {
        return false;
      }
    }
    return true;
  }

  /* Get only the text-node content (ignoring child element text) */
  function ownText(el) {
    let s = '';
    for (const n of el.childNodes) {
      if (n.nodeType === Node.TEXT_NODE) s += n.textContent;
    }
    return s.trim();
  }

  /* Replace only text nodes inside el, preserving child elements */
  function setOwnText(el, newText) {
    let done = false;
    for (const n of el.childNodes) {
      if (n.nodeType === Node.TEXT_NODE && n.textContent.trim()) {
        if (!done) {
          // Preserve surrounding whitespace
          const ws = n.textContent.match(/^(\s*)/)[1];
          const we = n.textContent.match(/(\s*)$/)[1];
          n.textContent = ws + newText + we;
          done = true;
        } else {
          n.textContent = '';
        }
      }
    }
    if (!done && el.childElementCount === 0) {
      el.textContent = newText;
    }
  }

  /* Core translation function */
  function applyGlobal(lang) {
    const isEN = (lang === 'en');
    const dict = isEN ? FR_EN : EN_FR;

    /* 1. Elements with explicit [data-i18n] or [data-lt] attributes */
    document.querySelectorAll('[data-i18n],[data-lt]').forEach(el => {
      const key = el.dataset.i18n || el.dataset.lt;
      if (!key) return;
      /* Page-specific dicts handle these; we only act if no page script did */
      if (!origCache.has(el)) origCache.set(el, el.textContent.trim());
    });

    /* 2. h1, h2, h3, th — translate by text-content lookup */
    document.querySelectorAll('h1,h2,h3,th').forEach(el => {
      /* Skip elements inside forms, modals authored in EN, or with complex markup */
      if (!isSafeEl(el)) return;

      const raw = ownText(el) || el.textContent.trim();

      /* Store French original on first encounter */
      if (!origCache.has(el)) origCache.set(el, raw);
      const frText = origCache.get(el);

      if (isEN && FR_EN[frText]) {
        setOwnText(el, FR_EN[frText]);
      } else if (!isEN && frText) {
        setOwnText(el, frText);
      }
    });

    /* 3. Sync all language-toggle buttons */
    document.querySelectorAll(
      '.od-lang,.sa-lang-btn,.rp-lang,.em-lang,.st-lang,.al-lang,.lang-btn,[id*="lang-btn"],[id*="langBtn"]'
    ).forEach(btn => {
      if (['BUTTON','A'].includes(btn.tagName)) {
        btn.textContent = isEN ? 'FR' : 'EN';
      }
    });

    localStorage.setItem('__lt_lang_raw', lang); /* internal, non-intercepted */

    /* 4. Sync sidebar */
    if (typeof window.applySidebarLang === 'function') window.applySidebarLang(lang);
  }

  /* ── Intercept localStorage.setItem for 'lt_lang' ──
     Any page-specific lang toggle that calls setItem will also
     fire the global engine — zero per-page JS changes needed. */
  const _origSet = Storage.prototype.setItem;
  Storage.prototype.setItem = function (key, val) {
    _origSet.call(this, key, val);
    if (key === 'lt_lang' && this === localStorage) {
      /* Microtask: runs after page-specific applyLang() finishes */
      Promise.resolve().then(() => applyGlobal(val));
    }
  };

  /* ── Initial page load ── */
  function init() {
    const saved = localStorage.getItem('lt_lang') || 'fr';
    if (saved === 'en') applyGlobal('en');
    /* Always sync sidebar even in FR */
    if (typeof window.applySidebarLang === 'function') window.applySidebarLang(saved);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    setTimeout(init, 30); /* give page-specific scripts time to set up */
  }

  /* ── Expose globally ── */
  window.applyGlobalLang = applyGlobal;
  window.LT_I18N_DICT   = FR_EN;

})();
