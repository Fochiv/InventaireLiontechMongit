<?php
/* ============================================================
   approval_center.php — Tally Business Manager
   Owner + Manager — approve/reject stock requests
   ============================================================ */
require_once __DIR__ . '/../Config.php';
startSecureSession();
requireRole([ROLE_BUSINESS_OWNER, ROLE_MANAGER]);

$user       = currentUser();
$pdo        = getDB();
$businessId = (int)($user['business_id'] ?? 0);

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$message = ''; $messageType = '';

/* ── Actions ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action']     ?? '';
    $type      = $_POST['type']       ?? '';
    $requestId = (int)($_POST['request_id'] ?? 0);
    $reason    = trim($_POST['rejection_reason'] ?? 'Rejeté');

    try {
        $pdo->beginTransaction();

        if ($action === 'approve' && $type === 'stock_in') {
            $stmt = $pdo->prepare('SELECT * FROM stock_in_requests WHERE request_id=? AND business_id=? AND status="pending" FOR UPDATE');
            $stmt->execute([$requestId, $businessId]);
            $req = $stmt->fetch();
            if ($req) {
                $pdo->prepare('UPDATE products SET quantity=quantity+?,updated_at=NOW() WHERE product_id=? AND business_id=?')->execute([(float)$req['quantity'],(int)$req['product_id'],$businessId]);
                $pdo->prepare('UPDATE stock_in_requests SET status="approved",approved_by=?,approved_at=NOW() WHERE request_id=?')->execute([(int)$user['user_id'],$requestId]);
                $pdo->prepare('INSERT INTO stock_movements (request_id,business_id,product_id,movement_type,quantity,reason,created_by,approved_by,created_at) VALUES (?,?,?,"stock_in",?,"Approuvé",?,?,NOW())')->execute([$requestId,$businessId,(int)$req['product_id'],(float)$req['quantity'],(int)$req['created_by'],(int)$user['user_id']]);
                $message = 'Stock entrant approuvé. Inventaire mis à jour.'; $messageType = 'success';
            }
        }

        if ($action === 'reject' && $type === 'stock_in') {
            $pdo->prepare('UPDATE stock_in_requests SET status="rejected",approved_by=?,approved_at=NOW(),rejection_reason=? WHERE request_id=? AND business_id=? AND status="pending"')->execute([(int)$user['user_id'],$reason,$requestId,$businessId]);
            $message = 'Demande rejetée.'; $messageType = 'success';
        }

        if ($action === 'approve' && $type === 'stock_out') {
            $stmt = $pdo->prepare('SELECT * FROM stock_out_requests WHERE request_id=? AND business_id=? AND status="pending" FOR UPDATE');
            $stmt->execute([$requestId, $businessId]);
            $req = $stmt->fetch();
            if ($req) {
                $pdo->prepare('UPDATE products SET quantity=quantity-?,updated_at=NOW() WHERE product_id=? AND business_id=?')->execute([(float)$req['quantity'],(int)$req['product_id'],$businessId]);
                $pdo->prepare('UPDATE stock_out_requests SET status="approved",approved_by=?,approved_at=NOW() WHERE request_id=?')->execute([(int)$user['user_id'],$requestId]);
                $pdo->prepare('INSERT INTO stock_movements (request_id,business_id,product_id,movement_type,quantity,reason,created_by,approved_by,created_at) VALUES (?,?,?,"stock_out",?,"Approuvé",?,?,NOW())')->execute([$requestId,$businessId,(int)$req['product_id'],(float)$req['quantity'],(int)$req['created_by'],(int)$user['user_id']]);
                $message = 'Stock sortant approuvé. Inventaire mis à jour.'; $messageType = 'success';
            }
        }

        if ($action === 'reject' && $type === 'stock_out') {
            $pdo->prepare('UPDATE stock_out_requests SET status="rejected",approved_by=?,approved_at=NOW(),rejection_reason=? WHERE request_id=? AND business_id=? AND status="pending"')->execute([(int)$user['user_id'],$reason,$requestId,$businessId]);
            $message = 'Demande rejetée.'; $messageType = 'success';
        }

        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = 'Erreur: '.$ex->getMessage(); $messageType = 'error';
    }
}

/* ── Load pending requests ── */
$pendingIn = [];
try {
    $stmt = $pdo->prepare('SELECT r.*,p.name AS product_name,p.unit,u.full_name AS requested_by FROM stock_in_requests r JOIN products p ON p.product_id=r.product_id LEFT JOIN users u ON u.user_id=r.created_by WHERE r.business_id=? AND r.status="pending" ORDER BY r.created_at ASC');
    $stmt->execute([$businessId]);
    $pendingIn = $stmt->fetchAll();
} catch (Throwable $e) {}

$pendingOut = [];
try {
    $stmt = $pdo->prepare('SELECT r.*,p.name AS product_name,p.unit,u.full_name AS requested_by FROM stock_out_requests r JOIN products p ON p.product_id=r.product_id LEFT JOIN users u ON u.user_id=r.created_by WHERE r.business_id=? AND r.status="pending" ORDER BY r.created_at ASC');
    $stmt->execute([$businessId]);
    $pendingOut = $stmt->fetchAll();
} catch (Throwable $e) {}

$totalPending = count($pendingIn) + count($pendingOut);

$initials = '';
foreach (explode(' ', trim($user['full_name'] ?? 'O')) as $w) $initials .= strtoupper(substr($w, 0, 1));
$initials = substr($initials ?: 'O', 0, 2);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Centre de Validation — LionTech</title>
</head>
<body>
<div class="od-layout">
  <?php include __DIR__ . '/../LionTech_Owner_Dashboard/Sidebar.php'; ?>

  <main class="od-main">
    <header class="od-topbar">
      <div class="od-business-title">
        <h1>Centre de Validation</h1>
        <p>Approuvez ou refusez les demandes de stock des employés</p>
      </div>
      <div class="od-top-actions">
        <?php if ($totalPending > 0): ?>
        <span style="background:#FEF3C7;color:#92400E;border-radius:50px;padding:5px 14px;font-size:12px;font-weight:700"><?= $totalPending ?> en attente</span>
        <?php endif; ?>
        <button class="od-lang" id="ac-lang-btn" type="button" style="border:1px solid #E5E7EB;background:#fff;border-radius:999px;padding:9px 13px;font-weight:800;cursor:pointer;font-family:inherit">FR</button>
        <div class="od-avatar"><?= e($initials) ?></div>
      </div>
    </header>

    <?php if ($message): ?>
    <div style="background:<?=$messageType==='success'?'#F0FDF4':'#FEF2F2'?>;border:1px solid <?=$messageType==='success'?'#86EFAC':'#FECACA'?>;padding:12px 24px;font-size:13px;color:<?=$messageType==='success'?'#166534':'#991B1B'?>">
      <?=$messageType==='success'?'<span class="icon-ok">✓</span>':'<span class="icon-warn">⚠</span>'?> <?= e($message) ?>
    </div>
    <?php endif; ?>

    <!-- Stat cards -->
    <div style="padding:20px 24px 0;display:grid;grid-template-columns:repeat(4,1fr);gap:14px">
      <div class="od-card stat"><span class="stat-icon blue">📥</span><div><small>Stock entrant</small><strong><?= count($pendingIn) ?></strong></div></div>
      <div class="od-card stat"><span class="stat-icon amber">📤</span><div><small>Stock sortant</small><strong><?= count($pendingOut) ?></strong></div></div>
      <div class="od-card stat"><span class="stat-icon" style="background:#FEE2E2"><span class="icon-warn">⚠</span></span><div><small>Total urgent</small><strong><?= $totalPending ?></strong></div></div>
      <div class="od-card stat"><span class="stat-icon green"><span class="icon-ok">✓</span></span><div><small>Action requise</small><strong><?= $totalPending > 0 ? 'Oui' : 'Non' ?></strong></div></div>
    </div>

    <div style="padding:20px 24px 40px;display:flex;flex-direction:column;gap:20px">

      <!-- Stock In pending -->
      <div class="od-card" style="padding:0;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid #F1F5F9;display:flex;align-items:center;justify-content:space-between">
          <div>
            <h2 style="font-size:15px;font-weight:700;color:#0B1F3A;margin:0">Demandes Stock Entrant</h2>
            <p style="font-size:12px;color:#6B7280;margin:3px 0 0">Quantités reçues à valider avant d'ajouter à l'inventaire</p>
          </div>
          <span style="background:#EFF6FF;color:#1E40AF;border-radius:50px;padding:3px 12px;font-size:12px;font-weight:700"><?= count($pendingIn) ?> en attente</span>
        </div>
        <div class="od-table-wrap">
          <table class="od-table">
            <thead><tr><th>Produit</th><th>Quantité</th><th>Fournisseur</th><th>Demandé par</th><th>Date</th><th>Preuve</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($pendingIn): foreach ($pendingIn as $r): ?>
            <tr>
              <td><strong><?= e($r['product_name']) ?></strong></td>
              <td><?= e($r['quantity']) ?> <?= e($r['unit']??'') ?></td>
              <td><?= e($r['supplier']??'—') ?></td>
              <td><?= e($r['requested_by']??'—') ?></td>
              <td><?= e(date('d/m/Y H:i',strtotime($r['created_at']))) ?></td>
              <td><?php if($r['proof_image_url']): ?><a href="<?=e($r['proof_image_url'])?>" target="_blank" style="color:#1A9E7A;font-size:12px">Voir</a><?php else: ?>—<?php endif; ?></td>
              <td>
                <div style="display:flex;gap:6px">
                  <form method="POST">
                    <input type="hidden" name="action" value="approve"/>
                    <input type="hidden" name="type" value="stock_in"/>
                    <input type="hidden" name="request_id" value="<?=(int)$r['request_id']?>"/>
                    <button type="submit" style="padding:6px 12px;font-size:12px;background:#F0FDF4;border:1.5px solid #86EFAC;border-radius:8px;color:#166534;cursor:pointer;font-family:inherit"><span class="icon-ok">✓</span> Approuver</button>
                  </form>
                  <form method="POST">
                    <input type="hidden" name="action" value="reject"/>
                    <input type="hidden" name="type" value="stock_in"/>
                    <input type="hidden" name="request_id" value="<?=(int)$r['request_id']?>"/>
                    <input type="hidden" name="rejection_reason" value="Rejeté après vérification"/>
                    <button type="submit" style="padding:6px 12px;font-size:12px;background:#FEF2F2;border:1.5px solid #FECACA;border-radius:8px;color:#991B1B;cursor:pointer;font-family:inherit"><span class="icon-err">✗</span> Refuser</button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="7" class="od-empty">Aucune demande de stock entrant en attente.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Stock Out pending -->
      <div class="od-card" style="padding:0;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid #F1F5F9;display:flex;align-items:center;justify-content:space-between">
          <div>
            <h2 style="font-size:15px;font-weight:700;color:#0B1F3A;margin:0">Demandes Stock Sortant</h2>
            <p style="font-size:12px;color:#6B7280;margin:3px 0 0">Sorties à valider avant de déduire de l'inventaire</p>
          </div>
          <span style="background:#FEF3C7;color:#92400E;border-radius:50px;padding:3px 12px;font-size:12px;font-weight:700"><?= count($pendingOut) ?> en attente</span>
        </div>
        <div class="od-table-wrap">
          <table class="od-table">
            <thead><tr><th>Produit</th><th>Quantité</th><th>Raison</th><th>Demandé par</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($pendingOut): foreach ($pendingOut as $r): ?>
            <tr>
              <td><strong><?= e($r['product_name']) ?></strong></td>
              <td><?= e($r['quantity']) ?> <?= e($r['unit']??'') ?></td>
              <td><?= e($r['reason']??'—') ?></td>
              <td><?= e($r['requested_by']??'—') ?></td>
              <td><?= e(date('d/m/Y H:i',strtotime($r['created_at']))) ?></td>
              <td>
                <div style="display:flex;gap:6px">
                  <form method="POST">
                    <input type="hidden" name="action" value="approve"/>
                    <input type="hidden" name="type" value="stock_out"/>
                    <input type="hidden" name="request_id" value="<?=(int)$r['request_id']?>"/>
                    <button type="submit" style="padding:6px 12px;font-size:12px;background:#F0FDF4;border:1.5px solid #86EFAC;border-radius:8px;color:#166534;cursor:pointer;font-family:inherit"><span class="icon-ok">✓</span> Approuver</button>
                  </form>
                  <form method="POST">
                    <input type="hidden" name="action" value="reject"/>
                    <input type="hidden" name="type" value="stock_out"/>
                    <input type="hidden" name="request_id" value="<?=(int)$r['request_id']?>"/>
                    <input type="hidden" name="rejection_reason" value="Rejeté après vérification"/>
                    <button type="submit" style="padding:6px 12px;font-size:12px;background:#FEF2F2;border:1.5px solid #FECACA;border-radius:8px;color:#991B1B;cursor:pointer;font-family:inherit"><span class="icon-err">✗</span> Refuser</button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6" class="od-empty">Aucune demande de stock sortant en attente.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>
<script>
(function(){
  var btn = document.getElementById('ac-lang-btn');
  if (btn) btn.addEventListener('click', function(){
    var cur = localStorage.getItem('lt_lang') || 'fr';
    localStorage.setItem('lt_lang', cur === 'fr' ? 'en' : 'fr');
  });
  var saved = localStorage.getItem('lt_lang') || 'fr';
  if (btn) btn.textContent = saved === 'fr' ? 'EN' : 'FR';
})();
</script>
</body>
</html>