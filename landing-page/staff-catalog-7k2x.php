<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$stmt = getDb()->query(
    "SELECT p.*,
            (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.sort_order, pi.id LIMIT 1) AS cover_image
     FROM products p
     ORDER BY p.created_at DESC"
);
$products = $stmt->fetchAll();

function formatPrice($price): string {
    if ($price === null || $price === '') {
        return 'ไม่ระบุราคา';
    }
    return number_format((float) $price, 0) . ' บาท';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>แคตตาล็อกสินค้า (สำหรับพนักงาน) | AMN SURE</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --green-dark:#173B2E; --green:#3F7A5C; --green-mid:#6FA989;
    --green-soft:#BFDCCA; --green-mist:#EEF6F1; --white:#FFFFFF;
    --text:#33443C; --text-light:#6B7C74;
  }
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'Prompt',sans-serif;background:var(--green-mist);color:var(--text);line-height:1.6;}
  .wrap{max-width:1100px;margin:0 auto;padding:36px 24px 80px;}
  h1{font-size:22px;color:var(--green-dark);margin-bottom:4px;}
  .sub{color:var(--text-light);font-size:13.5px;margin-bottom:28px;}
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px;}
  .card{background:var(--white);border-radius:16px;overflow:hidden;box-shadow:0 10px 30px -18px rgba(23,59,46,.25);}
  .cover{aspect-ratio:4/3;background:var(--green-mist);display:flex;align-items:center;justify-content:center;overflow:hidden;}
  .cover img{width:100%;height:100%;object-fit:contain;}
  .body{padding:16px 18px;}
  .badge{display:inline-block;font-size:11px;font-weight:600;padding:3px 10px;border-radius:999px;margin-bottom:8px;}
  .badge-available{background:var(--green-mist);color:var(--green-dark);}
  .badge-sold{background:#fdf0f0;color:#a33;}
  .badge-hidden{background:#eee;color:#777;}
  h3{font-size:16px;color:var(--green-dark);margin-bottom:2px;}
  .meta{font-size:13px;color:var(--text-light);margin-bottom:6px;}
  .price{font-size:15px;font-weight:600;color:var(--green-dark);margin-top:8px;}
  .thumbs{display:flex;gap:6px;margin-top:10px;flex-wrap:wrap;}
  .thumbs img{width:40px;height:40px;object-fit:cover;border-radius:6px;}
  .empty{color:var(--text-light);padding:40px 0;text-align:center;}
</style>
</head>
<body>
<div class="wrap">
  <h1>แคตตาล็อกสินค้า — สำหรับพนักงาน</h1>
  <p class="sub">แสดงสินค้าทุกชิ้นในสต็อก รวมรายการที่ซ่อนจากหน้าเว็บสาธารณะ · ลิงก์นี้ไม่เผยแพร่สาธารณะ กรุณาอย่าส่งต่อ</p>

  <?php if (empty($products)): ?>
    <p class="empty">ยังไม่มีสินค้าในระบบ</p>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($products as $p): ?>
        <?php
          $imgStmt = getDb()->prepare('SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order, id');
          $imgStmt->execute([$p['id']]);
          $allImages = $imgStmt->fetchAll();
        ?>
        <div class="card">
          <div class="cover">
            <?php if ($p['cover_image']): ?>
              <img src="<?= h($p['cover_image']) ?>" alt="">
            <?php else: ?>
              <img src="logo.png" alt="" style="width:35%;opacity:.4;">
            <?php endif; ?>
          </div>
          <div class="body">
            <span class="badge badge-<?= h($p['status']) ?>"><?= h(statusLabel($p['status'])) ?></span>
            <h3><?= h($p['name']) ?></h3>
            <p class="meta"><?= h(trim(($p['brand'] ?? '') . ' ' . ($p['model'] ?? ''))) ?: '&nbsp;' ?></p>
            <?php if ($p['condition_text']): ?>
              <p class="meta">สภาพ: <?= h($p['condition_text']) ?></p>
            <?php endif; ?>
            <p class="price"><?= h(formatPrice($p['price'] ?? null)) ?></p>
            <?php if (count($allImages) > 1): ?>
              <div class="thumbs">
                <?php foreach ($allImages as $img): ?>
                  <img src="<?= h($img['image_path']) ?>" alt="">
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
