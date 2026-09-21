<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$stmt = getDb()->query(
    "SELECT id, name, brand, model, price, condition_text, image_path, status
     FROM products
     WHERE status != 'hidden'
     ORDER BY created_at DESC"
);
$products = $stmt->fetchAll();

$pageTitle = 'เครื่องมือแพทย์มือสอง | AMN SURE';
$pageDescription = 'รายการเครื่องมือแพทย์มือสองคุณภาพ ผ่านการตรวจสอบมาตรฐานจาก AMN SURE';
require __DIR__ . '/includes/site-header.php';
?>

<section class="container" style="padding:64px 0 90px;">
  <span class="eyebrow">Pre-owned Equipment</span>
  <h1>เครื่องมือแพทย์มือสอง</h1>
  <p style="max-width:620px;margin-bottom:40px;">รายการเครื่องมือแพทย์มือสองที่ผ่านการตรวจสอบคุณภาพและมาตรฐานจากทีมงาน AMN SURE สนใจรายการไหนติดต่อสอบถามได้ทันที</p>

  <?php if (empty($products)): ?>
    <p style="padding:40px 0;">ยังไม่มีรายการสินค้าในขณะนี้ กรุณากลับมาดูใหม่อีกครั้ง</p>
  <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:28px;">
      <?php foreach ($products as $p): ?>
        <a href="product.php?id=<?= (int) $p['id'] ?>" style="display:block;border:1px solid rgba(23,59,46,.1);border-radius:20px;overflow:hidden;background:var(--white);transition:.3s ease;">
          <div style="aspect-ratio:4/3;background:var(--green-mist);display:flex;align-items:center;justify-content:center;overflow:hidden;">
            <?php if ($p['image_path']): ?>
              <img src="<?= h($p['image_path']) ?>" alt="<?= h($p['name']) ?>" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
              <img src="logo.png" alt="" style="width:40%;opacity:.5;">
            <?php endif; ?>
          </div>
          <div style="padding:18px 20px;">
            <?php if ($p['status'] === 'sold'): ?>
              <span style="display:inline-block;font-size:11.5px;font-weight:600;color:#a33;background:#fdf0f0;padding:3px 10px;border-radius:999px;margin-bottom:8px;">ขายแล้ว</span>
            <?php endif; ?>
            <h3 style="font-size:17px;margin-bottom:4px;"><?= h($p['name']) ?></h3>
            <p style="font-size:13.5px;margin-bottom:10px;">
              <?= h(trim(($p['brand'] ?? '') . ' ' . ($p['model'] ?? ''))) ?: '&nbsp;' ?>
            </p>
            <p style="font-weight:600;color:var(--green-dark);font-size:15px;"><?= h(formatPrice($p['price'])) ?></p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/site-footer.php'; ?>
