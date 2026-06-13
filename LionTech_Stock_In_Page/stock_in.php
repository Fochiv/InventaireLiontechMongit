<?php
require_once __DIR__ . '/../Config.php';
startSecureSession(); requireLogin();
$user=currentUser(); $pdo=getDB();
$businessId=(int)($user['business_id']??0); $role=(string)($user['role']??'');
if($businessId<=0){header('Location: '.APP_URL.'/Logininventory/login.php?error=unauthorized');exit;}
function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function qf($v):string{return rtrim(rtrim(number_format((float)$v,2,'.',''  ),'0'),'.');}
function xaf($v):string{return number_format((float)$v,0,',',' ').' XAF';}
function sl(string $n):string{$n=strtolower(trim($n));$n=preg_replace('/[^a-z0-9\._-]+/','-',$n);return trim($n,'-');}
$stmt=$pdo->prepare('SELECT * FROM businesses WHERE business_id=? LIMIT 1');$stmt->execute([$businessId]);$business=$stmt->fetch();
if(!$business){header('Location: '.APP_URL.'/Logininventory/login.php?error=unauthorized');exit;}
$inventoryEnabled=true;
try{$s=$pdo->prepare('SELECT inventory_management FROM business_features WHERE business_id=? LIMIT 1');$s->execute([$businessId]);$f=$s->fetch();if($f&&(int)$f['inventory_management']!==1)$inventoryEnabled=false;}catch(Throwable $e){}
$isApprover=in_array($role,[ROLE_BUSINESS_OWNER,ROLE_MANAGER],true);
$canCreate=$inventoryEnabled&&!in_array($business['subscription_status']??'trial',['expired','suspended'],true);
$canApprove=$isApprover&&$canCreate;
$message='';$messageType='';
$uploadDir=__DIR__.'/uploads/stock_in';$uploadUrlBase='uploads/stock_in';
if(!is_dir($uploadDir))@mkdir($uploadDir,0775,true);

if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='create_stock_in'){
 if(!$canCreate){$message='Action disabled.';$messageType='error';}
 else{
  $productId=(int)($_POST['product_id']??0);$quantity=(float)($_POST['quantity']??0);
  $costPrice=(float)($_POST['cost_price']??0);$supplier=trim($_POST['supplier']??'');
  $deliveryDate=trim($_POST['delivery_date']??'');$note=trim($_POST['note']??'');$proofUrl=null;
  $s=$pdo->prepare('SELECT product_id,unit_price FROM products WHERE product_id=? AND business_id=? AND status="active" LIMIT 1');
  $s->execute([$productId,$businessId]);$prod=$s->fetch();
  if(!$prod){$message='Please select a valid product.';$messageType='error';}
  elseif($quantity<=0){$message='Quantity must be greater than zero.';$messageType='error';}
  else{
   $sellPrice=(float)($prod['unit_price']??0);
   $potentialRevenue=$quantity*$sellPrice;$potentialProfit=$quantity*($sellPrice-$costPrice);
   if(!empty($_FILES['proof_image']['name'])&&is_uploaded_file($_FILES['proof_image']['tmp_name'])){
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $mime=mime_content_type($_FILES['proof_image']['tmp_name']);
    if(isset($allowed[$mime])&&$_FILES['proof_image']['size']<=3*1024*1024){
     $fn=time().'-'.random_int(1000,9999).'-'.sl($_FILES['proof_image']['name']);
     if(move_uploaded_file($_FILES['proof_image']['tmp_name'],$uploadDir.'/'.$fn))$proofUrl=$uploadUrlBase.'/'.$fn;
    }
   }
   try{
    $pdo->prepare('INSERT INTO stock_in_requests(business_id,product_id,quantity,cost_price,potential_revenue,potential_profit,supplier,delivery_date,note,proof_image_url,status,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,"pending",?,NOW())')
      ->execute([$businessId,$productId,$quantity,$costPrice,$potentialRevenue,$potentialProfit,$supplier?:null,$deliveryDate?:null,$note?:null,$proofUrl,(int)$user['user_id']]);
    $message='Stock in submitted. Waiting for approval.';$messageType='success';
   }catch(Throwable $ex){$message='Error: '.$ex->getMessage();$messageType='error';}
  }
 }
}
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='approve_stock_in'&&$canApprove){
 $requestId=(int)($_POST['request_id']??0);
 try{
  $pdo->beginTransaction();
  $s=$pdo->prepare('SELECT * FROM stock_in_requests WHERE request_id=? AND business_id=? AND status="pending" FOR UPDATE');
  $s->execute([$requestId,$businessId]);$req=$s->fetch();
  if($req){
   $pdo->prepare('UPDATE products SET quantity=quantity+?,updated_at=NOW() WHERE product_id=? AND business_id=?')->execute([(float)$req['quantity'],(int)$req['product_id'],$businessId]);
   $pdo->prepare('UPDATE stock_in_requests SET status="approved",approved_by=?,approved_at=NOW() WHERE request_id=? AND business_id=?')->execute([(int)$user['user_id'],$requestId,$businessId]);
   $pdo->commit();$message='Stock in approved. Quantity updated.';$messageType='success';
  }else{$pdo->rollBack();$message='Request not found.';$messageType='error';}
 }catch(Throwable $ex){if($pdo->inTransaction())$pdo->rollBack();$message='Error: '.$ex->getMessage();$messageType='error';}
}
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='reject_stock_in'&&$canApprove){
 $requestId=(int)($_POST['request_id']??0);
 $pdo->prepare('UPDATE stock_in_requests SET status="rejected",approved_by=?,approved_at=NOW(),rejection_reason="Rejected after review" WHERE request_id=? AND business_id=? AND status="pending"')->execute([(int)$user['user_id'],$requestId,$businessId]);
 $message='Request rejected.';$messageType='success';
}

$stmt=$pdo->prepare('SELECT product_id,name,sku,category,unit,quantity,unit_price,cost_price,low_stock_level FROM products WHERE business_id=? AND status="active" ORDER BY name ASC');
$stmt->execute([$businessId]);$products=$stmt->fetchAll();
$stmt=$pdo->prepare('SELECT r.*,p.name product_name,p.unit,p.unit_price,u.full_name created_by_name,a.full_name approved_by_name FROM stock_in_requests r JOIN products p ON p.product_id=r.product_id LEFT JOIN users u ON u.user_id=r.created_by LEFT JOIN users a ON a.user_id=r.approved_by WHERE r.business_id=? ORDER BY r.created_at DESC LIMIT 100');
$stmt->execute([$businessId]);$requests=$stmt->fetchAll();
$pendingCount=count(array_filter($requests,fn($r)=>$r['status']==='pending'));
$approvedToday=count(array_filter($requests,fn($r)=>$r['status']==='approved'&&substr((string)$r['approved_at'],0,10)===date('Y-m-d')));
$pendingQty=array_sum(array_map(fn($r)=>$r['status']==='pending'?(float)$r['quantity']:0,$requests));
$initials='';foreach(explode(' ',trim($user['full_name']??'U')) as $w)$initials.=strtoupper(substr($w,0,1));
$initials=substr($initials?:'U',0,2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Stock In — LionTech</title>
<link rel="icon" type="image/png" href="<?= APP_URL ?>/Image/TALLYLOGO.png"/>
<link rel="manifest" href="<?= APP_URL ?>/manifest.json"/>
<link rel="stylesheet" href="stock_in.css"/>
<link rel="stylesheet" href="<?= APP_URL ?>/icons.css">
</head>
<body>
<div class="od-layout">
<?php include __DIR__.'/../LionTech_Owner_Dashboard/Sidebar.php'; ?>
<main class="od-main">
<header class="od-topbar">
  <div class="od-business-title">
    <h1 data-i18n="page_title">Stock In</h1>
    <p data-i18n="page_subtitle">Record products arriving at your store.</p>
  </div>
  <div class="od-top-actions">
    <button id="si-lang" class="od-lang" onclick="toggleSiLang()">FR</button>
    <?php if($canCreate&&count($products)>0): ?>
    <button class="od-primary" id="openStockIn" style="border:none;cursor:pointer;font-family:inherit">+ <span data-i18n="btn_add">Add Stock In</span></button>
    <?php endif; ?>
    <div class="od-avatar"><?=e($initials)?></div>
  </div>
</header>

<?php if($message): ?>
<div style="background:<?=$messageType==='success'?'#F0FDF4':'#FEF2F2'?>;border:1px solid <?=$messageType==='success'?'#86EFAC':'#FECACA'?>;padding:12px 24px;font-size:13px;color:<?=$messageType==='success'?'#166534':'#991B1B'?>">
  <?=$messageType==='success'?'<span class="icon-ok">✓</span>':'<span class="icon-warn">⚠</span>'?> <?=e($message)?></div>
<?php endif; ?>

<div style="padding:20px 24px 0;display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px">
  <div class="od-card stat"><span class="stat-icon amber"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span><div><small data-i18n="stat_pending">Pending</small><strong><?=(int)$pendingCount?></strong></div></div>
  <div class="od-card stat"><span class="stat-icon green"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16A34A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span><div><small data-i18n="stat_today">Approved today</small><strong><?=(int)$approvedToday?></strong></div></div>
  <div class="od-card stat"><span class="stat-icon blue"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></span><div><small data-i18n="stat_products">Products</small><strong><?=count($products)?></strong></div></div>
  <div class="od-card stat"><span class="stat-icon" style="background:#EDE9FE"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#7C3AED" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></span><div><small data-i18n="stat_qty">Qty pending</small><strong><?=qf($pendingQty)?></strong></div></div>
</div>

<div style="padding:16px 24px 40px">
  <div class="od-card" style="padding:0;overflow:hidden">
    <div style="padding:16px 20px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #F1F5F9;flex-wrap:wrap;gap:10px">
      <h2 style="font-size:15px;font-weight:700;color:#0B1F3A;margin:0" data-i18n="table_title">Stock In Requests</h2>
      <div style="display:flex;gap:10px">
        <input id="si-search" type="search" placeholder="Search..." style="padding:8px 13px;border:1.5px solid #E5E7EB;border-radius:9px;font-size:13px;font-family:inherit"/>
        <select id="si-status-filter" style="padding:8px 13px;border:1.5px solid #E5E7EB;border-radius:9px;font-size:13px;font-family:inherit">
          <option value="all" data-i18n="filter_all">All</option>
          <option value="pending" data-i18n="filter_pending">Pending</option>
          <option value="approved" data-i18n="filter_approved">Approved</option>
          <option value="rejected" data-i18n="filter_rejected">Rejected</option>
        </select>
      </div>
    </div>
    <div style="overflow-x:auto">
      <table id="si-table" style="width:100%;border-collapse:collapse;font-size:13px;min-width:900px">
        <thead style="background:#F8FAFC">
        <tr>
          <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase;white-space:nowrap" data-i18n="th_product">Product</th>
          <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase" data-i18n="th_qty">Qty</th>
          <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase;white-space:nowrap" data-i18n="th_cost">Cost/u</th>
          <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase;white-space:nowrap" data-i18n="th_sell">Sell Price</th>
          <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase;white-space:nowrap" data-i18n="th_revenue">Potential Revenue</th>
          <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase;white-space:nowrap" data-i18n="th_profit">Potential Profit</th>
          <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase" data-i18n="th_supplier">Supplier</th>
          <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase" data-i18n="th_date">Date</th>
          <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase" data-i18n="th_by">By</th>
          <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase" data-i18n="th_status">Status</th>
          <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase" data-i18n="th_actions">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach($requests as $r): ?>
        <tr data-status="<?=e($r['status'])?>" data-search="<?=e(strtolower(($r['product_name']??'').' '.($r['supplier']??'').' '.($r['created_by_name']??'')))?>">
          <td style="padding:11px 14px;border-bottom:1px solid #F1F5F9"><strong><?=e($r['product_name'])?></strong></td>
          <td style="padding:11px 14px;border-bottom:1px solid #F1F5F9"><?=qf($r['quantity'])?> <?=e($r['unit']??'')?></td>
          <td style="padding:11px 14px;border-bottom:1px solid #F1F5F9"><?=$r['cost_price']>0?xaf($r['cost_price']):'<span style="color:#9CA3AF">—</span>'?></td>
          <td style="padding:11px 14px;border-bottom:1px solid #F1F5F9"><?=xaf($r['unit_price']??0)?></td>
          <td style="padding:11px 14px;border-bottom:1px solid #F1F5F9"><?=$r['potential_revenue']>0?xaf($r['potential_revenue']):'<span style="color:#9CA3AF">—</span>'?></td>
          <td style="padding:11px 14px;border-bottom:1px solid #F1F5F9"><strong style="color:#059669"><?=$r['potential_profit']>0?xaf($r['potential_profit']):'<span style="color:#9CA3AF">—</span>'?></strong></td>
          <td style="padding:11px 14px;border-bottom:1px solid #F1F5F9"><?=e($r['supplier']??'—')?></td>
          <td style="padding:11px 14px;border-bottom:1px solid #F1F5F9;font-size:12px"><?=e(substr((string)$r['created_at'],0,10))?></td>
          <td style="padding:11px 14px;border-bottom:1px solid #F1F5F9;font-size:12px"><?=e($r['created_by_name']??'—')?></td>
          <td style="padding:11px 14px;border-bottom:1px solid #F1F5F9">
            <?php $bc=$r['status']==='approved'?'#DCFCE7;color:#166534':($r['status']==='rejected'?'#FEE2E2;color:#991B1B':'#FEF3C7;color:#92400E'); ?>
            <span style="display:inline-block;padding:3px 9px;border-radius:20px;background:<?=$bc?>;font-size:11px;font-weight:700"><?=ucfirst(e($r['status']))?></span>
          </td>
          <td style="padding:11px 14px;border-bottom:1px solid #F1F5F9">
            <?php if($r['status']==='pending'&&$canApprove): ?>
            <div style="display:flex;gap:6px">
              <form method="POST" style="display:inline"><input type="hidden" name="action" value="approve_stock_in"><input type="hidden" name="request_id" value="<?=(int)$r['request_id']?>"><button type="submit" style="padding:5px 10px;font-size:12px;background:#F0FDF4;border:1.5px solid #86EFAC;border-radius:8px;color:#166534;cursor:pointer" data-i18n="btn_approve">Approve</button></form>
              <form method="POST" style="display:inline"><input type="hidden" name="action" value="reject_stock_in"><input type="hidden" name="request_id" value="<?=(int)$r['request_id']?>"><button type="submit" style="padding:5px 10px;font-size:12px;background:#FEF2F2;border:1.5px solid #FECACA;border-radius:8px;color:#991B1B;cursor:pointer" data-i18n="btn_reject">Reject</button></form>
            </div>
            <?php elseif($r['status']==='approved'): ?><small style="color:#166534"><span class="icon-ok">✓</span> <?=e($r['approved_by_name']??'OK')?></small>
            <?php else: ?><small style="color:#6B7280">—</small><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; if(!$requests): ?>
        <tr><td colspan="11" style="text-align:center;padding:28px;color:#6B7280" data-i18n="empty">No stock in requests recorded.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</main>
</div>

<!-- Modal -->
<div id="stockInModal" style="display:none;position:fixed;inset:0;z-index:500;background:rgba(11,31,58,.5);align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:18px;width:100%;max-width:540px;max-height:90vh;overflow-y:auto;padding:28px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h2 style="font-size:17px;font-weight:700;color:#0B1F3A;margin:0" data-i18n="modal_title">Add Stock In</h2>
      <button id="closeStockIn" style="background:none;border:none;font-size:22px;cursor:pointer;color:#6B7280">×</button>
    </div>
    <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:14px">
      <input type="hidden" name="action" value="create_stock_in"/>
      <div>
        <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px"><span data-i18n="lbl_product">Product</span> *</label>
        <select name="product_id" id="siProduct" required style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit">
          <option value="" data-i18n="select_product">Choose a product...</option>
          <?php foreach($products as $p): ?>
          <option value="<?=(int)$p['product_id']?>" data-unit-price="<?=e($p['unit_price']??0)?>" data-cost="<?=e($p['cost_price']??0)?>">
            <?=e($p['name'])?> — <?=qf($p['quantity'])?> <?=e($p['unit']??'')?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div>
          <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px"><span data-i18n="lbl_qty">Qty received</span> *</label>
          <input type="number" step="0.01" min="0.01" name="quantity" id="siQty" required placeholder="0" style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;box-sizing:border-box"/>
        </div>
        <div>
          <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px"><span data-i18n="lbl_cost">Cost / unit (XAF)</span></label>
          <input type="number" step="0.01" min="0" name="cost_price" id="siCost" placeholder="0" style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;box-sizing:border-box"/>
        </div>
      </div>
      <div id="siProfitPreview" style="display:none;background:#F0FDF4;border:1px solid #86EFAC;border-radius:10px;padding:10px 14px;font-size:12.5px">
        <span data-i18n="preview_revenue">Revenue:</span> <strong id="siRevenue">—</strong> &nbsp;|&nbsp;
        <span data-i18n="preview_profit">Profit:</span> <strong id="siProfit">—</strong>
      </div>
      <div>
        <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px" data-i18n="lbl_supplier">Supplier</label>
        <input type="text" name="supplier" placeholder="Supplier name" style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;box-sizing:border-box"/>
      </div>
      <div>
        <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px" data-i18n="lbl_date">Delivery date</label>
        <input type="date" name="delivery_date" value="<?=e(date('Y-m-d'))?>" style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;box-sizing:border-box"/>
      </div>
      <div>
        <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px" data-i18n="lbl_proof">Delivery photo</label>
        <input type="file" name="proof_image" accept="image/jpeg,image/png,image/webp" style="width:100%"/>
      </div>
      <div>
        <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px" data-i18n="lbl_note">Note</label>
        <textarea name="note" rows="2" placeholder="Optional note..." style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;resize:vertical;box-sizing:border-box"></textarea>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px">
        <button type="button" id="cancelStockIn" style="padding:11px 20px;background:#fff;border:1.5px solid #E5E7EB;border-radius:11px;font-size:13.5px;cursor:pointer;font-family:inherit" data-i18n="btn_cancel">Cancel</button>
        <button type="submit" class="od-primary" style="padding:11px 24px;border:none;border-radius:11px;font-size:13.5px;font-weight:700;cursor:pointer;font-family:inherit" data-i18n="btn_submit">Submit</button>
      </div>
    </form>
  </div>
</div>
<script>
const SI_T={
  en:{page_title:'Stock In',page_subtitle:'Record products arriving at your store.',btn_add:'Add Stock In',stat_pending:'Pending',stat_today:'Approved today',stat_products:'Products',stat_qty:'Qty pending',table_title:'Stock In Requests',filter_all:'All',filter_pending:'Pending',filter_approved:'Approved',filter_rejected:'Rejected',th_product:'Product',th_qty:'Qty',th_cost:'Cost/u',th_sell:'Sell Price',th_revenue:'Potential Revenue',th_profit:'Potential Profit',th_supplier:'Supplier',th_date:'Date',th_by:'By',th_status:'Status',th_actions:'Actions',btn_approve:'Approve',btn_reject:'Reject',empty:'No stock in requests recorded.',modal_title:'Add Stock In',lbl_product:'Product',select_product:'Choose a product...',lbl_qty:'Qty received',lbl_cost:'Cost / unit (XAF)',preview_revenue:'Revenue:',preview_profit:'Profit:',lbl_supplier:'Supplier',lbl_date:'Delivery date',lbl_proof:'Delivery photo',lbl_note:'Note',btn_cancel:'Cancel',btn_submit:'Submit'},
  fr:{page_title:'Stock entrant',page_subtitle:"Enregistrez les produits qui arrivent.",btn_add:'Ajouter Stock Entrant',stat_pending:'En attente',stat_today:"Approuvés aujourd'hui",stat_products:'Produits',stat_qty:'Qté en attente',table_title:'Demandes de stock entrant',filter_all:'Tout',filter_pending:'En attente',filter_approved:'Approuvé',filter_rejected:'Rejeté',th_product:'Produit',th_qty:'Quantité',th_cost:"Prix achat/u",th_sell:'Prix vente',th_revenue:'Revenu potentiel',th_profit:'Profit potentiel',th_supplier:'Fournisseur',th_date:'Date',th_by:'Par',th_status:'Statut',th_actions:'Actions',btn_approve:'Approuver',btn_reject:'Rejeter',empty:'Aucun stock entrant.',modal_title:'Ajouter Stock Entrant',lbl_product:'Produit',select_product:'Choisir un produit...',lbl_qty:'Quantité reçue',lbl_cost:"Prix d'achat / unité (XAF)",preview_revenue:'Revenu:',preview_profit:'Profit:',lbl_supplier:'Fournisseur',lbl_date:'Date livraison',lbl_proof:'Photo livraison',lbl_note:'Note',btn_cancel:'Annuler',btn_submit:'Soumettre'}
};
let siLang=localStorage.getItem('lt_lang')||'en';
function applySiLang(lang){siLang=lang;const T=SI_T[lang]||SI_T.en;document.querySelectorAll('[data-i18n]').forEach(el=>{const k=el.dataset.i18n;if(T[k])el.textContent=T[k];});document.querySelectorAll('[data-i18n-opt]').forEach(el=>{const k=el.dataset.i18nOpt;if(T[k])el.textContent=T[k];});const b=document.getElementById('si-lang');if(b)b.textContent=lang==='en'?'FR':'EN';}
window.toggleSiLang=function(){const n=siLang==='en'?'fr':'en';localStorage.setItem('lt_lang',n);applySiLang(n);if(typeof window.applySidebarLang==='function')window.applySidebarLang(n);};
document.getElementById('openStockIn')?.addEventListener('click',()=>document.getElementById('stockInModal').style.display='flex');
['closeStockIn','cancelStockIn'].forEach(id=>document.getElementById(id)?.addEventListener('click',()=>document.getElementById('stockInModal').style.display='none'));
function updatePreview(){const opt=document.getElementById('siProduct')?.selectedOptions[0];const qty=parseFloat(document.getElementById('siQty')?.value||0);const cost=parseFloat(document.getElementById('siCost')?.value||0);const sp=parseFloat(opt?.dataset?.unitPrice||0);const prev=document.getElementById('siProfitPreview');if(qty>0&&sp>0){document.getElementById('siRevenue').textContent=(qty*sp).toLocaleString('fr-FR')+' XAF';const p=qty*(sp-cost);document.getElementById('siProfit').textContent=p.toLocaleString('fr-FR')+' XAF';document.getElementById('siProfit').style.color=p>=0?'#059669':'#DC2626';if(prev)prev.style.display='block';}else if(prev)prev.style.display='none';}
document.getElementById('siProduct')?.addEventListener('change',function(){const c=parseFloat(this.selectedOptions[0]?.dataset?.cost||0);if(c>0)document.getElementById('siCost').value=c;updatePreview();});
document.getElementById('siQty')?.addEventListener('input',updatePreview);
document.getElementById('siCost')?.addEventListener('input',updatePreview);
document.getElementById('si-search')?.addEventListener('input',function(){const q=this.value.toLowerCase();document.querySelectorAll('#si-table tbody tr').forEach(tr=>{tr.style.display=(tr.dataset.search||'').includes(q)?'':'none';});});
document.getElementById('si-status-filter')?.addEventListener('change',function(){const st=this.value;document.querySelectorAll('#si-table tbody tr').forEach(tr=>{tr.style.display=(st==='all'||tr.dataset.status===st)?'':'none';});});
window.addEventListener('storage',e=>{if(e.key==='lt_lang')applySiLang(e.newValue);});
applySiLang(siLang);
</script>
</body>
</html>