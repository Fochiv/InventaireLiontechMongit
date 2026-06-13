<?php
require_once dirname(dirname(__DIR__)) . '/Config.php';

startSecureSession();

$pdo = getDB();

$token     = trim($_GET['token'] ?? '');
$transId   = (int)($_GET['id'] ?? 0);
$receiptId = (int)($_GET['rid'] ?? 0);
$isNew     = !empty($_GET['new']);

if ($token === '') {
    requireLogin();
    $currentUser = currentUser();
    $businessId = (int)($currentUser['business_id'] ?? 0);
} else {
    $currentUser = null;
    $businessId = 0;
}

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function fmtXAF($v) {
    return number_format((float)$v, 0, ',', ' ') . ' XAF';
}

function tableExists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function ensureReceiptTables(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS receipt_settings (
            setting_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            business_id INT UNSIGNED NOT NULL,
            brand_name VARCHAR(255) DEFAULT NULL,
            logo_url VARCHAR(500) DEFAULT NULL,
            brand_color VARCHAR(20) NOT NULL DEFAULT '#0B1F3A',
            return_policy TEXT DEFAULT NULL,
            footer_message TEXT DEFAULT NULL,
            show_cashier TINYINT(1) NOT NULL DEFAULT 1,
            show_client_phone TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (setting_id),
            UNIQUE KEY uq_receipt_settings_business (business_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS receipts (
            receipt_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            business_id INT UNSIGNED NOT NULL,
            transaction_id INT UNSIGNED NOT NULL,
            receipt_number VARCHAR(80) NOT NULL,
            public_token VARCHAR(80) NOT NULL,
            client_name VARCHAR(255) DEFAULT NULL,
            client_phone VARCHAR(30) DEFAULT NULL,
            cashier_id INT UNSIGNED DEFAULT NULL,
            cashier_name VARCHAR(255) DEFAULT NULL,
            total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            receipt_snapshot JSON DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (receipt_id),
            UNIQUE KEY uq_receipt_transaction (transaction_id),
            UNIQUE KEY uq_receipt_token (public_token),
            KEY idx_receipt_business_phone (business_id, client_phone),
            KEY idx_receipt_number (receipt_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function getReceiptSettings(PDO $pdo, int $businessId): array {
    $stmt = $pdo->prepare("
        SELECT 
            b.business_name,
            b.business_type,
            b.phone,
            b.email,
            b.city,
            b.address,
            b.logo_url AS business_logo,
            rs.brand_name,
            rs.logo_url AS receipt_logo,
            rs.brand_color,
            rs.return_policy,
            rs.footer_message,
            rs.show_cashier,
            rs.show_client_phone
        FROM businesses b
        LEFT JOIN receipt_settings rs ON rs.business_id = b.business_id
        WHERE b.business_id = ?
        LIMIT 1
    ");
    $stmt->execute([$businessId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'brand_name'        => $row['brand_name'] ?: ($row['business_name'] ?? 'Mon Business'),
        'business_type'     => $row['business_type'] ?? '',
        'phone'             => $row['phone'] ?? '',
        'email'             => $row['email'] ?? '',
        'city'              => $row['city'] ?? '',
        'address'           => $row['address'] ?? '',
        'logo_url'          => $row['receipt_logo'] ?: ($row['business_logo'] ?? ''),
        'brand_color'       => $row['brand_color'] ?: '#0B1F3A',
        'return_policy'     => $row['return_policy'] ?: '',
        'footer_message'    => $row['footer_message'] ?: 'Merci pour votre achat.',
        'show_cashier'      => isset($row['show_cashier']) ? (int)$row['show_cashier'] : 1,
        'show_client_phone' => isset($row['show_client_phone']) ? (int)$row['show_client_phone'] : 1,
    ];
}

function buildSnapshotFromTransaction(PDO $pdo, int $businessId, int $transId): ?array {
    $stmt = $pdo->prepare("
        SELECT tc.*, u.full_name AS cashier_name
        FROM transactions_caisse tc
        LEFT JOIN users u ON u.user_id = tc.caissier_id
        WHERE tc.transaction_id = ?
          AND tc.business_id = ?
        LIMIT 1
    ");
    $stmt->execute([$transId, $businessId]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM items_transaction
        WHERE transaction_id = ?
        ORDER BY item_id ASC
    ");
    $stmt->execute([$transId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT *
        FROM paiements_mixtes
        WHERE transaction_id = ?
        ORDER BY paiement_id ASC
    ");
    $stmt->execute([$transId]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $settings = getReceiptSettings($pdo, $businessId);

    return [
        'receipt_number' => $transaction['numero_facture'],
        'business'       => $settings,
        'transaction'    => $transaction,
        'items'          => $items,
        'payments'       => $payments,
        'created_at'     => $transaction['created_at'],
    ];
}

function createReceiptIfMissing(PDO $pdo, int $businessId, int $transId): ?array {
    $snapshot = buildSnapshotFromTransaction($pdo, $businessId, $transId);

    if (!$snapshot) {
        return null;
    }

    $transaction = $snapshot['transaction'];
    $token = bin2hex(random_bytes(24));

    $stmt = $pdo->prepare("
        INSERT INTO receipts (
            business_id,
            transaction_id,
            receipt_number,
            public_token,
            client_name,
            client_phone,
            cashier_id,
            cashier_name,
            total_amount,
            receipt_snapshot,
            created_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            receipt_snapshot = VALUES(receipt_snapshot)
    ");

    $stmt->execute([
        $businessId,
        $transId,
        $transaction['numero_facture'],
        $token,
        $transaction['client_nom'] ?? null,
        $transaction['client_phone'] ?? null,
        $transaction['caissier_id'] ?? null,
        $transaction['cashier_name'] ?? null,
        $transaction['total_ttc'] ?? 0,
        json_encode($snapshot, JSON_UNESCAPED_UNICODE),
    ]);

    return $snapshot;
}

ensureReceiptTables($pdo);

$receipt = null;
$snapshot = null;

if ($token !== '') {
    $stmt = $pdo->prepare("
        SELECT *
        FROM receipts
        WHERE public_token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($receipt) {
        $businessId = (int)$receipt['business_id'];
    }
} elseif ($receiptId > 0) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM receipts
        WHERE receipt_id = ?
          AND business_id = ?
        LIMIT 1
    ");
    $stmt->execute([$receiptId, $businessId]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($transId > 0) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM receipts
        WHERE transaction_id = ?
          AND business_id = ?
        LIMIT 1
    ");
    $stmt->execute([$transId, $businessId]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$receipt) {
        $snapshot = createReceiptIfMissing($pdo, $businessId, $transId);
    }
}

if ($receipt && !empty($receipt['receipt_snapshot'])) {
    $snapshot = json_decode($receipt['receipt_snapshot'], true);
}

if (!$snapshot && $transId > 0 && $businessId > 0) {
    $snapshot = buildSnapshotFromTransaction($pdo, $businessId, $transId);
}

if (!$snapshot) {
    http_response_code(404);
    exit('Facture introuvable.');
}

$business    = $snapshot['business'] ?? [];
$transaction = $snapshot['transaction'] ?? [];
$items       = $snapshot['items'] ?? [];
$payments    = $snapshot['payments'] ?? [];

$brandColor = $business['brand_color'] ?? '#0B1F3A';
$brandName  = $business['brand_name'] ?? 'Mon Business';

$modeLabels = [
    'especes'      => '<span class="icon-money">&#36;</span> Espèces',
    'mtn_momo'     => '<span class="icon-phone"><span class="icon-phone">☎</span></span> MTN MoMo',
    'orange_money' => '🟠 Orange Money',
];

$typeOperation = $transaction['type_operation'] ?? 'vente';
$badgeClass = $typeOperation === 'remboursement' ? 'remb' : ($typeOperation === 'abime' ? 'abime' : '');
$badgeText  = $typeOperation === 'vente' ? '✓ PAYÉ' : ($typeOperation === 'remboursement' ? '↩ REMBOURSEMENT' : '<span class="icon-warn">⚠</span> ABÎMÉ');

$printTitle = $transaction['numero_facture'] ?? ($snapshot['receipt_number'] ?? 'Facture');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Facture <?= e($printTitle) ?></title>
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body {
    font-family:'Segoe UI', Arial, sans-serif;
    background:#F3F4F6;
    color:#1F2937;
}
.action-bar {
    background:<?= e($brandColor) ?>;
    padding:12px 32px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
}
.action-bar h1 {
    color:#fff;
    font-size:15px;
    font-weight:800;
}
.action-btn {
    padding:9px 18px;
    border-radius:10px;
    font-size:13px;
    font-weight:800;
    cursor:pointer;
    border:none;
    font-family:inherit;
    text-decoration:none;
    display:inline-block;
}
.btn-print { background:#F59E0B; color:#0B1F3A; }
.btn-back { background:rgba(255,255,255,.15); color:#fff; margin-right:8px; }
.new-alert {
    background:#10B981;
    color:#fff;
    padding:12px 40px;
    font-size:13px;
    font-weight:800;
}
.facture-wrap {
    max-width:760px;
    margin:28px auto;
    background:#fff;
    border-radius:20px;
    overflow:hidden;
    box-shadow:0 8px 40px rgba(0,0,0,.1);
}
.fac-header {
    background:linear-gradient(135deg, <?= e($brandColor) ?> 0%, #1E3A5F 100%);
    color:#fff;
    padding:32px 40px 24px;
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:20px;
}
.fac-logo {
    height:54px;
    max-width:120px;
    object-fit:contain;
    border-radius:8px;
    margin-bottom:10px;
    background:#fff;
    padding:4px;
}
.fac-brand-name {
    font-size:26px;
    font-weight:900;
    letter-spacing:-1px;
}
.fac-brand-sub {
    font-size:11px;
    opacity:.7;
    margin-top:3px;
}
.fac-brand-info {
    font-size:12px;
    opacity:.8;
    margin-top:10px;
    line-height:1.7;
}
.fac-meta { text-align:right; }
.fac-num {
    font-size:20px;
    font-weight:900;
    color:#F59E0B;
}
.fac-date {
    font-size:12px;
    opacity:.7;
    margin-top:4px;
}
.fac-badge {
    display:inline-block;
    background:#10B981;
    color:#fff;
    font-size:11px;
    font-weight:800;
    padding:3px 12px;
    border-radius:20px;
    margin-top:8px;
}
.fac-badge.remb { background:#6366F1; }
.fac-badge.abime { background:#EF4444; }
.fac-type-bar {
    background:#F59E0B;
    color:#0B1F3A;
    padding:8px 40px;
    font-size:12px;
    font-weight:800;
    letter-spacing:1px;
    text-transform:uppercase;
}
.fac-body { padding:32px 40px; }
.fac-parties {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
    margin-bottom:28px;
}
.fac-party {
    background:#F9FAFB;
    border:1px solid #E5E7EB;
    border-radius:12px;
    padding:16px 18px;
}
.fac-party-label {
    font-size:10px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:1px;
    color:#9CA3AF;
    margin-bottom:8px;
}
.fac-party-name {
    font-size:15px;
    font-weight:800;
    color:#0B1F3A;
}
.fac-party-detail {
    font-size:12px;
    color:#6B7280;
    margin-top:4px;
    line-height:1.6;
}
.fac-table {
    width:100%;
    border-collapse:collapse;
    margin-bottom:24px;
    font-size:13.5px;
}
.fac-table thead tr {
    background:<?= e($brandColor) ?>;
    color:#fff;
}
.fac-table th,
.fac-table td {
    padding:12px 14px;
    border-bottom:1px solid #F3F4F6;
    text-align:left;
}
.fac-table th:last-child,
.fac-table td:last-child {
    text-align:right;
}
.fac-table th {
    font-size:12px;
}
.fac-table tfoot td {
    text-align:right;
    font-size:13px;
    color:#6B7280;
}
.grand-total td {
    font-size:16px !important;
    font-weight:900;
    color:#0B1F3A !important;
    border-top:2.5px solid <?= e($brandColor) ?>;
}
.grand-total td:last-child {
    color:#F59E0B !important;
}
.fac-paiements {
    background:#F0FDF4;
    border:1px solid #86EFAC;
    border-radius:12px;
    padding:16px 18px;
    margin-bottom:24px;
}
.fac-paiements-title {
    font-size:11px;
    font-weight:800;
    color:#166534;
    text-transform:uppercase;
    margin-bottom:8px;
}
.fac-paiement-row {
    display:flex;
    justify-content:space-between;
    font-size:13px;
    margin-bottom:4px;
}
.fac-monnaie {
    background:#EFF6FF;
    border:1px solid #BFDBFE;
    border-radius:12px;
    padding:12px 18px;
    margin-bottom:24px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    font-size:13px;
}
.fac-monnaie strong {
    font-size:16px;
    color:#1D4ED8;
}
.fac-note,
.fac-policy {
    background:#FFFBEB;
    border:1px solid #FDE68A;
    border-radius:10px;
    padding:12px 16px;
    font-size:12.5px;
    color:#78350F;
    margin-bottom:20px;
    line-height:1.6;
}
.fac-footer {
    background:#F9FAFB;
    border-top:1px solid #E5E7EB;
    padding:16px 40px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    font-size:11px;
    color:#9CA3AF;
}
.fac-footer strong {
    color:#0B1F3A;
}
@media(max-width:700px) {
    .action-bar,
    .fac-header,
    .fac-footer {
        flex-direction:column;
        align-items:flex-start;
    }
    .fac-meta {
        text-align:left;
    }
    .fac-parties {
        grid-template-columns:1fr;
    }
    .facture-wrap {
        margin:0;
        border-radius:0;
    }
    .fac-body,
    .fac-header,
    .fac-footer {
        padding:24px 18px;
    }
    .fac-table {
        font-size:12px;
    }
    .fac-table th,
    .fac-table td {
        padding:9px 8px;
    }
}
@media print {
    body { background:#fff; }
    .action-bar,
    .new-alert { display:none !important; }
    .facture-wrap {
        margin:0;
        box-shadow:none;
        border-radius:0;
    }
    @page {
        margin:10mm;
        size:A4;
    }
}
</style>
</head>
<body>

<div class="action-bar">
    <h1><span class="icon-brand">T</span> <?= e($brandName) ?> — Facture <?= e($printTitle) ?></h1>
    <div>
        <?php if ($token === ''): ?>
        <a href="<?= APP_URL ?>/Vente_cashier/Vente.php#receiptsTable" class="action-btn btn-back">← Retour</a>
        <?php endif; ?>
        <button onclick="window.print()" class="action-btn btn-print">🖨️ Imprimer / PDF</button>
    </div>
</div>

<?php if ($isNew): ?>
<div class="new-alert"><span class="icon-ok">✓</span> Vente enregistrée avec succès ! Imprimez cette facture pour le client.</div>
<?php endif; ?>

<div class="facture-wrap">

    <div class="fac-header">
        <div>
            <?php if (!empty($business['logo_url'])): ?>
            <img class="fac-logo" src="<?= e($business['logo_url']) ?>" alt="Logo">
            <?php endif; ?>

            <div class="fac-brand-name"><?= e($brandName) ?></div>
            <div class="fac-brand-sub"><?= e($business['business_type'] ?? '') ?></div>

            <div class="fac-brand-info">
                <?php if (!empty($business['address'])): ?><?= e($business['address']) ?><br><?php endif; ?>
                <?php if (!empty($business['city'])): ?><?= e($business['city']) ?><br><?php endif; ?>
                <?php if (!empty($business['phone'])): ?><?= e($business['phone']) ?><br><?php endif; ?>
                <?php if (!empty($business['email'])): ?><?= e($business['email']) ?><?php endif; ?>
            </div>
        </div>

        <div class="fac-meta">
            <div style="font-size:11px;opacity:.6;margin-bottom:4px">FACTURE</div>
            <div class="fac-num"><?= e($printTitle) ?></div>
            <div class="fac-date">
                <?= !empty($transaction['created_at']) ? date('d/m/Y à H:i', strtotime($transaction['created_at'])) : date('d/m/Y H:i') ?>
            </div>
            <div class="fac-badge <?= e($badgeClass) ?>"><?= e($badgeText) ?></div>
        </div>
    </div>

    <?php if ($typeOperation !== 'vente'): ?>
    <div class="fac-type-bar">
        <?= $typeOperation === 'remboursement' ? '↩ NOTE DE CRÉDIT / REMBOURSEMENT' : '<span class="icon-warn">⚠</span> DÉCLARATION PRODUIT ABÎMÉ' ?>
    </div>
    <?php endif; ?>

    <div class="fac-body">

        <div class="fac-parties">
            <div class="fac-party">
                <div class="fac-party-label">Vendeur</div>
                <div class="fac-party-name"><?= e($brandName) ?></div>
                <?php if (!empty($business['show_cashier'])): ?>
                <div class="fac-party-detail">
                    Caissier : <?= e($transaction['cashier_name'] ?? $transaction['caissier_nom'] ?? '—') ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="fac-party">
                <div class="fac-party-label">Client</div>
                <div class="fac-party-name"><?= e($transaction['client_nom'] ?: 'Client') ?></div>
                <?php if (!empty($business['show_client_phone']) && !empty($transaction['client_phone'])): ?>
                <div class="fac-party-detail"><?= e($transaction['client_phone']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($items)): ?>
        <table class="fac-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Désignation</th>
                    <th>SKU</th>
                    <th>P.U.</th>
                    <th>Qté</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $i => $item): ?>
                <tr>
                    <td style="color:#9CA3AF"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></td>
                    <td><strong><?= e($item['product_name'] ?? '') ?></strong></td>
                    <td style="color:#9CA3AF;font-size:11px"><?= e($item['product_sku'] ?? '—') ?></td>
                    <td><?= fmtXAF($item['prix_unitaire'] ?? 0) ?></td>
                    <td><?= rtrim(rtrim(number_format((float)($item['quantite'] ?? 0), 2, '.', ''), '0'), '.') ?></td>
                    <td><?= fmtXAF($item['total_ligne'] ?? 0) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5">Sous-total</td>
                    <td><?= fmtXAF($transaction['sous_total'] ?? 0) ?></td>
                </tr>

                <?php if (($transaction['remise_montant'] ?? 0) > 0): ?>
                <tr style="color:#EF4444">
                    <td colspan="5">
                        Remise
                        <?= ($transaction['remise_type'] ?? '') === 'pourcentage'
                            ? '(' . e($transaction['remise_valeur'] ?? 0) . '%)'
                            : '(fixe)' ?>
                    </td>
                    <td>- <?= fmtXAF($transaction['remise_montant'] ?? 0) ?></td>
                </tr>
                <?php endif; ?>

                <?php if (!empty($transaction['tva_active'])): ?>
                <tr>
                    <td colspan="5">TVA (<?= e($transaction['tva_taux'] ?? 0) ?>%)</td>
                    <td><?= fmtXAF($transaction['tva_montant'] ?? 0) ?></td>
                </tr>
                <?php endif; ?>

                <tr class="grand-total">
                    <td colspan="5">TOTAL TTC</td>
                    <td><?= fmtXAF($transaction['total_ttc'] ?? 0) ?></td>
                </tr>
            </tfoot>
        </table>
        <?php endif; ?>

        <?php if (!empty($transaction['note'])): ?>
        <div class="fac-note">
            <strong style="display:block;font-size:10px;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">Note</strong>
            <?= e($transaction['note']) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($payments)): ?>
        <div class="fac-paiements">
            <div class="fac-paiements-title">Mode(s) de paiement</div>

            <?php foreach ($payments as $payment): ?>
            <div class="fac-paiement-row">
                <span>
                    <?= $modeLabels[$payment['mode']] ?? e($payment['mode']) ?>
                    <?php if (!empty($payment['reference'])): ?>
                    — <small><?= e($payment['reference']) ?></small>
                    <?php endif; ?>
                </span>
                <strong><?= fmtXAF($payment['montant'] ?? 0) ?></strong>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (($transaction['monnaie_rendue'] ?? 0) > 0): ?>
        <div class="fac-monnaie">
            <span><span class="icon-money">&#36;</span> Monnaie rendue au client</span>
            <strong><?= fmtXAF($transaction['monnaie_rendue']) ?></strong>
        </div>
        <?php endif; ?>

        <?php if (!empty($business['return_policy'])): ?>
        <div class="fac-policy">
            <strong style="display:block;font-size:10px;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">Politique de retour</strong>
            <?= nl2br(e($business['return_policy'])) ?>
        </div>
        <?php endif; ?>

    </div>

    <div class="fac-footer">
        <div><?= e($business['footer_message'] ?? 'Merci pour votre achat.') ?></div>
        <div>Généré par <strong>Tally Business Manager</strong></div>
        <div>Facture N° <strong><?= e($printTitle) ?></strong></div>
    </div>

</div>

<?php if ($isNew): ?>
<script>
setTimeout(() => window.print(), 800);
</script>
<?php endif; ?>

</body>
</html>