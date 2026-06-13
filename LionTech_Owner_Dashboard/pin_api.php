<?php
/* ============================================================
   pin_api.php — LionTech PIN Management
   Owner + Manager can generate / reset PINs for employees
   Path: C:\Xampp\htdocs\InventoryLiontech\LionTech_Employee_Management\pin_api.php
   ============================================================ */
require_once dirname(__DIR__) . '/Config.php';
startSecureSession();
requireRole([ROLE_BUSINESS_OWNER, ROLE_MANAGER]);
header('Content-Type: application/json; charset=utf-8');

$pdo    = getDB();
$me     = currentUser();
$bizId  = (int)($me['business_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function out($a){ echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function body(){ $d = json_decode(file_get_contents('php://input'), true); return is_array($d)?$d:[]; }

/* ── Validate target user belongs to this business ── */
function getEmployee(PDO $pdo, int $userId, int $bizId): ?array {
    $s = $pdo->prepare("SELECT user_id, full_name, role FROM users WHERE user_id=? AND business_id=? LIMIT 1");
    $s->execute([$userId, $bizId]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}

try {
    switch ($action) {

        /* ── Generate (or regenerate) a PIN ── */
        case 'generate':
            $userId = (int)(body()['user_id'] ?? 0);
            if (!$userId) out(['success'=>false,'message'=>'user_id requis']);

            $emp = getEmployee($pdo, $userId, $bizId);
            if (!$emp) out(['success'=>false,'message'=>'Employé introuvable']);

            /* Generate 4-digit PIN (zero-padded) */
            $pin  = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $hash = password_hash($pin, PASSWORD_BCRYPT);

            /* Upsert into pin_codes */
            $pdo->prepare("
                INSERT INTO pin_codes (user_id, business_id, pin_hash, must_change, created_at, updated_at)
                VALUES (?, ?, ?, 0, NOW(), NOW())
                ON DUPLICATE KEY UPDATE pin_hash=VALUES(pin_hash), updated_at=NOW()
            ")->execute([$userId, $bizId, $hash]);

            out([
                'success'   => true,
                'pin'       => $pin,
                'user_name' => $emp['full_name'],
                'role'      => $emp['role'],
            ]);

        /* ── Check if employee already has a PIN ── */
        case 'has_pin':
            $userId = (int)($_GET['user_id'] ?? 0);
            if (!$userId) out(['success'=>false]);

            $s = $pdo->prepare("SELECT pin_id, updated_at FROM pin_codes WHERE user_id=? LIMIT 1");
            $s->execute([$userId]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            out(['success'=>true, 'has_pin'=>(bool)$row, 'updated_at'=>$row['updated_at']??null]);

        /* ── List all employees with PIN status ── */
        case 'list':
            $s = $pdo->prepare("
                SELECT u.user_id, u.full_name, u.role,
                    CASE WHEN pc.pin_id IS NOT NULL THEN 1 ELSE 0 END AS has_pin,
                    pc.updated_at AS pin_updated_at
                FROM users u
                LEFT JOIN pin_codes pc ON pc.user_id = u.user_id
                WHERE u.business_id = ? AND u.status = 'active'
                  AND u.role NOT IN ('business_owner','super_admin')
                ORDER BY u.role ASC, u.full_name ASC
            ");
            $s->execute([$bizId]);
            out(['success'=>true, 'employees'=>$s->fetchAll(PDO::FETCH_ASSOC)]);

        default:
            out(['success'=>false,'message'=>'Action inconnue']);
    }
} catch (Throwable $e) {
    out(['success'=>false,'message'=>$e->getMessage()]);
}