<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$product = [
    'id' => 0, 'name' => '', 'brand' => '', 'model' => '',
    'condition_text' => '', 'description' => '', 'status' => 'available',
];
$images = [];
$error = '';

if ($id) {
    $stmt = getDb()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) {
        $product = $found;
        $imgStmt = getDb()->prepare('SELECT id, image_path FROM product_images WHERE product_id = ? ORDER BY sort_order, id');
        $imgStmt->execute([$id]);
        $images = $imgStmt->fetchAll();
    } else {
        $id = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();

    $product['name'] = trim($_POST['name'] ?? '');
    $product['brand'] = trim($_POST['brand'] ?? '');
    $product['model'] = trim($_POST['model'] ?? '');
    $product['condition_text'] = trim($_POST['condition_text'] ?? '');
    $product['description'] = trim($_POST['description'] ?? '');
    $product['status'] = in_array($_POST['status'] ?? '', ['available', 'sold', 'hidden'], true)
        ? $_POST['status'] : 'available';

    if ($product['name'] === '') {
        $error = 'กรุณากรอกชื่อสินค้า';
    }

    if ($error === '') {
        try {
            $newImagePaths = handleMultipleImageUploads('images');

            $db = getDb();

            if ($id) {
                $stmt = $db->prepare(
                    'UPDATE products SET name=?, brand=?, model=?, condition_text=?, description=?, status=? WHERE id=?'
                );
                $stmt->execute([
                    $product['name'], $product['brand'], $product['model'],
                    $product['condition_text'], $product['description'], $product['status'], $id,
                ]);
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO products (name, brand, model, condition_text, description, status)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $product['name'], $product['brand'], $product['model'],
                    $product['condition_text'], $product['description'], $product['status'],
                ]);
                $id = (int) $db->lastInsertId();
            }

            // ลบรูปที่ผู้ใช้เลือกลบ (เฉพาะรูปที่เป็นของสินค้านี้จริง ๆ)
            $removeIds = array_map('intval', $_POST['remove_images'] ?? []);
            if ($removeIds) {
                $placeholders = implode(',', array_fill(0, count($removeIds), '?'));
                $sel = $db->prepare("SELECT id, image_path FROM product_images WHERE product_id = ? AND id IN ($placeholders)");
                $sel->execute([$id, ...$removeIds]);
                $toDelete = $sel->fetchAll();

                $del = $db->prepare('DELETE FROM product_images WHERE id = ?');
                foreach ($toDelete as $row) {
                    $del->execute([$row['id']]);
                    $filePath = __DIR__ . '/../' . $row['image_path'];
                    if (is_file($filePath)) {
                        unlink($filePath);
                    }
                }
            }

            // เพิ่มรูปใหม่ที่อัปโหลดเข้ามา
            if ($newImagePaths) {
                $maxOrderStmt = $db->prepare('SELECT COALESCE(MAX(sort_order), -1) FROM product_images WHERE product_id = ?');
                $maxOrderStmt->execute([$id]);
                $maxOrder = (int) $maxOrderStmt->fetchColumn();
                $ins = $db->prepare('INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)');
                foreach ($newImagePaths as $path) {
                    $maxOrder++;
                    $ins->execute([$id, $path, $maxOrder]);
                }
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

    <div class="form-field">
      <label for="condition_text">สภาพเครื่อง</label>
      <input type="text" id="condition_text" name="condition_text" placeholder="เช่น สภาพดี 90%" value="<?= h($product['condition_text']) ?>">
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

    <?php if ($images): ?>
      <div class="form-field">
        <label>รูปที่มีอยู่ — ติ๊กเพื่อลบ</label>
        <div style="display:flex;flex-wrap:wrap;gap:14px;">
          <?php foreach ($images as $img): ?>
            <label style="text-align:center;font-size:12px;cursor:pointer;">
              <img src="../<?= h($img['image_path']) ?>" alt="" style="width:96px;height:96px;object-fit:cover;border-radius:10px;display:block;margin-bottom:6px;">
              <input type="checkbox" name="remove_images[]" value="<?= (int) $img['id'] ?>"> ลบรูปนี้
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="form-field">
      <label for="images">เพิ่มรูปสินค้า (เลือกได้หลายรูป — JPG/PNG/WEBP ไม่เกิน 5MB ต่อรูป)</label>
      <input type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
    </div>

    <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
  </form>
</div>

<?php require __DIR__ . '/_layout_foot.php'; ?>
