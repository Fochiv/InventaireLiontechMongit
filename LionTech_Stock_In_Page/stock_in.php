<?php
/* ============================================================
   stock_in.php — LionTech Business Manager
   FIXED: all roles allowed, correct redirects, od-layout
   Path: C:\Xampp\htdocs\InventoryLiontech\LionTech_Stock_In_Page\
         liontech_stock_in_page\stock_in.php
   ============================================================ */
require_once __DIR__ . '/../../Config.php';
startSecureSession();
requireLogin();

$user       = currentUser();
$pdo        = getDB();
$businessId = (int)($user['business_id'] ?? 0);
$role       = (string)($user['role'] ?? '');

if ($businessId <= 0) {
    header('Location: ' . APP_URL . '/Logininventory/login.php?error=unauthorized');
    exit;
}

function e($v): string     { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function qfmt($v): string  { return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.'); }
function slug_file(string $n): string {
    $n = strtolower(trim($n));
    $n = preg_replace('/[^a-z0-9\._-]+/', '-', $n);
    return trim($n, '-');
}

/* Business */
$stmt = $pdo->prepare('SELECT * FROM businesses WHERE business_id = ? LIMIT 1');
$stmt->execute([$businessId]);
$business = $stmt->fetch();
if (!$business) {
    header('Location: ' . APP_URL . '/Logininventory/login.php?error=unauthorized');
    exit;
}

/* Feature gate */
$inventoryEnabled = true;
try {
    $stmt = $pdo->prepare('SELECT inventory_management FROM business_features WHERE business_id = ? LIMIT 1');
    $stmt->execute([$businessId]);
    $features = $stmt->fetch();
    if ($features && (int)$features['inventory_management'] !== 1) $inventoryEnabled = false;
} catch (Throwable $ex) { $inventoryEnabled = true; }

/* ── Role gate — ALL roles allowed, approval enforced by workflow ── */
$isAllowed  = in_array($role, [ROLE_BUSINESS_OWNER, ROLE_MANAGER, ROLE_EMPLOYEE], true);
$isApprover = in_array($role, [ROLE_BUSINESS_OWNER, ROLE_MANAGER], true);
if (!$isAllowed) {
    header('Location: ' . APP_URL . '/Logininventory/login.php?error=unauthorized');
    exit;
}

$subscriptionStatus = $business['subscription_status'] ?? 'trial';
$isExpired  = in_array($subscriptionStatus, ['expired','suspended'], true);
$canCreate  = $inventoryEnabled && !$isExpired;
$canApprove = $isApprover && $inventoryEnabled && !$isExpired;

$message = ''; $messageType = '';

$uploadDir     = __DIR__ . '/uploads/stock_in';
$uploadUrlBase = 'uploads/stock_in';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);

/* ── Create Stock In Request ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_stock_in') {
    if (!$canCreate) {
        $message = 'Cette action est désactivée.'; $messageType = 'error';
    } else {
        $productId    = (int)($_POST['product_id']    ?? 0);
        $quantity     = (float)($_POST['quantity']      ?? 0);
        $supplier     = trim($_POST['supplier']       ?? '');
        $deliveryDate = trim($_POST['delivery_date']  ?? '');
        $note         = trim($_POST['note']           ?? '');
        $proofImageUrl = null;

        $stmt = $pdo->prepare('SELECT product_id FROM products WHERE product_id=? AND business_id=? AND status="active" LIMIT 1');
        $stmt->execute([$productId, $businessId]);
        $productExists = $stmt->fetchColumn();

        if (!$productExists) {
            $message = 'Veuillez sélectionner un produit valide.'; $messageType = 'error';
        } elseif ($quantity <= 0) {
            $message = 'La quantité doit être supérieure à zéro.'; $messageType = 'error';
        } else {
            if (!empty($_FILES['proof_image']['name']) && is_uploaded_file($_FILES['proof_image']['tmp_name'])) {
                $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                $mime = mime_content_type($_FILES['proof_image']['tmp_name']);
                if (isset($allowed[$mime]) && $_FILES['proof_image']['size'] <= 3*1024*1024) {
                    $fileName = time().'-'.random_int(1000,9999).'-'.slug_file($_FILES['proof_image']['name']);
                    $target = $uploadDir.'/'.$fileName;
                    if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $target))
                        $proofImageUrl = $uploadUrlBase.'/'.$fileName;
                }
            }
            try {
                $pdo->prepare('INSERT INTO stock_in_requests (business_id,product_id,quantity,supplier,delivery_date,note,proof_image_url,status,created_by,created_at) VALUES (?,?,?,?,?,?,?,"pending",?,NOW())')
                    ->execute([$businessId,$productId,$quantity,$supplier?:null,$deliveryDate?:null,$note?:null,$proofImageUrl,(int)$user['user_id']]);
                $message = 'Stock entrant soumis avec succès. En attente d\'approbation.'; $messageType = 'success';
            } catch (Throwable $ex) {
                $message = 'Erreur: '.$ex->getMessage(); $messageType = 'error';
            }
        }
    }
}

/* ── Approve ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve_stock_in' && $canApprove) {
    $requestId = (int)($_POST['request_id'] ?? 0);
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM stock_in_requests WHERE request_id=? AND business_id=? AND status="pending" FOR UPDATE');
        $stmt->execute([$requestId,$businessId]);
        $req = $stmt->fetch();
        if ($req) {
            $pdo->prepare('UPDATE products SET quantity=quantity+?,updated_at=NOW() WHERE product_id=? AND business_id=?')->execute([(float)$req['quantity'],(int)$req['product_id'],$businessId]);
            $pdo->prepare('UPDATE stock_in_requests SET status="approved",approved_by=?,approved_at=NOW() WHERE request_id=? AND business_id=?')->execute([(int)$user['user_id'],$requestId,$businessId]);
            $pdo->prepare('INSERT INTO stock_movements (request_id,business_id,product_id,movement_type,quantity,reason,supplier,proof_image_url,created_by,approved_by,created_at) VALUES (?,?,?,"stock_in",?,"Stock In approved",?,?,?,?,NOW())')->execute([$requestId,$businessId,(int)$req['product_id'],(float)$req['quantity'],$req['supplier'],$req['proof_image_url'],(int)$req['created_by'],(int)$user['user_id']]);
            $pdo->commit();
            $message = 'Stock entrant approuvé. Quantité mise à jour.'; $messageType = 'success';
        } else { $pdo->rollBack(); $message = 'Demande introuvable.'; $messageType = 'error'; }
    } catch (Throwable $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); $message = 'Erreur: '.$ex->getMessage(); $messageType = 'error'; }
}

/* ── Reject ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject_stock_in' && $canApprove) {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $reason    = trim($_POST['rejection_reason'] ?? 'Rejeté');
    $pdo->prepare('UPDATE stock_in_requests SET status="rejected",approved_by=?,approved_at=NOW(),rejection_reason=? WHERE request_id=? AND business_id=? AND status="pending"')->execute([(int)$user['user_id'],$reason,$requestId,$businessId]);
    $message = 'Demande rejetée.'; $messageType = 'success';
}

/* ── Load data ── */
$stmt = $pdo->prepare('SELECT product_id,name,sku,category,unit,quantity,low_stock_level,image_url FROM products WHERE business_id=? AND status="active" ORDER BY name ASC');
$stmt->execute([$businessId]);
$products = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT r.*,p.name AS product_name,p.unit,p.category,u.full_name AS created_by_name,a.full_name AS approved_by_name FROM stock_in_requests r JOIN products p ON p.product_id=r.product_id LEFT JOIN users u ON u.user_id=r.created_by LEFT JOIN users a ON a.user_id=r.approved_by WHERE r.business_id=? ORDER BY r.created_at DESC LIMIT 100');
$stmt->execute([$businessId]);
$requests = $stmt->fetchAll();

$pendingCount         = count(array_filter($requests, fn($r)=>$r['status']==='pending'));
$approvedToday        = count(array_filter($requests, fn($r)=>$r['status']==='approved'&&substr((string)$r['approved_at'],0,10)===date('Y-m-d')));
$totalReceivedPending = array_sum(array_map(fn($r)=>$r['status']==='pending'?(float)$r['quantity']:0, $requests));

$initials = '';
foreach (explode(' ', trim($user['full_name']??'U')) as $w) $initials .= strtoupper(substr($w,0,1));
$initials = substr($initials?:'U',0,2);
?>
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Stock Entrant — LionTech</title>
  <link rel="stylesheet" href="stock_in.css"/>
</head>
<body>
<div class="od-layout">
  <?php include __DIR__ . '/../LionTech_Owner_Dashboard/Sidebar.php'; ?>

  <main class="od-main">
    <header class="od-topbar">
      <div class="od-business-title">
        <h1>Stock Entrant</h1>
        <p>Enregistrez les produits qui entrent dans votre inventaire.</p>
      </div>
      <div class="od-top-actions">
        <button class="od-lang" id="si-lang">FR</button>
        <?php if ($canCreate && count($products) > 0): ?>
        <button class="od-primary" id="openStockIn" style="border:none;cursor:pointer;font-family:inherit">+ Ajouter Stock Entrant</button>
        <?php else: ?>
        <button class="od-primary" disabled style="opacity:.45;border:none;font-family:inherit">+ Ajouter Stock Entrant</button>
        <?php endif; ?>
        <div class="od-avatar"><?= e($initials) ?></div>
      </div>
    </header>

    <?php if (!$inventoryEnabled): ?>
    <div style="padding:40px 24px;text-align:center">
      <div style="font-size:40px">🔒</div>
      <h2 style="color:#0B1F3A">Inventaire non activé</h2>
      <p style="color:#6B7280;font-size:14px">Contactez LionTech pour activer cette fonctionnalité.</p>
    </div>
    <?php else: ?>

    <?php if ($isExpired): ?>
    <div style="background:#FEF3C7;border:1px solid #FDE68A;padding:12px 24px;font-size:13px;color:#92400E">
      ⚠️ Abonnement expiré. Vous pouvez consulter l'historique mais les nouvelles actions sont désactivées.
    </div>
    <?php endif; ?>

    <?php if (count($products) === 0): ?>
    <div style="background:#EFF6FF;border:1px solid #BFDBFE;padding:12px 24px;font-size:13px;color:#1E40AF">
      📦 Aucun produit trouvé. Ajoutez d'abord des produits avant d'enregistrer du stock entrant.
    </div>
    <?php endif; ?>

    <?php if ($role === ROLE_EMPLOYEE): ?>
    <div style="background:#FEF3C7;border:1px solid #FDE68A;padding:12px 24px;font-size:13px;color:#92400E">
      ⏳ Les demandes soumises par un employé nécessitent une approbation avant de modifier l'inventaire.
    </div>
    <?php endif; ?>

    <?php if ($message): ?>
    <div style="background:<?=$messageType==='success'?'#F0FDF4':'#FEF2F2'?>;border:1px solid <?=$messageType==='success'?'#86EFAC':'#FECACA'?>;padding:12px 24px;font-size:13px;color:<?=$messageType==='success'?'#166534':'#991B1B'?>">
      <?=$messageType==='success'?'✅':'⚠️'?> <?=e($message)?>
    </div>
    <?php endif; ?>

    <!-- Stat cards -->
    <div style="padding:20px 24px 0;display:grid;grid-template-columns:repeat(4,1fr);gap:14px">
      <div class="od-card stat"><span class="stat-icon amber">⏳</span><div><small>Demandes en attente</small><strong><?=(int)$pendingCount?></strong></div></div>
      <div class="od-card stat"><span class="stat-icon green">✅</span><div><small>Approuvés aujourd'hui</small><strong><?=(int)$approvedToday?></strong></div></div>
      <div class="od-card stat"><span class="stat-icon blue">📦</span><div><small>Produits actifs</small><strong><?=count($products)?></strong></div></div>
      <div class="od-card stat"><span class="stat-icon" style="background:#EDE9FE">➕</span><div><small>Qté en attente</small><strong><?=qfmt($totalReceivedPending)?></strong></div></div>
    </div>

    <!-- How it works -->
    <div style="padding:16px 24px 0">
      <div class="od-card" style="padding:16px 20px;background:#F8FAFC">
        <p style="font-size:13px;color:#6B7280;margin:0">
          ℹ️ Les employés soumettent les quantités reçues. Le propriétaire ou le manager vérifie et approuve. La quantité du produit est mise à jour uniquement après approbation.
        </p>
      </div>
    </div>

    <!-- Table -->
    <div style="padding:16px 24px 40px">
      <div class="od-card" style="padding:0;overflow:hidden">
        <div style="padding:16px 20px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #F1F5F9;flex-wrap:wrap;gap:10px">
          <div>
            <h2 style="font-size:15px;font-weight:700;color:#0B1F3A;margin:0">Demandes de stock entrant</h2>
            <p style="font-size:12px;color:#6B7280;margin:3px 0 0">Livraisons, approbations et entrées de stock en attente.</p>
          </div>
          <div style="display:flex;gap:10px">
            <input id="si-search" type="search" placeholder="Rechercher..." style="padding:8px 13px;border:1.5px solid #E5E7EB;border-radius:9px;font-size:13px;font-family:inherit"/>
            <select id="si-status-filter" style="padding:8px 13px;border:1.5px solid #E5E7EB;border-radius:9px;font-size:13px;font-family:inherit">
              <option value="all">Tout</option>
              <option value="pending">En attente</option>
              <option value="approved">Approuvé</option>
              <option value="rejected">Rejeté</option>
            </select>
          </div>
        </div>
        <div class="od-table-wrap">
          <table class="od-table" id="si-table">
            <thead>
              <tr>
                <th>Produit</th><th>Quantité</th><th>Fournisseur</th>
                <th>Date</th><th>Soumis par</th><th>Statut</th>
                <th>Preuve</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach($requests as $r): ?>
            <tr data-status="<?=e($r['status'])?>" data-search="<?=e(strtolower($r['product_name'].' '.($r['supplier']??'').' '.($r['created_by_name']??'')))?>">
              <td><strong><?=e($r['product_name'])?></strong><br><small style="color:#6B7280"><?=e($r['category']??'')?></small></td>
              <td><?=qfmt($r['quantity'])?> <?=e($r['unit']??'')?></td>
              <td><?=e($r['supplier']??'—')?></td>
              <td><?=e($r['delivery_date']??substr((string)$r['created_at'],0,10))?><br><small style="color:#6B7280"><?=e(substr((string)$r['created_at'],0,16))?></small></td>
              <td><?=e($r['created_by_name']??'—')?></td>
              <td>
                <span class="od-badge <?=$r['status']==='approved'?'success':'danger'?>">
                  <?=ucfirst(e($r['status']))?>
                </span>
              </td>
              <td><?php if($r['proof_image_url']): ?><a href="<?=e($r['proof_image_url'])?>" target="_blank" style="color:#1A9E7A;font-size:12px">Voir</a><?php else: ?>—<?php endif; ?></td>
              <td>
                <?php if($r['status']==='pending' && $canApprove): ?>
                <div style="display:flex;gap:6px">
                  <form method="POST">
                    <input type="hidden" name="action" value="approve_stock_in"/>
                    <input type="hidden" name="request_id" value="<?=(int)$r['request_id']?>"/>
                    <button type="submit" style="padding:5px 10px;font-size:12px;background:#F0FDF4;border:1.5px solid #86EFAC;border-radius:8px;color:#166534;cursor:pointer">Approuver</button>
                  </form>
                  <form method="POST">
                    <input type="hidden" name="action" value="reject_stock_in"/>
                    <input type="hidden" name="request_id" value="<?=(int)$r['request_id']?>"/>
                    <input type="hidden" name="rejection_reason" value="Rejeté après vérification"/>
                    <button type="submit" style="padding:5px 10px;font-size:12px;background:#FEF2F2;border:1.5px solid #FECACA;border-radius:8px;color:#991B1B;cursor:pointer">Rejeter</button>
                  </form>
                </div>
                <?php elseif($r['status']==='pending'): ?>
                <small style="color:#6B7280">En attente d'approbation</small>
                <?php elseif($r['status']==='approved'): ?>
                <small style="color:#166534">✅ <?=e($r['approved_by_name']??'Approuvé')?></small>
                <?php else: ?>
                <small style="color:#991B1B"><?=e($r['rejection_reason']??'Rejeté')?></small>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$requests): ?>
            <tr><td colspan="8" class="od-empty">Aucun stock entrant enregistré.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </main>
</div>

<!-- Add Stock In Modal -->
<div id="stockInModal" style="display:none;position:fixed;inset:0;z-index:500;background:rgba(11,31,58,.5);align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:18px;width:100%;max-width:560px;padding:28px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h2 style="font-size:17px;font-weight:700;color:#0B1F3A;margin:0">Ajouter Stock Entrant</h2>
      <button id="closeStockIn" style="background:none;border:none;font-size:22px;cursor:pointer;color:#6B7280">×</button>
    </div>
    <form method="POST" enctype="multipart/form-data" id="stockInForm" style="display:flex;flex-direction:column;gap:14px">
      <input type="hidden" name="action" value="create_stock_in"/>
      <div style="display:grid;grid-template-columns:1fr ;gap:14px">
        <div style="grid-column:1/-1">
          <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px">Produit *</label>
          <select name="product_id" required style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit">
            <option value="">Choisir un produit</option>
            <?php foreach($products as $p): ?>
            <option value="<?=(int)$p['product_id']?>"><?=e($p['name'])?> — <?=qfmt($p['quantity'])?> <?=e($p['unit']??'')?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px">Quantité reçue *</label>
          <input type="number" step="0.01" min="0.01" name="quantity" required placeholder="20" style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;box-sizing:border-box"/>
        </div>
        <div>
          <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px">Fournisseur</label>
          <input type="text" name="supplier" placeholder="Nom du fournisseur" style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;box-sizing:border-box"/>
        </div>
        <div>
          <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px">Date de livraison</label>
          <input type="date" name="delivery_date" value="<?=e(date('Y-m-d'))?>" style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;box-sizing:border-box"/>
        </div>
        <div style="grid-column:1/-1">
          <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px">Photo de livraison / facture</label>
          <input type="file" name="proof_image" accept="image/jpeg,image/png,image/webp" style="width:100%"/>
        </div>
        <div style="grid-column:1/-1">
          <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px">Note</label>
          <textarea name="note" rows="3" placeholder="Note optionnelle sur la livraison" style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;resize:vertical;box-sizing:border-box"></textarea>
        </div>
      </div>
      <div style="background:#FEF3C7;border:1px solid #FDE68A;border-radius:10px;padding:10px 14px;font-size:12.5px;color:#92400E">
        ⏳ Cette entrée de stock attendra l'approbation avant de modifier la quantité du produit.
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px">
        <button type="button" id="cancelStockIn" style="padding:11px 20px;background:#fff;border:1.5px solid #E5E7EB;border-radius:11px;font-size:13.5px;cursor:pointer;font-family:inherit">Annuler</button>
        <button type="submit" class="od-primary" style="padding:11px 24px;border:none;border-radius:11px;font-size:13.5px;font-weight:700;cursor:pointer;font-family:inherit">Soumettre</button>
      </div>
    </form>
  </div>
</div>

<script src="stock_in.js"></script>
</body>
</html>