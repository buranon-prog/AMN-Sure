<?php $isSeller = $type === 'SELLER'; ?>
<div class="crumbs"><a href="<?= e(url('leads')) ?>">ลีด / ติดตาม</a> › <?= $isSeller ? 'ลีดผู้ขายใหม่' : 'ลีดผู้ซื้อใหม่' ?></div>
<div class="page-head">
  <div>
    <h1><?= $isSeller ? 'ลีดผู้ขายใหม่' : 'ลีดผู้ซื้อใหม่' ?></h1>
    <div class="sub"><?= $isSeller ? 'ลูกค้าต้องการขายเครื่องให้ AMN Sure' : 'ลูกค้าสนใจซื้อเครื่องจาก AMN Sure' ?></div>
  </div>
  <div class="actions">
    <a class="btn <?= $isSeller ? 'primary' : '' ?>" href="<?= e(url('leads.new', ['type' => 'SELLER', 'org' => $org['id'] ?? null])) ?>">ลีดผู้ขาย</a>
    <a class="btn <?= !$isSeller ? 'primary' : '' ?>" href="<?= e(url('leads.new', ['type' => 'BUYER', 'org' => $org['id'] ?? null])) ?>">ลีดผู้ซื้อ</a>
  </div>
</div>

<?php if (!$org): ?>
<form method="get" class="card">
  <input type="hidden" name="r" value="leads.new"><input type="hidden" name="type" value="<?= e($type) ?>">
  <h2>1. ลูกค้าเดิม?</h2>
  <p class="muted">ถ้าเป็นลูกค้าที่มีในระบบแล้ว เลือกก่อนเพื่อดึงผู้ติดต่อและเครื่องของลูกค้ามาให้เลือก — ถ้าเป็นลูกค้าใหม่ ข้ามไปกรอกด้านล่างได้เลย</p>
  <div class="form-grid">
    <?= f_select('org', 'ลูกค้าเดิม', org_options(), null, ['blank' => '— เลือกลูกค้า —', 'search' => true]) ?>
    <div class="field"><label>&nbsp;</label><button class="btn" type="submit">ใช้ลูกค้านี้</button></div>
  </div>
</form>
<?php endif; ?>

<form method="post" action="<?= e(url('leads.create')) ?>" class="card">
  <?= csrf_field() ?>
  <input type="hidden" name="type" value="<?= e($type) ?>">

  <h2><?= $org ? '1' : '2' ?>. ลูกค้าและผู้ติดต่อ</h2>
  <?php if ($org): ?>
    <input type="hidden" name="organization_id" value="<?= e($org['id']) ?>">
    <p><b><?= e($org['name']) ?></b> <span class="ref"><?= e($org['ref_no']) ?></span> · <a href="<?= e(url('leads.new', ['type' => $type])) ?>">เปลี่ยนลูกค้า</a></p>
    <div class="form-grid">
      <?= f_select('contact_id', 'ผู้ติดต่อ', array_column(array_map(function ($c) { return ['id' => $c['id'], 'n' => $c['name'] . ($c['position'] ? ' (' . $c['position'] . ')' : '')]; }, $contacts), 'n', 'id'), null, ['blank' => '— ผู้ติดต่อใหม่ (กรอกด้านล่าง) —']) ?>
    </div>
  <?php else: ?>
    <div class="form-grid three">
      <?= f_input('new_org_name', 'ชื่อลูกค้า / คลินิก / โรงพยาบาล', null, ['required' => true]) ?>
      <?= f_select('new_org_type', 'ประเภท', labels('org_type'), 'CLINIC') ?>
      <?= f_input('new_org_province', 'จังหวัด', null) ?>
      <?= f_input('new_org_phone', 'โทรศัพท์ลูกค้า', null, ['attrs' => ['inputmode' => 'tel']]) ?>
      <?= f_input('new_org_line', 'LINE', null) ?>
      <?= f_input('new_org_email', 'อีเมล', null, ['type' => 'email']) ?>
      <?= f_checkbox('confirm_org_duplicate', 'ยืนยันว่าเป็นลูกค้าใหม่จริง (ติ๊กเมื่อระบบเตือนว่าอาจซ้ำ)', false, ['class' => 'full']) ?>
    </div>
  <?php endif; ?>
  <div class="form-grid three">
    <?= f_input('new_contact_name', 'ผู้ติดต่อใหม่: ชื่อ', null) ?>
    <?= f_input('new_contact_position', 'ตำแหน่ง', null) ?>
    <?= f_input('new_contact_phone', 'โทรศัพท์', null, ['attrs' => ['inputmode' => 'tel']]) ?>
    <?= f_input('new_contact_line', 'LINE', null) ?>
    <?= f_input('new_contact_email', 'อีเมล', null, ['type' => 'email']) ?>
  </div>
  <p class="hint">ต้องมีช่องทางติดต่ออย่างน้อย 1 ช่องทาง (โทร / LINE / อีเมล) ของลูกค้าหรือผู้ติดต่อ</p>

  <?php if ($isSeller): ?>
    <h2><?= $org ? '2' : '3' ?>. เครื่องที่ต้องการขาย</h2>
    <?php if ($devices): ?>
      <div class="form-grid">
        <?= f_select('device_id', 'เครื่องของลูกค้าที่มีในระบบ', array_column(array_map(function ($x) { return ['id' => $x['id'], 'n' => $x['ref_no'] . ' ' . $x['brand'] . ' ' . $x['model'] . ($x['serial_number'] ? ' #' . $x['serial_number'] : '')]; }, $devices), 'n', 'id'), null, ['blank' => '— เครื่องใหม่ (กรอกด้านล่าง) —']) ?>
      </div>
    <?php endif; ?>
    <?php partial('device_fields', ['dv' => [], 'optional' => (bool) $devices]); ?>
    <div class="form-grid three">
      <?= f_input('expected_price', 'ราคาที่ผู้ขายต้องการ (บาท)', null, ['type' => 'money', 'required' => true]) ?>
      <?= f_input('asking_price', 'ราคาที่ผู้ขายตั้ง (ถ้าต่างจากช่องแรก)', null, ['type' => 'money']) ?>
      <?= f_input('location', 'สถานที่ตั้งเครื่อง', null, ['required' => true]) ?>
      <?= f_input('reason_for_sale', 'เหตุผลที่ขาย', null, ['class' => 'full']) ?>
      <?= f_checkbox('confirm_duplicate', 'ยืนยันว่าเป็นเครื่องใหม่ ไม่ซ้ำกับเครื่องในระบบ (ติ๊กเมื่อระบบเตือนว่าอาจซ้ำ)', false, ['class' => 'full']) ?>
    </div>
  <?php else: ?>
    <h2><?= $org ? '2' : '3' ?>. ความสนใจของผู้ซื้อ</h2>
    <div class="form-grid three">
      <?= f_input('interested_device', 'เครื่องที่สนใจ', null, ['required' => true, 'class' => 'full', 'placeholder' => 'เช่น เลเซอร์กำจัดขน Candela GentleMax']) ?>
      <?= f_input('budget_max', 'งบประมาณ (บาท)', null, ['type' => 'money']) ?>
      <?= f_input('expected_purchase_date', 'คาดว่าจะซื้อภายใน', null, ['type' => 'date']) ?>
      <?= f_input('timeline', 'ระยะเวลา/หมายเหตุ', null, ['placeholder' => 'เช่น ก่อนเปิดสาขาใหม่ ม.ค.']) ?>
    </div>
  <?php endif; ?>

  <h2><?= $org ? '3' : '4' ?>. การติดตาม (BR-01)</h2>
  <div class="form-grid three">
    <?= f_select('source', 'แหล่งที่มาของลีด', master_options('LEAD_SOURCE'), null, ['required' => true, 'blank' => '— เลือก —']) ?>
    <?= f_select('owner_id', 'ผู้รับผิดชอบ', user_options(), current_user_id(), ['required' => true]) ?>
    <?= f_input('next_follow_up_date', 'วันติดตามครั้งถัดไป', add_days(today(), 1), ['type' => 'date', 'required' => true]) ?>
    <?= f_input('next_action', 'Next action', $isSeller ? 'นัดตรวจเครื่อง' : 'ส่งข้อมูลเครื่องให้ลูกค้า', ['required' => true, 'class' => 'full']) ?>
    <?= f_textarea('summary', 'สรุปการคุยครั้งแรก', null, ['rows' => 2, 'class' => 'full']) ?>
  </div>
  <div class="form-actions"><button class="btn primary" type="submit">สร้างลีด</button><a class="btn" href="<?= e(url('leads')) ?>">ยกเลิก</a></div>
</form>
