<?php
/* ============================================================
   add_product.php — LionTech Business Manager
   Standalone Add Product Page
   Path: C:\Xampp\htdocs\InventoryLiontech\Produit\add_product.php
   ============================================================ */

require_once __DIR__ . '/../Config.php';
startSecureSession();
requireLogin();

$user       = currentUser();
$pdo        = getDB();
$businessId = (int)($user['business_id'] ?? 0);
$role       = (string)($user['role'] ?? '');

if ($businessId <= 0) {
    header('Location: ' . APP_URL . '/Logininventory/login.php?error=unauthorized');
    exit;
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function slugify_file(string $name): string {
    $name = strtolower(trim($name));
    $name = preg_replace('/[^a-z0-9\._-]+/', '-', $name);
    return trim($name, '-');
}

/* Business */
$stmt = $pdo->prepare('SELECT * FROM businesses WHERE business_id = ? LIMIT 1');
$stmt->execute([$businessId]);
$business = $stmt->fetch();

if (!$business) {
    header('Location: ' . APP_URL . '/Logininventory/login.php?error=unauthorized');
    exit;
}

/* Feature gate */
$inventoryEnabled = true;
try {
    $stmt = $pdo->prepare('SELECT inventory_management FROM business_features WHERE business_id = ? LIMIT 1');
    $stmt->execute([$businessId]);
    $features = $stmt->fetch();

    if ($features && (int)$features['inventory_management'] !== 1) {
        $inventoryEnabled = false;
    }
} catch (Throwable $ex) {
    $inventoryEnabled = true;
}

/* Only owner and manager can add products */
$canModify = $inventoryEnabled
    && !in_array(($business['subscription_status'] ?? 'trial'), ['expired', 'suspended'], true)
    && in_array($role, [ROLE_BUSINESS_OWNER, ROLE_MANAGER], true);

if (!$canModify) {
    header('Location: ' . APP_URL . '/Produit/products.php?error=not_allowed');
    exit;
}

$message = '';
$messageType = '';

$categories = [
    'Boissons',
    'Nourriture',
    'Cosmétiques',
    'Électronique',
    'Vêtements',
    'Médicaments',
    'Quincaillerie',
    'Nettoyage',
    'Autre'
];

$departments = [
    '',
    'Rayon principal',
    'Stock arrière',
    'Caisse',
    'Vitrine',
    'Cuisine',
    'Réserve',
    'Boutique',
    'Autre'
];

$units = [
    'pièce',
    'carton',
    'pack',
    'kg',
    'litre',
    'bouteille',
    'sac',
    'boîte',
    'mètre',
    'autre'
];

$old = [
    'product_name'     => '',
    'product_type'     => '',
    'upc_number'       => '',
    'department'       => '',
    'color'            => '',
    'quantity'         => '0',
    'unit'             => 'pièce',
    'unit_price'       => '0',
    'low_stock_level'  => '0',
    'expiration_date'  => '',
    'supplier'         => '',
    'description'      => ''
];

$uploadDir     = __DIR__ . '/uploads/products';
$uploadUrlBase = 'uploads/products';

if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

/* Submit */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $key => $value) {
        $old[$key] = trim($_POST[$key] ?? $value);
    }

    $name          = $old['product_name'];
    $productType   = $old['product_type'];
    $upcNumber     = $old['upc_number'];
    $department    = $old['department'];
    $color         = $old['color'];
    $quantity      = (float)$old['quantity'];
    $unit          = $old['unit'] ?: 'pièce';
    $unitPrice     = (float)$old['unit_price'];
    $lowStockLevel = (float)$old['low_stock_level'];
    $expiration    = $old['expiration_date'] ?: null;
    $supplier      = $old['supplier'] ?: null;

    $descriptionParts = [];

    if ($department !== '') {
        $descriptionParts[] = 'Département: ' . $department;
    }

    if ($color !== '') {
        $descriptionParts[] = 'Couleur: ' . $color;
    }

    if ($old['description'] !== '') {
        $descriptionParts[] = $old['description'];
    }

    $description = $descriptionParts ? implode("\n", $descriptionParts) : null;

    if ($name === '') {
        $message = 'Le nom du produit est obligatoire. / Product name is required.';
        $messageType = 'error';
    } elseif ($productType === '') {
        $message = 'Le type de produit est obligatoire. / Product type is required.';
        $messageType = 'error';
    } elseif ($quantity < 0 || $unitPrice < 0 || $lowStockLevel < 0) {
        $message = 'La quantité, le prix et le seuil ne peuvent pas être négatifs. / Quantity, price and low stock level cannot be negative.';
        $messageType = 'error';
    } else {
        $imageUrl = null;

        if (!empty($_FILES['product_image']['name']) && is_uploaded_file($_FILES['product_image']['tmp_name'])) {
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp'
            ];

            $mime = mime_content_type($_FILES['product_image']['tmp_name']);

            if (isset($allowed[$mime]) && $_FILES['product_image']['size'] <= 2 * 1024 * 1024) {
                $fileName = time() . '-' . random_int(1000, 9999) . '-' . slugify_file($_FILES['product_image']['name']);
                $target = $uploadDir . '/' . $fileName;

                if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target)) {
                    $imageUrl = $uploadUrlBase . '/' . $fileName;
                }
            }
        }

        try {
            $pdo->beginTransaction();

            $sku = 'PRD-' . strtoupper(substr(md5($businessId . $name . microtime()), 0, 8));

            /*
              Mapping:
              - product_type goes into category
              - upc_number goes into barcode
              - department + color are saved inside description for now
            */
            $stmt = $pdo->prepare('
                INSERT INTO products
                (
                    business_id,
                    name,
                    sku,
                    barcode,
                    category,
                    unit,
                    quantity,
                    unit_price,
                    low_stock_level,
                    expiration_date,
                    supplier,
                    image_url,
                    description,
                    status,
                    created_by,
                    created_at
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "active", ?, NOW()
                )
            ');

            $stmt->execute([
                $businessId,
                $name,
                $sku,
                $upcNumber ?: null,
                $productType ?: null,
                $unit,
                $quantity,
                $unitPrice,
                $lowStockLevel,
                $expiration,
                $supplier,
                $imageUrl,
                $description,
                (int)$user['user_id']
            ]);

            $productId = (int)$pdo->lastInsertId();

            $pdo->prepare('
                INSERT INTO stock_movements
                (
                    business_id,
                    product_id,
                    movement_type,
                    quantity,
                    reason,
                    created_by,
                    created_at
                )
                VALUES
                (
                    ?, ?, "initial", ?, "Initial product quantity", ?, NOW()
                )
            ')->execute([
                $businessId,
                $productId,
                $quantity,
                (int)$user['user_id']
            ]);

            $pdo->commit();

            header('Location: ' . APP_URL . '/Produit/products.php?created=1');
            exit;

        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message = 'Impossible de sauvegarder le produit: ' . $ex->getMessage();
            $messageType = 'error';
        }
    }
}

$initials = '';
foreach (explode(' ', trim($user['full_name'] ?? 'U')) as $w) {
    $initials .= strtoupper(substr($w, 0, 1));
}
$initials = substr($initials ?: 'U', 0, 2);
?>
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Ajouter produit — LionTech</title>
  <link rel="stylesheet" href="products.css"/>
  <style>
    .ap-wrap {
      padding: 24px;
      max-width: 1180px;
      margin: 0 auto;
    }

    .ap-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.4fr) minmax(300px, .6fr);
      gap: 20px;
      align-items: start;
    }

    .ap-panel {
      background: #fff;
      border: 1px solid #E5E7EB;
      border-radius: 22px;
      box-shadow: 0 20px 50px rgba(11,31,58,.10);
      overflow: hidden;
    }

    .ap-panel-header {
      padding: 20px 22px;
      border-bottom: 1px solid #E5E7EB;
    }

    .ap-panel-header h2 {
      margin: 0;
      color: #0B1F3A;
      font-size: 18px;
    }

    .ap-panel-header p {
      margin: 6px 0 0;
      color: #6B7280;
      font-size: 13.5px;
      line-height: 1.5;
    }

    .ap-form {
      padding: 22px;
    }

    .ap-form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }

    .ap-full {
      grid-column: 1 / -1;
    }

    .ap-field label {
      display: block;
      font-size: 12px;
      font-weight: 800;
      color: #0B1F3A;
      margin-bottom: 7px;
      text-transform: uppercase;
      letter-spacing: .2px;
    }

    .ap-field label em {
      display: block;
      text-transform: none;
      letter-spacing: 0;
      font-weight: 500;
      font-size: 11.5px;
      color: #6B7280;
      margin-top: 2px;
    }

    .ap-field input,
    .ap-field select,
    .ap-field textarea {
      width: 100%;
      border: 1.5px solid #E5E7EB;
      border-radius: 14px;
      padding: 12px 13px;
      font: inherit;
      font-size: 14px;
      outline: none;
      box-sizing: border-box;
      background: #fff;
    }

    .ap-field input:focus,
    .ap-field select:focus,
    .ap-field textarea:focus {
      border-color: #00A6A6;
      box-shadow: 0 0 0 4px rgba(0,166,166,.12);
    }

    .ap-help {
      margin-top: 7px;
      font-size: 12px;
      line-height: 1.55;
      color: #6B7280;
      background: #F8FAFC;
      border: 1px dashed #CBD5E1;
      border-radius: 12px;
      padding: 10px 12px;
    }

    .ap-help strong {
      color: #0B1F3A;
    }

    .ap-actions {
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      margin-top: 22px;
      flex-wrap: wrap;
    }

    .ap-side {
      padding: 20px;
    }

    .ap-preview-box {
      border: 1px solid #E5E7EB;
      border-radius: 18px;
      padding: 16px;
      margin-bottom: 14px;
      background: #F8FAFC;
    }

    .ap-preview-row {
      display: flex;
      justify-content: space-between;
      gap: 14px;
      border-bottom: 1px solid #E5E7EB;
      padding: 9px 0;
      font-size: 13px;
    }

    .ap-preview-row:last-child {
      border-bottom: 0;
    }

    .ap-preview-row span {
      color: #6B7280;
    }

    .ap-preview-row strong {
      text-align: right;
      color: #0B1F3A;
    }

    .ap-tip {
      font-size: 13px;
      color: #6B7280;
      line-height: 1.7;
      background: #FFFBEB;
      border: 1px solid #FDE68A;
      border-radius: 16px;
      padding: 14px;
    }

    @media(max-width: 900px) {
      .ap-grid {
        grid-template-columns: 1fr;
      }
      .ap-form-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
<div class="od-layout">
  <?php include __DIR__ . '/../LionTech_Owner_Dashboard/liontech_owner_dashboard/Sidebar.php'; ?>

  <main class="od-main">
    <header class="od-topbar">
      <div class="od-business-title">
        <h1>Ajouter un produit</h1>
        <p>Add a product to your inventory for <?= e($business['business_name'] ?? 'your business') ?>.</p>
      </div>

      <div class="od-top-actions">
        <a class="od-primary" href="<?= APP_URL ?>/Produit/products.php" style="text-decoration:none">
          ← Retour produits
        </a>
        <div class="od-avatar"><?= e($initials) ?></div>
      </div>
    </header>

    <?php if ($message): ?>
    <div class="pr-alert <?= $messageType === 'success' ? 'success' : 'error' ?>" style="margin:0 24px 18px">
      <?= $messageType === 'success' ? '✅' : '⚠️' ?> <?= e($message) ?>
    </div>
    <?php endif; ?>

    <div class="ap-wrap">
      <div class="ap-grid">

        <section class="ap-panel">
          <div class="ap-panel-header">
            <h2>Informations du produit</h2>
            <p>
              <strong>Remplissez les informations principales du produit.</strong><br>
              <em>Fill in the main product information.</em>
            </p>
          </div>

          <form class="ap-form" method="POST" enctype="multipart/form-data" id="addProductPageForm">
            <div class="ap-form-grid">

              <div class="ap-field ap-full">
                <label>
                  Nom du produit *
                  <em>Product name *</em>
                </label>
                <input
                  type="text"
                  name="product_name"
                  id="product_name"
                  value="<?= e($old['product_name']) ?>"
                  placeholder="Ex: Coca-Cola, Savon, Riz parfumé..."
                  required
                />
              </div>

              <div class="ap-field">
                <label>
                  Type de produit *
                  <em>Product type *</em>
                </label>
                <select name="product_type" id="product_type" required>
                  <option value="">Sélectionner...</option>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?= e($cat) ?>" <?= $old['product_type'] === $cat ? 'selected' : '' ?>>
                      <?= e($cat) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="ap-field">
                <label>
                  Numéro UPC
                  <em>UPC number</em>
                </label>
                <input
                  type="text"
                  name="upc_number"
                  id="upc_number"
                  value="<?= e($old['upc_number']) ?>"
                  placeholder="Ex: 012345678905"
                />
                <div class="ap-help">
                  <strong>FR:</strong> Le numéro UPC est le code-barres du produit. Il aide à identifier rapidement un article avec un scanner ou une recherche.<br>
                  <strong>EN:</strong> The UPC number is the product barcode. It helps identify an item quickly with a scanner or search.
                </div>
              </div>

              <div class="ap-field">
                <label>
                  Département
                  <em>Department, if any</em>
                </label>
                <select name="department" id="department">
                  <?php foreach ($departments as $dep): ?>
                    <option value="<?= e($dep) ?>" <?= $old['department'] === $dep ? 'selected' : '' ?>>
                      <?= $dep === '' ? 'Aucun / None' : e($dep) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="ap-field">
                <label>
                  Couleur
                  <em>Color, optional</em>
                </label>
                <input
                  type="text"
                  name="color"
                  id="color"
                  value="<?= e($old['color']) ?>"
                  placeholder="Ex: Rouge, Noir, Bleu..."
                />
              </div>

              <div class="ap-field">
                <label>
                  Quantité / Compte *
                  <em>Count / quantity *</em>
                </label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  name="quantity"
                  id="quantity"
                  value="<?= e($old['quantity']) ?>"
                  required
                />
              </div>

              <div class="ap-field">
                <label>
                  Unité *
                  <em>Unit *</em>
                </label>
                <select name="unit" id="unit" required>
                  <?php foreach ($units as $unit): ?>
                    <option value="<?= e($unit) ?>" <?= $old['unit'] === $unit ? 'selected' : '' ?>>
                      <?= e($unit) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="ap-field">
                <label>
                  Prix unitaire
                  <em>Unit price</em>
                </label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  name="unit_price"
                  id="unit_price"
                  value="<?= e($old['unit_price']) ?>"
                />
              </div>

              <div class="ap-field">
                <label>
                  Seuil stock faible
                  <em>Low stock alert level</em>
                </label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  name="low_stock_level"
                  id="low_stock_level"
                  value="<?= e($old['low_stock_level']) ?>"
                />
              </div>

              <div class="ap-field">
                <label>
                  Date d'expiration
                  <em>Expiration date</em>
                </label>
                <input
                  type="date"
                  name="expiration_date"
                  id="expiration_date"
                  value="<?= e($old['expiration_date']) ?>"
                />
              </div>

              <div class="ap-field">
                <label>
                  Fournisseur
                  <em>Supplier</em>
                </label>
                <input
                  type="text"
                  name="supplier"
                  id="supplier"
                  value="<?= e($old['supplier']) ?>"
                  placeholder="Ex: Brasserie, grossiste, marché..."
                />
              </div>

              <div class="ap-field ap-full">
                <label>
                  Image du produit
                  <em>Product image</em>
                </label>
                <input
                  type="file"
                  name="product_image"
                  id="product_image"
                  accept="image/png,image/jpeg,image/webp"
                />
                <div class="ap-help">
                  Image optionnelle. Maximum 2MB. / Optional image. Max 2MB.
                </div>
              </div>

              <div class="ap-field ap-full">
                <label>
                  Description / Notes
                  <em>Description / notes</em>
                </label>
                <textarea
                  name="description"
                  id="description"
                  rows="4"
                  placeholder="Notes optionnelles..."
                ><?= e($old['description']) ?></textarea>
              </div>
            </div>

            <div class="ap-actions">
              <a class="pr-secondary" href="<?= APP_URL ?>/Produit/products.php" style="text-decoration:none">
                Annuler / Cancel
              </a>
              <button type="submit" class="pr-primary">
                ✅ Sauvegarder produit / Save product
              </button>
            </div>
          </form>
        </section>

        <aside class="ap-panel">
          <div class="ap-panel-header">
            <h2>Aperçu</h2>
            <p>Quick product summary.</p>
          </div>

          <div class="ap-side">
            <div class="ap-preview-box">
              <div class="ap-preview-row">
                <span>Produit</span>
                <strong id="pv-name">—</strong>
              </div>
              <div class="ap-preview-row">
                <span>Type</span>
                <strong id="pv-type">—</strong>
              </div>
              <div class="ap-preview-row">
                <span>UPC</span>
                <strong id="pv-upc">—</strong>
              </div>
              <div class="ap-preview-row">
                <span>Département</span>
                <strong id="pv-department">—</strong>
              </div>
              <div class="ap-preview-row">
                <span>Couleur</span>
                <strong id="pv-color">—</strong>
              </div>
              <div class="ap-preview-row">
                <span>Quantité</span>
                <strong id="pv-quantity">0</strong>
              </div>
              <div class="ap-preview-row">
                <span>Prix</span>
                <strong id="pv-price">0 XAF</strong>
              </div>
            </div>

            <div class="ap-tip">
              <strong>Conseil / Tip</strong><br>
              Pour les petits commerces, commencez simple: nom, type, quantité, prix et seuil stock faible.
              Le UPC est utile si le produit a un code-barres.
              <br><br>
              For small businesses, start simple: name, type, quantity, price and low stock level.
              UPC is useful when the product has a barcode.
            </div>
          </div>
        </aside>

      </div>
    </div>
  </main>
</div>

<script>
(function(){
  const map = {
    product_name: 'pv-name',
    product_type: 'pv-type',
    upc_number: 'pv-upc',
    department: 'pv-department',
    color: 'pv-color',
    quantity: 'pv-quantity',
    unit_price: 'pv-price'
  };

  function updatePreview() {
    Object.entries(map).forEach(([fieldName, previewId]) => {
      const field = document.querySelector(`[name="${fieldName}"]`);
      const preview = document.getElementById(previewId);

      if (!field || !preview) return;

      let value = field.value || '—';

      if (fieldName === 'unit_price') {
        value = field.value ? Number(field.value).toLocaleString() + ' XAF' : '0 XAF';
      }

      preview.textContent = value;
    });
  }

  Object.keys(map).forEach(fieldName => {
    const field = document.querySelector(`[name="${fieldName}"]`);
    if (!field) return;

    field.addEventListener('input', updatePreview);
    field.addEventListener('change', updatePreview);
  });

  const form = document.getElementById('addProductPageForm');

  if (form) {
    form.addEventListener('submit', function(e){
      const name = document.getElementById('product_name')?.value.trim();
      const type = document.getElementById('product_type')?.value.trim();

      if (!name || !type) {
        e.preventDefault();
        alert('Veuillez remplir le nom et le type du produit. / Please fill the product name and type.');
        return false;
      }
    });
  }

  updatePreview();
})();
</script>
</body>
</html>
