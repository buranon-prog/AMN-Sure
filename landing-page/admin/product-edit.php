<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$product = [
    'id' => 0, 'name' => '', 'brand' => '', 'model' => '', 'price' => '',
    'condition_text' => '', 'description' => '', 'image_path' => null, 'status' => 'available',
];
$error = '';

if ($id) {
    $stmt = getDb()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) {
        $product = $found;
    } else {
        $id = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();

    $product['name'] = trim($_POST['name'] ?? '');
    $product['brand'] = trim($_POST['brand'] ?? '');
    $product['model'] = trim($_POST['model'] ?? '');
    $product['price'] = trim($_POST['price'] ?? '');
    $product['condition_text'] = trim($_POST['condition_text'] ?? '');
    $product['description'] = trim($_POST['description'] ?? '');
    $product['status'] = in_array($_POST['status'] ?? '', ['available', 'sold', 'hidden'], true)
        ? $_POST['status'] : 'available';

    if ($product['name'] === '') {
        $error = 'กรุณากรอกชื่อสินค้า';
    } elseif ($product['price'] !== '' && !is_numeric($product['price'])) {
        $error = 'ราคาต้องเป็นตัวเลขเท่านั้น';
    }

    if ($error === '') {
        try {
            $imagePath = $product['image_path'];
            $uploaded = handleImageUpload('image');
            if ($uploaded) {
                $imagePath = $uploaded;
            }

            $priceValue = $product['price'] === '' ? null : (float) $product['price'];

            if ($id) {
                $stmt = getDb()->prepare(
                    'UPDATE products SET name=?, brand=?, model=?, price=?, condition_text=?, description=?, image_path=?, status=? WHERE id=?'
                );
                $stmt->execute([
                    $product['name'], $product['brand'], $product['model'], $priceValue,
                    $product['condition_text'], $product['description'], $imagePath, $product['status'], $id,
                ]);
            } else {
                $stmt = getDb()->prepare(
                    'INSERT INTO products (name, brand, model, price, condition_text, description, image_path, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $product['name'], $product['brand'], $product['model'], $priceValue,
                    $product['condition_text'], $product['description'], $imagePath, $product['status'],
                ]);
            }

            header('Location: index.php?saved=1');
            exit;
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }
}

$pageTitle = $id ? 'แก้ไขสินค้า' : 'เพิ่มสินค้าใหม่';
require __DIR__ . '/_layout_head.php';
?>

<div class="topbar">
  <h1><?= h($pageTitle) ?></h1>
  <nav><a href="index.php">&larr; กลับไปรายการสินค้า</a></nav>
</div>

<div class="card" style="max-width:640px;">
  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int) $id ?>">

    <div class="form-field">
      <label for="name">ชื่อสินค้า *</label>
      <input type="text" id="name" name="name" required value="<?= h($product['name']) ?>">
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
      <div class="form-field">
        <label for="brand">ยี่ห้อ</label>
        <input type="text" id="brand" name="brand" value="<?= h($product['brand']) ?>">
      </div>
      <div class="form-field">
        <label for="model">รุ่น</label>
        <input type="text" id="model" name="model" value="<?= h($product['model']) ?>">
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
      <div class="form-field">
        <label for="price">ราคา (บาท) — เว้นว่างถ้าให้ติดต่อสอบถามราคา</label>
        <input type="number" step="0.01" min="0" id="price" name="price" value="<?= h((string) $product['price']) ?>">
      </div>
      <div class="form-field">
        <label for="condition_text">สภาพเครื่อง</label>
        <input type="text" id="condition_text" name="condition_text" placeholder="เช่น สภาพดี 90%" value="<?= h($product['condition_text']) ?>">
      </div>
    </div>

    <div class="form-field">
      <label for="description">รายละเอียดสินค้า</label>
      <textarea id="description" name="description"><?= h($product['description']) ?></textarea>
    </div>

    <div class="form-field">
      <label for="status">สถานะ</label>
      <select id="status" name="status">
        <option value="available" <?= $product['status'] === 'available' ? 'selected' : '' ?>>พร้อมขาย</option>
        <option value="sold" <?= $product['status'] === 'sold' ? 'selected' : '' ?>>ขายแล้ว</option>
        <option value="hidden" <?= $product['status'] === 'hidden' ? 'selected' : '' ?>>ซ่อน (ไม่แสดงบนเว็บ)</option>
      </select>
    </div>

    <div class="form-field">
      <label for="image">รูปสินค้า (JPG/PNG/WEBP ไม่เกิน 5MB)</label>
      <?php if ($product['image_path']): ?>
        <img src="../<?= h($product['image_path']) ?>" alt="" style="width:120px;height:120px;object-fit:cover;border-radius:10px;margin-bottom:10px;display:block;">
      <?php endif; ?>
      <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
    </div>

    <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
  </form>
</div>

<?php require __DIR__ . '/_layout_foot.php'; ?>
