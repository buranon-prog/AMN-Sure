<?php
$t = $d['trx'];
$st = $t['status'];
$steps = ['IN_PREPARATION' => 'เตรียมส่งมอบ', 'READY_FOR_DELIVERY' => 'พร้อมส่งมอบ', 'DELIVERED' => 'ส่งมอบแล้ว', 'TRANSACTION_COMPLETED' => 'เสร็จสมบูรณ์'];
$qcItems = qc_items();
$delivered = array_column($d['deliveries'], null, 'device_id');
$latestInst = [];
foreach ($d['installations'] as $in) if (!isset($latestInst[$in['device_id']])) $latestInst[$in['device_id']] = $in;
?>
<div class="crumbs"><a href="<?= e(url('trx')) ?>">ธุรกรรมขาย</a> › <?= e($t['ref_no']) ?></div>
<div class="page-head">
  <div>
    <h1>ธุรกรรม <span class="ref"><?= e($t['ref_no']) ?></span> · <?= e($d['buyer']['name']) ?></h1>
    <div class="sub"><?= badge('status', $st) ?> <span>ปิดการขาย <?= e(dt($t['won_at'])) ?></span> <span>มูลค่า <?= money($t['total_price']) ?> บาท</span>
      <span>ดีล <a href="<?= e(url('sales.view', ['id' => $d['so']['id']])) ?>"><?= e($d['so']['ref_no']) ?></a></span></div>
  </div>
  <div class="actions no-print">
    <?php if (in_array($st, ['IN_PREPARATION', 'READY_FOR_DELIVERY'], true) && can('transaction.cancel')): ?>
      <details><summary class="btn danger">ยกเลิกธุรกรรม</summary>
        <form method="post" action="<?= e(url('trx.cancel')) ?>" class="card" style="position:absolute;right:24px;z-index:5;min-width:320px" data-confirm="ยกเลิกธุรกรรมนี้? เครื่องจะกลับเข้าสต็อก">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($t['id']) ?>">
          <?= f_input('reason', 'เหตุผล', null, ['required' => true]) ?>
          <button class="btn danger" type="submit">ยืนยันยกเลิก</button>
        </form>
      </details>
    <?php endif; ?>
  </div>
</div>

<?php partial('stepper', ['steps' => $steps, 'current' => $st, 'stopped' => ['CANCELLED']]); ?>

<div class="card highlight">
<?php if ($st === 'IN_PREPARATION'): ?>
  <div class="next-action">
    <div><div class="what">เตรียมส่งมอบ: ทำเช็กลิสต์ให้ครบ และ QC ต้องผ่านทุกเครื่อง (BR-12)</div>
      <?php if ($d['ready_missing']): ?><ul class="missing"><?php foreach ($d['ready_missing'] as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?></div>
    <?php if (can('qc.edit')): ?><?= post_button('trx.ready', ['id' => $t['id']], 'พร้อมส่งมอบ', ['class' => 'primary' . ($d['ready_missing'] ? '' : ''), 'confirm' => 'ยืนยันว่าพร้อมส่งมอบ?']) ?><?php endif; ?>
  </div>
<?php elseif ($st === 'READY_FOR_DELIVERY'): ?>
  <div class="what">ส่งมอบเครื่องให้ลูกค้า — ต้องยืนยัน serial ของเครื่องที่ส่งจริงทุกเครื่อง (BR-13)</div>
<?php elseif ($st === 'DELIVERED'): ?>
  <div class="what">ติดตั้ง ทดสอบระบบ สอนใช้งาน และให้ลูกค้าตรวจรับ</div>
<?php elseif ($st === 'TRANSACTION_COMPLETED'): ?>
  <div class="what">✅ ธุรกรรมเสร็จสมบูรณ์เมื่อ <?= e(dt($t['completed_at'])) ?> — เครื่องเป็นของลูกค้าแล้ว</div>
<?php else: ?>
  <div class="what">ยกเลิกแล้ว: <?= e($t['cancel_reason']) ?></div>
<?php endif; ?>
</div>

<div class="card flush">
  <div class="card-head"><h2>เครื่องในธุรกรรม</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>เครื่อง</th><th>เป็นของ AMN Sure</th><th>QC ล่าสุด</th><th>ส่งมอบ</th><th>ติดตั้ง</th></tr></thead>
    <tbody><?php foreach ($d['devices'] as $dv): $qc = latest_qc($t['id'], $dv['id']); ?>
      <tr>
        <td><a href="<?= e(url('devices.view', ['id' => $dv['id']])) ?>"><b><?= e($dv['brand']) ?></b> <?= e($dv['model']) ?></a><div class="muted"><?= e($dv['ref_no']) ?> · S/N <?= e($dv['serial_number'] ?? '—') ?></div></td>
        <td><?= (int) $dv['owned_by_amn'] === 1 || $st === 'TRANSACTION_COMPLETED' ? '✅' : '<span class="badge red">ยังไม่ได้ซื้อเข้า</span>' ?></td>
        <td><?= $qc ? badge('result', $qc['overall_result']) . ' <span class="muted">' . e(dt($qc['checked_at'])) . '</span>' : '—' ?></td>
        <td><?= isset($delivered[$dv['id']]) ? '✅ ' . e(d($delivered[$dv['id']]['delivered_date'])) : '—' ?></td>
        <td><?= isset($latestInst[$dv['id']]) ? badge('result', $latestInst[$dv['id']]['result']) . ($latestInst[$dv['id']]['customer_accepted'] ? ' ✅ตรวจรับ' : '') : '—' ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
</div>

<div class="card" id="checklist">
  <h2>เช็กลิสต์ภายใน</h2>
  <div class="grid-2">
  <?php foreach ($d['checklists'] as $cl): $canCl = can('checklist.' . $cl['section']); ?>
    <div class="card" style="box-shadow:none;margin:0">
      <div class="card-head"><h3 style="margin:0"><?= e(label('checklist_section', $cl['section'])) ?></h3><?= badge('status', $cl['status']) ?></div>
      <ul class="list-plain">
        <?php foreach ($cl['items'] as $it): ?>
          <li style="display:flex;justify-content:space-between;gap:8px;align-items:center">
            <span><?= $it['done'] ? '✅' : '⬜' ?> <?= e($it['label']) ?><?= $it['is_mandatory'] ? ' <span class="req">*</span>' : '' ?>
              <?php if ($it['done']): ?><span class="muted">— <?= e($it['done_by_name'] ?? '') ?> <?= e(dt($it['done_at'])) ?></span><?php endif; ?>
              <?php if ($it['note']): ?><div class="muted"><?= e($it['note']) ?></div><?php endif; ?></span>
            <?php if ($canCl && in_array($st, $it['done'] ? ['IN_PREPARATION'] : ['IN_PREPARATION', 'READY_FOR_DELIVERY'], true)): ?>
              <form method="post" action="<?= e(url('trx.checklist')) ?>" class="inline"><?= csrf_field() ?>
                <input type="hidden" name="item_id" value="<?= e($it['id']) ?>"><input type="hidden" name="done" value="<?= $it['done'] ? '0' : '1' ?>">
                <button class="btn sm <?= $it['done'] ? '' : 'primary' ?>" type="submit"><?= $it['done'] ? 'ยกเลิก' : 'เสร็จ' ?></button></form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; ?>
  </div>
</div>

<?php if ($st === 'IN_PREPARATION' && can('qc.edit')): ?>
<div class="card" id="qc">
  <h2>QC ก่อนส่งมอบ (Pre-delivery external check)</h2>
  <?php foreach ($d['devices'] as $dv): ?>
    <details class="card" style="box-shadow:none" <?= count($d['devices']) === 1 ? 'open' : '' ?>>
      <summary><b><?= e($dv['ref_no'] . ' ' . $dv['brand'] . ' ' . $dv['model']) ?></b></summary>
      <form method="post" action="<?= e(url('trx.qc')) ?>" style="margin-top:10px" data-warn-unsaved>
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($t['id']) ?>"><input type="hidden" name="device_id" value="<?= e($dv['id']) ?>">
        <table class="table"><tbody>
          <?php foreach ($qcItems as $it): ?>
            <tr><td style="width:36%"><?= e($it['label']) ?></td>
              <td><div class="result-pick">
                <?php foreach (['PASS', 'FAIL', 'NA'] as $r): ?><label><input type="radio" name="checks[<?= e($it['code']) ?>][result]" value="<?= $r ?>" required <?= $r === 'PASS' ? '' : '' ?>><span><?= e(label('result', $r)) ?></span></label><?php endforeach; ?>
              </div></td>
              <td><input type="text" name="checks[<?= e($it['code']) ?>][note]" placeholder="หมายเหตุ"></td></tr>
          <?php endforeach; ?>
        </tbody></table>
        <?= f_input('note', 'หมายเหตุรวม', null) ?>
        <button class="btn primary" type="submit">บันทึกผล QC</button>
        <span class="hint">ถ้ามีรายการใดไม่ผ่าน ผลรวมจะเป็น "ไม่ผ่าน" และระบบเปิดใบงานซ่อมให้อัตโนมัติ</span>
      </form>
    </details>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($st === 'READY_FOR_DELIVERY' && can('delivery.edit')): ?>
<div class="card" id="delivery">
  <h2>บันทึกการส่งมอบ</h2>
  <?php foreach ($d['devices'] as $dv): if (isset($delivered[$dv['id']])) continue; ?>
    <form method="post" action="<?= e(url('trx.delivery')) ?>" enctype="multipart/form-data" class="card" style="box-shadow:none">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($t['id']) ?>"><input type="hidden" name="device_id" value="<?= e($dv['id']) ?>">
      <h3 style="margin-top:0"><?= e($dv['ref_no'] . ' ' . $dv['brand'] . ' ' . $dv['model']) ?></h3>
      <div class="form-grid three">
        <?= f_input('serial_confirmed', $dv['serial_number'] ? 'พิมพ์ Serial ที่อ่านจากตัวเครื่องจริง' : 'เครื่องไม่มี serial: พิมพ์ Device ID (' . $dv['ref_no'] . ')', null, ['required' => true, 'attrs' => ['autocomplete' => 'off']]) ?>
        <?= f_input('delivered_date', 'วันที่ส่งมอบ', today(), ['type' => 'date', 'required' => true]) ?>
        <?= f_input('receiving_person', 'ผู้รับสินค้า', null, ['required' => true]) ?>
        <?= f_input('transport_method', 'วิธีขนส่ง', null) ?>
        <?= f_input('address', 'สถานที่ส่ง', trim(($d['buyer']['address'] ?? '') . ' ' . ($d['buyer']['province'] ?? ''))) ?>
        <div class="field"><label for="ev_<?= e($dv['id']) ?>">หลักฐาน (รูปใบรับของ/รูปเครื่อง)</label><input type="file" name="evidence" id="ev_<?= e($dv['id']) ?>" accept="image/*,application/pdf"></div>
        <?= f_textarea('accessories_delivered', 'อุปกรณ์ที่ส่งมอบ', $dv['accessories'], ['rows' => 2, 'class' => 'full']) ?>
      </div>
      <button class="btn primary" type="submit">บันทึกการส่งมอบ</button>
    </form>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($st === 'DELIVERED' && can('installation.edit')): ?>
<div class="card" id="installation">
  <h2>บันทึกการติดตั้ง</h2>
  <?php foreach ($d['devices'] as $dv): $li = $latestInst[$dv['id']] ?? null; if ($li && $li['result'] === 'SUCCESS' && $li['customer_accepted']) continue; ?>
    <form method="post" action="<?= e(url('trx.installation')) ?>" enctype="multipart/form-data" class="card" style="box-shadow:none">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($t['id']) ?>"><input type="hidden" name="device_id" value="<?= e($dv['id']) ?>">
      <h3 style="margin-top:0"><?= e($dv['ref_no'] . ' ' . $dv['brand'] . ' ' . $dv['model']) ?></h3>
      <div class="form-grid three">
        <?= f_input('installed_date', 'วันที่ติดตั้ง', today(), ['type' => 'date', 'required' => true]) ?>
        <?= f_select('result', 'ผลการติดตั้ง', ['SUCCESS' => 'สำเร็จ', 'PARTIAL' => 'สำเร็จบางส่วน', 'FAILED' => 'ไม่สำเร็จ'], 'SUCCESS') ?>
        <?= f_input('accepted_by_name', 'ผู้ตรวจรับฝั่งลูกค้า', null) ?>
        <?= f_textarea('system_test_result', 'ผลทดสอบระบบ', null, ['rows' => 2]) ?>
        <?= f_textarea('note', 'หมายเหตุ', null, ['rows' => 2]) ?>
        <div class="field"><label for="iev_<?= e($dv['id']) ?>">เอกสารตรวจรับ</label><input type="file" name="evidence" id="iev_<?= e($dv['id']) ?>" accept="image/*,application/pdf"></div>
        <?= f_checkbox('customer_accepted', 'ลูกค้าตรวจรับแล้ว', false) ?>
        <?= f_checkbox('training_done', 'สอนการใช้งานแล้ว', false) ?>
      </div>
      <button class="btn primary" type="submit">บันทึกการติดตั้ง</button>
    </form>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($d['qc'] || $d['deliveries'] || $d['installations']): ?>
<div class="card flush">
  <div class="card-head"><h2>บันทึก QC / ส่งมอบ / ติดตั้ง</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>เมื่อ</th><th>ขั้นตอน</th><th>เครื่อง</th><th>รายละเอียด</th><th>ผล</th></tr></thead>
    <tbody>
    <?php $devName = array_column($d['devices'], 'ref_no', 'id'); ?>
    <?php foreach ($d['qc'] as $q): $checks = json_decode($q['checks_json'], true) ?: []; $fails = array_filter($checks, function ($c) { return $c['result'] === 'FAIL'; }); ?>
      <tr><td><?= e(dt($q['checked_at'])) ?></td><td>QC</td><td><?= e($devName[$q['device_id']] ?? '') ?></td><td><?= e($q['engineer_name']) ?><?= $fails ? ' · ไม่ผ่าน: ' . e(implode(', ', array_column($fails, 'item'))) : '' ?></td><td><?= badge('result', $q['overall_result']) ?></td></tr>
    <?php endforeach; ?>
    <?php foreach ($d['deliveries'] as $dl): ?>
      <tr><td><?= e(d($dl['delivered_date'])) ?></td><td>ส่งมอบ</td><td><?= e($devName[$dl['device_id']] ?? '') ?></td><td>ผู้รับ <?= e($dl['receiving_person']) ?> · S/N <?= e($dl['serial_confirmed']) ?> · โดย <?= e($dl['engineer_name'] ?? '') ?></td><td><span class="badge green">ส่งแล้ว</span></td></tr>
    <?php endforeach; ?>
    <?php foreach ($d['installations'] as $in): ?>
      <tr><td><?= e(d($in['installed_date'])) ?></td><td>ติดตั้ง</td><td><?= e($devName[$in['device_id']] ?? '') ?></td><td><?= $in['customer_accepted'] ? 'ตรวจรับโดย ' . e($in['accepted_by_name']) : 'ยังไม่ตรวจรับ' ?><?= $in['training_done'] ? ' · สอนใช้งานแล้ว' : '' ?><?= $in['system_test_result'] ? ' · ' . e($in['system_test_result']) : '' ?></td><td><?= badge('result', $in['result']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<?php if ($d['jobs']): ?>
<div class="card flush">
  <div class="card-head"><h2>ใบงานซ่อมที่เกี่ยวข้อง</h2></div>
  <div class="table-wrap"><table class="table"><tbody>
    <?php foreach ($d['jobs'] as $j): ?><tr><td><a href="<?= e(url('jobs.view', ['id' => $j['id']])) ?>"><span class="ref"><?= e($j['ref_no']) ?></span></a> <?= e($j['title']) ?></td><td><?= badge('status', $j['status']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
<?php endif; ?>

<div class="card">
  <h2>สรุปการขาย</h2>
  <dl class="kv">
    <dt>ใบเสนอราคา</dt><dd><a href="<?= e(url('quotations.view', ['id' => $d['quotation']['id'], 'v' => $d['qv']['version_no']])) ?>"><span class="ref"><?= e($d['quotation']['ref_no']) ?></span> ฉบับที่ <?= (int) $d['qv']['version_no'] ?></a></dd>
    <dt>สัญญา</dt><dd><?= $d['contract'] ? e($d['contract']['contract_no']) . ' (ลงนาม ' . e(d($d['contract']['signed_date'])) . ')' : '—' ?></dd>
    <dt>มูลค่า</dt><dd><?= money($t['total_price']) ?> บาท (รวม VAT)</dd>
    <dt>ติดตั้ง</dt><dd><?= $t['installation_required'] ? 'ต้องติดตั้ง' : 'ไม่ต้องติดตั้ง' ?></dd>
  </dl>
</div>

<?php partial('tasks', ['parentType' => 'SALES_TRANSACTION', 'parentId' => $t['id'], 'tasks' => $d['tasks']]); ?>
<?php partial('activity', ['parentType' => 'SALES_TRANSACTION', 'parentId' => $t['id'], 'activities' => $d['activities']]); ?>
<?php partial('documents', ['parentType' => 'SALES_TRANSACTION', 'parentId' => $t['id'], 'documents' => $d['documents'], 'defaultCategory' => 'INVOICE']); ?>
