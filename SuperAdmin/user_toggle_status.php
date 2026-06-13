<?php
/* ============================================================
   user_toggle_status.php — SuperAdmin AJAX endpoint
   Toggles a user's status: active ↔ suspended
   POST: user_id (int)
   Response: JSON {ok, status, label}
   ============================================================ */
require_once __DIR__ . '/../Config.php';
startSecureSession();
requireRole([ROLE_SUPER_ADMIN]);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
    exit;
}

$userId = (int)($_POST['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Invalid user_id']);
    exit;
}

try {
    $pdo = getDB();

    /* Prevent SuperAdmin from suspending themselves */
    $self = currentUser();
    if ((int)($self['user_id'] ?? 0) === $userId) {
        echo json_encode(['ok'=>false,'error'=>'Impossible de modifier votre propre compte.']);
        exit;
    }

    /* Get current status — also verify user exists & is not super_admin */
    $stmt = $pdo->prepare('SELECT status, role FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if (!$row) {
        echo json_encode(['ok'=>false,'error'=>'Utilisateur introuvable.']);
        exit;
    }
    if ($row['role'] === 'super_admin') {
        echo json_encode(['ok'=>false,'error'=>'Action non autorisée sur ce compte.']);
        exit;
    }

    /* Toggle */
    $newStatus = ($row['status'] === 'active') ? 'suspended' : 'active';
    $pdo->prepare('UPDATE users SET status = ? WHERE user_id = ?')
        ->execute([$newStatus, $userId]);

    $label = $newStatus === 'active' ? 'Actif' : 'Suspendu';

    /* ── Log the action ── */
    try {
        $desc = ($newStatus === 'suspended')
            ? "Compte suspendu par SuperAdmin (ID #{$userId})"
            : "Compte réactivé par SuperAdmin (ID #{$userId})";
        $ip = $_SERVER['REMOTE_ADDR'] ?? '::1';
        $pdo->prepare(
            "INSERT INTO activity_logs (user_id, business_id, action, description, icon, ip_address)
             VALUES (?, NULL, ?, ?, 'user', ?)"
        )->execute([
            (int)($self['user_id'] ?? 0),
            $newStatus === 'suspended' ? 'user_suspend' : 'user_activate',
            $desc,
            $ip,
        ]);
    } catch (Throwable $_e) {}

    echo json_encode(['ok'=>true, 'status'=>$newStatus, 'label'=>$label]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Erreur serveur.']);
}
