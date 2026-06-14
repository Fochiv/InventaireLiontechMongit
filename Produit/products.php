<?php
/* ============================================================
   products.php — Tally Business Manager
   FIXED: all roles allowed, correct redirects, od-layout
   Path: C:\Xampp\htdocs\InventoryLiontech\Produit\products.php
   ============================================================ */
require_once __DIR__ . '/../Config.php';
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

function e($v): string    { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function money($v): string { return number_format((float)$v, 2); }
function slugify_file(string $n): string {
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

/* ── Role gate — ALL roles can view, only owner/manager can modify ── */
$isAllowed = in_array($role, [ROLE_BUSINESS_OWNER, ROLE_MANAGER, ROLE_EMPLOYEE], true);
if (!$isAllowed) {
    header('Location: ' . APP_URL . '/Logininventory/login.php?error=unauthorized');
    exit;
}

$subscriptionStatus = $business['subscription_status'] ?? 'trial';
$isExpired = in_array($subscriptionStatus, ['expired','suspended'], true);

/* Employees can only view — cannot add/archive/restore */
$canModify = $inventoryEnabled && !$isExpired && in_array($role, [ROLE_BUSINESS_OWNER, ROLE_MANAGER], true);

$message = ''; $messageType = '';

$uploadDir     = __DIR__ . '/uploads/products';
$uploadUrlBase = 'Produit/uploads/products';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);

/* ── Add Product ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_product') {
    if (!$canModify) {
        $message = 'Cette action est désactivée.'; $messageType = 'error';
    } else {
        $name           = trim($_POST['product_name']    ?? '');
        $categorySelect = trim($_POST['category_select'] ?? '');
        $categoryCustom = trim($_POST['category_custom'] ?? '');
        $category       = $categoryCustom !== '' ? $categoryCustom : $categorySelect;
        $unit           = trim($_POST['unit']            ?? 'piece');
        $quantity       = (float)($_POST['quantity']       ?? 0);
        $unitPrice      = (float)($_POST['unit_price']     ?? 0);
        $lowStockLevel  = (float)($_POST['low_stock_level']?? 0);
        $barcode        = trim($_POST['barcode']         ?? '');
        $expirationDate = trim($_POST['expiration_date'] ?? '');
        $supplier       = trim($_POST['supplier']        ?? '');
        $description    = trim($_POST['description']     ?? '');
        $imageUrl       = null;

        if ($name === '') {
            $message = 'Le nom du produit est obligatoire.'; $messageType = 'error';
        } elseif ($quantity < 0 || $unitPrice < 0 || $lowStockLevel < 0) {
            $message = 'La quantité, le prix et le seuil ne peuvent pas être négatifs.'; $messageType = 'error';
        } else {
            if (!empty($_FILES['product_image']['name']) && is_uploaded_file($_FILES['product_image']['tmp_name'])) {
                $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                $mime = mime_content_type($_FILES['product_image']['tmp_name']);
                if (isset($allowed[$mime]) && $_FILES['product_image']['size'] <= 2*1024*1024) {
                    $fileName = time().'-'.random_int(1000,9999).'-'.slugify_file($_FILES['product_image']['name']);
                    $target = $uploadDir.'/'.$fileName;
                    if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target))
                        $imageUrl = $uploadUrlBase.'/'.$fileName;
                }
            }
            try {
                $pdo->beginTransaction();
                $sku = 'PRD-'.strtoupper(substr(md5($businessId.$name.microtime()),0,8));
                $stmt = $pdo->prepare('INSERT INTO products (business_id,name,sku,barcode,category,unit,quantity,unit_price,low_stock_level,expiration_date,supplier,image_url,description,status,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,"active",?,NOW())');
                $stmt->execute([$businessId,$name,$sku,$barcode?:null,$category?:null,$unit,$quantity,$unitPrice,$lowStockLevel,$expirationDate?:null,$supplier?:null,$imageUrl,$description?:null,(int)$user['user_id']]);
                $productId = (int)$pdo->lastInsertId();
                $pdo->prepare('INSERT INTO stock_movements (business_id,product_id,movement_type,quantity,reason,created_by,created_at) VALUES (?,?,"initial",?,"Initial product quantity",?,NOW())')->execute([$businessId,$productId,$quantity,(int)$user['user_id']]);
                $pdo->commit();
                $message = 'Produit ajouté avec succès.'; $messageType = 'success';
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $message = 'Impossible de sauvegarder le produit: '.$ex->getMessage(); $messageType = 'error';
            }
        }
    }
}

/* ── Archive ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'archive_product' && $canModify) {
    $productId = (int)($_POST['product_id'] ?? 0);
    $pdo->prepare('UPDATE products SET status="archived",updated_at=NOW() WHERE product_id=? AND business_id=?')->execute([$productId,$businessId]);
    $message = 'Produit archivé.'; $messageType = 'success';
}

/* ── Restore ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore_product' && $canModify) {
    $productId = (int)($_POST['product_id'] ?? 0);
    $pdo->prepare('UPDATE products SET status="active",updated_at=NOW() WHERE product_id=? AND business_id=?')->execute([$productId,$businessId]);
    $message = 'Produit restauré.'; $messageType = 'success';
}

/* ── Load products ── */
$stmt = $pdo->prepare('SELECT * FROM products WHERE business_id=? ORDER BY created_at DESC');
$stmt->execute([$businessId]);
$products = $stmt->fetchAll();

$totalProducts = count(array_filter($products, fn($p)=>($p['status']??'active')==='active'));
$lowStock      = count(array_filter($products, fn($p)=>($p['status']??'active')==='active'&&(float)$p['quantity']<=(float)($p['low_stock_level']??0)&&(float)($p['low_stock_level']??0)>0));
$outOfStock    = count(array_filter($products, fn($p)=>($p['status']??'active')==='active'&&(float)$p['quantity']<=0));
$archived      = count(array_filter($products, fn($p)=>($p['status']??'active')==='archived'));

$categories = ['Boissons','Nourriture','Cosmétiques','Électronique','Vêtements','Médicaments','Quincaillerie','Nettoyage','Autre'];
$units      = ['pièce','carton','pack','kg','litre','bouteille','sac','boîte','mètre','autre'];

$initials = '';
foreach (explode(' ', trim($user['full_name']??'U')) as $w) $initials .= strtoupper(substr($w,0,1));
$initials = substr($initials?:'U',0,2);
?>
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Produits — LionTech</title>
  <link rel="icon" type="image/png" href="<?= APP_URL ?>/Image/TALLYLOGO.png"/>
  <link rel="stylesheet" href="products.css"/>
<link rel="stylesheet" href="<?= APP_URL ?>/icons.css">
<link rel="stylesheet" href="<?= APP_URL ?>/responsive_utils.css">
</head>
<body>
<div class="od-layout">
  <?php include __DIR__ . '/../LionTech_Owner_Dashboard/Sidebar.php'; ?>

  <main class="od-main">
    <header class="od-topbar">
      <div class="od-business-title">
        <h1>Produits</h1>
        <p>Gérez les produits de votre inventaire.</p>
      </div>
      <div class="od-top-actions">
        <button class="od-lang" id="pr-lang">FR</button>
        <?php if ($canModify): ?>
          <button class="od-primary" id="openAddProduct" style="border:none;cursor:pointer;font-family:inherit">+ Ajouter produit</button>
        <?php endif; ?>
        <div class="od-avatar"><?= e($initials) ?></div>
      </div>
    </header>

    <?php if (!$inventoryEnabled): ?>
    <div style="padding:40px 24px;text-align:center">
      <div style="font-size:40px"><span class="icon-lock">🔒</span></div>
      <h2 style="color:#0B1F3A;margin:12px 0 8px">Inventaire non activé</h2>
      <p style="color:#6B7280;font-size:14px">Contactez LionTech pour activer cette fonctionnalité.</p>
    </div>
    <?php else: ?>

    <?php if ($isExpired): ?>
    <div style="background:#FEF3C7;border:1px solid #FDE68A;padding:12px 24px;font-size:13px;color:#92400E">
      <span class="icon-warn">⚠</span> Abonnement expiré. Vous pouvez consulter les produits mais les modifications sont désactivées.
    </div>
    <?php endif; ?>

    <?php if ($role === ROLE_EMPLOYEE): ?>
    <div style="background:#EFF6FF;border:1px solid #BFDBFE;padding:12px 24px;font-size:13px;color:#1E40AF">
      👁️ Vous consultez les produits en lecture seule.
    </div>
    <?php endif; ?>

    <?php if ($message): ?>
    <div style="background:<?=$messageType==='success'?'#F0FDF4':'#FEF2F2'?>;border:1px solid <?=$messageType==='success'?'#86EFAC':'#FECACA'?>;padding:12px 24px;font-size:13px;color:<?=$messageType==='success'?'#166534':'#991B1B'?>">
      <?=$messageType==='success'?'<span class="icon-ok">✓</span>':'<span class="icon-warn">⚠</span>'?> <?=e($message)?>
    </div>
    <?php endif; ?>

    <!-- Stat cards -->
    <div class="pr-stat-grid" style="padding:20px 24px 0">
      <div class="od-card stat">
        <span class="stat-icon blue"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></span>
        <div><small>Total Produits</small><strong><?=e($totalProducts)?></strong></div>
      </div>
      <div class="od-card stat">
        <span class="stat-icon amber"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span>
        <div><small>Stock Faible</small><strong><?=e($lowStock)?></strong></div>
      </div>
      <div class="od-card stat">
        <span class="stat-icon" style="background:#FEE2E2"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></span>
        <div><small>Rupture</small><strong><?=e($outOfStock)?></strong></div>
      </div>
      <div class="od-card stat">
        <span class="stat-icon" style="background:#F1F5F9"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></span>
        <div><small>Archivés</small><strong><?=e($archived)?></strong></div>
      </div>
    </div>
    <style>.pr-stat-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}@media(max-width:900px){.pr-stat-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:480px){.pr-stat-grid{grid-template-columns:1fr}}</style>

    <!-- Toolbar -->
    <div style="padding:16px 24px 0;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
      <div style="display:flex;align-items:center;gap:8px;background:#fff;border:1.5px solid #E5E7EB;border-radius:10px;padding:8px 13px;flex:1;min-width:200px">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="search" id="productSearch" placeholder="Rechercher des produits..."
          style="border:none;outline:none;font-size:13.5px;width:100%;font-family:inherit"/>
      </div>
      <select id="categoryFilter" style="padding:9px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13px;font-family:inherit">
        <option value="">Toutes catégories</option>
        <?php foreach($categories as $cat): ?><option value="<?=e(strtolower($cat))?>"><?=e($cat)?></option><?php endforeach; ?>
      </select>
      <select id="stockFilter" style="padding:9px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13px;font-family:inherit">
        <option value="">Tout le stock</option>
        <option value="low">Stock Faible</option>
        <option value="out">Rupture</option>
        <option value="archived">Archivés</option>
      </select>
      <?php if ($canModify): ?>
      <button id="exportCsv" style="padding:9px 16px;background:#fff;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13px;cursor:pointer;font-family:inherit">Export</button>
      <?php endif; ?>
    </div>

    <!-- Table -->
    <div style="padding:16px 24px 40px">
      <div class="od-card" style="padding:0;overflow:hidden">
        <div style="padding:16px 20px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #F1F5F9">
          <h2 style="font-size:15px;font-weight:700;color:#0B1F3A;margin:0">Liste des produits</h2>
          <span style="font-size:12px;color:#6B7280">Les produits sont archivés, jamais supprimés.</span>
        </div>
        <div class="od-table-wrap">
          <table class="od-table" id="productsTable">
            <thead>
              <tr>
                <th>Produit</th><th>Catégorie</th><th>Quantité</th>
                <th>Prix</th><th>Seuil</th><th>Expiration</th>
                <th>Statut</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$products): ?>
              <tr><td colspan="8" class="od-empty">Aucun produit. Ajoutez votre premier produit.</td></tr>
              <?php endif; ?>
              <?php foreach($products as $p):
                $q         = (float)$p['quantity'];
                $low       = (float)($p['low_stock_level'] ?? 0);
                $status    = $p['status'] ?? 'active';
                $stockState= $status==='archived'?'archived':($q<=0?'out':($low>0&&$q<=$low?'low':'ok'));
              ?>
              <tr data-name="<?=e(strtolower($p['name']))?>"
                  data-category="<?=e(strtolower($p['category']??''))?>"
                  data-stock="<?=e($stockState)?>"
                  data-status="<?=e($status)?>">
                <td>
                  <div class="product-cell">
                    <div class="product-img">
                      <?php if(!empty($p['image_url'])): ?><?php
                        $imgSrc=$p['image_url'];
                        if(!str_starts_with($imgSrc,'http')){
                            if(str_starts_with($imgSrc,'uploads/products/'))$imgSrc=APP_URL.'/Produit/'.$imgSrc;
                            else $imgSrc=APP_URL.'/'.ltrim($imgSrc,'/');
                        }
                      ?><img src="<?=e($imgSrc)?>" alt=""/><?php else: ?><span class="icon-box">▣</span><?php endif; ?>
                    </div>
                    <div><strong><?=e($p['name'])?></strong><small><?=e($p['sku']??'')?></small></div>
                  </div>
                </td>
                <td><?=e($p['category']??'—')?></td>
                <td><strong><?=rtrim(rtrim(number_format($q,2),'0'),'.')?></strong> <?=e($p['unit']??'')?></td>
                <td><?=money($p['unit_price']??0)?> XAF</td>
                <td><?=rtrim(rtrim(number_format($low,2),'0'),'.')?></td>
                <td><?=e($p['expiration_date']??'—')?></td>
                <td>
                  <span class="od-badge <?=$stockState==='ok'||$stockState==='active'?'success':'danger'?>">
                    <?=$stockState==='ok'?'OK':ucfirst($stockState)?>
                  </span>
                </td>
                <td>
                  <div style="display:flex;gap:6px;flex-wrap:wrap">
                    <button type="button" style="padding:5px 10px;font-size:12px;border:1.5px solid #E5E7EB;border-radius:8px;background:#fff;cursor:pointer"
                      data-view-product='<?=e(json_encode($p))?>'>Voir</button>
                    <?php if ($canModify && $status !== 'archived'): ?>
                    <form method="POST" onsubmit="return confirm('Archiver ce produit?')">
                      <input type="hidden" name="action" value="archive_product"/>
                      <input type="hidden" name="product_id" value="<?=(int)$p['product_id']?>"/>
                      <button type="submit" style="padding:5px 10px;font-size:12px;border:1.5px solid #FECACA;border-radius:8px;background:#FEF2F2;color:#991B1B;cursor:pointer">Archiver</button>
                    </form>
                    <?php elseif ($canModify && $status === 'archived'): ?>
                    <form method="POST">
                      <input type="hidden" name="action" value="restore_product"/>
                      <input type="hidden" name="product_id" value="<?=(int)$p['product_id']?>"/>
                      <button type="submit" style="padding:5px 10px;font-size:12px;border:1.5px solid #86EFAC;border-radius:8px;background:#F0FDF4;color:#166534;cursor:pointer">Restaurer</button>
                    </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </main>
</div>

<!-- Add Product Modal -->
<?php if ($canModify): ?>
<div id="addProductModal" style="display:none;position:fixed;inset:0;z-index:500;background:rgba(11,31,58,.5);display:none;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:18px;width:100%;max-width:680px;max-height:90vh;overflow-y:auto;padding:28px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h2 style="font-size:18px;font-weight:700;color:#0B1F3A;margin:0">Ajouter un produit</h2>
      <button type="button" id="closeAddProduct" style="background:none;border:none;font-size:22px;cursor:pointer;color:#6B7280">×</button>
    </div>
    <form method="POST" enctype="multipart/form-data" id="addProductForm">
      <input type="hidden" name="action" value="add_product"/>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div style="grid-column:1/-1">
          <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px">Nom du produit *</label>
          <input name="product_name" required placeholder="Ex: Coca-Cola" style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;box-sizing:border-box"/>
        </div>
        <div>
          <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px">Catégorie</label>
          <select name="category_select" style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit">
            <option value="">Sélectionner...</option>
            <?php foreach($categories as $cat): ?><option value="<?=e($cat)?>"><?=e($cat)?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px">Catégorie personnalisée</label>
          <input name="category_custom" placeholder="Ex: Produits beauté" style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;box-sizing:border-box"/>
        </div>
        <div>
          <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px">Unité *</label>
          <select name="unit" required style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit">
            <?php foreach($units as $u): ?><option value="<?=e($u)?>"><?=e($u)?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px">Quantité *</label>
          <input name="quantity" type="number" step="0.01" min="0" required value="0" style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;box-sizing:border-box"/>
        </div>
        <div>
          <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px">Prix unitaire (XAF)</label>
          <input name="unit_price" type="number" step="0.01" min="0" value="0" style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;box-sizing:border-box"/>
        </div>
        <div>
          <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px">Seuil stock faible</label>
          <input name="low_stock_level" type="number" step="0.01" min="0" value="0" style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;box-sizing:border-box"/>
        </div>
        <div>
          <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px">Code-barres</label>
          <input name="barcode" placeholder="Optionnel" style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;box-sizing:border-box"/>
        </div>
        <div>
          <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px">Date d'expiration</label>
          <input name="expiration_date" type="date" style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;box-sizing:border-box"/>
        </div>
        <div>
          <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px">Fournisseur</label>
          <input name="supplier" placeholder="Optionnel" style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;box-sizing:border-box"/>
        </div>
        <div style="grid-column:1/-1">
          <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px">Image produit</label>
          <input name="product_image" type="file" accept="image/png,image/jpeg,image/webp" style="width:100%"/>
          <small style="color:#6B7280;font-size:11px">Optionnel. Max 2MB.</small>
        </div>
        <div style="grid-column:1/-1">
          <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px">Description / Notes</label>
          <textarea name="description" rows="3" placeholder="Notes optionnelles" style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;resize:vertical;box-sizing:border-box"></textarea>
        </div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px">
        <button type="button" id="cancelAddProduct" style="padding:11px 20px;background:#fff;border:1.5px solid #E5E7EB;border-radius:11px;font-size:13.5px;cursor:pointer;font-family:inherit">Annuler</button>
        <button type="submit" class="od-primary" style="padding:11px 24px;border:none;border-radius:11px;font-size:13.5px;font-weight:700;cursor:pointer;font-family:inherit">Sauvegarder</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div id="viewProductModal" style="display:none;position:fixed;inset:0;z-index:500;background:rgba(11,31,58,.5);align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:18px;width:100%;max-width:520px;padding:28px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h2 style="font-size:17px;font-weight:700;color:#0B1F3A;margin:0">Détails du produit</h2>
      <button type="button" id="closeViewProduct" style="background:none;border:none;font-size:22px;cursor:pointer;color:#6B7280">×</button>
    </div>
    <div id="viewProductBody"></div>
  </div>
</div>

<script src="products.js"></script>
</body>
</html>