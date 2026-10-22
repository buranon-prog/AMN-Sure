<?php
$isEdit = !empty($dv['id']);
$dups = $_SESSION['dup_result'] ?? null;
unset($_SESSION['dup_result']);
?>
<div class="crumbs"><a href="<?= e(url('devices')) ?>">เครื่องทั้งหมด</a> › <?= $isEdit ? e($dv['ref_no']) : 'เพิ่มใหม่' ?></div>
<div class="page-head"><h1><?= $isEdit ? 'แก้ไขข้อมูลเครื่อง' : 'เพิ่มเครื่อง' ?></h1></div>

<?php if ($dups !== null && !$isEdit): ?>
  <div class="dup-box" id="dups">
    <?php if (!$dups['exact'] && !$dups['probable']): ?>
      ✅ ไม่พบเครื่องที่อาจซ้ำ บันทึกได้เลย
    <?php else: ?>
      <b>พบเครื่องที่อาจซ้ำ — ตรวจสอบก่อนสร้างใหม่</b>
      <ul>
        <?php foreach ($dups['exact'] as $x): ?><li>❌ Serial ตรงกัน: <a href="<?= e(url('devices.view', ['id' => $x['id']])) ?>"><?= e($x['ref_no'] . ' ' . $x['brand'] . ' ' . $x['model'] . ' #' . $x['serial_number']) ?></a> — ใช้เครื่องนี้แทน</li><?php endforeach; ?>
        <?php foreach ($dups['probable'] as $x): ?><li>⚠️ ยี่ห้อ/รุ่นเดียวกัน เจ้าของหรือสถานที่เดียวกัน: <a href="<?= e(url('devices.view', ['id' => $x['id']])) ?>"><?= e($x['ref_no'] . ' ' . $x['brand'] . ' ' . $x['model'] . ($x['serial_number'] ? ' #' . $x['serial_number'] : '')) ?></a></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
<?php endif; ?>

<form method="post" action="<?= e(url($isEdit ? 'devices.update' : 'devices.create')) ?>" class="card">
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($dv['id']) ?>"><?php endif; ?>
  <?php partial('device_fields', ['dv' => $dv, 'withLocation' => true]); ?>
  <div class="form-grid">
    <?php if (!$isEdit): ?>
      <?= f_select('current_owner_org_id', 'เจ้าของปัจจุบัน', org_options(), $dv['current_owner_org_id'] ?? null, ['blank' => '— ไม่ทราบ / ไม่ระบุ —', 'search' => true]) ?>
    <?php endif; ?>
    <?= f_textarea('notes', 'หมายเหตุ', $dv['notes'] ?? null, ['rows' => 2, 'class' => 'full']) ?>
  </div>
  <?php if (!$isEdit && can('device.opening_stock')): ?>
    <fieldset>
      <legend>ยอดยกมา (เฉพาะ GM)</legend>
      <?= f_checkbox('opening_stock', 'เครื่องนี้เป็นของ AMN Sure อยู่ในสต็อกแล้วตั้งแต่ก่อนเริ่มใช้ระบบ', false, ['attrs' => []]) ?>
      <div class="form-grid three" data-show-when="f_opening_stock=1">
        <?= f_input('received_date', 'วันที่รับเข้า', today(), ['type' => 'date']) ?>
        <?= f_input('book_cost', 'ต้นทุนบัญชี', null, ['type' => 'money']) ?>
        <?= f_input('list_price', 'ราคาตั้งขาย', null, ['type' => 'money']) ?>
        <?= f_input('storage_location', 'ที่เก็บ', null) ?>
      </div>
    </fieldset>
  <?php endif; ?>
  <?php if (!$isEdit): ?><?= f_checkbox('confirm_duplicate', 'ยืนยันว่าเป็นเครื่องใหม่ ไม่ซ้ำกับเครื่องที่ระบบเตือน', false) ?><?php endif; ?>
  <div class="form-actions">
    <button class="btn primary" type="submit">บันทึก</button>
    <?php if (!$isEdit): ?><button class="btn" type="submit" formaction="<?= e(url('devices.check')) ?>" formnovalidate>ตรวจเครื่องซ้ำก่อน</button><?php endif; ?>
    <a class="btn" href="<?= e($isEdit ? url('devices.view', ['id' => $dv['id']]) : url('devices')) ?>">ยกเลิก</a>
  </div>
</form>
