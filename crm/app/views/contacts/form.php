<?php $isEdit = !empty($c['id']); ?>
<div class="crumbs"><a href="<?= e(url('contacts')) ?>">ผู้ติดต่อ</a> › <?= $isEdit ? e($c['name']) : 'เพิ่มใหม่' ?></div>
<div class="page-head"><h1><?= $isEdit ? 'แก้ไขผู้ติดต่อ' : 'เพิ่มผู้ติดต่อ' ?></h1></div>
<form method="post" action="<?= e(url($isEdit ? 'contacts.update' : 'contacts.create')) ?>" class="card">
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($c['id']) ?>"><?php endif; ?>
  <div class="form-grid">
    <?= f_select('organization_id', 'องค์กร', org_options(), $c['organization_id'] ?? null, ['blank' => '— ไม่สังกัดองค์กร —', 'search' => true, 'class' => 'full']) ?>
    <?= f_input('name', 'ชื่อ-นามสกุล', $c['name'] ?? null, ['required' => true]) ?>
    <?= f_input('position', 'ตำแหน่ง', $c['position'] ?? null) ?>
    <?= f_input('phone', 'โทรศัพท์', $c['phone'] ?? null, ['attrs' => ['inputmode' => 'tel']]) ?>
    <?= f_input('line_id', 'LINE ID', $c['line_id'] ?? null) ?>
    <?= f_input('email', 'อีเมล', $c['email'] ?? null, ['type' => 'email']) ?>
    <?= f_checkbox('is_primary', 'ผู้ติดต่อหลักขององค์กร', !empty($c['is_primary'])) ?>
    <?= f_textarea('notes', 'หมายเหตุ', $c['notes'] ?? null, ['rows' => 2, 'class' => 'full']) ?>
  </div>
  <div class="form-actions"><button class="btn primary" type="submit">บันทึก</button></div>
</form>
<?php if ($isEdit && can('customer.edit')): ?>
  <form method="post" action="<?= e(url('contacts.archive')) ?>" class="card danger" data-confirm="นำผู้ติดต่อนี้ออกจากรายการ?">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($c['id']) ?>">
    <div class="form-grid"><?= f_input('reason', 'เหตุผลที่นำออก (เช่น ลาออกแล้ว)', null) ?></div>
    <button class="btn danger" type="submit">นำออกจากรายการ</button>
  </form>
<?php endif; ?>
