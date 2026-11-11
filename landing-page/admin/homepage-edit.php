<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/content.php';

requireLogin();

/**
 * โครงสร้างฟิลด์ทั้งหมดที่แก้ไขได้ จัดกลุ่มตามส่วนของหน้าแรก
 * 'html' => true หมายถึงฟิลด์นี้พิมพ์ <br> ขึ้นบรรทัดใหม่ หรือ <em>...</em> ทำตัวหนังสือไฮไลท์สีได้
 * 'single' => true หมายถึงมีค่าเดียว ไม่ต้องแยกไทย/อังกฤษ (เช่น เบอร์โทร, อีเมล)
 */
$sections = [
    'หัวข้อหลัก (Hero)' => [
        ['key' => 'hero_eyebrow', 'label' => 'ข้อความเล็กด้านบนหัวข้อ'],
        ['key' => 'hero_heading', 'label' => 'หัวข้อใหญ่', 'textarea' => true, 'html' => true],
        ['key' => 'hero_body', 'label' => 'ข้อความอธิบาย', 'textarea' => true],
        ['key' => 'hero_cta_label', 'label' => 'ข้อความปุ่ม "ติดต่อสอบถาม"'],
    ],
    'เกี่ยวกับเรา (About)' => [
        ['key' => 'about_eyebrow', 'label' => 'ข้อความเล็กด้านบนหัวข้อ'],
        ['key' => 'about_heading', 'label' => 'หัวข้อ', 'textarea' => true, 'html' => true],
        ['key' => 'about_body', 'label' => 'ข้อความอธิบาย', 'textarea' => true],
        ['key' => 'about_point_1', 'label' => 'รายการที่ 1'],
        ['key' => 'about_point_2', 'label' => 'รายการที่ 2'],
        ['key' => 'about_point_3', 'label' => 'รายการที่ 3'],
    ],
    'บริการของเรา (Services)' => [
        ['key' => 'services_eyebrow', 'label' => 'ข้อความเล็กด้านบนหัวข้อ'],
        ['key' => 'services_heading', 'label' => 'หัวข้อ'],
        ['key' => 'service_1_title', 'label' => 'การ์ดที่ 1'],
        ['key' => 'service_2_title', 'label' => 'การ์ดที่ 2'],
        ['key' => 'service_3_title', 'label' => 'การ์ดที่ 3'],
        ['key' => 'service_4_title', 'label' => 'การ์ดที่ 4'],
    ],
    'ทำไมต้อง SURE (Why Us)' => [
        ['key' => 'why_eyebrow', 'label' => 'ข้อความเล็กด้านบนหัวข้อ'],
        ['key' => 'why_heading', 'label' => 'หัวข้อ'],
        ['key' => 'why_1_title', 'label' => 'หัวข้อย่อยที่ 1'],
        ['key' => 'why_1_body', 'label' => 'คำอธิบายข้อ 1'],
        ['key' => 'why_2_title', 'label' => 'หัวข้อย่อยที่ 2'],
        ['key' => 'why_2_body', 'label' => 'คำอธิบายข้อ 2'],
        ['key' => 'why_3_title', 'label' => 'หัวข้อย่อยที่ 3'],
        ['key' => 'why_3_body', 'label' => 'คำอธิบายข้อ 3'],
        ['key' => 'why_4_title', 'label' => 'หัวข้อย่อยที่ 4'],
        ['key' => 'why_4_body', 'label' => 'คำอธิบายข้อ 4'],
    ],
    'ติดต่อเรา (Contact / CTA)' => [
        ['key' => 'cta_eyebrow', 'label' => 'ข้อความเล็กด้านบนหัวข้อ'],
        ['key' => 'cta_heading', 'label' => 'หัวข้อ', 'textarea' => true, 'html' => true],
        ['key' => 'cta_body', 'label' => 'ข้อความอธิบาย', 'textarea' => true],
        ['key' => 'cta_line_label', 'label' => 'ข้อความปุ่ม "ติดต่อผ่าน LINE"'],
    ],
    'ฟอร์มสนใจสินค้า' => [
        ['key' => 'inquiry_eyebrow', 'label' => 'ข้อความเล็กด้านบนหัวข้อ'],
        ['key' => 'inquiry_heading', 'label' => 'หัวข้อ'],
        ['key' => 'inquiry_body', 'label' => 'ข้อความอธิบาย', 'textarea' => true],
    ],
    'ท้ายเว็บไซต์ (Footer)' => [
        ['key' => 'footer_tagline', 'label' => 'คำโปรยใต้โลโก้', 'textarea' => true],
        ['key' => 'footer_address', 'label' => 'ที่อยู่บริษัท (ใช้ปักหมุดแผนที่ด้วย)', 'textarea' => true],
        ['key' => 'footer_bottom_tagline', 'label' => 'ข้อความเล็กด้านล่างสุด'],
    ],
    'ป๊อปอัพ LINE' => [
        ['key' => 'line_modal_heading', 'label' => 'หัวข้อ'],
        ['key' => 'line_modal_body', 'label' => 'ข้อความอธิบาย'],
    ],
    'ข้อมูลติดต่อ (ใช้ร่วมกันทุกจุดในหน้าเว็บ)' => [
        ['key' => 'contact_phone', 'label' => 'เบอร์โทรศัพท์', 'single' => true],
        ['key' => 'contact_email', 'label' => 'อีเมล', 'single' => true],
    ],
];

$error = '';
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();

    foreach ($sections as $fields) {
        foreach ($fields as $field) {
            $key = $field['key'];
            if (!empty($field['single'])) {
                $valueTh = trim($_POST[$key] ?? '');
                saveContent($key, $valueTh, null);
            } else {
                $valueTh = trim($_POST[$key . '_th'] ?? '');
                $valueEn = trim($_POST[$key . '_en'] ?? '');
                saveContent($key, $valueTh, $valueEn);
            }
        }
    }

    // อัปโหลดรูปโลโก้ใหม่ (ถ้ามี) — ทับไฟล์ logo.png เดิมโดยตรง เพราะทุกจุดในเว็บอ้างอิงชื่อไฟล์นี้
    foreach (['logo' => __DIR__ . '/../logo.png', 'line_qr' => __DIR__ . '/../line-qr.png'] as $field => $targetPath) {
        if (!empty($_FILES[$field]['name']) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES[$field]['tmp_name'];
            $mime = mime_content_type($tmpName);
            if ($mime !== 'image/png') {
                $error = 'ไฟล์รูปโลโก้/QR ต้องเป็นไฟล์ PNG เท่านั้น';
                continue;
            }
            if ($_FILES[$field]['size'] > MAX_UPLOAD_BYTES) {
                $error = 'ไฟล์รูปมีขนาดใหญ่เกินไป (จำกัดไม่เกิน 5MB)';
                continue;
            }
            move_uploaded_file($tmpName, $targetPath);
        }
    }

    if ($error === '') {
        $saved = true;
    }
}

// โหลดค่าปัจจุบันใหม่เสมอ (รวมค่าที่เพิ่งบันทึกไป)
$content = [];
$stmt = getDb()->query('SELECT content_key, value_th, value_en FROM site_content');
foreach ($stmt->fetchAll() as $row) {
    $content[$row['content_key']] = ['th' => $row['value_th'] ?? '', 'en' => $row['value_en'] ?? ''];
}
function val(array $content, string $key, string $lang = 'th'): string {
    return $content[$key][$lang] ?? '';
}

$pageTitle = 'แก้ไขหน้าแรก';
require __DIR__ . '/_layout_head.php';
?>

<div class="topbar">
  <h1>แก้ไขหน้าแรก</h1>
  <nav>
    <a href="index.php">&larr; จัดการสินค้า</a>
    <a href="../index.php" target="_blank">ดูหน้าแรกจริง &rarr;</a>
    <a href="logout.php">ออกจากระบบ</a>
  </nav>
</div>

<?php if ($saved): ?>
  <div class="alert alert-success">บันทึกข้อมูลเรียบร้อยแล้ว</div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

  <div class="card">
    <h3 style="margin-bottom:16px;color:var(--green-dark);">รูปภาพหลัก</h3>
    <p style="font-size:13px;color:var(--text-light);margin-bottom:18px;">อัปโหลดเฉพาะไฟล์ชนิด PNG เท่านั้น (เพื่อให้ทับไฟล์เดิมได้พอดี) — ถ้าไม่เลือกไฟล์ใหม่ รูปเดิมจะไม่เปลี่ยนแปลง</p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
      <div class="form-field">
        <label>โลโก้บริษัท (ใช้ทุกจุดในเว็บ)</label>
        <img src="../logo.png?t=<?= time() ?>" alt="" style="height:60px;display:block;margin-bottom:10px;">
        <input type="file" name="logo" accept="image/png">
      </div>
      <div class="form-field">
        <label>QR Code LINE</label>
        <img src="../line-qr.png?t=<?= time() ?>" alt="" style="height:100px;display:block;margin-bottom:10px;" onerror="this.style.display='none';">
        <input type="file" name="line_qr" accept="image/png">
      </div>
    </div>
  </div>

  <?php foreach ($sections as $sectionTitle => $fields): ?>
    <div class="card">
      <h3 style="margin-bottom:18px;color:var(--green-dark);"><?= h($sectionTitle) ?></h3>
      <?php foreach ($fields as $field): ?>
        <?php $key = $field['key']; $isTextarea = !empty($field['textarea']); ?>
        <?php if (!empty($field['single'])): ?>
          <div class="form-field">
            <label for="<?= h($key) ?>"><?= h($field['label']) ?></label>
            <input type="text" id="<?= h($key) ?>" name="<?= h($key) ?>" value="<?= h(val($content, $key, 'th')) ?>">
          </div>
        <?php else: ?>
          <div class="form-field">
            <label><?= h($field['label']) ?><?php if (!empty($field['html'])): ?> — ใช้ <code>&lt;br&gt;</code> ขึ้นบรรทัดใหม่, <code>&lt;em&gt;...&lt;/em&gt;</code> ทำตัวหนังสือไฮไลท์สีเขียวได้<?php endif; ?></label>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
              <div>
                <label style="font-size:12px;color:var(--text-light);margin-bottom:4px;">ภาษาไทย</label>
                <?php if ($isTextarea): ?>
                  <textarea name="<?= h($key) ?>_th"><?= h(val($content, $key, 'th')) ?></textarea>
                <?php else: ?>
                  <input type="text" name="<?= h($key) ?>_th" value="<?= h(val($content, $key, 'th')) ?>">
                <?php endif; ?>
              </div>
              <div>
                <label style="font-size:12px;color:var(--text-light);margin-bottom:4px;">English</label>
                <?php if ($isTextarea): ?>
                  <textarea name="<?= h($key) ?>_en"><?= h(val($content, $key, 'en')) ?></textarea>
                <?php else: ?>
                  <input type="text" name="<?= h($key) ?>_en" value="<?= h(val($content, $key, 'en')) ?>">
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <button type="submit" class="btn btn-primary" style="position:sticky;bottom:20px;box-shadow:0 10px 30px -10px rgba(23,59,46,.5);">บันทึกการเปลี่ยนแปลงทั้งหมด</button>
</form>

<?php require __DIR__ . '/_layout_foot.php'; ?>
