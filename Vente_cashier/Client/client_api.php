<?php
/* client_api.php — Tally Client AJAX API */
require_once __DIR__ . '/config_client.php';
clientSession(); $pdo=getDB(); ensureClientTables($pdo);
header('Content-Type: application/json; charset=utf-8');

function out(array $d){ echo json_encode($d,JSON_UNESCAPED_UNICODE); exit; }
function body(): array { $r=json_decode(file_get_contents('php://input'),true); return is_array($r)?$r:[]; }

$loggedIn = isClientLoggedIn();
$client   = $loggedIn ? currentClient() : [];
$action   = $_GET['action'] ?? body()['action'] ?? '';

/* Resolve phone for this request */
$phone = '';
if($loggedIn) $phone=$client['phone'];
elseif(!empty(body()['phone'])) $phone=preg_replace('/[^\d\+]/','',body()['phone']);

/* Helper: upsert action row */
function upsertAction(PDO $pdo, int $rid, string $phone, ?int $cid, array $set): bool {
    $stmt=$pdo->prepare("SELECT action_id,business_id FROM receipts WHERE receipt_id=? LIMIT 1");
    $stmt->execute([$rid]); $r=$stmt->fetch(PDO::FETCH_ASSOC); if(!$r) return false;
    $bizId=(int)$r['business_id'];
    $fields=[]; $vals=[];
    foreach($set as $k=>$v){ $fields[]="`$k`=?"; $vals[]=$v; }
    $ins=$pdo->prepare("INSERT INTO client_receipt_actions(client_id,client_phone,receipt_id,business_id) VALUES(?,?,?,?)
        ON DUPLICATE KEY UPDATE ".implode(',',$fields));
    $ins->execute(array_merge([$cid,$phone,$rid,$bizId],$vals));
    return true;
}

try {
switch($action) {

/* ── Public: get receipts by phone ── */
case 'get_receipts':
    $p = preg_replace('/[^\d\+]/','',body()['phone']??$_GET['phone']??'');
    if(!$p) out(['success'=>false,'message'=>'Phone required']);
    $rows=$pdo->prepare("SELECT r.*,b.business_name,b.phone biz_phone,
        COALESCE(cra.is_saved,0) is_saved, COALESCE(cra.category,'other') category
        FROM receipts r JOIN businesses b ON b.business_id=r.business_id
        LEFT JOIN client_receipt_actions cra ON cra.receipt_id=r.receipt_id AND cra.client_phone=?
        WHERE r.client_phone=? AND COALESCE(cra.is_hidden,0)=0 ORDER BY r.created_at DESC LIMIT 100");
    $rows->execute([$p,$p]); out(['success'=>true,'receipts'=>$rows->fetchAll(PDO::FETCH_ASSOC)]);

/* ── Save receipt ── */
case 'save_receipt':
    if(!$loggedIn) out(['success'=>false,'code'=>'login_required','message'=>'Login required']);
    $rid=(int)(body()['receipt_id']??0); if(!$rid) out(['success'=>false,'message'=>'receipt_id required']);
    upsertAction($pdo,$rid,$phone,$client['client_id'],['is_saved'=>1]);
    out(['success'=>true]);

/* ── Unsave receipt ── */
case 'unsave_receipt':
    if(!$loggedIn) out(['success'=>false,'code'=>'login_required']);
    $rid=(int)(body()['receipt_id']??0);
    upsertAction($pdo,$rid,$phone,$client['client_id'],['is_saved'=>0]);
    out(['success'=>true]);

/* ── Hide receipt ── */
case 'hide_receipt':
    if(!$loggedIn) out(['success'=>false,'code'=>'login_required']);
    $rid=(int)(body()['receipt_id']??0);
    upsertAction($pdo,$rid,$phone,$client['client_id'],['is_hidden'=>1]);
    out(['success'=>true]);

/* ── Set category ── */
case 'set_category':
    if(!$loggedIn) out(['success'=>false,'code'=>'login_required']);
    $rid=(int)(body()['receipt_id']??0);
    $cats=['food','clothes','pharmacy','electronics','beauty','transport','restaurant','other'];
    $cat=in_array(body()['category']??'',$cats,true)?(body()['category']):'other';
    upsertAction($pdo,$rid,$phone,$client['client_id'],['category'=>$cat]);
    out(['success'=>true]);

/* ── Report receipt ── */
case 'report_receipt':
    $rid=(int)(body()['receipt_id']??0);
    $reason=trim(body()['reason']??'');
    if(!$rid||!$reason) out(['success'=>false,'message'=>'Missing data']);
    if(!$phone) out(['success'=>false,'message'=>'Phone required']);
    $cid=$loggedIn?(int)$client['client_id']:null;
    upsertAction($pdo,$rid,$phone,$cid,['is_reported'=>1,'report_reason'=>$reason]);
    out(['success'=>true]);

/* ── Favorite business ── */
case 'favorite_business':
    if(!$loggedIn) out(['success'=>false,'code'=>'login_required']);
    $rid=(int)(body()['receipt_id']??0); $toggle=(bool)(body()['toggle']??true);
    upsertAction($pdo,$rid,$phone,$client['client_id'],['is_favorite_business'=>$toggle?1:0]);
    out(['success'=>true]);

/* ── Monthly summary ── */
case 'monthly_summary':
    $p = $phone ?: preg_replace('/[^\d\+]/','',body()['phone']??'');
    if(!$p) out(['success'=>false,'message'=>'Phone required']);
    $y=(int)(body()['year']??date('Y')); $m=(int)(body()['month']??date('m'));
    $s=$pdo->prepare("SELECT COUNT(*) cnt, COALESCE(SUM(r.total_amount),0) total,
        COUNT(DISTINCT r.business_id) biz_count
        FROM receipts r WHERE r.client_phone=? AND YEAR(r.created_at)=? AND MONTH(r.created_at)=?");
    $s->execute([$p,$y,$m]); out(['success'=>true,'summary'=>$s->fetch(PDO::FETCH_ASSOC)]);

default:
    out(['success'=>false,'message'=>'Action inconnue']);
}
} catch(Throwable $e){ out(['success'=>false,'message'=>$e->getMessage()]); }