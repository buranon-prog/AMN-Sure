<div class="page-head"><div><h1>ตั้งค่าระบบ</h1><div class="sub">ทุกการเปลี่ยนแปลงถูกบันทึกใน audit log</div></div></div>
<form method="post" action="<?= e(url('admin.settings')) ?>" class="card">
  <?= csrf_field() ?>
  <h2>ทั่วไป</h2>
  <div class="form-grid">
    <?php foreach (SETTINGS_FORM as $key => $def): if ($def[1] === 'bool') continue; ?>
      <?= f_input($key, $def[0], setting($key), ['required' => true, 'hint' => $def[2]]) ?>
    <?php endforeach; ?>
  </div>
  <h2>เงื่อนไขปิดการขาย (WON rule — BR-11)</h2>
  <p class="muted">ระบบเปลี่ยนดีลขายเป็น WON และสร้างธุรกรรมให้อัตโนมัติเมื่อครบทุกข้อที่เลือก (ค่าเริ่มต้นตามสเปก: ตอบรับใบเสนอราคา + ได้รับมัดจำ + ลงนามสัญญา)</p>
  <?php foreach (SETTINGS_FORM as $key => $def): if ($def[1] !== 'bool') continue; ?>
    <?= f_checkbox($key, $def[0], (int) setting($key) === 1, ['hint' => $def[2]]) ?>
  <?php endforeach; ?>
  <div class="form-actions"><button class="btn primary" type="submit">บันทึกการตั้งค่า</button></div>
</form>
<div class="card">
  <h2>ข้อมูลระบบ</h2>
  <dl class="kv">
    <dt>เวอร์ชัน</dt><dd><?= e(APP_VERSION) ?></dd>
    <dt>PHP</dt><dd><?= e(PHP_VERSION) ?> · upload_max_filesize <?= e(ini_get('upload_max_filesize')) ?> · post_max_size <?= e(ini_get('post_max_size')) ?></dd>
    <dt>ฐานข้อมูล</dt><dd><?= e((string) val('SELECT VERSION()')) ?></dd>
    <dt>HTTPS</dt><dd><?= is_https() ? '✅ เปิดใช้งาน' : '⚠️ ยังไม่ได้เปิด — ตั้งค่า SSL ใน cPanel' ?></dd>
    <dt>พื้นที่ไฟล์แนบ</dt><dd><?php $size = 0; if (is_dir(STORAGE_DIR . '/uploads')) { foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(STORAGE_DIR . '/uploads', FilesystemIterator::SKIP_DOTS)) as $f) $size += $f->getSize(); } ?><?= e(human_size($size)) ?></dd>
  </dl>
</div>
