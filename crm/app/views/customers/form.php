<?php $o = $org ?? []; ?>
<div class="crumbs"><a href="<?= e(url('customers')) ?>">ลูกค้า</a> › <?= $org ? e($org['name']) : 'เพิ่มใหม่' ?></div>
<div class="page-head"><h1><?= $org ? 'แก้ไขลูกค้า' : 'เพิ่มลูกค้า / องค์กร' ?></h1></div>
<form method="post" action="<?= e(url($org ? 'customers.update' : 'customers.create')) ?>" class="card">
  <?= csrf_field() ?>
  <?php if ($org): ?><input type="hidden" name="id" value="<?= e($org['id']) ?>"><?php endif; ?>
  <div class="form-grid">
    <?= f_input('name', 'ชื่อลูกค้า / องค์กร', $o['name'] ?? null, ['required' => true, 'class' => 'full']) ?>
    <?= f_select('type', 'ประเภท', labels('org_type'), $o['type'] ?? 'CLINIC', ['required' => true]) ?>
    <?= f_input('branch', 'สาขา', $o['branch'] ?? null) ?>
    <?= f_input('phone', 'โทรศัพท์', $o['phone'] ?? null, ['type' => 'text', 'attrs' => ['inputmode' => 'tel']]) ?>
    <?= f_input('line_id', 'LINE ID', $o['line_id'] ?? null) ?>
    <?= f_input('email', 'อีเมล', $o['email'] ?? null, ['type' => 'email']) ?>
    <?= f_input('tax_id', 'เลขประจำตัวผู้เสียภาษี', $o['tax_id'] ?? null) ?>
    <?= f_textarea('address', 'ที่อยู่', $o['address'] ?? null, ['rows' => 2, 'class' => 'full']) ?>
    <?= f_input('province', 'จังหวัด', $o['province'] ?? null) ?>
    <?= f_select('account_owner_id', 'ผู้ดูแลลูกค้า (Account owner)', user_options(), $o['account_owner_id'] ?? current_user_id(), ['blank' => '—']) ?>
    <?= f_textarea('notes', 'หมายเหตุ', $o['notes'] ?? null, ['rows' => 2, 'class' => 'full']) ?>
    <?php if (!$org): ?><?= f_checkbox('confirm_duplicate', 'ยืนยันว่าไม่ซ้ำกับลูกค้าเดิม (ติ๊กเมื่อระบบเตือนว่าอาจซ้ำ)', false, ['class' => 'full']) ?><?php endif; ?>
  </div>
  <div class="form-actions">
    <button class="btn primary" type="submit">บันทึก</button>
    <a class="btn" href="<?= e($org ? url('customers.view', ['id' => $org['id']]) : url('customers')) ?>">ยกเลิก</a>
  </div>
</form>
<?php if ($org && can('customer.edit')): ?>
  <form method="post" action="<?= e(url('customers.archive')) ?>" class="card danger" data-confirm="เก็บลูกค้ารายนี้เข้าคลัง? (ข้อมูลและประวัติยังอยู่ครบ)">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($org['id']) ?>">
    <h2>เก็บเข้าคลัง</h2>
    <p class="muted">ใช้เมื่อเลิกติดต่อกับลูกค้ารายนี้ ข้อมูลจะไม่ถูกลบ (ห้ามลบข้อมูลที่มีประวัติธุรกรรม)</p>
    <div class="form-grid"><?= f_input('reason', 'เหตุผล', null, ['required' => true]) ?></div>
    <button class="btn danger" type="submit">เก็บเข้าคลัง</button>
  </form>
<?php endif; ?>
