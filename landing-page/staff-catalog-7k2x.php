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

$catalogData = [];
$productsWithImages = [];
foreach ($products as $p) {
    $imgStmt = getDb()->prepare('SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order, id');
    $imgStmt->execute([$p['id']]);
    $allImages = array_column($imgStmt->fetchAll(), 'image_path');

    $p['_images'] = $allImages;
    $catalogData[] = [
        'name' => $p['name'],
        'brand' => $p['brand'],
        'model' => $p['model'],
        'condition_text' => $p['condition_text'],
        'description' => $p['description'],
        'status' => $p['status'],
        'statusLabel' => statusLabel($p['status']),
        'price' => formatPrice($p['price'] ?? null),
        'images' => $allImages,
    ];
    $productsWithImages[] = $p;
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
  .card{background:var(--white);border-radius:16px;overflow:hidden;box-shadow:0 10px 30px -18px rgba(23,59,46,.25);cursor:pointer;transition:.2s ease;}
  .card:hover{transform:translateY(-4px);box-shadow:0 16px 36px -18px rgba(23,59,46,.35);}
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

  /* ---- detail modal ---- */
  .modal-overlay{display:none;position:fixed;inset:0;background:rgba(10,10,10,.65);z-index:200;align-items:center;justify-content:center;padding:20px;}
  .modal-overlay.open{display:flex;}
  .modal-box{background:var(--white);border-radius:20px;max-width:900px;width:100%;max-height:90vh;overflow-y:auto;position:relative;}
  .modal-close{position:absolute;top:14px;right:14px;width:34px;height:34px;border-radius:50%;background:var(--green-mist);color:var(--green-dark);border:none;font-size:18px;cursor:pointer;z-index:2;display:flex;align-items:center;justify-content:center;}
  .modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:0;}
  .modal-image-col{background:var(--green-mist);padding:24px;position:relative;}
  .modal-main-img{width:100%;aspect-ratio:4/3;object-fit:contain;background:var(--white);border-radius:12px;}
  .modal-nav{position:absolute;top:50%;transform:translateY(-50%);width:36px;height:36px;border-radius:50%;border:none;background:rgba(255,255,255,.9);color:var(--green-dark);font-size:16px;cursor:pointer;box-shadow:0 4px 12px rgba(0,0,0,.15);}
  .modal-nav.prev{left:34px;}
  .modal-nav.next{right:34px;}
  .modal-counter{text-align:center;font-size:12.5px;color:var(--text-light);margin-top:10px;}
  .modal-thumbs{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;}
  .modal-thumbs img{width:52px;height:52px;object-fit:cover;border-radius:8px;cursor:pointer;border:2px solid transparent;}
  .modal-thumbs img.active{border-color:var(--green);}
  .modal-download{display:inline-flex;align-items:center;gap:8px;margin-top:14px;padding:10px 20px;border-radius:999px;background:linear-gradient(120deg,var(--green-dark),var(--green));color:var(--white);text-decoration:none;font-size:13.5px;font-weight:500;}
  .modal-body-col{padding:28px 30px;}
  .modal-body-col h2{font-size:20px;color:var(--green-dark);margin-bottom:6px;}
  .modal-body-col .meta{font-size:14px;margin-bottom:6px;}
  .modal-desc{margin-top:16px;font-size:14px;white-space:pre-line;color:var(--text);}
  @media(max-width:720px){
    .modal-grid{grid-template-columns:1fr;}
    .modal-nav.prev{left:14px;}
    .modal-nav.next{right:14px;}
  }
</style>
</head>
<body>
<div class="wrap">
  <h1>แคตตาล็อกสินค้า — สำหรับพนักงาน</h1>
  <p class="sub">แสดงสินค้าทุกชิ้นในสต็อก รวมรายการที่ซ่อนจากหน้าเว็บสาธารณะ · คลิกที่สินค้าเพื่อดูรูปและรายละเอียดเพิ่มเติม · ลิงก์นี้ไม่เผยแพร่สาธารณะ กรุณาอย่าส่งต่อ</p>

  <?php if (empty($products)): ?>
    <p class="empty">ยังไม่มีสินค้าในระบบ</p>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($productsWithImages as $i => $p): ?>
        <div class="card" onclick="openDetail(<?= (int) $i ?>)">
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
            <?php if (count($p['_images']) > 1): ?>
              <div class="thumbs">
                <?php foreach ($p['_images'] as $img): ?>
                  <img src="<?= h($img) ?>" alt="">
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="modal-overlay" id="modalOverlay">
  <div class="modal-box">
    <button type="button" class="modal-close" onclick="closeDetail()">&times;</button>
    <div class="modal-grid">
      <div class="modal-image-col">
        <img id="modalMainImg" class="modal-main-img" src="" alt="">
        <button type="button" class="modal-nav prev" onclick="modalStep(-1)" id="modalPrev">&larr;</button>
        <button type="button" class="modal-nav next" onclick="modalStep(1)" id="modalNext">&rarr;</button>
        <p class="modal-counter" id="modalCounter"></p>
        <div class="modal-thumbs" id="modalThumbs"></div>
        <a id="modalDownload" class="modal-download" href="#" download>&darr; ดาวน์โหลดรูปนี้</a>
      </div>
      <div class="modal-body-col">
        <span class="badge" id="modalBadge"></span>
        <h2 id="modalName"></h2>
        <p class="meta" id="modalBrandModel"></p>
        <p class="meta" id="modalCondition"></p>
        <p class="price" id="modalPrice"></p>
        <p class="modal-desc" id="modalDesc"></p>
      </div>
    </div>
  </div>
</div>

<script>
  var catalogData = <?= json_encode($catalogData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  var currentProduct = null;
  var currentImageIndex = 0;

  function openDetail(index) {
    currentProduct = catalogData[index];
    currentImageIndex = 0;
    renderModal();
    document.getElementById('modalOverlay').classList.add('open');
  }

  function closeDetail() {
    document.getElementById('modalOverlay').classList.remove('open');
  }

  function modalStep(step) {
    if (!currentProduct || !currentProduct.images.length) { return; }
    currentImageIndex = (currentImageIndex + step + currentProduct.images.length) % currentProduct.images.length;
    renderModal();
  }

  function selectImage(i) {
    currentImageIndex = i;
    renderModal();
  }

  var badgeClassMap = { available: 'badge-available', sold: 'badge-sold', hidden: 'badge-hidden' };

  function renderModal() {
    if (!currentProduct) { return; }
    var images = currentProduct.images;
    var hasImages = images.length > 0;
    var mainSrc = hasImages ? images[currentImageIndex] : 'logo.png';

    document.getElementById('modalMainImg').src = mainSrc;
    document.getElementById('modalCounter').textContent = hasImages ? (currentImageIndex + 1) + ' / ' + images.length : 'ไม่มีรูปภาพ';
    document.getElementById('modalPrev').style.display = images.length > 1 ? 'flex' : 'none';
    document.getElementById('modalNext').style.display = images.length > 1 ? 'flex' : 'none';
    document.getElementById('modalDownload').href = mainSrc;
    document.getElementById('modalDownload').style.display = hasImages ? 'inline-flex' : 'none';

    var badge = document.getElementById('modalBadge');
    badge.textContent = currentProduct.statusLabel;
    badge.className = 'badge ' + (badgeClassMap[currentProduct.status] || '');

    document.getElementById('modalName').textContent = currentProduct.name;
    document.getElementById('modalBrandModel').textContent = [currentProduct.brand, currentProduct.model].filter(Boolean).join(' ');
    document.getElementById('modalCondition').textContent = currentProduct.condition_text ? ('สภาพ: ' + currentProduct.condition_text) : '';
    document.getElementById('modalPrice').textContent = currentProduct.price;
    document.getElementById('modalDesc').textContent = currentProduct.description || '';

    var thumbsEl = document.getElementById('modalThumbs');
    thumbsEl.innerHTML = '';
    images.forEach(function (src, i) {
      var img = document.createElement('img');
      img.src = src;
      img.className = i === currentImageIndex ? 'active' : '';
      img.onclick = function () { selectImage(i); };
      thumbsEl.appendChild(img);
    });
  }

  document.getElementById('modalOverlay').addEventListener('click', function (e) {
    if (e.target === this) { closeDetail(); }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeDetail(); }
    if (e.key === 'ArrowLeft') { modalStep(-1); }
    if (e.key === 'ArrowRight') { modalStep(1); }
  });
</script>
</body>
</html>
