<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = getDb()->prepare("SELECT * FROM products WHERE id = ? AND status != 'hidden'");
$stmt->execute([$id]);
$product = $stmt->fetch();

$images = [];
if ($product) {
    $imgStmt = getDb()->prepare('SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order, id');
    $imgStmt->execute([$id]);
    $images = $imgStmt->fetchAll();
}

if (!$product) {
    http_response_code(404);
    $pageTitle = 'ไม่พบสินค้า | AMN SURE';
    require __DIR__ . '/includes/site-header.php';
    echo '<div class="container" style="padding:80px 0;text-align:center;">';
    echo '<h1>ไม่พบรายการสินค้านี้</h1>';
    echo '<p style="margin:16px 0 28px;">สินค้าอาจถูกลบหรือขายไปแล้ว</p>';
    echo '<a href="used-equipment.php" class="btn btn-primary">กลับไปหน้ารายการสินค้า</a>';
    echo '</div>';
    require __DIR__ . '/includes/site-footer.php';
    exit;
}

$pageTitle = h($product['name']) . ' | AMN SURE';
$pageDescription = h(mb_substr((string) $product['description'], 0, 150));
require __DIR__ . '/includes/site-header.php';
?>

<section class="container" style="padding:56px 0 90px;">
  <a href="used-equipment.php" style="font-size:14px;color:var(--green);display:inline-block;margin-bottom:24px;">&larr; กลับไปหน้ารายการสินค้า</a>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:50px;align-items:start;">
    <div>
      <div id="mainImageBox" style="aspect-ratio:4/3;background:var(--green-mist);border-radius:20px;overflow:hidden;display:flex;align-items:center;justify-content:center;margin-bottom:14px;">
        <?php if ($images): ?>
          <img id="mainImage" src="<?= h($images[0]['image_path']) ?>" alt="<?= h($product['name']) ?>" style="width:100%;height:100%;object-fit:cover;">
        <?php else: ?>
          <img src="logo.png" alt="" style="width:35%;opacity:.5;">
        <?php endif; ?>
      </div>
      <?php if (count($images) > 1): ?>
        <div id="thumbRow" style="display:flex;gap:10px;flex-wrap:wrap;">
          <?php foreach ($images as $i => $img): ?>
            <img src="<?= h($img['image_path']) ?>" alt="" class="thumb-item" onclick="selectImage(this)"
                 style="width:70px;height:70px;object-fit:cover;border-radius:10px;cursor:pointer;border:2px solid <?= $i === 0 ? 'var(--green)' : 'transparent' ?>;">
          <?php endforeach; ?>
        </div>
        <script>
          function selectImage(el) {
            document.getElementById('mainImage').src = el.src;
            document.querySelectorAll('#thumbRow .thumb-item').forEach(function (t) {
              t.style.borderColor = 'transparent';
            });
            el.style.borderColor = 'var(--green)';
          }
        </script>
      <?php endif; ?>
    </div>

    <div>
      <?php if ($product['status'] === 'sold'): ?>
        <span style="display:inline-block;font-size:12px;font-weight:600;color:#a33;background:#fdf0f0;padding:4px 12px;border-radius:999px;margin-bottom:12px;">ขายแล้ว</span>
      <?php endif; ?>
      <h1 style="margin-top:0;"><?= h($product['name']) ?></h1>
      <p style="font-size:15px;margin-bottom:24px;">
        <?= h(trim(($product['brand'] ?? '') . ' ' . ($product['model'] ?? ''))) ?>
        <?php if ($product['condition_text']): ?> · สภาพ: <?= h($product['condition_text']) ?><?php endif; ?>
      </p>

      <?php if ($product['description']): ?>
        <p style="white-space:pre-line;margin-bottom:30px;"><?= h($product['description']) ?></p>
      <?php endif; ?>

      <div style="display:flex;gap:14px;flex-wrap:wrap;">
        <a href="tel:0812354316" class="btn btn-primary">โทรสอบถาม 081-235-4316</a>
        <a href="mailto:sure@amnsure.co.th?subject=<?= rawurlencode('สอบถามสินค้า: ' . $product['name']) ?>" class="btn btn-outline">ส่งอีเมลสอบถาม</a>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/site-footer.php'; ?>
