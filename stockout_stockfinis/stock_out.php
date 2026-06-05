<?php
/* ============================================================
   stock_out.php — LionTech Business Manager
   FIXED: redirects, layout classes, all roles allowed
   ============================================================ */
require_once __DIR__ . '/../Config.php';
startSecureSession();

if (!isLoggedIn()) {
    header('Location: ' . APP_URL . '/Logininventory/login.php?error=session_expired');
    exit;
}

$role       = $_SESSION['role'] ?? '';
$businessId = $_SESSION['business_id'] ?? null;

$allowedRoles = [ROLE_BUSINESS_OWNER, ROLE_MANAGER, ROLE_EMPLOYEE];
if (!in_array($role, $allowedRoles, true)) {
    header('Location: ' . APP_URL . '/Logininventory/login.php?error=unauthorized');
    exit;
}

$success = '';
$error   = '';

function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

function uploadProofFile($inputName) {
    if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) throw new Exception('Proof upload failed.');
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    $mime = mime_content_type($_FILES[$inputName]['tmp_name']);
    if (!in_array($mime, $allowed, true)) throw new Exception('Only JPG, PNG, WEBP, or PDF files are allowed.');
    $dir = __DIR__ . '/uploads/stock_out/';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $ext = pathinfo($_FILES[$inputName]['name'], PATHINFO_EXTENSION);
    $filename = 'stockout_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
    $target = $dir . $filename;
    if (!move_uploaded_file($_FILES[$inputName]['tmp_name'], $target)) throw new Exception('Could not save proof file.');
    return 'uploads/stock_out/' . $filename;
}

try {
    $pdo = getDB();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_stock_out') {
        $productId  = (int)($_POST['product_id'] ?? 0);
        $quantity   = (float)($_POST['quantity'] ?? 0);
        $reason     = trim($_POST['reason'] ?? '');
        $note       = trim($_POST['note'] ?? '');
        $recipient  = trim($_POST['recipient'] ?? '');
        $recordedBy = $_SESSION['user_id'] ?? null;

        if ($productId <= 0) throw new Exception('Please select a product.');
        if ($quantity <= 0)  throw new Exception('Quantity must be greater than zero.');

        $allowedReasons = ['Sold','Used','Damaged','Expired','Missing/Lost','Returned'];
        if (!in_array($reason, $allowedReasons, true)) throw new Exception('Please select a valid stock-out reason.');

        $stmt = $pdo->prepare("SELECT product_id, name, quantity, unit FROM products WHERE product_id = ? AND business_id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$productId, $businessId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) throw new Exception('Product not found for this business.');
        if ($quantity > (float)$product['quantity']) throw new Exception('Stock out quantity cannot be higher than available stock.');

        $proofPath     = uploadProofFile('proof_file');
        $needsApproval = ($role === ROLE_EMPLOYEE);
        $status        = $needsApproval ? 'pending' : 'approved';

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO stock_out_requests (business_id, product_id, quantity, reason, recipient, note, proof_image_url, status, created_by, approved_by, approved_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $approvedBy = $needsApproval ? null : $recordedBy;
        $approvedAt = $needsApproval ? null : date('Y-m-d H:i:s');
        $stmt->execute([$businessId, $productId, $quantity, $reason, $recipient, $note, $proofPath, $status, $recordedBy, $approvedBy, $approvedAt]);
        $stockOutId = (int)$pdo->lastInsertId();

        if (!$needsApproval) {
            $pdo->prepare("UPDATE products SET quantity = quantity - ?, updated_at = NOW() WHERE product_id = ? AND business_id = ?")->execute([$quantity, $productId, $businessId]);
            $pdo->prepare("INSERT INTO stock_movements (business_id, product_id, movement_type, quantity, reason, request_id, created_by, created_at) VALUES (?, ?, 'stock_out', ?, ?, ?, ?, NOW())")->execute([$businessId, $productId, $quantity, $reason, $stockOutId, $recordedBy]);
            $success = 'Stock out recorded successfully and inventory updated.';
        } else {
            $pdo->prepare("INSERT INTO notifications (business_id, title, message, type, created_at) VALUES (?, 'Stock Out Approval Needed', ?, 'warning', NOW())")->execute([$businessId, 'An employee submitted a stock out request for ' . $product['name'] . '.']);
            $success = 'Stock out request submitted. Waiting for owner/manager approval.';
        }
        $pdo->commit();
    }

    $stmt = $pdo->prepare("SELECT product_id, name, category, quantity, unit, low_stock_level, image_url FROM products WHERE business_id = ? AND status = 'active' ORDER BY name ASC");
    $stmt->execute([$businessId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT so.*, p.name AS product_name, p.unit, u.full_name AS requested_by_name FROM stock_out_requests so JOIN products p ON so.product_id = p.product_id LEFT JOIN users u ON so.created_by = u.user_id WHERE so.business_id = ? ORDER BY so.created_at DESC LIMIT 50");
    $stmt->execute([$businessId]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $ex) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $error    = $ex->getMessage();
    $products = $products ?? [];
    $history  = $history  ?? [];
}

$isEmployee = ($role === ROLE_EMPLOYEE);
?>
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sortie de Stock — LionTech Business Manager</title>
    <link rel="stylesheet" href="stock_out.css">
</head>
<body>
<div class="od-layout">
   <?php include __DIR__ . '/../LionTech_Owner_Dashboard/Sidebar.php'; ?>

    <main class="od-main">
        <header class="od-topbar">
            <div class="od-business-title">
                <h1 data-i18n="page_title">Sortie de Stock</h1>
                <p data-i18n="page_subtitle">Enregistrez les produits vendus, utilisés, abîmés, expirés ou perdus.</p>
            </div>
            <button id="langBtn" class="od-lang">EN</button>
        </header>

        <?php if ($isEmployee): ?>
        <div style="background:#FEF3C7;border:1px solid #FDE68A;padding:12px 20px;margin:0 24px 16px;border-radius:12px;font-size:13px;color:#92400E">
            ⚠️ <span data-i18n="employee_warning">Les sorties de stock enregistrées par un employé doivent être approuvées avant de modifier l'inventaire.</span>
        </div>
        <?php endif; ?>

        <?php if ($success): ?><div style="background:#F0FDF4;border:1px solid #86EFAC;padding:12px 20px;margin:0 24px 16px;border-radius:12px;color:#166534;font-size:13px">✅ <?= e($success) ?></div><?php endif; ?>
        <?php if ($error):   ?><div style="background:#FEF2F2;border:1px solid #FECACA;padding:12px 20px;margin:0 24px 16px;border-radius:12px;color:#991B1B;font-size:13px">⚠️ <?= e($error) ?></div><?php endif; ?>

        <div style="padding:0 24px 40px;display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

            <div class="od-card" style="padding:28px">
                <h2 style="font-size:17px;font-weight:700;color:#0B1F3A;margin-bottom:20px" data-i18n="form_title">Nouvelle sortie de stock</h2>
                <form method="POST" enctype="multipart/form-data" id="stockOutForm" style="display:flex;flex-direction:column;gap:14px">
                    <input type="hidden" name="action" value="create_stock_out">

                    <div><label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px" data-i18n="product_label">Produit</label>
                    <select name="product_id" id="productSelect" required style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit">
                        <option value="" data-i18n="select_product">Sélectionner un produit</option>
                        <?php foreach ($products as $p): ?>
                        <option value="<?= e($p['product_id']) ?>" data-qty="<?= e($p['quantity']) ?>" data-unit="<?= e($p['unit']) ?>">
                            <?= e($p['name']) ?> — <?= e($p['quantity']) ?> <?= e($p['unit']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select></div>

                    <div id="stockInfo" style="font-size:12px;color:#6B7280;padding:8px 12px;background:#F8FAFC;border-radius:8px">Stock disponible: --</div>

                    <div><label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px" data-i18n="quantity_label">Quantité sortie</label>
                    <input type="number" step="0.01" min="0.01" name="quantity" id="quantityInput" required style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit"></div>

                    <div><label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px" data-i18n="reason_label">Raison</label>
                    <select name="reason" required style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit">
                        <option value="" data-i18n="select_reason">Choisir une raison</option>
                        <option value="Sold">Vendu</option>
                        <option value="Used">Utilisé</option>
                        <option value="Damaged">Abîmé</option>
                        <option value="Expired">Expiré</option>
                        <option value="Missing/Lost">Manquant/Perdu</option>
                        <option value="Returned">Retourné</option>
                    </select></div>

                    <div><label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px" data-i18n="recipient_label">Client / Destination (optionnel)</label>
                    <input type="text" name="recipient" placeholder="Ex: Client, Cuisine, Bar" style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit"></div>

                    <div><label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px" data-i18n="proof_label">Preuve / photo / reçu (optionnel)</label>
                    <input type="file" name="proof_file" accept="image/*,.pdf" style="width:100%"></div>

                    <div><label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px" data-i18n="note_label">Note</label>
                    <textarea name="note" rows="3" placeholder="Ajouter une note si nécessaire" style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;resize:vertical"></textarea></div>

                    <button type="submit" class="od-primary" style="padding:13px;font-size:14px;font-weight:700;border:none;cursor:pointer;border-radius:12px" data-i18n="submit_btn">Enregistrer la sortie</button>
                </form>
            </div>

            <div class="od-card" style="padding:24px">
                <h2 style="font-size:15px;font-weight:700;color:#0B1F3A;margin-bottom:12px" data-i18n="help_title">À quoi sert cette page?</h2>
                <p style="font-size:13px;color:#6B7280;margin-bottom:12px" data-i18n="help_text">Enregistre tout ce qui sort du stock: produits vendus, utilisés, abîmés, expirés ou perdus.</p>
                <ul style="font-size:13px;color:#6B7280;padding-left:16px;display:flex;flex-direction:column;gap:6px">
                    <li data-i18n="help_1">Empêche les sorties supérieures au stock disponible.</li>
                    <li data-i18n="help_2">Garde un historique de qui a enregistré la sortie.</li>
                    <li data-i18n="help_3">Demande une approbation si l'action vient d'un employé.</li>
                    <li data-i18n="help_4">Met à jour l'inventaire immédiatement pour owner/manager.</li>
                </ul>
            </div>
        </div>

        <div style="padding:0 24px 40px">
            <div class="od-card">
                <div class="od-card-head" style="padding:18px 20px 0">
                    <div><h2 data-i18n="history_title">Historique des sorties</h2></div>
                    <input type="search" id="searchHistory" placeholder="Rechercher..." style="padding:8px 13px;border:1.5px solid #E5E7EB;border-radius:9px;font-size:13px;font-family:inherit">
                </div>
                <div style="overflow-x:auto;padding:12px 0">
                    <table id="historyTable" style="width:100%;border-collapse:collapse;font-size:13px;min-width:620px">
                        <thead><tr style="background:#F8FAFC;border-bottom:1.5px solid #E5E7EB">
                            <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase" data-i18n="th_product">Produit</th>
                            <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase" data-i18n="th_qty">Quantité</th>
                            <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase" data-i18n="th_reason">Raison</th>
                            <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase" data-i18n="th_by">Enregistré par</th>
                            <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase" data-i18n="th_status">Statut</th>
                            <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase" data-i18n="th_date">Date</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($history as $row): ?>
                        <tr style="border-bottom:1px solid #F1F5F9">
                            <td style="padding:12px 16px"><?= e($row['product_name']) ?></td>
                            <td style="padding:12px 16px"><?= e($row['quantity']) ?> <?= e($row['unit']) ?></td>
                            <td style="padding:12px 16px"><?= e($row['reason']) ?></td>
                            <td style="padding:12px 16px"><?= e($row['requested_by_name'] ?? 'Utilisateur') ?></td>
                            <td style="padding:12px 16px">
                                <span style="padding:4px 10px;border-radius:50px;font-size:11px;font-weight:700;background:<?= $row['status']==='approved'?'#DCFCE7':'#FEF3C7' ?>;color:<?= $row['status']==='approved'?'#166534':'#92400E' ?>">
                                    <?= e($row['status']) ?>
                                </span>
                            </td>
                            <td style="padding:12px 16px;font-size:12px;color:#6B7280"><?= e($row['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($history)): ?>
                        <tr><td colspan="6" style="text-align:center;padding:32px;color:#6B7280" data-i18n="empty_history">Aucune sortie enregistrée.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="stock_out.js"></script>
</body>
</html>