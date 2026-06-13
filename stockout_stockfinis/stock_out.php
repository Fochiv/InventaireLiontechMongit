<?php
require_once __DIR__ . '/../Config.php';
startSecureSession();
if(!isLoggedIn()){header('Location: '.APP_URL.'/Logininventory/login.php?error=session_expired');exit;}
$pdo=getDB();$user=currentUser();
$role=$_SESSION['role']??'';
$businessId=(int)($_SESSION['business_id']??($user['business_id']??0));
$userId=(int)($_SESSION['user_id']??($user['user_id']??0));
$allowedRoles=[ROLE_BUSINESS_OWNER,ROLE_MANAGER,ROLE_EMPLOYEE];
if(!in_array($role,$allowedRoles,true)||$businessId<=0||$userId<=0){header('Location: '.APP_URL.'/Logininventory/login.php?error=unauthorized');exit;}
if(!function_exists('e')){function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}}
function qf2($v):string{return rtrim(rtrim(number_format((float)$v,2,'.',''  ),'0'),'.');}
function xaf2($v):string{return number_format((float)$v,0,',',' ').' XAF';}

$stmt=$pdo->prepare("SELECT * FROM businesses WHERE business_id=? LIMIT 1");$stmt->execute([$businessId]);$business=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$business){header('Location: '.APP_URL.'/Logininventory/login.php?error=unauthorized');exit;}
$subscriptionStatus=$business['subscription_status']??'active';$isExpired=in_array($subscriptionStatus,['expired','suspended'],true);
$inventoryEnabled=true;
try{$stmt=$pdo->prepare("SELECT inventory_management FROM business_features WHERE business_id=? LIMIT 1");$stmt->execute([$businessId]);$feature=$stmt->fetch(PDO::FETCH_ASSOC);if($feature&&(int)$feature['inventory_management']!==1)$inventoryEnabled=false;}catch(Throwable $e){$inventoryEnabled=true;}
$isEmployee=($role===ROLE_EMPLOYEE);$isApprover=in_array($role,[ROLE_BUSINESS_OWNER,ROLE_MANAGER],true);
$canCreate=$inventoryEnabled&&!$isExpired;$canApprove=$isApprover&&$inventoryEnabled&&!$isExpired;
$success='';$error='';

function movementType(string $reason):string{
    return match($reason){'Damaged'=>'broken','Missing/Lost'=>'lost',default=>'normal'};
}

try{
    /* CREATE */
    if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='create_stock_out'){
        if(!$canCreate)throw new Exception('This action is disabled.');
        $productId=(int)($_POST['product_id']??0);$quantity=(float)($_POST['quantity']??0);
        $reason=trim($_POST['reason']??'');$recipient=trim($_POST['recipient']??'');$note=trim($_POST['note']??'');
        if($productId<=0)throw new Exception('Please select a product.');
        if($quantity<=0)throw new Exception('Quantity must be greater than zero.');
        $allowed=['Sold','Used','Damaged','Expired','Missing/Lost','Returned'];
        if(!in_array($reason,$allowed,true))throw new Exception('Please select a valid reason.');
        $stmt=$pdo->prepare("SELECT product_id,name,quantity,unit,unit_price FROM products WHERE product_id=? AND business_id=? AND status='active' LIMIT 1");
        $stmt->execute([$productId,$businessId]);$product=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$product)throw new Exception('Product not found.');
        if($quantity>(float)$product['quantity'])throw new Exception('Stock out quantity cannot exceed available stock.');
        $mtype=movementType($reason);
        $brokenQty=in_array($mtype,['broken','lost'])?$quantity:0;
        $lossAmount=in_array($mtype,['broken','lost'])?$quantity*(float)($product['unit_price']??0):0;
        $proofPath=null;
        if(!empty($_FILES['proof_file']['name'])&&$_FILES['proof_file']['error']===UPLOAD_ERR_OK){
            $allowed2=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'];
            $mime=mime_content_type($_FILES['proof_file']['tmp_name']);
            if(isset($allowed2[$mime])&&$_FILES['proof_file']['size']<=3*1024*1024){
                $ud=__DIR__.'/uploads/stock_out'; if(!is_dir($ud))mkdir($ud,0775,true);
                $fn=time().'-'.random_int(1000,9999).'-'.basename($_FILES['proof_file']['name']);
                if(move_uploaded_file($_FILES['proof_file']['tmp_name'],$ud.'/'.$fn))$proofPath='uploads/stock_out/'.$fn;
            }
        }
        $needsApproval=$isEmployee;$status=$needsApproval?'pending':'approved';
        $approvedBy=$needsApproval?null:$userId;$approvedAt=$needsApproval?null:date('Y-m-d H:i:s');
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO stock_out_requests(business_id,product_id,quantity,reason,movement_type,broken_qty,loss_amount,recipient,note,proof_image_url,status,created_by,approved_by,approved_at,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())")
            ->execute([$businessId,$productId,$quantity,$reason,$mtype,$brokenQty,$lossAmount,$recipient?:null,$note?:null,$proofPath,$status,$userId,$approvedBy,$approvedAt]);
        $requestId=(int)$pdo->lastInsertId();
        if(!$needsApproval){
            $pdo->prepare("UPDATE products SET quantity=quantity-?,updated_at=NOW() WHERE product_id=? AND business_id=?")->execute([$quantity,$productId,$businessId]);
            $pdo->prepare("INSERT INTO stock_movements(request_id,business_id,product_id,movement_type,quantity,reason,proof_image_url,created_by,approved_by,created_at) VALUES(?,?,?,'stock_out',?,?,?,?,?,NOW())")
                ->execute([$requestId,$businessId,$productId,$quantity,$reason,$proofPath,$userId,$userId]);
            $success='Stock out recorded and inventory updated.';
        }else{
            try{$pdo->prepare("INSERT INTO notifications(business_id,title,message,type,created_at) VALUES(?,'Stock Out Approval Needed',?,'warning',NOW())")->execute([$businessId,'Employee submitted stock out for '.$product['name'].'.' ]);}catch(Throwable $e){}
            $success='Stock out request submitted. Waiting for approval.';
        }
        $pdo->commit();
    }
    /* APPROVE */
    if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='approve_stock_out'&&$canApprove){
        $requestId=(int)($_POST['request_id']??0);$pdo->beginTransaction();
        $stmt=$pdo->prepare("SELECT so.*,p.quantity AS current_qty FROM stock_out_requests so JOIN products p ON p.product_id=so.product_id WHERE so.request_id=? AND so.business_id=? AND so.status='pending' FOR UPDATE");
        $stmt->execute([$requestId,$businessId]);$req=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$req)throw new Exception('Request not found or already processed.');
        if((float)$req['quantity']>(float)$req['current_qty'])throw new Exception('Cannot approve. Quantity exceeds available stock.');
        $pdo->prepare("UPDATE products SET quantity=quantity-?,updated_at=NOW() WHERE product_id=? AND business_id=?")->execute([(float)$req['quantity'],(int)$req['product_id'],$businessId]);
        $pdo->prepare("UPDATE stock_out_requests SET status='approved',approved_by=?,approved_at=NOW() WHERE request_id=? AND business_id=?")->execute([$userId,$requestId,$businessId]);
        $pdo->prepare("INSERT INTO stock_movements(request_id,business_id,product_id,movement_type,quantity,reason,proof_image_url,created_by,approved_by,created_at) VALUES(?,?,?,'stock_out',?,?,?,?,?,NOW())")
            ->execute([$requestId,$businessId,(int)$req['product_id'],(float)$req['quantity'],$req['reason'],$req['proof_image_url'],(int)$req['created_by'],$userId]);
        $pdo->commit();$success='Stock out approved. Inventory updated.';
    }
    /* REJECT */
    if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='reject_stock_out'&&$canApprove){
        $requestId=(int)($_POST['request_id']??0);$reason2=trim($_POST['rejection_reason']??'Rejected after review');
        $pdo->prepare("UPDATE stock_out_requests SET status='rejected',approved_by=?,approved_at=NOW(),rejection_reason=? WHERE request_id=? AND business_id=? AND status='pending'")->execute([$userId,$reason2,$requestId,$businessId]);
        $success='Stock out request rejected.';
    }

    $stmt=$pdo->prepare("SELECT product_id,name,category,quantity,unit,unit_price,low_stock_level FROM products WHERE business_id=? AND status='active' ORDER BY name ASC");
    $stmt->execute([$businessId]);$products=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt=$pdo->prepare("SELECT so.*,p.name product_name,p.unit,p.category,u.full_name requested_by_name,a.full_name approved_by_name FROM stock_out_requests so JOIN products p ON p.product_id=so.product_id LEFT JOIN users u ON u.user_id=so.created_by LEFT JOIN users a ON a.user_id=so.approved_by WHERE so.business_id=? ORDER BY so.created_at DESC LIMIT 100");
    $stmt->execute([$businessId]);$history=$stmt->fetchAll(PDO::FETCH_ASSOC);
}catch(Throwable $ex){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();$error=$ex->getMessage();$products=$products??[];$history=$history??[];}

$pendingCount=count(array_filter($history??[],fn($r)=>$r['status']==='pending'));
$approvedToday=count(array_filter($history??[],fn($r)=>$r['status']==='approved'&&substr((string)$r['approved_at'],0,10)===date('Y-m-d')));
$totalPendingQty=array_sum(array_map(fn($r)=>$r['status']==='pending'?(float)$r['quantity']:0,$history??[]));
$initials='';foreach(explode(' ',trim($user['full_name']??'U')) as $w)$initials.=strtoupper(substr($w,0,1));
$initials=substr($initials?:'U',0,2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Stock Out — LionTech</title>
<link rel="icon" type="image/png" href="<?= APP_URL ?>/Image/TALLYLOGO.png"/>
<link rel="manifest" href="<?= APP_URL ?>/manifest.json"/>
<link rel="stylesheet" href="stock_out.css"/>
<link rel="stylesheet" href="<?= APP_URL ?>/icons.css">
</head>
<body>
<div class="od-layout">
<?php include __DIR__.'/../LionTech_Owner_Dashboard/Sidebar.php'; ?>
<main class="od-main">
<header class="od-topbar">
  <div class="od-business-title">
    <h1 data-i18n="page_title">Stock Out</h1>
    <p data-i18n="page_subtitle">Record products sold, used, damaged or lost.</p>
  </div>
  <div class="od-top-actions">
    <button id="langBtn" class="od-lang" onclick="toggleSoLang()">FR</button>
    <div class="od-avatar"><?=e($initials)?></div>
  </div>
</header>

<?php if($success): ?><div style="background:#F0FDF4;border:1px solid #86EFAC;padding:12px 24px;font-size:13px;color:#166534">&#9989; <?=e($success)?></div><?php endif; ?>
<?php if($error):  ?><div style="background:#FEF2F2;border:1px solid #FECACA;padding:12px 24px;font-size:13px;color:#991B1B">&#9888; <?=e($error)?></div><?php endif; ?>
<?php if($isEmployee): ?><div style="background:#FEF3C7;border:1px solid #FDE68A;padding:12px 24px;font-size:13px;color:#92400E" data-i18n="employee_note">Stock out requests from employees require approval.</div><?php endif; ?>

<section style="padding:20px 24px 0;display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px">
  <div class="od-card stat"><span class="stat-icon amber">&#9201;</span><div><small data-i18n="stat_pending">Pending</small><strong><?=(int)$pendingCount?></strong></div></div>
  <div class="od-card stat"><span class="stat-icon green">&#9989;</span><div><small data-i18n="stat_today">Approved today</small><strong><?=(int)$approvedToday?></strong></div></div>
  <div class="od-card stat"><span class="stat-icon blue">&#128230;</span><div><small data-i18n="stat_products">Active products</small><strong><?=count($products??[])?></strong></div></div>
  <div class="od-card stat"><span class="stat-icon" style="background:#EDE9FE">&#10134;</span><div><small data-i18n="stat_qty">Qty pending</small><strong><?=qf2($totalPendingQty)?></strong></div></div>
</section>

<div style="padding:20px 24px 40px;display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start">
  <section class="od-card" style="padding:28px">
    <h2 style="font-size:17px;font-weight:700;color:#0B1F3A;margin-bottom:20px" data-i18n="form_title">New Stock Out</h2>
    <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:14px">
      <input type="hidden" name="action" value="create_stock_out">
      <div>
        <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px"><span data-i18n="lbl_product">Product</span> *</label>
        <select name="product_id" id="productSelect" required style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit">
          <option value="" data-i18n="select_product">Select a product</option>
          <?php foreach($products??[] as $p): ?>
          <option value="<?=(int)$p['product_id']?>" data-qty="<?=e($p['quantity'])?>" data-unit="<?=e($p['unit']??'')?>" data-price="<?=e($p['unit_price']??0)?>">
            <?=e($p['name'])?> — <?=qf2($p['quantity'])?> <?=e($p['unit']??'')?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div id="stockInfo" style="font-size:12px;color:#6B7280;padding:8px 12px;background:#F8FAFC;border-radius:8px">
        <span data-i18n="stock_available">Available stock:</span> <strong id="stockQty">--</strong>
      </div>
      <div>
        <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px"><span data-i18n="lbl_qty">Quantity</span> *</label>
        <input type="number" step="0.01" min="0.01" name="quantity" id="quantityInput" required style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;box-sizing:border-box">
      </div>
      <div>
        <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px"><span data-i18n="lbl_reason">Reason</span> *</label>
        <select name="reason" id="reasonSelect" required style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit">
          <option value="" data-i18n="select_reason">Choose a reason</option>
          <option value="Sold" data-i18n="r_sold">Sold</option>
          <option value="Used" data-i18n="r_used">Used</option>
          <option value="Damaged" data-i18n="r_damaged">Damaged</option>
          <option value="Expired" data-i18n="r_expired">Expired</option>
          <option value="Missing/Lost" data-i18n="r_lost">Missing / Lost</option>
          <option value="Returned" data-i18n="r_returned">Returned</option>
        </select>
      </div>
      <!-- Loss preview for Damaged / Lost -->
      <div id="lossPreview" style="display:none;background:#FEF2F2;border:1px solid #FECACA;border-radius:10px;padding:10px 14px;font-size:12.5px;color:#991B1B">
        <span data-i18n="loss_label">Estimated loss:</span> <strong id="lossAmount">—</strong>
      </div>
      <div>
        <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px" data-i18n="lbl_dest">Destination / Client</label>
        <input type="text" name="recipient" placeholder="Ex: Client, Kitchen..." style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;box-sizing:border-box">
      </div>
      <div>
        <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px" data-i18n="lbl_proof">Proof / Photo</label>
        <input type="file" name="proof_file" accept="image/*,.pdf" style="width:100%">
      </div>
      <div>
        <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px" data-i18n="lbl_note">Note</label>
        <textarea name="note" rows="3" placeholder="Optional note..." style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;resize:vertical;box-sizing:border-box"></textarea>
      </div>
      <button type="submit" class="od-primary" style="padding:13px;font-size:14px;font-weight:700;border:none;cursor:pointer;border-radius:12px" <?=!$canCreate?'disabled':''?> data-i18n="btn_record">Record Stock Out</button>
    </form>
  </section>

  <aside class="od-card" style="padding:20px">
    <h2 style="font-size:15px;font-weight:700;color:#0B1F3A;margin-bottom:10px" data-i18n="help_title">How it works</h2>
    <p style="font-size:13px;color:#6B7280;margin-bottom:10px" data-i18n="help_text">Records anything leaving stock: sold, used, damaged, or lost. Damaged and Missing/Lost entries automatically calculate the monetary loss shown on Vente reports.</p>
    <div style="background:#FEF3C7;border:1px solid #FDE68A;border-radius:10px;padding:10px 12px;font-size:12px;color:#92400E" data-i18n="help_fraud">Discrepancies between theoretical and real stock are flagged on the Sales dashboard as possible fraud.</div>
  </aside>
</div>

<section style="padding:0 24px 40px">
  <div class="od-card" style="padding:0;overflow:hidden">
    <div style="padding:16px 20px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #F1F5F9;flex-wrap:wrap;gap:10px">
      <h2 style="font-size:15px;font-weight:700;color:#0B1F3A;margin:0" data-i18n="history_title">Stock Out History</h2>
      <input type="search" id="searchHistory" placeholder="Search..." style="padding:8px 13px;border:1.5px solid #E5E7EB;border-radius:9px;font-size:13px;font-family:inherit">
    </div>
    <div style="overflow-x:auto">
      <table id="historyTable" style="width:100%;border-collapse:collapse;font-size:13px;min-width:960px">
        <thead>
        <tr style="background:#F8FAFC;border-bottom:1.5px solid #E5E7EB">
          <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase" data-i18n="th_product">Product</th>
          <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase" data-i18n="th_qty">Quantity</th>
          <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase" data-i18n="th_reason">Reason</th>
          <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase" data-i18n="th_type">Type</th>
          <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase" data-i18n="th_loss">Loss Amount</th>
          <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase" data-i18n="th_by">Submitted by</th>
          <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase" data-i18n="th_status">Status</th>
          <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase" data-i18n="th_date">Date</th>
          <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase" data-i18n="th_actions">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach($history??[] as $h):
          $mtype=$h['movement_type']??'normal';
          $mtypeBadge=match($mtype){'broken'=>['#FEE2E2','#991B1B','Damaged'],'lost'=>['#FEF3C7','#92400E','Lost'],default=>['#F3F4F6','#6B7280','Normal']};
        ?>
        <tr data-search="<?=e(strtolower(($h['product_name']??'').' '.($h['reason']??'').' '.($h['requested_by_name']??'')))?>">
          <td style="padding:11px 14px;border-bottom:1px solid #F1F5F9"><strong><?=e($h['product_name']??'—')?></strong></td>
          <td style="padding:11px 14px;border-bottom:1px solid #F1F5F9"><?=qf2($h['quantity'])?> <?=e($h['unit']??'')?></td>
          <td style="padding:11px 14px;border-bottom:1px solid #F1F5F9"><?=e($h['reason']??'—')?></td>
          <td style="padding:11px 14px;border-bottom:1px solid #F1F5F9">
            <span style="display:inline-block;padding:3px 9px;border-radius:20px;background:<?=$mtypeBadge[0]?>;color:<?=$mtypeBadge[1]?>;font-size:11px;font-weight:700"><?=$mtypeBadge[2]?></span>
          </td>
          <td style="padding:11px 14px;border-bottom:1px solid #F1F5F9">
            <?php if(($h['loss_amount']??0)>0): ?>
            <strong style="color:#DC2626">-<?=xaf2($h['loss_amount'])?></strong>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td style="padding:11px 14px;border-bottom:1px solid #F1F5F9;font-size:12px"><?=e($h['requested_by_name']??'—')?></td>
          <td style="padding:11px 14px;border-bottom:1px solid #F1F5F9">
            <?php $bc=$h['status']==='approved'?'#DCFCE7;color:#166534':($h['status']==='rejected'?'#FEE2E2;color:#991B1B':'#FEF3C7;color:#92400E'); ?>
            <span style="display:inline-block;padding:3px 9px;border-radius:20px;background:<?=$bc?>;font-size:11px;font-weight:700"><?=ucfirst(e($h['status']))?></span>
          </td>
          <td style="padding:11px 14px;border-bottom:1px solid #F1F5F9;font-size:12px"><?=e(substr((string)$h['created_at'],0,16))?></td>
          <td style="padding:11px 14px;border-bottom:1px solid #F1F5F9">
            <?php if($h['status']==='pending'&&$canApprove): ?>
            <div style="display:flex;gap:6px">
              <form method="POST"><input type="hidden" name="action" value="approve_stock_out"><input type="hidden" name="request_id" value="<?=(int)$h['request_id']?>"><button type="submit" style="padding:5px 10px;font-size:12px;background:#F0FDF4;border:1.5px solid #86EFAC;border-radius:8px;color:#166534;cursor:pointer" data-i18n="btn_approve">Approve</button></form>
              <form method="POST"><input type="hidden" name="action" value="reject_stock_out"><input type="hidden" name="request_id" value="<?=(int)$h['request_id']?>"><input type="hidden" name="rejection_reason" value="Rejected after review"><button type="submit" style="padding:5px 10px;font-size:12px;background:#FEF2F2;border:1.5px solid #FECACA;border-radius:8px;color:#991B1B;cursor:pointer" data-i18n="btn_reject">Reject</button></form>
            </div>
            <?php elseif($h['status']==='approved'): ?><small style="color:#166534">&#9989; <?=e($h['approved_by_name']??'OK')?></small>
            <?php else: ?><small style="color:#991B1B"><?=e($h['rejection_reason']??'Rejected')?></small><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; if(empty($history)): ?>
        <tr><td colspan="9" style="text-align:center;padding:28px;color:#6B7280" data-i18n="empty">No stock out recorded.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
</main>
</div>
<script>
const SO_T={
  en:{page_title:'Stock Out',page_subtitle:'Record products sold, used, damaged or lost.',employee_note:'Stock out requests from employees require approval.',stat_pending:'Pending',stat_today:'Approved today',stat_products:'Active products',stat_qty:'Qty pending',form_title:'New Stock Out',lbl_product:'Product',select_product:'Select a product',lbl_qty:'Quantity',lbl_reason:'Reason',select_reason:'Choose a reason',r_sold:'Sold',r_used:'Used',r_damaged:'Damaged',r_expired:'Expired',r_lost:'Missing / Lost',r_returned:'Returned',loss_label:'Estimated loss:',stock_available:'Available stock:',lbl_dest:'Destination / Client',lbl_proof:'Proof / Photo',lbl_note:'Note',btn_record:'Record Stock Out',help_title:'How it works',help_text:'Records anything leaving stock. Damaged and Missing/Lost calculate monetary loss shown on Vente reports.',help_fraud:'Discrepancies between theoretical and real stock are flagged on the Sales dashboard.',history_title:'Stock Out History',th_product:'Product',th_qty:'Quantity',th_reason:'Reason',th_type:'Type',th_loss:'Loss Amount',th_by:'Submitted by',th_status:'Status',th_date:'Date',th_actions:'Actions',btn_approve:'Approve',btn_reject:'Reject',empty:'No stock out recorded.'},
  fr:{page_title:'Stock sortant',page_subtitle:'Enregistrez les produits vendus, utilisés, abîmés ou perdus.',employee_note:"Les sorties d'employés nécessitent une approbation.",stat_pending:'En attente',stat_today:"Approuvés aujourd'hui",stat_products:'Produits actifs',stat_qty:'Qté en attente',form_title:'Nouvelle sortie de stock',lbl_product:'Produit',select_product:'Sélectionner un produit',lbl_qty:'Quantité',lbl_reason:'Raison',select_reason:'Choisir une raison',r_sold:'Vendu',r_used:'Utilisé',r_damaged:'Abîmé',r_expired:'Expiré',r_lost:'Manquant / Perdu',r_returned:'Retourné',loss_label:'Perte estimée:',stock_available:'Stock disponible:',lbl_dest:'Destination / Client',lbl_proof:'Preuve / Photo',lbl_note:'Note',btn_record:'Enregistrer la sortie',help_title:'Comment ça marche',help_text:'Enregistre tout ce qui sort du stock. Abîmé et Perdu calculent automatiquement la perte monétaire visible dans les rapports Vente.',help_fraud:"Les écarts entre stock théorique et réel sont signalés dans le tableau Vente comme possible fraude.",history_title:'Historique des sorties',th_product:'Produit',th_qty:'Quantité',th_reason:'Raison',th_type:'Type',th_loss:'Montant perdu',th_by:'Soumis par',th_status:'Statut',th_date:'Date',th_actions:'Actions',btn_approve:'Approuver',btn_reject:'Rejeter',empty:'Aucune sortie enregistrée.'}
};
let soLang=localStorage.getItem('lt_lang')||'en';
function applySoLang(lang){soLang=lang;const T=SO_T[lang]||SO_T.en;document.querySelectorAll('[data-i18n]').forEach(el=>{const k=el.dataset.i18n;if(T[k])el.textContent=T[k];});const b=document.getElementById('langBtn');if(b)b.textContent=lang==='en'?'FR':'EN';}
window.toggleSoLang=function(){const n=soLang==='en'?'fr':'en';localStorage.setItem('lt_lang',n);applySoLang(n);if(typeof window.applySidebarLang==='function')window.applySidebarLang(n);};
const productSelect=document.getElementById('productSelect');
const stockQtyEl=document.getElementById('stockQty');
const quantityInput=document.getElementById('quantityInput');
const lossPreview=document.getElementById('lossPreview');
const lossAmountEl=document.getElementById('lossAmount');
function updateLoss(){
  const opt=productSelect?.selectedOptions[0];
  const qty=parseFloat(quantityInput?.value||0);
  const price=parseFloat(opt?.dataset?.price||0);
  const reason=document.getElementById('reasonSelect')?.value||'';
  const isLoss=['Damaged','Missing/Lost'].includes(reason);
  if(isLoss&&qty>0&&price>0){lossAmountEl.textContent=(qty*price).toLocaleString('fr-FR')+' XAF';lossPreview.style.display='block';}
  else lossPreview.style.display='none';
}
productSelect?.addEventListener('change',function(){const opt=this.selectedOptions[0];const qty=opt?.dataset?.qty||'--';const unit=opt?.dataset?.unit||'';if(stockQtyEl)stockQtyEl.textContent=qty+' '+unit;if(quantityInput&&qty!=='--')quantityInput.max=qty;updateLoss();});
quantityInput?.addEventListener('input',updateLoss);
document.getElementById('reasonSelect')?.addEventListener('change',updateLoss);
document.getElementById('searchHistory')?.addEventListener('input',function(){const q=this.value.toLowerCase();document.querySelectorAll('#historyTable tbody tr').forEach(tr=>{tr.style.display=(tr.dataset.search||'').includes(q)?'':'none';});});
window.addEventListener('storage',e=>{if(e.key==='lt_lang')applySoLang(e.newValue);});
applySoLang(soLang);
</script>
</body>
</html>