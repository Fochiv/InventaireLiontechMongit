<?php
/* ============================================================
   Vente_api.php — LionTech Caisse POS API
   Column names aligned to brother's caisse_tables.sql schema
   Path: C:\Xampp\htdocs\InventoryLiontech\Vente_cashier\Vente_api.php
   ============================================================ */
require_once __DIR__ . '/../Config.php';
startSecureSession();
header('Content-Type: application/json');

if (!isLoggedIn()) {
    exit(json_encode(['success'=>false,'message'=>'Non autorisé']));
}

$user       = currentUser();
$pdo        = getDB();
$businessId = (int)($user['business_id'] ?? 0);
$userId     = (int)($user['user_id'] ?? 0);
$role       = $user['role'] ?? '';
$action     = $_GET['action'] ?? $_POST['action'] ?? '';

/* ── Allowed roles (includes caissier) ── */
$allowedRoles = [ROLE_BUSINESS_OWNER, ROLE_MANAGER, ROLE_EMPLOYEE, 'caissier'];
if (!in_array($role, $allowedRoles, true)) {
    exit(json_encode(['success'=>false,'message'=>'Accès refusé']));
}

/* ── GET PRODUCTS ── */
if ($action === 'get_products') {
    try {
        $stmt = $pdo->prepare("
            SELECT product_id, name, sku, barcode, unit_price, quantity,
                   unit, category, image_url, low_stock_level
            FROM products
            WHERE business_id = ? AND status = 'active'
            ORDER BY name ASC
        ");
        $stmt->execute([$businessId]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        exit(json_encode(['success'=>true,'products'=>$products]));
    } catch (Throwable $e) {
        exit(json_encode(['success'=>false,'message'=>$e->getMessage()]));
    }
}

/* ── GET NEXT INVOICE NUMBER (brother's schema: annee + dernier_num) ── */
if ($action === 'next_invoice') {
    try {
        $annee = (int)date('Y');

        /* Insert if not exists for this business + year */
        $pdo->prepare("
            INSERT INTO facture_sequence (business_id, annee, dernier_num)
            VALUES (?, ?, 0)
            ON DUPLICATE KEY UPDATE dernier_num = dernier_num
        ")->execute([$businessId, $annee]);

        /* Increment */
        $pdo->prepare("
            UPDATE facture_sequence
            SET dernier_num = dernier_num + 1
            WHERE business_id = ? AND annee = ?
        ")->execute([$businessId, $annee]);

        $stmt = $pdo->prepare("
            SELECT dernier_num FROM facture_sequence
            WHERE business_id = ? AND annee = ?
        ");
        $stmt->execute([$businessId, $annee]);
        $row = $stmt->fetch();
        $num = str_pad((string)$row['dernier_num'], 4, '0', STR_PAD_LEFT);
        $invoice = 'FAC-' . $annee . '-' . $num;
        exit(json_encode(['success'=>true,'invoice'=>$invoice]));
    } catch (Throwable $e) {
        exit(json_encode(['success'=>false,'message'=>$e->getMessage()]));
    }
}

/* ── SAVE SALE (column names match brother's transactions_caisse) ── */
if ($action === 'save_sale' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!$data) {
        exit(json_encode(['success'=>false,'message'=>'Données invalides']));
    }

    /* Map incoming JS data to brother's column names */
    $numeroFacture = trim($data['facture_numero']    ?? '');
    $clientNom     = trim($data['client_name']       ?? '') ?: null;
    $clientPhone   = trim($data['client_phone']      ?? '') ?: null;
    $sousTotal     = (float)($data['subtotal']       ?? 0);
    $tvaTaux       = (float)($data['tva_rate']       ?? 0);
    $tvaMontant    = (float)($data['tva_amount']     ?? 0);
    $tvaActive     = $tvaTaux > 0 ? 1 : 0;
    $totalTtc      = (float)($data['total_ttc']      ?? 0);
    $payMethod     = trim($data['payment_method']    ?? 'especes');
    $montantRecu   = isset($data['amount_given']) ? (float)$data['amount_given'] : $totalTtc;
    $monnaieRendue = isset($data['change_given'])  ? (float)$data['change_given'] : 0;
    $payRef        = trim($data['payment_reference'] ?? '') ?: null;
    $note          = trim($data['note']              ?? '') ?: null;
    $offlineId     = trim($data['offline_id']        ?? '') ?: null;
    $items         = $data['items'] ?? [];
    $sessionId     = $data['session_id']             ?? null;

    /* Map our payment method names to brother's enum */
    $modeMap = [
        'cash'         => 'especes',
        'mtn_momo'     => 'mtn_momo',
        'orange_money' => 'orange_money',
        'especes'      => 'especes',
    ];
    $modePaiement = $modeMap[$payMethod] ?? 'especes';

    if (empty($numeroFacture) || empty($items) || $totalTtc <= 0) {
        exit(json_encode(['success'=>false,'message'=>'Données de vente incomplètes']));
    }

    /* Check duplicate offline_id */
    if ($offlineId) {
        try {
            $stmt = $pdo->prepare("
                SELECT transaction_id FROM transactions_caisse
                WHERE business_id=? AND offline_id=? LIMIT 1
            ");
            $stmt->execute([$businessId, $offlineId]);
            if ($stmt->fetch()) {
                exit(json_encode(['success'=>true,'message'=>'Déjà synchronisé','duplicate'=>true]));
            }
        } catch (Throwable $e) {}
    }

    try {
        $pdo->beginTransaction();

        /* Insert transaction (brother's column names) */
        $pdo->prepare("
            INSERT INTO transactions_caisse
            (business_id, session_id, caissier_id, numero_facture,
             type_operation, sous_total, tva_active, tva_taux, tva_montant,
             total_ttc, montant_recu, monnaie_rendue,
             client_nom, client_phone, note, statut, offline_id)
            VALUES
            (?, ?, ?, ?,
             'vente', ?, ?, ?, ?,
             ?, ?, ?,
             ?, ?, ?, 'validee', ?)
        ")->execute([
            $businessId,
            $sessionId,
            $userId,
            $numeroFacture,
            $sousTotal,
            $tvaActive,
            $tvaTaux,
            $tvaMontant,
            $totalTtc,
            $montantRecu,
            $monnaieRendue,
            $clientNom,
            $clientPhone,
            $note,
            $offlineId,
        ]);
        $transId = (int)$pdo->lastInsertId();

        /* Insert payment into paiements_mixtes (brother's schema) */
        $pdo->prepare("
            INSERT INTO paiements_mixtes (transaction_id, mode, montant, reference)
            VALUES (?, ?, ?, ?)
        ")->execute([$transId, $modePaiement, $totalTtc, $payRef]);

        /* Insert items + deduct stock */
        foreach ($items as $item) {
            $productId   = (int)($item['product_id']   ?? 0);
            $productName = trim($item['product_name']  ?? '');
            $productSku  = trim($item['sku']           ?? '') ?: null;
            $unitPrice   = (float)($item['unit_price'] ?? 0);
            $qty         = (float)($item['quantity']   ?? 1);
            $totalLigne  = (float)($item['total']      ?? 0);

            $pdo->prepare("
                INSERT INTO items_transaction
                (transaction_id, product_id, product_name, product_sku,
                 quantite, prix_unitaire, total_ligne)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $transId, $productId, $productName, $productSku,
                $qty, $unitPrice, $totalLigne
            ]);

            /* Deduct stock */
            $pdo->prepare("
                UPDATE products
                SET quantity = GREATEST(0, quantity - ?)
                WHERE product_id = ? AND business_id = ?
            ")->execute([$qty, $productId, $businessId]);
        }

        /* Log activity */
        try {
            $pdo->prepare("
                INSERT INTO activity_logs
                (user_id, business_id, action, description, icon, ip_address, created_at)
                VALUES (?,?,'vente',?,?,?,NOW())
            ")->execute([
                $userId, $businessId,
                "Vente {$numeroFacture} — {$totalTtc} XAF",
                '🧾',
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (Throwable $e) {}

        $pdo->commit();
        exit(json_encode(['success'=>true,'transaction_id'=>$transId,'facture'=>$numeroFacture]));

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        exit(json_encode(['success'=>false,'message'=>$e->getMessage()]));
    }
}

/* ── GET SETTINGS (TVA + caisse_code) ── */
if ($action === 'get_settings') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM businesses WHERE business_id=? LIMIT 1");
        $stmt->execute([$businessId]);
        $biz = $stmt->fetch(PDO::FETCH_ASSOC);

        $tvaEnabled = false;
        $tvaRate    = 19.25;
        $caisseCode = null;
        try {
            $stmt2 = $pdo->prepare("SELECT * FROM business_settings WHERE business_id=? LIMIT 1");
            $stmt2->execute([$businessId]);
            $settings = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($settings) {
                $tvaEnabled = (bool)($settings['tva_enabled'] ?? false);
                $tvaRate    = (float)($settings['tva_rate']   ?? 19.25);
                $caisseCode = $settings['caisse_code'] ?? null;
            }
        } catch (Throwable $e) {}

        exit(json_encode([
            'success'        => true,
            'business_name'  => $biz['business_name'] ?? '',
            'tva_enabled'    => $tvaEnabled,
            'tva_rate'       => $tvaRate,
            'requires_code'  => !empty($caisseCode),
        ]));
    } catch (Throwable $e) {
        exit(json_encode(['success'=>false,'message'=>$e->getMessage()]));
    }
}

/* ── VERIFY CAISSE CODE (for regular employees) ── */
if ($action === 'verify_caisse_code' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $code = trim($data['code'] ?? '');

    if (!$code) exit(json_encode(['success'=>false,'message'=>'Code requis']));

    try {
        $stmt = $pdo->prepare("
            SELECT caisse_code FROM business_settings
            WHERE business_id=? LIMIT 1
        ");
        $stmt->execute([$businessId]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$settings || empty($settings['caisse_code'])) {
            /* No code set — allow access */
            exit(json_encode(['success'=>true]));
        }

        if ($settings['caisse_code'] === $code) {
            exit(json_encode(['success'=>true]));
        }

        exit(json_encode(['success'=>false,'message'=>'Code caisse incorrect']));
    } catch (Throwable $e) {
        exit(json_encode(['success'=>false,'message'=>$e->getMessage()]));
    }
}

/* ── VERIFY PIN (for lock screen after inactivity) ── */
if ($action === 'verify_pin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $pin  = trim($data['pin'] ?? '');

    if (!$pin) exit(json_encode(['success'=>false]));

    try {
        $stmt = $pdo->prepare("SELECT pin_hash FROM users WHERE user_id=? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !$row['pin_hash']) {
            exit(json_encode(['success'=>false]));
        }

        $hash = str_replace('$2b$', '$2y$', $row['pin_hash']);
        $ok   = password_verify($pin, $hash);
        exit(json_encode(['success'=>$ok]));
    } catch (Throwable $e) {
        exit(json_encode(['success'=>false]));
    }
}

/* ── CHECK CLIENT BY PHONE (brother's column: client_nom) ── */
if ($action === 'check_client') {
    $phone = trim($_GET['phone'] ?? '');
    if (!$phone) exit(json_encode(['success'=>false]));
    try {
        $stmt = $pdo->prepare("
            SELECT client_nom, client_phone, COUNT(*) as visits
            FROM transactions_caisse
            WHERE business_id=? AND client_phone=? AND client_nom IS NOT NULL
            GROUP BY client_nom, client_phone
            LIMIT 1
        ");
        $stmt->execute([$businessId, $phone]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($client) {
            exit(json_encode([
                'success' => true,
                'found'   => true,
                'name'    => $client['client_nom'],
                'visits'  => $client['visits']
            ]));
        }
        exit(json_encode(['success'=>true,'found'=>false]));
    } catch (Throwable $e) {
        exit(json_encode(['success'=>false]));
    }
}

exit(json_encode(['success'=>false,'message'=>'Action inconnue']));