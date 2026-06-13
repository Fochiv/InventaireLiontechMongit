<?php
/* ============================================================
   notifications_api.php — LionTech
   JSON endpoint — unread count + latest notifications
   Called by Sidebar polling script every 30 s
   ============================================================ */
require_once __DIR__ . '/../Config.php';
startSecureSession();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user = currentUser();
if (!$user) { echo json_encode(['unread'=>0,'items'=>[]]); exit; }

$pdo        = getDB();
$userId     = (int)$user['user_id'];
$businessId = (int)($user['business_id'] ?? 0);
$role       = $_SESSION['role'] ?? '';

/* ── Mark-read action ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    try {
        if ($_POST['mark_read'] === 'all') {
            $pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=? OR (business_id=? AND user_id IS NULL)')
                ->execute([$userId, $businessId]);
        } else {
            $pdo->prepare('UPDATE notifications SET is_read=1 WHERE notification_id=?')
                ->execute([(int)$_POST['mark_read']]);
        }
    } catch (Throwable $_e) {}
    echo json_encode(['ok'=>true]); exit;
}

/* ── Ensure table exists ── */
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        notification_id INT AUTO_INCREMENT PRIMARY KEY,
        business_id     INT            DEFAULT NULL,
        user_id         INT            DEFAULT NULL,
        type            VARCHAR(60)    NOT NULL DEFAULT 'info',
        title           VARCHAR(255)   NOT NULL,
        message         TEXT,
        is_read         TINYINT(1)     NOT NULL DEFAULT 0,
        created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_biz  (business_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $_e) {}

/* ── Fetch notifications for this user ── */
$items   = [];
$unread  = 0;
try {
    $stmt = $pdo->prepare(
        'SELECT * FROM notifications
          WHERE (user_id = ? OR (business_id = ? AND user_id IS NULL))
          ORDER BY created_at DESC LIMIT 20'
    );
    $stmt->execute([$userId, $businessId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $r) {
        if (!$r['is_read']) $unread++;
        $items[] = [
            'id'      => (int)$r['notification_id'],
            'type'    => $r['type'],
            'title'   => $r['title'],
            'message' => $r['message'] ?? '',
            'is_read' => (bool)$r['is_read'],
            'ago'     => ltNotifAgo($r['created_at']),
        ];
    }
} catch (Throwable $_e) {}

/* ── Auto-generate contextual notifications ── */
if ($role === 'business_owner' && $businessId) {
    try {
        /* Subscription expiry warning */
        $biz = $pdo->prepare('SELECT subscription_expires_at, subscription_status FROM businesses WHERE business_id=?');
        $biz->execute([$businessId]);
        $b = $biz->fetch();
        if ($b && $b['subscription_expires_at']) {
            $daysLeft = (int)ceil((strtotime($b['subscription_expires_at']) - time()) / 86400);
            if ($daysLeft <= 7 && $daysLeft >= 0) {
                $key = 'sub_expiry_' . $businessId;
                $exists = $pdo->prepare("SELECT 1 FROM notifications WHERE business_id=? AND type='sub_expiry' AND is_read=0 AND created_at > DATE_SUB(NOW(),INTERVAL 1 DAY)");
                $exists->execute([$businessId]);
                if (!$exists->fetchColumn()) {
                    $pdo->prepare("INSERT INTO notifications (business_id,type,title,message) VALUES (?,?,?,?)")
                        ->execute([$businessId,'sub_expiry',
                            "Abonnement expire bientôt",
                            "Votre abonnement expire dans {$daysLeft} jour(s). Renouvelez pour éviter l'interruption."]);
                }
            }
        }

        /* Pending stock approvals */
        $pending = $pdo->prepare("SELECT COUNT(*) FROM stock_in_requests WHERE business_id=? AND status='pending'");
        $pending->execute([$businessId]);
        $cnt = (int)$pending->fetchColumn();
        if ($cnt > 0) {
            $existsApproval = $pdo->prepare("SELECT 1 FROM notifications WHERE business_id=? AND type='stock_approval' AND is_read=0 AND created_at > DATE_SUB(NOW(),INTERVAL 1 HOUR)");
            $existsApproval->execute([$businessId]);
            if (!$existsApproval->fetchColumn()) {
                $pdo->prepare("INSERT INTO notifications (business_id,type,title,message) VALUES (?,?,?,?)")
                    ->execute([$businessId,'stock_approval',
                        "{$cnt} demande(s) de stock en attente",
                        "Des employés ont soumis des demandes de stock qui nécessitent votre validation."]);
            }
        }
    } catch (Throwable $_e) {}

    /* Re-fetch after auto-generate */
    try {
        $stmt = $pdo->prepare(
            'SELECT * FROM notifications
              WHERE (user_id = ? OR (business_id = ? AND user_id IS NULL))
              ORDER BY created_at DESC LIMIT 20'
        );
        $stmt->execute([$userId, $businessId]);
        $rows   = $stmt->fetchAll();
        $items  = [];
        $unread = 0;
        foreach ($rows as $r) {
            if (!$r['is_read']) $unread++;
            $items[] = [
                'id'      => (int)$r['notification_id'],
                'type'    => $r['type'],
                'title'   => $r['title'],
                'message' => $r['message'] ?? '',
                'is_read' => (bool)$r['is_read'],
                'ago'     => ltNotifAgo($r['created_at']),
            ];
        }
    } catch (Throwable $_e) {}
}

echo json_encode(['unread' => $unread, 'items' => $items]);

function ltNotifAgo(string $ts): string {
    $diff = time() - strtotime($ts);
    if ($diff < 60)    return 'à l\'instant';
    if ($diff < 3600)  return (int)($diff/60) . ' min';
    if ($diff < 86400) return (int)($diff/3600) . 'h';
    return (int)($diff/86400) . 'j';
}
