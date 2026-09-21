<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();

$stmt = getDb()->query(
    'SELECT p.id, p.name, p.brand, p.status,
            (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.sort_order, pi.id LIMIT 1) AS cover_image
     FROM products p
     ORDER BY p.created_at DESC'
);
$products = $stmt->fetchAll();

$pageTitle = 'จัดการสินค้า';
require __DIR__ . '/_layout_head.php';
?>

<div class="topbar">
  <h1>จัดการสินค้ามือสอง</h1>
  <nav>
    <a href="../used-equipment.php" target="_blank">ดูหน้าเว็บจริง &rarr;</a>
    <a href="logout.php">ออกจากระบบ</a>
  </nav>
</div>

<?php if (!empty($_GET['saved'])): ?>
  <div class="alert alert-success">บันทึกข้อมูลเรียบร้อยแล้ว</div>
<?php endif; ?>
<?php if (!empty($_GET['deleted'])): ?>
  <div class="alert alert-success">ลบรายการเรียบร้อยแล้ว</div>
<?php endif; ?>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
    <strong>รายการสินค้าทั้งหมด (<?= count($products) ?>)</strong>
    <a href="product-edit.php" class="btn btn-primary btn-sm">+ เพิ่มสินค้าใหม่</a>
  </div>

  <?php if (empty($products)): ?>
    <p style="color:var(--text-light);">ยังไม่มีสินค้าในระบบ กด "เพิ่มสินค้าใหม่" เพื่อเริ่มต้น</p>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th></th>
          <th>ชื่อสินค้า</th>
          <th>สถานะ</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($products as $p): ?>
          <tr>
            <td>
              <img class="thumb" src="<?= h($p['cover_image'] ?: '../logo.png') ?>" alt="">
            </td>
            <td>
              <?= h($p['name']) ?><br>
              <span style="color:var(--text-light);font-size:12.5px;"><?= h($p['brand'] ?? '') ?></span>
            </td>
            <td><span class="badge badge-<?= h($p['status']) ?>"><?= h(statusLabel($p['status'])) ?></span></td>
            <td>
              <div class="row-actions">
                <a href="product-edit.php?id=<?= (int) $p['id'] ?>" class="btn btn-outline btn-sm">แก้ไข</a>
                <form method="post" action="delete.php" onsubmit="return confirm('ยืนยันลบสินค้านี้?');">
                  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">ลบ</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_foot.php'; ?>
