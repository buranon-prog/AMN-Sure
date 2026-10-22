<?php $sources = master_options('LEAD_SOURCE'); ?>
<div class="crumbs"><a href="<?= e(url('leads')) ?>">ลีด / ติดตาม</a> › <?= e($lead['ref_no']) ?></div>
<div class="page-head">
  <div>
    <h1>ลีด<?= $lead['type'] === 'SELLER' ? 'ผู้ขาย' : 'ผู้ซื้อ' ?> <span class="ref"><?= e($lead['ref_no']) ?></span></h1>
    <div class="sub"><a href="<?= e(url('customers.view', ['id' => $org['id']])) ?>"><?= e($org['name']) ?></a> <?= badge('status', $lead['status']) ?>
      <span>แหล่งที่มา: <?= e($sources[$lead['source']] ?? $lead['source'] ?? '—') ?></span></div>
  </div>
</div>
<div class="grid-2">
  <div class="card">
    <h2>ข้อมูลลีด</h2>
    <dl class="kv">
      <dt>ลูกค้า</dt><dd><a href="<?= e(url('customers.view', ['id' => $org['id']])) ?>"><?= e($org['name']) ?></a> · <?= e($org['phone'] ?? '') ?></dd>
      <dt>ผู้ติดต่อ</dt><dd><?= $contact ? '<a href="' . e(url('contacts.view', ['id' => $contact['id']])) . '">' . e($contact['name']) . '</a> · ' . e($contact['phone'] ?? $contact['line_id'] ?? '') : '—' ?></dd>
      <dt>ผู้รับผิดชอบ</dt><dd><?= e(user_name($lead['owner_id'])) ?></dd>
      <dt>สร้างเมื่อ</dt><dd><?= e(dt($lead['created_at'])) ?></dd>
      <?php if ($lead['summary']): ?><dt>สรุป</dt><dd class="pre"><?= e($lead['summary']) ?></dd><?php endif; ?>
    </dl>
  </div>
  <?php if (can('lead.edit')): ?>
  <form method="post" action="<?= e(url('leads.update')) ?>" class="card">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($lead['id']) ?>">
    <h2>แก้ไขลีด</h2>
    <div class="form-grid">
      <?= f_select('source', 'แหล่งที่มา', $sources, $lead['source'], ['required' => true]) ?>
      <?= f_select('owner_id', 'ผู้รับผิดชอบ', user_options(), $lead['owner_id'], ['required' => true]) ?>
      <?= f_textarea('summary', 'สรุป', $lead['summary'], ['rows' => 2, 'class' => 'full']) ?>
    </div>
    <button class="btn sm" type="submit">บันทึก</button>
  </form>
  <?php endif; ?>
</div>

<div class="card flush">
  <div class="card-head"><h2>ดีลภายใต้ลีดนี้</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ดีล</th><th>เรื่อง</th><th>Next action</th><th>วันติดตาม</th><th>สถานะ</th></tr></thead>
    <tbody>
    <?php foreach ($dos as $o): $active = !in_array($o['status'], DO_CLOSED, true); ?>
      <tr><td><a href="<?= e(url('acq.view', ['id' => $o['id']])) ?>"><span class="ref"><?= e($o['ref_no']) ?></span></a></td>
        <td><?= e($o['brand'] . ' ' . $o['model']) ?> <span class="muted"><?= e($o['device_ref']) ?></span></td>
        <td><?= e($o['next_action'] ?? '—') ?></td><td><?= followup_badge($o['next_follow_up_date'], $active) ?></td><td><?= badge('status', $o['status']) ?></td></tr>
    <?php endforeach; ?>
    <?php foreach ($sos as $o): $active = !in_array($o['status'], SO_CLOSED, true); ?>
      <tr><td><a href="<?= e(url('sales.view', ['id' => $o['id']])) ?>"><span class="ref"><?= e($o['ref_no']) ?></span></a></td>
        <td><?= e($o['interested_device']) ?></td>
        <td><?= e($o['next_action'] ?? '—') ?></td><td><?= followup_badge($o['next_follow_up_date'], $active) ?></td><td><?= badge('status', $o['status']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<?php if ($lead['type'] === 'SELLER' && can('lead.edit')): ?>
<details class="card">
  <summary><b>+ ผู้ขายรายนี้มีเครื่องอื่นจะขายด้วย</b></summary>
  <form method="post" action="<?= e(url('leads.add_device')) ?>" style="margin-top:12px">
    <?= csrf_field() ?><input type="hidden" name="lead_id" value="<?= e($lead['id']) ?>">
    <?php if ($devices): ?>
      <?= f_select('device_id', 'เครื่องของลูกค้าที่มีในระบบ', array_column(array_map(function ($x) { return ['id' => $x['id'], 'n' => $x['ref_no'] . ' ' . $x['brand'] . ' ' . $x['model']]; }, $devices), 'n', 'id'), null, ['blank' => '— เครื่องใหม่ (กรอกด้านล่าง) —']) ?>
    <?php endif; ?>
    <?php partial('device_fields', ['dv' => [], 'optional' => (bool) $devices]); ?>
    <div class="form-grid three">
      <?= f_input('expected_price', 'ราคาที่ผู้ขายต้องการ', null, ['type' => 'money', 'required' => true]) ?>
      <?= f_input('location', 'สถานที่ตั้งเครื่อง', null, ['required' => true]) ?>
      <?= f_input('reason_for_sale', 'เหตุผลที่ขาย', null) ?>
      <?= f_select('owner_id', 'ผู้รับผิดชอบ', user_options(), $lead['owner_id']) ?>
      <?= f_input('next_action', 'Next action', 'นัดตรวจเครื่อง', ['required' => true]) ?>
      <?= f_input('next_follow_up_date', 'วันติดตาม', add_days(today(), 1), ['type' => 'date', 'required' => true]) ?>
      <?= f_checkbox('confirm_duplicate', 'ยืนยันว่าเป็นเครื่องใหม่ (เมื่อระบบเตือนว่าอาจซ้ำ)', false, ['class' => 'full']) ?>
    </div>
    <button class="btn primary" type="submit">เพิ่มเครื่อง</button>
  </form>
</details>
<?php endif; ?>

<?php partial('activity', ['parentType' => 'LEAD', 'parentId' => $lead['id'], 'activities' => $activities]); ?>
