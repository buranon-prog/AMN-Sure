<?php
/** ช่องข้อมูลเครื่อง ใช้ทั้งในฟอร์มลีดผู้ขาย ฟอร์มเครื่อง และฟอร์มขอตรวจเครื่อง  @var array $dv @var bool $withLocation @var bool $optional */
$dv = $dv ?? [];
$req = empty($optional); // ในฟอร์มลีดที่เลือกเครื่องเดิมได้ ช่องยี่ห้อ/รุ่นไม่บังคับในหน้าเว็บ (server ตรวจอีกรอบ)
$brands = array_column(all('SELECT DISTINCT brand FROM devices ORDER BY brand LIMIT 500'), 'brand');
$cats = master_options('DEVICE_CATEGORY');
?>
<datalist id="brand-list"><?php foreach ($brands as $b): ?><option value="<?= e($b) ?>"><?php endforeach; ?></datalist>
<div class="form-grid three">
  <?= f_input('brand', 'ยี่ห้อ', $dv['brand'] ?? null, ['required' => $req, 'list' => 'brand-list']) ?>
  <?= f_input('model', 'รุ่น', $dv['model'] ?? null, ['required' => $req]) ?>
  <?= f_select('category', 'ประเภท / เทคโนโลยี', array_combine(array_values($cats), array_values($cats)), $dv['category'] ?? null, ['blank' => '—']) ?>
  <?= f_input('serial_number', 'Serial number', $dv['serial_number'] ?? null, ['hint' => 'ใช้ตรวจเครื่องซ้ำ — ถ้าไม่มีให้ระบุเหตุผลช่องถัดไป']) ?>
  <?= f_input('serial_missing_reason', 'เหตุผลที่ไม่มี serial', $dv['serial_missing_reason'] ?? null) ?>
  <?= f_input('manufacture_year', 'ปีที่ผลิต (ค.ศ.)', $dv['manufacture_year'] ?? null, ['type' => 'number', 'attrs' => ['min' => 1970, 'max' => (int) date('Y') + 1]]) ?>
  <?= f_input('installation_year', 'ปีที่ติดตั้ง (ค.ศ.)', $dv['installation_year'] ?? null, ['type' => 'number', 'attrs' => ['min' => 1970, 'max' => (int) date('Y') + 1]]) ?>
  <?= f_input('usage_value', 'การใช้งาน (ตัวเลข)', isset($dv['usage_value']) && $dv['usage_value'] !== null ? rtrim(rtrim((string) $dv['usage_value'], '0'), '.') : null, ['attrs' => ['inputmode' => 'decimal']]) ?>
  <?= f_select('usage_unit', 'หน่วย', labels('usage_unit'), $dv['usage_unit'] ?? null, ['blank' => '—']) ?>
  <?php if (!empty($withLocation)): ?>
    <?= f_input('current_location', 'สถานที่ตั้งเครื่อง', $dv['current_location'] ?? null, ['class' => 'full']) ?>
  <?php endif; ?>
  <?= f_textarea('accessories', 'อุปกรณ์เสริมที่มากับเครื่อง', $dv['accessories'] ?? null, ['rows' => 2, 'class' => 'full']) ?>
</div>
