<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$stmt = getDb()->query(
    "SELECT p.id, p.name, p.brand, p.model, p.condition_text, p.status,
            (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.sort_order, pi.id LIMIT 1) AS cover_image
     FROM products p
     WHERE p.status != 'hidden'
     ORDER BY p.created_at DESC"
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
    <div style="text-align:center;padding:70px 20px;background:var(--green-mist);border-radius:20px;">
      <p style="font-size:18px;font-weight:600;color:var(--green-dark);margin-bottom:8px;">ไม่มีสินค้า</p>
      <p style="font-size:14px;">ขณะนี้ยังไม่มีรายการเครื่องมือแพทย์มือสอง กรุณากลับมาดูใหม่อีกครั้ง</p>
    </div>
  <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:28px;">
      <?php foreach ($products as $p): ?>
        <a href="product.php?id=<?= (int) $p['id'] ?>" style="display:block;border:1px solid rgba(23,59,46,.1);border-radius:20px;overflow:hidden;background:var(--white);transition:.3s ease;">
          <div style="aspect-ratio:4/3;background:var(--green-mist);display:flex;align-items:center;justify-content:center;overflow:hidden;">
            <?php if ($p['cover_image']): ?>
              <img src="<?= h($p['cover_image']) ?>" alt="<?= h($p['name']) ?>" style="width:100%;height:100%;object-fit:contain;">
            <?php else: ?>
              <img src="logo.png" alt="" style="width:40%;opacity:.5;">
            <?php endif; ?>
          </div>
          <div style="padding:18px 20px;">
            <?php if ($p['status'] === 'sold'): ?>
              <span style="display:inline-block;font-size:11.5px;font-weight:600;color:#a33;background:#fdf0f0;padding:3px 10px;border-radius:999px;margin-bottom:8px;">ขายแล้ว</span>
            <?php endif; ?>
            <h3 style="font-size:17px;margin-bottom:4px;"><?= h($p['name']) ?></h3>
            <p style="font-size:13.5px;margin-bottom:4px;">
              <?= h(trim(($p['brand'] ?? '') . ' ' . ($p['model'] ?? ''))) ?: '&nbsp;' ?>
            </p>
            <?php if ($p['condition_text']): ?>
              <p style="font-size:13px;color:var(--green);">สภาพ: <?= h($p['condition_text']) ?></p>
            <?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/site-footer.php'; ?>
