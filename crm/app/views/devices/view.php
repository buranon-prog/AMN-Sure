<?php $dv = $d['device']; $fin = can('finance.view'); ?>
<div class="crumbs"><a href="<?= e(url('devices')) ?>">เครื่องทั้งหมด</a> › <?= e($dv['ref_no']) ?></div>
<div class="page-head">
  <div>
    <h1><?= e($dv['brand'] . ' ' . $dv['model']) ?></h1>
    <div class="sub"><span class="ref"><?= e($dv['ref_no']) ?></span> <?= badge('commercial', $dv['commercial_status']) ?> <?= badge('technical', $dv['technical_status']) ?>
      <span>เจ้าของ: <?= (int) $dv['owned_by_amn'] === 1 ? '<b>AMN Sure</b>' : ($d['owner'] ? '<a href="' . e(url('customers.view', ['id' => $d['owner']['id']])) . '">' . e($d['owner']['name']) . '</a>' : '—') ?></span></div>
  </div>
  <div class="actions">
    <?php if (can('lead.edit') && $dv['current_owner_org_id'] && in_array($dv['commercial_status'], ['EXTERNAL', 'INSTALLED_AT_CUSTOMER', 'DELIVERED'], true)): ?>
      <?= post_button('devices.open_deal', ['device_id' => $dv['id']], '+ เปิดดีลซื้อเครื่องนี้') ?>
    <?php endif; ?>
    <?php if (can('job.edit')): ?><a class="btn" href="<?= e(url('jobs.new', ['device' => $dv['id']])) ?>">+ ใบงานช่าง</a><?php endif; ?>
    <?php if (can('service_case.edit')): ?><a class="btn" href="<?= e(url('cases.new', ['device' => $dv['id']])) ?>">+ เคสบริการ</a><?php endif; ?>
    <?php if (can('device.edit')): ?><a class="btn primary" href="<?= e(url('devices.edit', ['id' => $dv['id']])) ?>">แก้ไข</a><?php endif; ?>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <h2>ข้อมูลเครื่อง</h2>
    <dl class="kv">
      <dt>Device ID</dt><dd><span class="ref"><?= e($dv['ref_no']) ?></span></dd>
      <dt>ยี่ห้อ / รุ่น</dt><dd><?= e($dv['brand'] . ' ' . $dv['model']) ?></dd>
      <dt>ประเภท</dt><dd><?= e($dv['category'] ?? '—') ?></dd>
      <dt>Serial number</dt><dd class="mono"><?= e($dv['serial_number'] ?? ('ไม่มี — ' . ($dv['serial_missing_reason'] ?? ''))) ?></dd>
      <dt>ปีผลิต / ปีติดตั้ง</dt><dd><?= e($dv['manufacture_year'] ?? '—') ?> / <?= e($dv['installation_year'] ?? '—') ?></dd>
      <dt>การใช้งาน</dt><dd><?= $dv['usage_value'] !== null ? e(number_format((float) $dv['usage_value']) . ' ' . label('usage_unit', $dv['usage_unit'])) . ' <span class="muted">(ณ ' . e(d($dv['usage_recorded_at'])) . ')</span>' : '—' ?></dd>
      <dt>สถานที่ตั้ง</dt><dd><?= e($dv['current_location'] ?? '—') ?></dd>
      <dt>อุปกรณ์เสริม</dt><dd class="pre"><?= e($dv['accessories'] ?? '—') ?></dd>
      <?php if ($dv['notes']): ?><dt>หมายเหตุ</dt><dd class="pre"><?= e($dv['notes']) ?></dd><?php endif; ?>
    </dl>
  </div>
  <div class="card flush">
    <div class="card-head"><h2>ประวัติเจ้าของ</h2></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>เจ้าของ</th><th>ตั้งแต่</th><th>ถึง</th><th>ที่มา</th></tr></thead>
      <tbody>
      <?php if (!$d['ownerships']): ?><tr><td colspan="4" class="empty">ไม่มีข้อมูล</td></tr><?php endif; ?>
      <?php foreach ($d['ownerships'] as $ow): ?>
        <tr><td><?= (int) $ow['owned_by_amn'] === 1 ? '<b>AMN Sure</b>' : ($ow['organization_id'] ? '<a href="' . e(url('customers.view', ['id' => $ow['organization_id']])) . '">' . e($ow['org_name']) . '</a>' : '—') ?><?= $ow['to_date'] ? '' : ' <span class="badge green">ปัจจุบัน</span>' ?></td>
          <td><?= e(d($ow['from_date'])) ?></td><td><?= e(d($ow['to_date'])) ?></td><td><?= e(label('ownership_source', $ow['source'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>

<div class="subnav no-print">
  <a href="#deals">ดีลซื้อ/ขาย</a><a href="#inspections">ผลตรวจ</a><a href="#service">ซ่อม / MA</a><?php if ($fin): ?><a href="#finance">ต้นทุน / มูลค่า</a><?php endif; ?><a href="#delivery">QC / ส่งมอบ</a><a href="#activities">Timeline</a><a href="#documents">เอกสาร</a>
</div>

<div id="deals">
<?php if ($d['seller_opps']): ?>
<div class="card flush">
  <div class="card-head"><h2>ดีลซื้อ (ผู้ขายเสนอเครื่องนี้)</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ดีล</th><th>ผู้ขาย</th><th>ผู้รับผิดชอบ</th><?php if (can('negotiation.view')): ?><th class="num">ราคาที่ตกลง</th><?php endif; ?><th>สถานะ</th></tr></thead>
    <tbody><?php foreach ($d['seller_opps'] as $o): ?>
      <tr><td><a href="<?= e(url('acq.view', ['id' => $o['id']])) ?>"><span class="ref"><?= e($o['ref_no']) ?></span></a> <span class="muted"><?= e(d($o['created_at'])) ?></span></td>
        <td><?= e($o['seller_name']) ?></td><td><?= e($o['owner_name']) ?></td><?php if (can('negotiation.view')): ?><td class="num"><?= money($o['final_negotiated_price']) ?></td><?php endif; ?><td><?= badge('status', $o['status']) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</div>
<?php endif; ?>
<?php if ($d['acquisitions'] || $d['inventory']): ?>
<div class="grid-2">
  <div class="card flush">
    <div class="card-head"><h2>การซื้อเข้า</h2></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>เลขที่</th><th>ผู้ขาย</th><th>วันที่</th><?php if (can_any('negotiation.view', 'acquisition.create')): ?><th class="num">ราคา</th><?php endif; ?></tr></thead>
      <tbody>
      <?php if (!$d['acquisitions']): ?><tr><td colspan="4" class="empty">ไม่มี</td></tr><?php endif; ?>
      <?php foreach ($d['acquisitions'] as $a): ?>
        <tr><td><a href="<?= e(url('acq.view', ['id' => $a['device_opportunity_id']])) ?>"><span class="ref"><?= e($a['ref_no']) ?></span></a></td><td><?= e($a['seller_name']) ?></td><td><?= e(d($a['purchase_date'])) ?></td><?php if (can_any('negotiation.view', 'acquisition.create')): ?><td class="num"><?= money($a['purchase_price']) ?></td><?php endif; ?></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <div class="card flush">
    <div class="card-head"><h2>สต็อก</h2></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>เลขที่</th><th>รับเข้า</th><th>ที่เก็บ</th><th class="num">ราคาตั้งขาย</th><?php if ($fin): ?><th class="num">ต้นทุนบัญชี</th><?php endif; ?><th>สถานะ</th></tr></thead>
      <tbody>
      <?php if (!$d['inventory']): ?><tr><td colspan="6" class="empty">ไม่มี</td></tr><?php endif; ?>
      <?php foreach ($d['inventory'] as $i): ?>
        <tr><td><span class="ref"><?= e($i['ref_no']) ?></span> <span class="muted"><?= e(label('inventory_source', $i['source'])) ?></span></td><td><?= e(d($i['received_date'])) ?></td><td><?= e($i['storage_location'] ?? '—') ?></td>
          <td class="num"><?= money($i['list_price']) ?></td><?php if ($fin): ?><td class="num"><?= money($i['book_cost'] ?? null) ?></td><?php endif; ?><td><?= badge('status', $i['status']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php endif; ?>
<?php if ($d['matches'] || $d['transactions']): ?>
<div class="grid-2">
  <div class="card flush">
    <div class="card-head"><h2>ผู้ซื้อที่จับคู่กับเครื่องนี้</h2></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>ดีลขาย</th><th>ผู้ซื้อ</th><th>การจับคู่</th></tr></thead>
      <tbody>
      <?php if (!$d['matches']): ?><tr><td colspan="3" class="empty">ไม่มี</td></tr><?php endif; ?>
      <?php foreach ($d['matches'] as $m): ?>
        <tr><td><a href="<?= e(url('sales.view', ['id' => $m['sales_opportunity_id']])) ?>"><span class="ref"><?= e($m['so_ref']) ?></span></a> <?= badge('status', $m['so_status']) ?></td><td><?= e($m['buyer_name']) ?></td><td><?= badge('status', $m['status']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <div class="card flush">
    <div class="card-head"><h2>ธุรกรรมขาย</h2></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>เลขที่</th><th>ผู้ซื้อ</th><th>วันที่</th><th>สถานะ</th></tr></thead>
      <tbody>
      <?php if (!$d['transactions']): ?><tr><td colspan="4" class="empty">ไม่มี</td></tr><?php endif; ?>
      <?php foreach ($d['transactions'] as $t): ?>
        <tr><td><a href="<?= e(url('trx.view', ['id' => $t['id']])) ?>"><span class="ref"><?= e($t['ref_no']) ?></span></a></td><td><?= e($t['buyer_name']) ?></td><td><?= e(d($t['won_at'])) ?></td><td><?= badge('status', $t['status']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php endif; ?>
</div>

<?php if (can('inspection.view')): ?>
<div class="card flush" id="inspections">
  <div class="card-head"><h2>ผลตรวจเครื่อง</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ขอตรวจเมื่อ</th><th>ดีล</th><th>ช่าง</th><th>ผลรวม</th><th>ความเสี่ยง</th><th>สถานะ</th></tr></thead>
    <tbody>
    <?php if (!$d['inspections']): ?><tr><td colspan="6" class="empty">ยังไม่เคยตรวจ</td></tr><?php endif; ?>
    <?php foreach ($d['inspections'] as $i): ?>
      <tr><td><a href="<?= e(url('acq.inspection', ['id' => $i['id']])) ?>"><?= e(dt($i['requested_at'])) ?></a></td><td><span class="ref"><?= e($i['opp_ref'] ?? '—') ?></span></td><td><?= e($i['engineer_name'] ?? '—') ?></td>
        <td><?= badge('result', $i['overall_result']) ?></td><td><?= $i['technical_risk'] ? badge('risk', $i['technical_risk']) : '—' ?></td><td><?= badge('status', $i['status']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<?php if (can('service_history.view')): ?>
<div id="service">
<div class="card flush">
  <div class="card-head"><h2>ประวัติซ่อม / บำรุงรักษา</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>วันที่</th><th>ประเภท</th><th>รายละเอียด</th><th>อะไหล่</th><th>ผู้ทำ</th><?php if ($fin): ?><th class="num">ค่าใช้จ่าย</th><?php endif; ?></tr></thead>
    <tbody>
    <?php if (!$d['service']): ?><tr><td colspan="6" class="empty">ไม่มีประวัติ</td></tr><?php endif; ?>
    <?php foreach ($d['service'] as $s): ?>
      <tr><td class="nowrap"><?= e(d($s['service_date'])) ?></td><td><?= e(label('service_type', $s['type'])) ?><?= $s['is_repeat_failure'] ? ' <span class="badge red">เสียซ้ำ</span>' : '' ?><div class="muted"><?= e(label('service_source', $s['source'])) ?></div></td>
        <td class="pre"><?= e($s['description']) ?></td><td><?= e($s['parts_replaced'] ?? '—') ?></td><td><?= e($s['performed_by'] ?? '—') ?></td><?php if ($fin): ?><td class="num"><?= money($s['cost'] ?? null) ?></td><?php endif; ?></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if (can('service_history.edit')): ?>
  <details style="padding:0 18px 14px">
    <summary class="btn sm">+ บันทึกประวัติซ่อม/บริการ</summary>
    <form method="post" action="<?= e(url('devices.service')) ?>" style="margin-top:10px">
      <?= csrf_field() ?><input type="hidden" name="device_id" value="<?= e($dv['id']) ?>">
      <div class="form-grid three">
        <?= f_input('service_date', 'วันที่', today(), ['type' => 'date', 'required' => true]) ?>
        <?= f_select('type', 'ประเภท', labels('service_type'), 'REPAIR') ?>
        <?= f_select('source', 'แหล่งข้อมูล', labels('service_source'), 'EXTERNAL_RECORD') ?>
        <?= f_textarea('description', 'รายละเอียด', null, ['rows' => 2, 'required' => true, 'class' => 'full']) ?>
        <?= f_input('parts_replaced', 'อะไหล่ที่เปลี่ยน', null) ?>
        <?= f_input('performed_by', 'ผู้ดำเนินการ', null) ?>
        <?php if ($fin): ?><?= f_input('cost', 'ค่าใช้จ่าย', null, ['type' => 'money']) ?><?php endif; ?>
        <?= f_checkbox('is_repeat_failure', 'เป็นอาการเสียซ้ำ', false) ?>
      </div>
      <button class="btn sm primary" type="submit">บันทึก</button>
    </form>
  </details>
  <?php endif; ?>
</div>
<div class="card flush">
  <div class="card-head"><h2>สัญญา MA</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ผู้ให้บริการ</th><th>เลขที่สัญญา</th><th>ระยะเวลา</th><th>ความคุ้มครอง</th><?php if ($fin): ?><th class="num">ค่าสัญญา</th><?php endif; ?><th>สถานะ</th></tr></thead>
    <tbody>
    <?php if (!$d['ma']): ?><tr><td colspan="6" class="empty">ไม่มีสัญญา MA</td></tr><?php endif; ?>
    <?php foreach ($d['ma'] as $m): $activeMa = $m['end_date'] >= today(); ?>
      <tr><td><?= e($m['provider']) ?></td><td><?= e($m['contract_no'] ?? '—') ?></td><td class="nowrap"><?= e(d($m['start_date'])) ?> – <?= e(d($m['end_date'])) ?></td>
        <td class="pre"><?= e($m['coverage'] ?? '—') ?></td><?php if ($fin): ?><td class="num"><?= money($m['cost'] ?? null) ?></td><?php endif; ?>
        <td><?= $activeMa ? '<span class="badge green">มีผล</span>' : '<span class="badge gray">หมดอายุ</span>' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if (can('service_history.edit')): ?>
  <details style="padding:0 18px 14px">
    <summary class="btn sm">+ บันทึกสัญญา MA</summary>
    <form method="post" action="<?= e(url('devices.ma')) ?>" style="margin-top:10px">
      <?= csrf_field() ?><input type="hidden" name="device_id" value="<?= e($dv['id']) ?>">
      <div class="form-grid three">
        <?= f_input('provider', 'ผู้ให้บริการ', null, ['required' => true]) ?>
        <?= f_input('contract_no', 'เลขที่สัญญา', null) ?>
        <?= f_input('start_date', 'เริ่ม', null, ['type' => 'date']) ?>
        <?= f_input('end_date', 'สิ้นสุด', null, ['type' => 'date', 'required' => true]) ?>
        <?php if ($fin): ?><?= f_input('cost', 'ค่าสัญญา', null, ['type' => 'money']) ?><?php endif; ?>
        <?= f_textarea('coverage', 'ความคุ้มครอง', null, ['rows' => 2, 'class' => 'full']) ?>
      </div>
      <button class="btn sm primary" type="submit">บันทึก</button>
    </form>
  </details>
  <?php endif; ?>
</div>
</div>
<?php endif; ?>

<?php if ($fin && ($d['cost_sheets'] || $d['valuations'])): ?>
<div class="grid-2" id="finance">
  <div class="card flush">
    <div class="card-head"><h2>Cost Sheet 🔒</h2></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>ดีล</th><th>ฉบับ</th><th class="num">ต้นทุนรวม</th><th>สถานะ</th></tr></thead>
      <tbody><?php foreach ($d['cost_sheets'] as $cs): ?>
        <tr><td><a href="<?= e(url('acq.view', ['id' => $cs['device_opportunity_id']])) ?>"><span class="ref"><?= e($cs['opp_ref']) ?></span></a></td><td><?= (int) $cs['version_no'] ?></td><td class="num"><?= money($cs['total_estimated_cost']) ?></td><td><?= badge('status', $cs['status']) ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </div>
  <div class="card flush">
    <div class="card-head"><h2>Valuation 🔒</h2></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>ดีล</th><th class="num">FMV</th><th class="num">ขายเป้าหมาย</th><th class="num">GP</th></tr></thead>
      <tbody><?php foreach ($d['valuations'] as $v): ?>
        <tr><td><span class="ref"><?= e($v['opp_ref']) ?></span> <span class="muted"><?= e(d($v['created_at'])) ?></span></td><td class="num"><?= money($v['fair_market_value'] ?? null) ?></td><td class="num"><?= money($v['target_selling_price'] ?? null) ?></td><td class="num"><?= money($v['expected_gp'] ?? null) ?> <span class="muted"><?= pct($v['expected_gp_margin'] ?? null) ?></span></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </div>
</div>
<?php endif; ?>

<?php if ($d['qc'] || $d['deliveries'] || $d['installations']): ?>
<div class="card flush" id="delivery">
  <div class="card-head"><h2>QC / ส่งมอบ / ติดตั้ง</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>วันที่</th><th>ขั้นตอน</th><th>ธุรกรรม</th><th>รายละเอียด</th><th>ผล</th></tr></thead>
    <tbody>
    <?php foreach ($d['qc'] as $q): ?><tr><td><?= e(dt($q['checked_at'])) ?></td><td>QC</td><td><span class="ref"><?= e($q['trx_ref']) ?></span></td><td><?= e($q['engineer_name']) ?></td><td><?= badge('result', $q['overall_result']) ?></td></tr><?php endforeach; ?>
    <?php foreach ($d['deliveries'] as $dl): ?><tr><td><?= e(d($dl['delivered_date'])) ?></td><td>ส่งมอบ</td><td><span class="ref"><?= e($dl['trx_ref']) ?></span></td><td>ผู้รับ: <?= e($dl['receiving_person']) ?> · S/N <?= e($dl['serial_confirmed']) ?></td><td><span class="badge green">ส่งแล้ว</span></td></tr><?php endforeach; ?>
    <?php foreach ($d['installations'] as $in): ?><tr><td><?= e(d($in['installed_date'])) ?></td><td>ติดตั้ง</td><td><span class="ref"><?= e($in['trx_ref']) ?></span></td><td><?= $in['customer_accepted'] ? 'ลูกค้าตรวจรับ: ' . e($in['accepted_by_name']) : 'ลูกค้ายังไม่ตรวจรับ' ?></td><td><?= badge('result', $in['result']) ?></td></tr><?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<?php if ($d['jobs'] || $d['cases']): ?>
<div class="grid-2">
  <div class="card flush">
    <div class="card-head"><h2>ใบงานช่าง</h2></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>ใบงาน</th><th>งาน</th><th>สถานะ</th></tr></thead>
      <tbody>
      <?php if (!$d['jobs']): ?><tr><td colspan="3" class="empty">ไม่มี</td></tr><?php endif; ?>
      <?php foreach ($d['jobs'] as $j): ?><tr><td><a href="<?= e(url('jobs.view', ['id' => $j['id']])) ?>"><span class="ref"><?= e($j['ref_no']) ?></span></a></td><td><?= e(label('job_type', $j['type'])) ?>: <?= e($j['title']) ?></td><td><?= badge('status', $j['status']) ?></td></tr><?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <div class="card flush">
    <div class="card-head"><h2>เคสบริการหลังการขาย</h2></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>เคส</th><th>ปัญหา</th><th>สถานะ</th></tr></thead>
      <tbody>
      <?php if (!$d['cases']): ?><tr><td colspan="3" class="empty">ไม่มี</td></tr><?php endif; ?>
      <?php foreach ($d['cases'] as $c): ?><tr><td><a href="<?= e(url('cases.view', ['id' => $c['id']])) ?>"><span class="ref"><?= e($c['ref_no']) ?></span></a></td><td><?= e(mb_substr($c['issue'], 0, 100)) ?></td><td><?= badge('status', $c['status']) ?></td></tr><?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php endif; ?>

<?php partial('activity', ['parentType' => 'DEVICE', 'parentId' => $dv['id'], 'activities' => $d['activities']]); ?>
<?php partial('documents', ['parentType' => 'DEVICE', 'parentId' => $dv['id'], 'documents' => $d['documents'], 'defaultCategory' => 'PHOTO']); ?>

<?php if (can('device.override_status')): ?>
<details class="card warn no-print">
  <summary><b>แก้สถานะเครื่องโดยตรง (ผู้ดูแลระบบ)</b></summary>
  <p class="muted" style="margin-top:8px">ปกติสถานะจะเปลี่ยนตาม workflow เอง ใช้ส่วนนี้เฉพาะแก้ข้อมูลผิดพลาด — ทุกครั้งถูกบันทึกใน audit log</p>
  <form method="post" action="<?= e(url('devices.override')) ?>" data-confirm="ยืนยันการแก้สถานะเครื่อง?">
    <?= csrf_field() ?><input type="hidden" name="device_id" value="<?= e($dv['id']) ?>">
    <div class="form-grid three">
      <?= f_select('commercial_status', 'สถานะทางการค้า', labels('commercial'), $dv['commercial_status']) ?>
      <?= f_select('technical_status', 'สถานะทางเทคนิค', labels('technical'), $dv['technical_status']) ?>
      <?= f_input('reason', 'เหตุผล', null, ['required' => true]) ?>
    </div>
    <button class="btn warn" type="submit">บันทึกการแก้สถานะ</button>
  </form>
</details>
<?php endif; ?>
