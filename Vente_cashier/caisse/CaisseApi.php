<?php
require_once __DIR__ . '/../../Config.php';

startSecureSession();
requireRole([ROLE_BUSINESS_OWNER, ROLE_MANAGER, ROLE_EMPLOYEE, 'caissier']);

header('Content-Type: application/json; charset=utf-8');

$user   = currentUser();
$pdo    = getDB();
$bizId  = (int)($user['business_id'] ?? 0);
$userId = (int)($user['user_id'] ?? 0);
$role   = (string)($user['role'] ?? '');

function out($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function body() {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
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

function colExists(PDO $pdo, string $table, string $col): bool {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$col]);
        return (bool)$stmt->fetch();
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

function nextInvoiceNumber(PDO $pdo, int $bizId): string {
    $year = (int)date('Y');

    $pdo->prepare("
        INSERT INTO facture_sequence (business_id, annee, dernier_num)
        VALUES (?, ?, 0)
        ON DUPLICATE KEY UPDATE dernier_num = dernier_num
    ")->execute([$bizId, $year]);

    $pdo->prepare("
        UPDATE facture_sequence
        SET dernier_num = dernier_num + 1
        WHERE business_id = ? AND annee = ?
    ")->execute([$bizId, $year]);

    $stmt = $pdo->prepare("
        SELECT dernier_num
        FROM facture_sequence
        WHERE business_id = ? AND annee = ?
        LIMIT 1
    ");
    $stmt->execute([$bizId, $year]);

    $num = (int)$stmt->fetchColumn();

    return 'FAC-' . $year . '-' . str_pad((string)$num, 5, '0', STR_PAD_LEFT);
}

function getReceiptSettings(PDO $pdo, int $bizId): array {
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
    $stmt->execute([$bizId]);
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

function createReceiptSnapshot(
    PDO $pdo,
    int $bizId,
    int $transactionId,
    string $invoice,
    array $settings
): array {
    $stmt = $pdo->prepare("
        SELECT tc.*, u.full_name AS cashier_name
        FROM transactions_caisse tc
        LEFT JOIN users u ON u.user_id = tc.caissier_id
        WHERE tc.transaction_id = ? AND tc.business_id = ?
        LIMIT 1
    ");
    $stmt->execute([$transactionId, $bizId]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        throw new Exception('Transaction introuvable pour le reçu.');
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM items_transaction
        WHERE transaction_id = ?
        ORDER BY item_id ASC
    ");
    $stmt->execute([$transactionId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT *
        FROM paiements_mixtes
        WHERE transaction_id = ?
        ORDER BY paiement_id ASC
    ");
    $stmt->execute([$transactionId]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'receipt_number' => $invoice,
        'business'       => $settings,
        'transaction'    => $transaction,
        'items'          => $items,
        'payments'       => $payments,
        'created_at'     => date('Y-m-d H:i:s'),
    ];
}

function saveReceipt(PDO $pdo, int $bizId, int $transactionId, string $invoice): array {
    ensureReceiptTables($pdo);

    $settings = getReceiptSettings($pdo, $bizId);
    $snapshot = createReceiptSnapshot($pdo, $bizId, $transactionId, $invoice, $settings);

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
            receipt_number = VALUES(receipt_number),
            client_name = VALUES(client_name),
            client_phone = VALUES(client_phone),
            cashier_id = VALUES(cashier_id),
            cashier_name = VALUES(cashier_name),
            total_amount = VALUES(total_amount),
            receipt_snapshot = VALUES(receipt_snapshot)
    ");

    $stmt->execute([
        $bizId,
        $transactionId,
        $invoice,
        $token,
        $transaction['client_nom'] ?? null,
        $transaction['client_phone'] ?? null,
        $transaction['caissier_id'] ?? null,
        $transaction['cashier_name'] ?? null,
        $transaction['total_ttc'] ?? 0,
        json_encode($snapshot, JSON_UNESCAPED_UNICODE),
    ]);

    $receiptId = (int)$pdo->lastInsertId();

    if ($receiptId <= 0) {
        $stmt = $pdo->prepare("SELECT receipt_id, public_token FROM receipts WHERE transaction_id = ? LIMIT 1");
        $stmt->execute([$transactionId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        $receiptId = (int)($existing['receipt_id'] ?? 0);
        $token = $existing['public_token'] ?? $token;
    }

    return [
        'receipt_id' => $receiptId,
        'token'      => $token,
    ];
}

function requireCaissePinIfNeeded(string $role): void {
    if (in_array($role, [ROLE_BUSINESS_OWNER, ROLE_MANAGER], true)) {
        return;
    }

    if (!empty($_SESSION['caisse_pin_ok'])) {
        return;
    }

    out([
        'success' => false,
        'code'    => 'pin_required',
        'message' => 'PIN caisse requis avant d’ouvrir la caisse.'
    ], 403);
}

$action = $_GET['action'] ?? '';

try {
    ensureReceiptTables($pdo);

    switch ($action) {

        case 'get_settings':
            $stmt = $pdo->prepare("
                SELECT 
                    b.business_name,
                    bs.tva_enabled,
                    bs.tva_rate,
                    bs.caisse_code
                FROM businesses b
                LEFT JOIN business_settings bs ON bs.business_id = b.business_id
                WHERE b.business_id = ?
                LIMIT 1
            ");
            $stmt->execute([$bizId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            out([
                'success'       => true,
                'business_name' => $row['business_name'] ?? '',
                'tva_enabled'   => (bool)($row['tva_enabled'] ?? false),
                'tva_rate'      => (float)($row['tva_rate'] ?? 19.25),
                'requires_code' => !empty($row['caisse_code']),
                'requires_pin'  => !in_array($role, [ROLE_BUSINESS_OWNER, ROLE_MANAGER], true),
            ]);

        case 'has_pin':
            $hasPin = false;

            try {
                $stmt = $pdo->prepare("SELECT pin_hash FROM pin_codes WHERE user_id = ? AND business_id = ? LIMIT 1");
                $stmt->execute([$userId, $bizId]);
                $hasPin = (bool)$stmt->fetch();
            } catch (Throwable $e) {}

            if (!$hasPin) {
                try {
                    $stmt = $pdo->prepare("SELECT temporary_pin_plain FROM users WHERE user_id = ? AND business_id = ? LIMIT 1");
                    $stmt->execute([$userId, $bizId]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $hasPin = !empty($row['temporary_pin_plain']);
                } catch (Throwable $e) {}
            }

            out(['success' => true, 'has_pin' => $hasPin]);

        case 'verify_pin':
            $pin = (string)(body()['pin'] ?? '');

            if ($pin === '') {
                out(['success' => false, 'message' => 'PIN requis.'], 400);
            }

            try {
                $stmt = $pdo->prepare("SELECT pin_hash FROM pin_codes WHERE user_id = ? AND business_id = ? LIMIT 1");
                $stmt->execute([$userId, $bizId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row && !empty($row['pin_hash'])) {
                    $hash = str_replace('$2b$', '$2y$', $row['pin_hash']);
                    if (password_verify($pin, $hash)) {
                        $_SESSION['caisse_pin_ok'] = true;
                        out(['success' => true]);
                    }
                }
            } catch (Throwable $e) {}

            try {
                $stmt = $pdo->prepare("
                    SELECT temporary_pin_plain
                    FROM users
                    WHERE user_id = ? AND business_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$userId, $bizId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!empty($row['temporary_pin_plain']) && hash_equals($row['temporary_pin_plain'], $pin)) {
                    $_SESSION['caisse_pin_ok'] = true;
                    out(['success' => true]);
                }
            } catch (Throwable $e) {}

            out(['success' => false, 'message' => 'PIN incorrect. Vérifiez votre PIN caisse.']);

        case 'verify_caisse_code':
            $code = trim(body()['code'] ?? '');

            if ($code === '') {
                out(['success' => false, 'message' => 'Code caisse requis.'], 400);
            }

            $stmt = $pdo->prepare("
                SELECT caisse_code
                FROM business_settings
                WHERE business_id = ?
                LIMIT 1
            ");
            $stmt->execute([$bizId]);
            $storedCode = (string)($stmt->fetchColumn() ?: '');

            if ($storedCode === '') {
                $_SESSION['caisse_code_ok'] = true;
                out(['success' => true]);
            }

            if (hash_equals($storedCode, $code)) {
                $_SESSION['caisse_code_ok'] = true;
                out(['success' => true]);
            }

            out(['success' => false, 'message' => 'Code caisse incorrect.']);

        case 'check_session_today':
            $stmt = $pdo->prepare("
                SELECT session_id, fond_ouverture, ouverture_at,
                       total_ventes, total_especes, total_mtn, total_orange
                FROM sessions_caisse
                WHERE business_id = ?
                  AND caissier_id = ?
                  AND statut = 'ouverte'
                  AND DATE(ouverture_at) = CURDATE()
                ORDER BY session_id DESC
                LIMIT 1
            ");
            $stmt->execute([$bizId, $userId]);

            out([
                'success' => true,
                'session' => $stmt->fetch(PDO::FETCH_ASSOC) ?: null
            ]);

        case 'open_session':
            requireCaissePinIfNeeded($role);

            $fond = max(0, (float)(body()['fond_caisse'] ?? 0));

            $pdo->prepare("
                UPDATE sessions_caisse
                SET statut = 'fermee', fermeture_at = NOW()
                WHERE business_id = ?
                  AND caissier_id = ?
                  AND statut = 'ouverte'
            ")->execute([$bizId, $userId]);

            $pdo->prepare("
                INSERT INTO sessions_caisse (
                    business_id,
                    caissier_id,
                    fond_ouverture,
                    ouverture_at,
                    statut
                )
                VALUES (?, ?, ?, NOW(), 'ouverte')
            ")->execute([$bizId, $userId, $fond]);

            out([
                'success'    => true,
                'session_id' => (int)$pdo->lastInsertId(),
                'fond'       => $fond
            ]);

        case 'get_session':
            $stmt = $pdo->prepare("
                SELECT cs.*,
                    COALESCE(SUM(CASE WHEN tc.statut = 'validee' THEN tc.total_ttc ELSE 0 END), 0) AS total_ventes,
                    COALESCE(SUM(CASE WHEN pm.mode = 'especes' THEN pm.montant ELSE 0 END), 0) AS total_especes,
                    COALESCE(SUM(CASE WHEN pm.mode = 'mtn_momo' THEN pm.montant ELSE 0 END), 0) AS total_mtn,
                    COALESCE(SUM(CASE WHEN pm.mode = 'orange_money' THEN pm.montant ELSE 0 END), 0) AS total_orange
                FROM sessions_caisse cs
                LEFT JOIN transactions_caisse tc ON tc.session_id = cs.session_id
                LEFT JOIN paiements_mixtes pm ON pm.transaction_id = tc.transaction_id
                WHERE cs.business_id = ?
                  AND cs.caissier_id = ?
                  AND cs.statut = 'ouverte'
                GROUP BY cs.session_id
                ORDER BY cs.session_id DESC
                LIMIT 1
            ");
            $stmt->execute([$bizId, $userId]);

            out([
                'success' => true,
                'session' => $stmt->fetch(PDO::FETCH_ASSOC) ?: null
            ]);

        case 'close_session':
            $sessionId = (int)(body()['session_id'] ?? 0);

            if ($sessionId <= 0) {
                out(['success' => false, 'message' => 'session_id requis.'], 400);
            }

            $stmt = $pdo->prepare("
                SELECT cs.*,
                    COALESCE(SUM(CASE WHEN tc.statut = 'validee' THEN tc.total_ttc ELSE 0 END), 0) AS total_ventes,
                    COALESCE(SUM(CASE WHEN pm.mode = 'especes' THEN pm.montant ELSE 0 END), 0) AS total_especes,
                    COALESCE(SUM(CASE WHEN pm.mode = 'mtn_momo' THEN pm.montant ELSE 0 END), 0) AS total_mtn,
                    COALESCE(SUM(CASE WHEN pm.mode = 'orange_money' THEN pm.montant ELSE 0 END), 0) AS total_orange
                FROM sessions_caisse cs
                LEFT JOIN transactions_caisse tc ON tc.session_id = cs.session_id
                LEFT JOIN paiements_mixtes pm ON pm.transaction_id = tc.transaction_id
                WHERE cs.session_id = ?
                  AND cs.business_id = ?
                  AND cs.caissier_id = ?
                  AND cs.statut = 'ouverte'
                GROUP BY cs.session_id
                LIMIT 1
            ");
            $stmt->execute([$sessionId, $bizId, $userId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session) {
                out(['success' => false, 'message' => 'Aucune session ouverte.'], 404);
            }

            $pdo->prepare("
                UPDATE sessions_caisse
                SET statut = 'fermee',
                    fermeture_at = NOW(),
                    total_ventes = ?,
                    total_especes = ?,
                    total_mtn = ?,
                    total_orange = ?
                WHERE session_id = ?
                  AND business_id = ?
                  AND caissier_id = ?
            ")->execute([
                $session['total_ventes'],
                $session['total_especes'],
                $session['total_mtn'],
                $session['total_orange'],
                $sessionId,
                $bizId,
                $userId
            ]);

            unset($_SESSION['caisse_pin_ok']);

            out(['success' => true, 'summary' => $session]);

        case 'get_products':
            $q = trim($_GET['q'] ?? '');

            if ($q === '') {
                out(['success' => true, 'products' => []]);
            }

            $like = '%' . $q . '%';

            $stmt = $pdo->prepare("
                SELECT product_id, name, sku, barcode, unit_price, cost_price,
                       quantity, unit, low_stock_level, image_url
                FROM products
                WHERE business_id = ?
                  AND status = 'active'
                  AND (
                    name LIKE ?
                    OR sku LIKE ?
                    OR barcode LIKE ?
                  )
                ORDER BY name ASC
                LIMIT 60
            ");
            $stmt->execute([$bizId, $like, $like, $like]);

            out(['success' => true, 'products' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

        case 'next_invoice':
            out([
                'success' => true,
                'invoice' => nextInvoiceNumber($pdo, $bizId)
            ]);

        case 'save_sale':
            $data = body();

            if (empty($data['items']) || !is_array($data['items'])) {
                out(['success' => false, 'message' => 'Panier vide.'], 400);
            }

            $offlineId = (string)($data['offline_id'] ?? '');

            if ($offlineId && colExists($pdo, 'transactions_caisse', 'offline_id')) {
                $stmt = $pdo->prepare("
                    SELECT transaction_id
                    FROM transactions_caisse
                    WHERE business_id = ? AND offline_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$bizId, $offlineId]);
                $oldId = $stmt->fetchColumn();

                if ($oldId) {
                    out([
                        'success'        => true,
                        'duplicate'      => true,
                        'transaction_id' => (int)$oldId
                    ]);
                }
            }

            $pdo->beginTransaction();

            $invoice = trim((string)($data['facture_numero'] ?? ''));

            if ($invoice === '') {
                $invoice = nextInvoiceNumber($pdo, $bizId);
            }

            foreach ($data['items'] as $item) {
                $productId = (int)($item['product_id'] ?? 0);
                $qty       = (float)($item['quantity'] ?? 0);

                if ($productId <= 0 || $qty <= 0) {
                    throw new Exception('Produit ou quantité invalide.');
                }

                $stmt = $pdo->prepare("
                    SELECT quantity, name
                    FROM products
                    WHERE product_id = ? AND business_id = ?
                    FOR UPDATE
                ");
                $stmt->execute([$productId, $bizId]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    throw new Exception('Produit introuvable.');
                }

                if ((float)$product['quantity'] < $qty) {
                    throw new Exception('Stock insuffisant pour ' . $product['name']);
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO transactions_caisse (
                    business_id,
                    session_id,
                    caissier_id,
                    numero_facture,
                    type_operation,
                    sous_total,
                    remise_type,
                    remise_valeur,
                    remise_montant,
                    tva_active,
                    tva_taux,
                    tva_montant,
                    total_ttc,
                    montant_recu,
                    monnaie_rendue,
                    client_nom,
                    client_phone,
                    note,
                    offline_id,
                    statut,
                    created_at
                )
                VALUES (
                    ?, ?, ?, ?, 'vente',
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, 'validee', NOW()
                )
            ");

            $stmt->execute([
                $bizId,
                !empty($data['session_id']) ? (int)$data['session_id'] : null,
                $userId,
                $invoice,
                (float)($data['subtotal'] ?? 0),
                (string)($data['remise_type'] ?? 'aucune'),
                (float)($data['remise_valeur'] ?? 0),
                (float)($data['remise_montant'] ?? 0),
                !empty($data['tva_amount']) ? 1 : 0,
                (float)($data['tva_rate'] ?? 0),
                (float)($data['tva_amount'] ?? 0),
                (float)($data['total_ttc'] ?? 0),
                (float)($data['montant_recu'] ?? 0),
                (float)($data['monnaie_rendue'] ?? 0),
                $data['client_name'] ?? null,
                $data['client_phone'] ?? null,
                $data['note'] ?? null,
                $offlineId ?: null
            ]);

            $transactionId = (int)$pdo->lastInsertId();

            foreach ($data['items'] as $item) {
                $productId = (int)$item['product_id'];
                $qty       = (float)$item['quantity'];

                $pdo->prepare("
                    INSERT INTO items_transaction (
                        transaction_id,
                        product_id,
                        product_name,
                        product_sku,
                        quantite,
                        prix_unitaire,
                        total_ligne
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $transactionId,
                    $productId,
                    (string)($item['product_name'] ?? ''),
                    (string)($item['sku'] ?? ''),
                    $qty,
                    (float)($item['unit_price'] ?? 0),
                    (float)($item['total'] ?? 0)
                ]);

                $pdo->prepare("
                    UPDATE products
                    SET quantity = quantity - ?
                    WHERE product_id = ? AND business_id = ?
                ")->execute([$qty, $productId, $bizId]);

                try {
                    $pdo->prepare("
                        INSERT INTO stock_movements (
                            business_id,
                            product_id,
                            movement_type,
                            quantity,
                            reason,
                            created_by,
                            created_at
                        )
                        VALUES (?, ?, 'sale', ?, ?, ?, NOW())
                    ")->execute([
                        $bizId,
                        $productId,
                        -$qty,
                        'Sale ' . $invoice,
                        $userId
                    ]);
                } catch (Throwable $e) {}
            }

            foreach (($data['paiements'] ?? []) as $payment) {
                $amount = (float)($payment['montant'] ?? 0);

                if ($amount <= 0) {
                    continue;
                }

                $mode = (string)($payment['mode'] ?? 'especes');

                if (!in_array($mode, ['especes', 'mtn_momo', 'orange_money'], true)) {
                    $mode = 'especes';
                }

                $pdo->prepare("
                    INSERT INTO paiements_mixtes (
                        transaction_id,
                        mode,
                        montant,
                        reference
                    )
                    VALUES (?, ?, ?, ?)
                ")->execute([
                    $transactionId,
                    $mode,
                    $amount,
                    $payment['reference'] ?? null
                ]);
            }

            $receipt = saveReceipt($pdo, $bizId, $transactionId, $invoice);

            $pdo->commit();

            out([
                'success'        => true,
                'transaction_id' => $transactionId,
                'invoice'        => $invoice,
                'receipt_id'     => $receipt['receipt_id'],
                'receipt_url'    => APP_URL . '/Vente_cashier/caisse/facture.php?id=' . $transactionId,
                'public_url'     => APP_URL . '/Vente_cashier/caisse/facture.php?token=' . $receipt['token']
            ]);

        default:
            out(['success' => false, 'message' => 'Action inconnue.'], 400);
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    out([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}