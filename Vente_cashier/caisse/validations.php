<?php
/* ============================================================
   caisse/validations.php — Tally Business Manager
   Validation remboursements (Owner) + produits abîmés (Manager)
   ============================================================ */
require_once dirname(dirname(__DIR__)) . '/Config.php';
startSecureSession();
requireLogin();

$user       = currentUser();
$role       = $user['role'] ?? '';
$businessId = (int)($user['business_id'] ?? 0);
$userId     = (int)($user['user_id'] ?? 0);
$pdo        = getDB();

if (!in_array($role, ['business_owner', 'manager'], true)) {
    header('Location: ' . APP_URL . '/Logininventory/login.php?error=unauthorized'); exit;
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function fmtXAF($v) { return number_format((float)$v, 0, ',', ' ') . ' XAF'; }

$isOwner   = ($role === 'business_owner');
$isManager = ($role === 'manager');
$msg = $msgType = '';

/* ── Actions POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $transId = (int)($_POST['trans_id'] ?? 0);

    /* Owner : valider remboursement */
    if ($action === 'valider_remb' && $isOwner && $transId) {
        $st = $pdo->prepare("SELECT * FROM transactions_caisse WHERE transaction_id=? AND business_id=? AND statut='pending_remb'");
        $st->execute([$transId, $businessId]);
        $t = $st->fetch();
        if ($t) {
            $pdo->prepare("UPDATE transactions_caisse SET statut='remb_validee', validee_par=?, validee_at=NOW() WHERE transaction_id=?")
                ->execute([$userId, $transId]);
            // Remettre stock si remboursement total lié à une vente
            $pdo->prepare("INSERT INTO notifications (business_id, title, message, type, created_at) VALUES (?,?,?,'info',NOW())")
                ->execute([$businessId, '<span class="icon-ok">✓</span> Remboursement validé', "Remboursement de " . fmtXAF($t['total_ttc']) . " validé par le owner."]);
            $msg = '<span class="icon-ok">✓</span> Remboursement validé.'; $msgType = 'success';
        }
    }

    /* Owner : rejeter remboursement */
    if ($action === 'rejeter_remb' && $isOwner && $transId) {
        $motif = trim($_POST['motif_rejet'] ?? '');
        $pdo->prepare("UPDATE transactions_caisse SET statut='remb_rejetee', validee_par=?, validee_at=NOW(), note=CONCAT(COALESCE(note,''), ' | Rejet: ', ?) WHERE transaction_id=? AND business_id=?")
            ->execute([$userId, $motif, $transId, $businessId]);
        $msg = '<span class="icon-warn">⚠</span> Remboursement rejeté.'; $msgType = 'warning';
    }

    /* Manager : confirmer produit abîmé */
    if ($action === 'confirmer_abime' && $isManager && $transId) {
        $st = $pdo->prepare("SELECT * FROM transactions_caisse WHERE transaction_id=? AND business_id=? AND statut='pending_abime'");
        $st->execute([$transId, $businessId]);
        $t = $st->fetch();
        if ($t) {
            $pdo->prepare("UPDATE transactions_caisse SET statut='abime_validee', validee_par=?, validee_at=NOW() WHERE transaction_id=?")
                ->execute([$userId, $transId]);
            $msg = '<span class="icon-ok">✓</span> Produit abîmé confirmé.'; $msgType = 'success';
        }
    }
}

/* ── Récupérer remboursements en attente ── */
$rembs = $pdo->prepare("SELECT tc.*, u.full_name AS caissier_nom,
    orig.numero_facture AS facture_origine
    FROM transactions_caisse tc
    LEFT JOIN users u ON tc.caissier_id = u.user_id
    LEFT JOIN transactions_caisse orig ON tc.transaction_ref = orig.transaction_id
    WHERE tc.business_id = ? AND tc.type_operation = 'remboursement'
    ORDER BY FIELD(tc.statut,'pending_remb','remb_validee','remb_rejetee'), tc.created_at DESC
    LIMIT 50");
$rembs->execute([$businessId]);
$remboursements = $rembs->fetchAll();

/* ── Récupérer produits abîmés en attente ── */
$abimes = $pdo->prepare("SELECT tc.*, u.full_name AS caissier_nom,
    pa.photo_url
    FROM transactions_caisse tc
    LEFT JOIN users u ON tc.caissier_id = u.user_id
    LEFT JOIN preuves_abime pa ON pa.transaction_id = tc.transaction_id
    WHERE tc.business_id = ? AND tc.type_operation = 'abime'
    ORDER BY FIELD(tc.statut,'pending_abime','abime_validee'), tc.created_at DESC
    LIMIT 50");
$abimes->execute([$businessId]);
$produitsAbimes = $abimes->fetchAll();

/* ── Business ── */
$stBiz = $pdo->prepare("SELECT * FROM businesses WHERE business_id=? LIMIT 1");
$stBiz->execute([$businessId]);
$business = $stBiz->fetch();

$initials = '';
foreach (explode(' ', trim($user['full_name'] ?? 'U')) as $w) $initials .= strtoupper(substr($w, 0, 1));
$initials = substr($initials ?: 'U', 0, 2);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Validations Caisse — LionTech</title>
<link rel="stylesheet" href="<?= APP_URL ?>/LionTech_Owner_Dashboard/owner_dashboard.css"/>
<style>
.val-tabs { display:flex; gap:4px; margin-bottom:20px; background:#F3F4F6; border-radius:12px; padding:4px; width:fit-content; }
.val-tab { padding:9px 20px; border-radius:10px; font-size:13px; font-weight:800; cursor:pointer; border:none; background:transparent; color:#6B7280; font-family:inherit; }
.val-tab.active { background:#0B1F3A; color:#fff; }
.val-section { display:none; }
.val-section.active { display:block; }
.val-table-wrap { overflow-x:auto; -webkit-overflow-scrolling:touch; }
.val-table { width:100%; border-collapse:collapse; font-size:13px; min-width:600px; }
.val-table th { background:#0B1F3A; color:#fff; padding:11px 14px; font-size:11px; text-align:left; }
.val-table td { padding:11px 14px; border-bottom:1px solid #F3F4F6; vertical-align:middle; }
.val-table tr:hover td { background:#F8FAFC; }
.badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:10px; font-weight:800; }
.badge-pending  { background:#FEF3C7; color:#92400E; }
.badge-validee  { background:#DCFCE7; color:#166534; }
.badge-rejetee  { background:#FEE2E2; color:#991B1B; }
.btn-val { background:#10B981; color:#fff; border:none; padding:6px 14px; border-radius:8px; font-size:11px; font-weight:700; cursor:pointer; margin-right:4px; }
.btn-rej { background:#EF4444; color:#fff; border:none; padding:6px 14px; border-radius:8px; font-size:11px; font-weight:700; cursor:pointer; }
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; align-items:center; justify-content:center; }
.modal-overlay.open { display:flex; }
.modal-box { background:#fff; border-radius:20px; padding:28px; width:100%; max-width:440px; }
.empty-state { text-align:center; padding:40px; color:#9CA3AF; }
.nb-badge { background:#EF4444; color:#fff; border-radius:20px; padding:2px 8px; font-size:10px; font-weight:800; margin-left:6px; }
</style>
  <!-- LionTech Global Assets -->
  
  
  
<link rel="stylesheet" href="<?= APP_URL ?>/icons.css">
</head>
<body>
<div class="od-layout">
<?php include dirname(dirname(__DIR__)) . '/LionTech_Owner_Dashboard/Sidebar.php'; ?>
<main class="od-main">

  <div class="od-topbar">
    <div class="od-business-title">
      <h1><span class="icon-money">&#36;</span> Validations Caisse</h1>
      <p>Remboursements et produits abîmés</p>
    </div>
    <div class="od-top-actions">
      <div class="od-avatar"><?= e($initials) ?></div>
    </div>
  </div>

  <div style="padding:0 24px 40px">

    <?php if ($msg): ?>
    <div style="background:<?= $msgType==='success'?'#F0FDF4':'#FFFBEB' ?>;border:1px solid <?= $msgType==='success'?'#86EFAC':'#FDE68A' ?>;border-radius:12px;padding:13px 18px;margin-bottom:18px;color:<?= $msgType==='success'?'#166534':'#92400E' ?>;font-size:13.5px">
      <?= e($msg) ?>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <?php
    $nbRembs = count(array_filter($remboursements, fn($r) => $r['statut'] === 'pending_remb'));
    $nbAbimes = count(array_filter($produitsAbimes, fn($a) => $a['statut'] === 'pending_abime'));
    ?>
    <div class="val-tabs">
      <?php if ($isOwner): ?>
      <button class="val-tab active" onclick="showTab('remb')">
        <span class="icon-money">&#36;</span> Remboursements
        <?php if ($nbRembs > 0): ?><span class="nb-badge"><?= $nbRembs ?></span><?php endif; ?>
      </button>
      <?php endif; ?>
      <button class="val-tab <?= !$isOwner ? 'active' : '' ?>" onclick="showTab('abime')">
        <span class="icon-warn">⚠</span> Produits abîmés
        <?php if ($nbAbimes > 0): ?><span class="nb-badge"><?= $nbAbimes ?></span><?php endif; ?>
      </button>
    </div>

    <!-- Section Remboursements -->
    <?php if ($isOwner): ?>
    <div id="tab-remb" class="val-section active">
      <div class="od-card" style="padding:0;overflow:hidden">
        <?php if (empty($remboursements)): ?>
        <div class="empty-state">Aucun remboursement</div>
        <?php else: ?>
        <div class="val-table-wrap">
        <table class="val-table">
          <thead>
            <tr>
              <th>N° Facture</th>
              <th>Facture origine</th>
              <th>Montant</th>
              <th>Caissier</th>
              <th>Motif</th>
              <th>Date</th>
              <th>Statut</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($remboursements as $r): ?>
            <tr>
              <td><strong><?= e($r['numero_facture']) ?></strong></td>
              <td><?= e($r['facture_origine'] ?? '—') ?></td>
              <td><strong><?= fmtXAF($r['total_ttc']) ?></strong></td>
              <td><?= e($r['caissier_nom'] ?? '—') ?></td>
              <td style="max-width:180px;font-size:11px;color:#6B7280"><?= e($r['note'] ?? '—') ?></td>
              <td><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
              <td>
                <span class="badge badge-<?= $r['statut']==='pending_remb'?'pending':($r['statut']==='remb_validee'?'validee':'rejetee') ?>">
                  <?= $r['statut']==='pending_remb'?'En attente':($r['statut']==='remb_validee'?'Validé':'Rejeté') ?>
                </span>
              </td>
              <td>
                <?php if ($r['statut'] === 'pending_remb'): ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="action" value="valider_remb">
                  <input type="hidden" name="trans_id" value="<?= $r['transaction_id'] ?>">
                  <button type="submit" class="btn-val" onclick="return confirm('Valider ce remboursement ?')">✓ Valider</button>
                </form>
                <button class="btn-rej" onclick="openRejet(<?= $r['transaction_id'] ?>)">✗ Rejeter</button>
                <?php else: ?>
                <span style="font-size:11px;color:#9CA3AF">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Section Produits Abîmés -->
    <div id="tab-abime" class="val-section <?= !$isOwner ? 'active' : '' ?>">
      <div class="od-card" style="padding:0;overflow:hidden">
        <?php if (empty($produitsAbimes)): ?>
        <div class="empty-state">Aucun produit abîmé signalé</div>
        <?php else: ?>
        <div class="val-table-wrap">
        <table class="val-table">
          <thead>
            <tr>
              <th>N° Ref</th>
              <th>Caissier</th>
              <th>Note</th>
              <th>Preuve</th>
              <th>Date</th>
              <th>Statut</th>
              <?php if ($isManager): ?><th>Action</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($produitsAbimes as $a): ?>
            <tr>
              <td><strong><?= e($a['numero_facture']) ?></strong></td>
              <td><?= e($a['caissier_nom'] ?? '—') ?></td>
              <td style="max-width:200px;font-size:11px;color:#6B7280"><?= e($a['note'] ?? '—') ?></td>
              <td>
                <?php if ($a['photo_url']): ?>
                <a href="<?= APP_URL . '/' . e($a['photo_url']) ?>" target="_blank"
                   style="color:#0B1F3A;font-weight:700;font-size:11px">📷 Voir</a>
                <?php else: ?>
                <span style="font-size:11px;color:#9CA3AF">—</span>
                <?php endif; ?>
              </td>
              <td><?= date('d/m/Y H:i', strtotime($a['created_at'])) ?></td>
              <td>
                <span class="badge badge-<?= $a['statut']==='pending_abime'?'pending':'validee' ?>">
                  <?= $a['statut']==='pending_abime'?'En attente':'Confirmé' ?>
                </span>
              </td>
              <?php if ($isManager): ?>
              <td>
                <?php if ($a['statut'] === 'pending_abime'): ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="action" value="confirmer_abime">
                  <input type="hidden" name="trans_id" value="<?= $a['transaction_id'] ?>">
                  <button type="submit" class="btn-val" onclick="return confirm('Confirmer ce produit abîmé ?')">✓ Confirmer</button>
                </form>
                <?php else: ?>
                <span style="font-size:11px;color:#9CA3AF">—</span>
                <?php endif; ?>
              </td>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</main>
</div>

<!-- Modal Rejet -->
<div class="modal-overlay" id="modalRejet">
  <div class="modal-box">
    <h3 style="font-size:16px;font-weight:800;color:#0B1F3A;margin-bottom:16px">✗ Rejeter le remboursement</h3>
    <form method="POST">
      <input type="hidden" name="action" value="rejeter_remb">
      <input type="hidden" name="trans_id" id="rejetTransId">
      <div style="margin-bottom:14px">
        <label style="font-size:12px;font-weight:700;color:#374151;display:block;margin-bottom:6px">Motif du rejet *</label>
        <textarea name="motif_rejet" rows="3" required
          style="width:100%;padding:9px 12px;border:1.5px solid #E5E7EB;border-radius:9px;font-size:13px;outline:none;box-sizing:border-box;resize:none"></textarea>
      </div>
      <div style="display:flex;gap:10px">
        <button type="button" onclick="closeModal('modalRejet')" style="flex:1;background:#F3F4F6;color:#374151;border:none;padding:11px;border-radius:10px;font-weight:700;cursor:pointer">Annuler</button>
        <button type="submit" style="flex:2;background:#EF4444;color:#fff;border:none;padding:11px;border-radius:10px;font-weight:700;cursor:pointer">✗ Confirmer le rejet</button>
      </div>
    </form>
  </div>
</div>

<script>
function showTab(tab) {
  document.querySelectorAll('.val-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.val-tab').forEach(t => t.classList.remove('active'));
  document.getElementById('tab-' + tab)?.classList.add('active');
  event.target.classList.add('active');
}
function openRejet(id) {
  document.getElementById('rejetTransId').value = id;
  document.getElementById('modalRejet').classList.add('open');
}
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});
</script>
  <!-- LionTech Global JS -->
  
</body>
</html>