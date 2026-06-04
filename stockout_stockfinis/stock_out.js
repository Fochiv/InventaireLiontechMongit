/* stock_out.js — bilingual text, quantity validation, history search */
const translations = {
  fr: {
    dashboard:"Tableau de bord", employees:"Employés", products:"Produits", stock_in:"Stock entrant",
    stock_out:"Stock sortant", reports:"Rapports", notifications:"Notifications", settings:"Paramètres", logout:"Déconnexion",
    page_title:"Sortie de Stock", page_subtitle:"Enregistrez les produits vendus, utilisés, abîmés, expirés ou perdus.",
    employee_warning:"Les sorties de stock enregistrées par un employé doivent être approuvées avant de modifier l’inventaire.",
    form_title:"Nouvelle sortie de stock", product_label:"Produit", select_product:"Sélectionner un produit",
    quantity_label:"Quantité sortie", reason_label:"Raison", select_reason:"Choisir une raison",
    sold:"Vendu", used:"Utilisé", damaged:"Abîmé", expired:"Expiré", missing:"Manquant/Perdu", returned:"Retourné",
    recipient_label:"Client / Département / Destination (optionnel)", proof_label:"Preuve / photo / reçu (optionnel)",
    note_label:"Note", submit_btn:"Enregistrer la sortie", help_title:"À quoi sert cette page?",
    help_text:"Cette page enregistre tout ce qui sort du stock: produits vendus, utilisés, abîmés, expirés ou perdus.",
    help_1:"Empêche les sorties supérieures au stock disponible.", help_2:"Garde un historique de qui a enregistré la sortie.",
    help_3:"Demande une approbation si l’action vient d’un employé.", help_4:"Met à jour l’inventaire immédiatement pour owner/manager/stock manager.",
    history_title:"Historique des sorties", th_product:"Produit", th_qty:"Quantité", th_reason:"Raison",
    th_by:"Enregistré par", th_status:"Statut", th_date:"Date", empty_history:"Aucune sortie enregistrée."
  },
  en: {
    dashboard:"Dashboard", employees:"Employees", products:"Products", stock_in:"Stock In",
    stock_out:"Stock Out", reports:"Reports", notifications:"Notifications", settings:"Settings", logout:"Logout",
    page_title:"Stock Out", page_subtitle:"Record products sold, used, damaged, expired, or missing.",
    employee_warning:"Stock out recorded by an employee must be approved before inventory is updated.",
    form_title:"New Stock Out", product_label:"Product", select_product:"Select a product",
    quantity_label:"Quantity Out", reason_label:"Reason", select_reason:"Select a reason",
    sold:"Sold", used:"Used", damaged:"Damaged", expired:"Expired", missing:"Missing/Lost", returned:"Returned",
    recipient_label:"Customer / Department / Destination (optional)", proof_label:"Proof / photo / receipt (optional)",
    note_label:"Note", submit_btn:"Record Stock Out", help_title:"What is this page for?",
    help_text:"This page records everything leaving inventory: products sold, used, damaged, expired, or missing.",
    help_1:"Blocks stock out greater than available stock.", help_2:"Keeps history of who recorded the stock out.",
    help_3:"Requires approval if the action comes from an employee.", help_4:"Updates inventory immediately for owner/manager/stock manager.",
    history_title:"Stock Out History", th_product:"Product", th_qty:"Quantity", th_reason:"Reason",
    th_by:"Recorded By", th_status:"Status", th_date:"Date", empty_history:"No stock out recorded."
  }
};

let currentLang = localStorage.getItem("lt_lang") || "fr";
const langBtn = document.getElementById("langBtn");

function applyLang() {
  document.documentElement.lang = currentLang;
  document.querySelectorAll("[data-i18n]").forEach(el => {
    const key = el.getAttribute("data-i18n");
    if (translations[currentLang][key]) el.textContent = translations[currentLang][key];
  });
  if (langBtn) langBtn.textContent = currentLang === "fr" ? "EN" : "FR";
}
if (langBtn) {
  langBtn.addEventListener("click", () => {
    currentLang = currentLang === "fr" ? "en" : "fr";
    localStorage.setItem("lt_lang", currentLang);
    applyLang();
  });
}
applyLang();

const productSelect = document.getElementById("productSelect");
const quantityInput = document.getElementById("quantityInput");
const stockInfo = document.getElementById("stockInfo");
const form = document.getElementById("stockOutForm");

function updateStockInfo() {
  const selected = productSelect.options[productSelect.selectedIndex];
  const qty = selected?.dataset?.qty || "--";
  const unit = selected?.dataset?.unit || "";
  stockInfo.textContent = currentLang === "fr"
    ? `Stock disponible: ${qty} ${unit}`
    : `Available stock: ${qty} ${unit}`;
}
if (productSelect) productSelect.addEventListener("change", updateStockInfo);

if (form) {
  form.addEventListener("submit", (e) => {
    const selected = productSelect.options[productSelect.selectedIndex];
    const available = parseFloat(selected?.dataset?.qty || 0);
    const requested = parseFloat(quantityInput.value || 0);

    if (!productSelect.value || requested <= 0) {
      e.preventDefault();
      alert(currentLang === "fr" ? "Veuillez sélectionner un produit et entrer une quantité valide." : "Please select a product and enter a valid quantity.");
      return;
    }

    if (requested > available) {
      e.preventDefault();
      alert(currentLang === "fr" ? "La quantité sortie ne peut pas dépasser le stock disponible." : "Stock out quantity cannot be higher than available stock.");
    }
  });
}

const searchHistory = document.getElementById("searchHistory");
const historyTable = document.getElementById("historyTable");
if (searchHistory && historyTable) {
  searchHistory.addEventListener("input", () => {
    const term = searchHistory.value.toLowerCase();
    historyTable.querySelectorAll("tbody tr").forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(term) ? "" : "none";
    });
  });
}
