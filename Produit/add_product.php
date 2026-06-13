<?php
/* ============================================================
   AjouterProduit.php — LionTech Add Product
   Path: C:\Xampp\htdocs\InventoryLiontech\Produit\AjouterProduit.php
   ============================================================ */
require_once __DIR__ . '/../Config.php';
startSecureSession();
requireLogin();

$user = currentUser();
$pdo  = getDB();
$businessId = (int)($user['business_id'] ?? 0);
$role = (string)($user['role'] ?? '');

if ($businessId <= 0) {
    header('Location: '.APP_URL.'/Logininventory/login.php?error=unauthorized'); exit;
}
function e($v):string{ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function sl2(string $n):string{ $n=strtolower(trim($n));$n=preg_replace('/[^a-z0-9\._-]+/','-',$n);return trim($n,'-'); }

$stmt=$pdo->prepare('SELECT * FROM businesses WHERE business_id=? LIMIT 1');
$stmt->execute([$businessId]); $business=$stmt->fetch();
if(!$business){ header('Location: '.APP_URL.'/Logininventory/login.php?error=unauthorized'); exit; }

$inventoryEnabled=true;
try{
    $stmt=$pdo->prepare('SELECT inventory_management FROM business_features WHERE business_id=? LIMIT 1');
    $stmt->execute([$businessId]); $f=$stmt->fetch();
    if($f&&(int)$f['inventory_management']!==1) $inventoryEnabled=false;
}catch(Throwable $ex){ $inventoryEnabled=true; }

$canModify=$inventoryEnabled
    &&!in_array($business['subscription_status']??'trial',['expired','suspended'],true)
    &&in_array($role,[ROLE_BUSINESS_OWNER,ROLE_MANAGER],true);

if(!$canModify){ header('Location: '.APP_URL.'/Produit/products.php?error=not_allowed'); exit; }

/* ── Success flash (PRG) ── */
$message=''; $messageType='';
if(isset($_GET['created'])){ $message='Product added successfully!'; $messageType='success'; }

$categories=['Beverages','Food','Cosmetics','Electronics','Clothing','Medicine','Hardware','Cleaning','Other'];
$units=['piece','carton','pack','kg','litre','bottle','bag','box','metre','other'];

$old=['product_name'=>'','category_select'=>'','category_custom'=>'','unit'=>'piece',
      'selling_price'=>'0','cost_price'=>'0','low_stock_level'=>'0',
      'barcode'=>'','expiration_date'=>'','description'=>''];

$uploadDir=__DIR__.'/uploads/products'; $uploadUrlBase='uploads/products';
if(!is_dir($uploadDir)) @mkdir($uploadDir,0775,true);

if($_SERVER['REQUEST_METHOD']==='POST'){
    foreach($old as $key=>$value) $old[$key]=trim($_POST[$key]??$value);

    $name        = $old['product_name'];
    $category    = $old['category_custom']!==''?$old['category_custom']:$old['category_select'];
    $unit        = $old['unit']?:'piece';
    $sellingPrice= (float)$old['selling_price'];
    $costPrice   = (float)$old['cost_price'];
    $lowStock    = (float)$old['low_stock_level'];
    $barcode     = $old['barcode'];
    $expiration  = $old['expiration_date']?:null;
    $description = $old['description']?:null;

    if($name==='')                          { $message='Product name is required.'; $messageType='error'; }
    elseif($category==='')                  { $message='Category is required.'; $messageType='error'; }
    elseif($sellingPrice<0||$lowStock<0)    { $message='Prices cannot be negative.'; $messageType='error'; }
    else{
        $imageUrl=null;
        if(!empty($_FILES['product_image']['name'])&&is_uploaded_file($_FILES['product_image']['tmp_name'])){
            $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            $mime=mime_content_type($_FILES['product_image']['tmp_name']);
            if(isset($allowed[$mime])&&$_FILES['product_image']['size']<=2*1024*1024){
                $fn=time().'-'.random_int(1000,9999).'-'.sl2($_FILES['product_image']['name']);
                if(move_uploaded_file($_FILES['product_image']['tmp_name'],$uploadDir.'/'.$fn))
                    $imageUrl=$uploadUrlBase.'/'.$fn;
            }
        }
        try{
            $sku='PRD-'.strtoupper(substr(md5($businessId.$name.microtime()),0,8));
            $pdo->prepare('INSERT INTO products
                (business_id,name,sku,barcode,category,unit,quantity,unit_price,cost_price,
                 low_stock_level,expiration_date,supplier,image_url,description,status,created_by,created_at)
                VALUES(?,?,?,?,?,?,0,?,?,?,?,NULL,?,?,"active",?,NOW())')
                ->execute([$businessId,$name,$sku,$barcode?:null,$category?:null,$unit,
                           $sellingPrice,$costPrice,$lowStock,$expiration,
                           $imageUrl,$description,(int)$user['user_id']]);

            /* PRG — redirect to same page with success flag */
            header('Location: '.APP_URL.'/Produit/AjouterProduit.php?created=1'); exit;
        }catch(Throwable $ex){
            $message='Unable to save product: '.$ex->getMessage(); $messageType='error';
        }
    }
}

$initials='';
foreach(explode(' ',trim($user['full_name']??'U')) as $w) $initials.=strtoupper(substr($w,0,1));
$initials=substr($initials?:'U',0,2);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Add Product — LionTech</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/LionTech_Owner_Dashboard/owner_dashboard.css"/>
  <link rel="stylesheet" href="products.css"/>
</head>
<body>
<div class="od-layout">
  <?php include __DIR__.'/../LionTech_Owner_Dashboard/Sidebar.php'; ?>

  <main class="od-main">
    <header class="od-topbar">
      <div class="od-business-title">
        <h1 data-i18n="add_product_title">Add Product</h1>
        <p data-i18n="add_product_page_subtitle">Create the product first. Stock quantity added later via Stock In.</p>
      </div>
      <div class="od-top-actions">
        <button class="od-lang" id="pr-lang">FR</button>
        <a class="od-primary" href="<?= APP_URL ?>/Produit/products.php" style="text-decoration:none" data-i18n="back_to_products">← Back to products</a>
        <div class="od-avatar"><?= e($initials) ?></div>
      </div>
    </header>

    <div style="padding:0 24px 60px">

      <?php if($message): ?>
      <div class="ap-alert ap-alert-<?= $messageType ?>" id="apAlert">
        <?= $messageType==='success' ? '✅' : '⚠️' ?>
        <span><?= e($message) ?></span>
        <?php if($messageType==='success'): ?>
        <a href="<?= APP_URL ?>/Produit/products.php" style="margin-left:16px;font-weight:700;color:#166534;text-decoration:underline" data-i18n="view_products">View all products →</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <div class="ap-grid">

        <!-- ── FORM ── -->
        <section class="ap-panel">
          <div class="ap-panel-header">
            <h2 data-i18n="product_identity">Product Identity</h2>
            <p data-i18n="product_identity_subtitle">Fill in product details. Quantity comes from Stock In deliveries.</p>
          </div>

          <form class="ap-form" method="POST" enctype="multipart/form-data" id="addProductPageForm">
            <div class="ap-form-grid">

              <div class="ap-field ap-full">
                <label data-i18n="product_name">Product Name *</label>
                <input type="text" name="product_name" id="product_name"
                  value="<?= e($old['product_name']) ?>"
                  placeholder="Ex: Coca-Cola, soap, rice..." required/>
              </div>

              <div class="ap-field">
                <label data-i18n="category">Category *</label>
                <select name="category_select" id="category_select">
                  <option value="" data-i18n="select_option">Select...</option>
                  <?php foreach($categories as $cat): ?>
                  <option value="<?= e($cat) ?>" <?= $old['category_select']===$cat?'selected':'' ?>><?= e($cat) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="ap-field">
                <label data-i18n="custom_category">Custom Category</label>
                <input type="text" name="category_custom" id="category_custom"
                  value="<?= e($old['category_custom']) ?>" placeholder="Ex: Beauty products"/>
              </div>

              <div class="ap-field">
                <label data-i18n="unit">Unit *</label>
                <select name="unit" id="unit" required>
                  <?php foreach($units as $u): ?>
                  <option value="<?= e($u) ?>" <?= $old['unit']===$u?'selected':'' ?>><?= e($u) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="ap-field">
                <label data-i18n="selling_price">Selling Price (XAF)</label>
                <input type="number" step="0.01" min="0" name="selling_price" id="selling_price"
                  value="<?= e($old['selling_price']) ?>"/>
                <div class="ap-help" data-i18n="selling_price_help">Price used by the cashier during sales.</div>
              </div>

              <div class="ap-field">
                <label data-i18n="cost_price">Purchase Price / Cost (XAF)</label>
                <input type="number" step="0.01" min="0" name="cost_price" id="cost_price"
                  value="<?= e($old['cost_price']) ?>"/>
                <div class="ap-help" data-i18n="cost_price_help">Optional. Used to calculate profit margin on Vente reports.</div>
              </div>

              <div class="ap-field">
                <label data-i18n="low_stock_level">Low Stock Alert Level</label>
                <input type="number" step="0.01" min="0" name="low_stock_level" id="low_stock_level"
                  value="<?= e($old['low_stock_level']) ?>"/>
              </div>

              <div class="ap-field">
                <label data-i18n="barcode">Barcode / UPC</label>
                <input type="text" name="barcode" id="barcode"
                  value="<?= e($old['barcode']) ?>" placeholder="Optional"/>
                <div class="ap-help" data-i18n="barcode_help">Use existing barcode or generate one later.</div>
              </div>

              <div class="ap-field">
                <label data-i18n="expiration_date">Expiration Date</label>
                <input type="date" name="expiration_date" id="expiration_date"
                  value="<?= e($old['expiration_date']) ?>"/>
              </div>

              <div class="ap-field ap-full">
                <label data-i18n="product_image">Product Image</label>
                <input type="file" name="product_image" id="product_image" accept="image/png,image/jpeg,image/webp"/>
                <div class="ap-help" data-i18n="image_note">Optional. Max 2 MB.</div>
              </div>

              <div class="ap-field ap-full">
                <label data-i18n="description">Description / Notes</label>
                <textarea name="description" id="description" rows="3"
                  placeholder="Optional notes..."><?= e($old['description']) ?></textarea>
              </div>

            </div>

            <div class="ap-actions">
              <a class="ap-btn-secondary" href="<?= APP_URL ?>/Produit/products.php" data-i18n="cancel">Cancel</a>
              <button type="submit" class="ap-btn-primary" data-i18n="save_product">Save Product</button>
            </div>
          </form>
        </section>

        <!-- ── PREVIEW ── -->
        <aside class="ap-panel">
          <div class="ap-panel-header">
            <h2 data-i18n="preview">Preview</h2>
            <p data-i18n="preview_subtitle">Live product summary.</p>
          </div>
          <div class="ap-side">
            <div class="ap-preview-box">
              <div class="ap-preview-row"><span data-i18n="product">Product</span><strong id="pv-name">—</strong></div>
              <div class="ap-preview-row"><span data-i18n="category">Category</span><strong id="pv-category">—</strong></div>
              <div class="ap-preview-row"><span data-i18n="unit">Unit</span><strong id="pv-unit">—</strong></div>
              <div class="ap-preview-row"><span data-i18n="selling_price">Sell Price</span><strong id="pv-price">0 XAF</strong></div>
              <div class="ap-preview-row"><span data-i18n="cost_price">Cost Price</span><strong id="pv-cost">0 XAF</strong></div>
              <div class="ap-preview-row" id="pv-margin-row" style="display:none">
                <span data-i18n="margin">Margin / unit</span>
                <strong id="pv-margin" style="color:#059669">—</strong>
              </div>
              <div class="ap-preview-row"><span data-i18n="barcode">Barcode</span><strong id="pv-barcode">—</strong></div>
              <div class="ap-preview-row"><span data-i18n="quantity">Quantity</span><strong>0</strong></div>
            </div>
            <div class="ap-tip" data-i18n="stock_note_text">
              Use Stock In when products are delivered. This keeps delivery records, cost prices, and profit history clean.
            </div>
          </div>
        </aside>

      </div>
    </div>
  </main>
</div>

<script src="products.js"></script>
<script>
/* Auto-dismiss success alert */
const apAlert = document.getElementById('apAlert');
if(apAlert && apAlert.classList.contains('ap-alert-success')){
  setTimeout(()=>{ apAlert.style.transition='opacity .5s'; apAlert.style.opacity='0'; setTimeout(()=>apAlert.remove(),500); }, 5000);
}
/* Cost price + margin preview */
(function(){
  function upd(){
    const sell=parseFloat(document.getElementById('selling_price')?.value||0);
    const cost=parseFloat(document.getElementById('cost_price')?.value||0);
    const pvCost=document.getElementById('pv-cost');
    const pvMargin=document.getElementById('pv-margin');
    const pvMarginRow=document.getElementById('pv-margin-row');
    if(pvCost)pvCost.textContent=cost.toLocaleString('fr-FR')+' XAF';
    if(pvMargin&&pvMarginRow){
      if(sell>0||cost>0){
        const m=sell-cost;
        pvMargin.textContent=m.toLocaleString('fr-FR')+' XAF';
        pvMargin.style.color=m>=0?'#059669':'#DC2626';
        pvMarginRow.style.display='flex';
      }else pvMarginRow.style.display='none';
    }
  }
  document.getElementById('selling_price')?.addEventListener('input',upd);
  document.getElementById('cost_price')?.addEventListener('input',upd);
  upd();
})();
</script>
</body>
</html>