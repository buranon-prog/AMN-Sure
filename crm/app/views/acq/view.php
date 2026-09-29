<?php
$o = $d['opp'];
$dv = $d['device'];
$st = $o['status'];
$active = !in_array($st, DO_CLOSED, true);
$fin = can('finance.view');
$steps = [];
foreach (DO_STEPS as $s) $steps[$s] = label('status', $s);
$latestInsp = $d['inspections'][0] ?? null;
$openInsp = null;
foreach ($d['inspections'] as $i) {
    if (in_array($i['status'], ['REQUESTED', 'IN_PROGRESS'], true)) { $openInsp = $i; break; }
}
$cs = $d['cost_sheets'][0] ?? null;
$val = $d['valuations'][0] ?? null;
$lastAppr = $d['approvals'][0] ?? null;
?>
<div class="crumbs"><a href="<?= e(url('acq')) ?>">ดีลซื้อ</a> › <?= e($o['ref_no']) ?></div>
<div class="page-head">
  <div>
    <h1><?= e($dv['brand'] . ' ' . $dv['model']) ?> <span class="ref"><?= e($o['ref_no']) ?></span></h1>
    <div class="sub"><?= badge('status', $st) ?>
      <span>ผู้ขาย: <a href="<?= e(url('customers.view', ['id' => $d['seller']['id']])) ?>"><?= e($d['seller']['name']) ?></a></span>
      <span>เครื่อง: <a href="<?= e(url('devices.view', ['id' => $dv['id']])) ?>"><?= e($dv['ref_no']) ?></a></span>
      <span>ลีด: <a href="<?= e(url('leads.view', ['id' => $d['lead']['id']])) ?>"><?= e($d['lead']['ref_no']) ?></a></span></div>
  </div>
  <div class="actions no-print">
    <button class="btn" data-print>พิมพ์</button>
    <?php if ($active && $st !== 'PENDING_APPROVAL' && can('lead.edit')): ?>
      <details><summary class="btn danger">ปิดดีล (ไม่สำเร็จ)</summary>
        <form method="post" action="<?= e(url('acq.lost')) ?>" class="card" style="position:absolute;right:24px;z-index:5;min-width:320px" data-confirm="ปิดดีลนี้เป็น 'ไม่สำเร็จ'?">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($o['id']) ?>">
          <?= f_input('reason', 'เหตุผล', null, ['required' => true, 'placeholder' => 'เช่น ผู้ขายขายให้คนอื่น / ราคาไม่ตรงกัน']) ?>
          <button class="btn danger" type="submit">ยืนยันปิดดีล</button>
        </form>
      </details>
    <?php endif; ?>
  </div>
</div>

<?php partial('stepper', ['steps' => $steps, 'current' => $st, 'stopped' => ['REJECTED', 'LOST']]); ?>

<!-- ============ NEXT ACTION ============ -->
<div class="card highlight" id="next">
<?php if ($st === 'NEW_SELLER_LEAD'): ?>
  <div class="what">ขั้นต่อไป: ตรวจข้อมูลเครื่องให้ครบ แล้วขอให้ Service Engineering ตรวจเครื่อง</div>
  <?php $missing = acq_device_info_missing($dv, $o); if ($missing): ?><ul class="missing"><?php foreach ($missing as $m): ?><li>ยังขาด: <?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <?php if (can('inspection.request')): ?>
    <form method="post" action="<?= e(url('acq.request_inspection')) ?>" style="margin-top:12px">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($o['id']) ?>">
      <?php partial('device_fields', ['dv' => $dv + ['current_location' => $o['location'] ?? $dv['current_location']], 'withLocation' => true]); ?>
      <div class="form-grid three">
        <?= f_input('asking_price', 'ราคาที่ผู้ขายตั้ง (asking)', $o['asking_price'], ['type' => 'money', 'required' => true]) ?>
        <?= f_input('reason_for_sale', 'เหตุผลที่ขาย', $o['reason_for_sale']) ?>
        <?= f_input('preferred_date', 'วันที่สะดวกให้ช่างเข้าตรวจ', add_days(today(), 1), ['type' => 'date']) ?>
        <?= f_textarea('request_note', 'ข้อความถึงช่าง', null, ['rows' => 2, 'class' => 'full', 'placeholder' => 'เช่น ติดต่อคุณ... ก่อนเข้า / ต้องนำ tool อะไรไป']) ?>
      </div>
      <button class="btn primary" type="submit">บันทึกข้อมูลเครื่อง + ขอตรวจเครื่อง</button>
    </form>
  <?php else: ?><p class="muted">รอผู้ประสานงานขายขอตรวจเครื่อง</p><?php endif; ?>

<?php elseif ($st === 'WAITING_INSPECTION' && $openInsp): ?>
  <div class="next-action"><div><div class="what">รอ Service Engineering เข้าตรวจเครื่อง</div>
    <div class="muted">ขอเมื่อ <?= e(dt($openInsp['requested_at'])) ?><?= $openInsp['preferred_date'] ? ' · วันที่สะดวก ' . e(d($openInsp['preferred_date'])) : '' ?><?= $openInsp['request_note'] ? ' · ' . e($openInsp['request_note']) : '' ?></div></div>
    <?php if (can('inspection.edit')): ?><?= post_button('acq.inspection_start', ['id' => $openInsp['id']], 'เริ่มตรวจเครื่อง', ['class' => 'primary']) ?><?php endif; ?></div>

<?php elseif ($st === 'INSPECTION_IN_PROGRESS' && $openInsp): ?>
  <div class="next-action"><div><div class="what">กำลังตรวจเครื่อง โดย <?= e($openInsp['engineer_name'] ?? '—') ?></div><div class="muted">เริ่มเมื่อ <?= e(dt($openInsp['started_at'])) ?></div></div>
    <a class="btn primary" href="<?= e(url('acq.inspection', ['id' => $openInsp['id']])) ?>"><?= can('inspection.edit') ? 'บันทึกผลตรวจ' : 'ดูผลตรวจ' ?></a></div>

<?php elseif ($st === 'INSPECTED'): ?>
  <div class="next-action"><div><div class="what">รอ Service Director จัดทำ Cost Sheet</div>
    <div class="muted">ผลตรวจ: <?= $latestInsp ? label('result', $latestInsp['overall_result']) . ' · ความเสี่ยง ' . label('risk', $latestInsp['technical_risk']) : '—' ?><?= $lastAppr && $lastAppr['decision'] === 'REVISION_REQUIRED' ? ' · GM ให้แก้ Cost Sheet: ' . e($lastAppr['comment']) : '' ?></div></div>
    <?php if (can('cost_sheet.edit')): ?><a class="btn primary" href="<?= e(url('acq.cost_sheet', ['id' => $o['id']])) ?>">ทำ Cost Sheet</a><?php endif; ?></div>

<?php elseif ($st === 'COSTED'): ?>
  <div class="next-action"><div><div class="what">รอ Sales Director ประเมินมูลค่า (Valuation)</div><?php if ($fin && $cs): ?><div class="muted">ต้นทุนรวมใน Cost Sheet ฉบับที่ <?= (int) $cs['version_no'] ?>: <?= money($cs['total_estimated_cost']) ?> บาท</div><?php endif; ?></div>
    <?php if (can('valuation.edit')): ?><a class="btn primary" href="<?= e(url('acq.valuation', ['id' => $o['id']])) ?>">ทำ Valuation</a><?php endif; ?></div>

<?php elseif (in_array($st, ['VALUED', 'NEGOTIATING'], true)): ?>
  <div class="what">เจรจาราคากับผู้ขาย แล้วส่งขออนุมัติ GM</div>
  <?php if ($lastAppr && $lastAppr['decision'] === 'REVISION_REQUIRED'): ?><div class="flash warning" style="margin:8px 0">GM ให้แก้ไข: <?= e($lastAppr['comment']) ?></div><?php endif; ?>
  <?php if ($val && isset($val['recommended_acq_price'])): ?>
    <p class="muted">ราคาแนะนำให้ซื้อ: <b><?= money($val['recommended_acq_price']) ?></b><?= isset($val['max_acq_price']) ? ' · ราคาซื้อสูงสุด: <b>' . money($val['max_acq_price']) . '</b>' : '' ?> · ผู้ขายต้องการ <?= money($o['expected_price']) ?></p>
  <?php endif; ?>
  <div class="grid-2">
    <?php if (can('negotiation.edit')): ?>
    <form method="post" action="<?= e(url('acq.negotiation')) ?>">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($o['id']) ?>">
      <div class="form-grid">
        <?= f_select('party', 'บันทึก', labels('negotiation_party'), 'AMN_OFFER') ?>
        <?= f_input('amount', 'จำนวนเงิน (บาท)', null, ['type' => 'money', 'required' => true]) ?>
        <?= f_input('note', 'หมายเหตุ', null, ['class' => 'full']) ?>
      </div>
      <button class="btn" type="submit">บันทึกการเจรจา</button>
      <div class="hint">เมื่อตกลงราคากันได้ ให้เลือก "ราคาที่ตกลง" เพื่อใช้ขออนุมัติ</div>
    </form>
    <?php endif; ?>
    <div>
      <?php $missing = approval_missing($o); ?>
      <p><b>ราคาที่ตกลง:</b> <?= $o['final_negotiated_price'] !== null ? money($o['final_negotiated_price']) . ' บาท' : '<span class="muted">ยังไม่มี</span>' ?></p>
      <?php if ($missing): ?><ul class="missing"><?php foreach ($missing as $m): ?><li>ยังขาด: <?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>
      <?php if (can('acquisition.submit') && $st === 'NEGOTIATING'): ?>
        <form method="post" action="<?= e(url('acq.submit_approval')) ?>" data-confirm="ส่งขออนุมัติซื้อให้ GM?">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($o['id']) ?>">
          <?= f_textarea('note', 'ข้อความถึง GM', null, ['rows' => 2]) ?>
          <button class="btn primary" type="submit" <?= $missing ? 'disabled' : '' ?>>ส่งขออนุมัติ GM</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($st === 'PENDING_APPROVAL' && $lastAppr): ?>
  <div class="next-action"><div><div class="what">รอ GM อนุมัติการซื้อ<?= can('negotiation.view') ? 'ที่ราคา ' . money($o['final_negotiated_price']) . ' บาท' : '' ?></div><div class="muted">ส่งโดย <?= e($lastAppr['requester_name']) ?> เมื่อ <?= e(dt($lastAppr['requested_at'])) ?></div></div>
    <?php if (can('acquisition.approve') || can('task.view_all')): ?><a class="btn primary" href="<?= e(url('approvals.view', ['id' => $lastAppr['id']])) ?>"><?= can('acquisition.approve') ? 'พิจารณาอนุมัติ' : 'ดูชุดข้อมูลอนุมัติ' ?></a><?php endif; ?></div>

<?php elseif ($st === 'APPROVED' && $lastAppr): ?>
  <div class="next-action"><div><div class="what">GM <?= e(label('approval', $lastAppr['decision'])) ?><?= can_any('negotiation.view', 'acquisition.create') ? ' วงเงิน ' . money($lastAppr['approved_amount']) . ' บาท' : '' ?> — บันทึกการซื้อ</div>
    <?php if ($lastAppr['condition_text']): ?><div class="flash warning" style="margin:6px 0 0">เงื่อนไข: <?= e($lastAppr['condition_text']) ?></div><?php endif; ?></div>
    <?php if (can('acquisition.create')): ?><a class="btn primary" href="<?= e(url('acq.purchase', ['id' => $o['id']])) ?>">บันทึกการซื้อ</a><?php endif; ?></div>

<?php elseif ($st === 'PURCHASED' && $d['acquisition']): $a = $d['acquisition']; ?>
  <div class="what">✅ ซื้อแล้ว <span class="ref"><?= e($a['ref_no']) ?></span><?= can_any('negotiation.view', 'acquisition.create') ? ' ราคา ' . money($a['purchase_price']) . ' บาท' : '' ?> เมื่อ <?= e(d($a['purchase_date'])) ?></div>
  <p class="muted">สถานะจ่ายเงิน: <?= e(label('payment_status', $a['payment_status'])) ?> · <?= $a['takes_stock'] ? 'เข้าสต็อกแล้ว' : 'ไม่เข้าสต็อก (ส่งต่อผู้ซื้อโดยตรง)' ?> · <a href="<?= e(url('devices.view', ['id' => $dv['id']])) ?>">ดูเครื่อง</a></p>

<?php else: ?>
  <div class="what"><?= e(label('status', $st)) ?><?= $o['lost_reason'] ? ': ' . e($o['lost_reason']) : '' ?></div>
  <?php if ($st === 'REJECTED' && $lastAppr): ?><p class="muted">GM: <?= e($lastAppr['comment']) ?></p><?php endif; ?>
<?php endif; ?>
</div>

<div class="grid-2">
  <div class="card">
    <h2>ข้อมูลดีล</h2>
    <dl class="kv">
      <dt>ผู้ขาย</dt><dd><a href="<?= e(url('customers.view', ['id' => $d['seller']['id']])) ?>"><?= e($d['seller']['name']) ?></a> · <?= e($d['seller']['phone'] ?? '') ?></dd>
      <dt>ผู้ติดต่อ</dt><dd><?= $d['contact'] ? e($d['contact']['name'] . ' · ' . ($d['contact']['phone'] ?? $d['contact']['line_id'] ?? '')) : '—' ?></dd>
      <dt>เครื่อง</dt><dd><a href="<?= e(url('devices.view', ['id' => $dv['id']])) ?>"><?= e($dv['brand'] . ' ' . $dv['model']) ?></a> <span class="ref"><?= e($dv['ref_no']) ?></span><br>
        <span class="muted">S/N <?= e($dv['serial_number'] ?? '—') ?> · ผลิต <?= e($dv['manufacture_year'] ?? '—') ?> · ใช้งาน <?= $dv['usage_value'] !== null ? e(number_format((float) $dv['usage_value']) . ' ' . label('usage_unit', $dv['usage_unit'])) : '—' ?></span></dd>
      <dt>สถานที่ตั้ง</dt><dd><?= e($o['location'] ?? $dv['current_location'] ?? '—') ?></dd>
      <?php if (can('negotiation.view')): ?>
      <dt>ราคาที่ผู้ขายต้องการ</dt><dd><?= money($o['expected_price']) ?> <span class="muted">(ตั้งไว้ <?= money($o['asking_price']) ?>)</span></dd>
      <dt>ราคาที่ตกลง</dt><dd><b><?= money($o['final_negotiated_price']) ?></b></dd>
      <?php endif; ?>
      <dt>เหตุผลที่ขาย</dt><dd><?= e($o['reason_for_sale'] ?? '—') ?></dd>
    </dl>
  </div>
  <div class="card">
    <h2>การติดตาม</h2>
    <?php if ($active && can('lead.edit')): ?>
      <form method="post" action="<?= e(url('leads.followup')) ?>">
        <?= csrf_field() ?><input type="hidden" name="kind" value="DO"><input type="hidden" name="id" value="<?= e($o['id']) ?>">
        <?= f_select('owner_id', 'ผู้รับผิดชอบ', user_options(), $o['owner_id'], ['required' => true]) ?>
        <?= f_input('next_action', 'Next action', $o['next_action'], ['required' => true]) ?>
        <div class="field"><label for="f_nfd">วันติดตามครั้งถัดไป <span class="req">*</span></label><input type="date" id="f_nfd" name="next_follow_up_date" value="<?= e(old('next_follow_up_date', $o['next_follow_up_date'])) ?>" required>
          <div class="hint"><?= followup_badge($o['next_follow_up_date']) ?></div></div>
        <button class="btn sm" type="submit">อัปเดตการติดตาม</button>
      </form>
    <?php else: ?>
      <dl class="kv"><dt>ผู้รับผิดชอบ</dt><dd><?= e(user_name($o['owner_id'])) ?></dd><dt>Next action</dt><dd><?= e($o['next_action'] ?? '—') ?></dd><dt>วันติดตาม</dt><dd><?= followup_badge($o['next_follow_up_date'], $active) ?></dd></dl>
    <?php endif; ?>
  </div>
</div>

<?php if (can('inspection.view') && $d['inspections']): ?>
<div class="card flush" id="inspection">
  <div class="card-head"><h2>ผลตรวจเครื่อง & Technical summary</h2></div>
  <?php if ($latestInsp && $latestInsp['status'] === 'COMPLETED'): ?>
    <div style="padding:0 18px 12px">
      <dl class="kv">
        <dt>ผลตรวจรวม</dt><dd><?= badge('result', $latestInsp['overall_result']) ?> สภาพ <?= e(label('condition', $latestInsp['overall_condition'])) ?> · ความเสี่ยง <?= badge('risk', $latestInsp['technical_risk']) ?></dd>
        <dt>ปัญหาที่พบ</dt><dd class="pre"><?= e($latestInsp['issues'] ?? '—') ?></dd>
        <dt>ต้องซ่อม</dt><dd class="pre"><?= e($latestInsp['required_repair'] ?? '—') ?></dd>
        <dt>แนะนำให้ซ่อม</dt><dd class="pre"><?= e($latestInsp['recommended_repair'] ?? '—') ?></dd>
        <dt>อุปกรณ์ที่ขาด</dt><dd><?= e($latestInsp['missing_accessories'] ?? '—') ?></dd>
        <dt>ระยะเวลาซ่อม</dt><dd><?= $latestInsp['est_repair_days'] !== null ? (int) $latestInsp['est_repair_days'] . ' วัน' : '—' ?></dd>
      </dl>
    </div>
  <?php endif; ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ขอตรวจเมื่อ</th><th>ช่าง</th><th>ผลรวม</th><th>สถานะ</th><th></th></tr></thead>
    <tbody><?php foreach ($d['inspections'] as $i): ?>
      <tr><td><?= e(dt($i['requested_at'])) ?></td><td><?= e($i['engineer_name'] ?? '—') ?></td><td><?= badge('result', $i['overall_result']) ?></td><td><?= badge('status', $i['status']) ?></td>
        <td class="right"><a class="btn sm" href="<?= e(url('acq.inspection', ['id' => $i['id']])) ?>">ดูรายละเอียด</a></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <div style="padding:10px 18px"><a href="<?= e(url('devices.view', ['id' => $dv['id']])) ?>#service">ประวัติซ่อม / MA ของเครื่อง →</a></div>
</div>
<?php endif; ?>

<?php if ($fin && $d['cost_sheets']): ?>
<div class="card" id="cost">
  <div class="card-head"><h2>Cost Sheet 🔒</h2><?php if (can('cost_sheet.edit') && in_array($st, ['INSPECTED', 'COSTED', 'VALUED', 'NEGOTIATING'], true)): ?><a class="btn sm" href="<?= e(url('acq.cost_sheet', ['id' => $o['id']])) ?>">แก้ไข / ออกฉบับใหม่</a><?php endif; ?></div>
  <?php $sub = latest_cost_sheet($o['id']); if ($sub): $items = all('SELECT * FROM cost_sheet_items WHERE cost_sheet_id = ? ORDER BY sort', [$sub['id']]); ?>
    <p class="muted">ฉบับที่ <?= (int) $sub['version_no'] ?> ส่งเมื่อ <?= e(dt($sub['submitted_at'])) ?> โดย <?= e(user_name($sub['submitted_by'])) ?></p>
    <table class="table"><tbody>
      <?php foreach ($items as $it): ?><tr><td><?= e(label('cost_category', $it['category'])) ?></td><td><?= e($it['description'] ?? '') ?></td><td class="num"><?= money($it['amount']) ?></td></tr><?php endforeach; ?>
    </tbody><tfoot><tr><td colspan="2">ต้นทุนรวมโดยประมาณ</td><td class="num"><?= money($sub['total_estimated_cost']) ?></td></tr></tfoot></table>
  <?php endif; ?>
  <?php if (count($d['cost_sheets']) > 1 || ($d['cost_sheets'][0]['status'] ?? '') === 'DRAFT'): ?>
    <p class="muted" style="margin-top:8px">ทุกฉบับ: <?php foreach ($d['cost_sheets'] as $c): ?>#<?= (int) $c['version_no'] ?> <?= badge('status', $c['status']) ?> <?= money($c['total_estimated_cost']) ?> · <?php endforeach; ?></p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($d['valuations']): ?>
<div class="card" id="valuation">
  <div class="card-head"><h2>Valuation 🔒</h2><?php if (can('valuation.edit') && in_array($st, ['COSTED', 'VALUED', 'NEGOTIATING'], true)): ?><a class="btn sm" href="<?= e(url('acq.valuation', ['id' => $o['id']])) ?>">ประเมินใหม่</a><?php endif; ?></div>
  <?php $v = $d['valuations'][0]; ?>
  <p class="muted">อ้างอิง Cost Sheet ฉบับที่ <?= (int) $v['cs_version'] ?> · บันทึกเมื่อ <?= e(dt($v['created_at'])) ?> โดย <?= e(user_name($v['created_by'])) ?></p>
  <div class="grid-4">
    <?php foreach (['fair_market_value' => 'Fair Market Value', 'recommended_acq_price' => 'ราคาแนะนำให้ซื้อ', 'max_acq_price' => 'ราคาซื้อสูงสุด',
                       'target_selling_price' => 'ราคาขายเป้าหมาย', 'min_selling_price' => 'ราคาขายต่ำสุด', 'expected_gp' => 'GP ที่คาด'] as $k => $lbl):
        if (!array_key_exists($k, $v)) continue; ?>
      <div class="stat"><div class="n" style="font-size:19px"><?= money($v[$k]) ?></div><div class="l"><?= e($lbl) ?><?= $k === 'expected_gp' ? ' (' . pct($v['expected_gp_margin']) . ')' : '' ?></div></div>
    <?php endforeach; ?>
  </div>
  <p class="muted" style="margin-top:10px">ความต้องการตลาด <?= e(label('demand', $v['demand'])) ?> · ความเสี่ยง <?= e(label('risk', $v['technical_risk'])) ?> · คาดขายได้ใน <?= $v['expected_days_to_sell'] !== null ? (int) $v['expected_days_to_sell'] . ' วัน' : '—' ?><?= $v['notes'] ? ' · ' . e($v['notes']) : '' ?></p>
</div>
<?php endif; ?>

<?php if (can('negotiation.view') && $d['negotiations']): ?>
<div class="card" id="negotiation">
  <h2>ประวัติการเจรจา</h2>
  <ul class="timeline">
    <?php foreach ($d['negotiations'] as $n): ?>
      <li><div class="meta"><?= e(dt($n['offered_at'])) ?> · <?= e($n['user_name']) ?></div>
        <div class="body"><b><?= e(label('negotiation_party', $n['party'])) ?>: <?= money($n['amount']) ?> บาท</b><?= $n['note'] ? ' — ' . e($n['note']) : '' ?></div></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php if ($d['approvals']): ?>
<div class="card flush" id="approvals">
  <div class="card-head"><h2>การอนุมัติ</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ขอเมื่อ</th><th>ผู้ขอ</th><th>ผล</th><th>GM</th><th>ความเห็น / เงื่อนไข</th><th></th></tr></thead>
    <tbody><?php foreach ($d['approvals'] as $a): ?>
      <tr><td><?= e(dt($a['requested_at'])) ?></td><td><?= e($a['requester_name']) ?></td><td><?= badge('approval', $a['decision']) ?><?= $a['approved_amount'] !== null && can_any('negotiation.view', 'acquisition.create') ? '<div class="muted">' . money($a['approved_amount']) . '</div>' : '' ?></td>
        <td><?= e($a['approver_name'] ?? '—') ?><div class="muted"><?= e(dt($a['decided_at'])) ?></div></td>
        <td class="pre"><?= e(trim(($a['comment'] ?? '') . ($a['condition_text'] ? "\nเงื่อนไข: " . $a['condition_text'] : ''))) ?: '—' ?></td>
        <td class="right"><?php if (can('acquisition.approve') || can('task.view_all') || can('acquisition.submit')): ?><a class="btn sm" href="<?= e(url('approvals.view', ['id' => $a['id']])) ?>">ชุดข้อมูล</a><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</div>
<?php endif; ?>

<?php if ($d['matches']): ?>
<div class="card flush">
  <div class="card-head"><h2>ผู้ซื้อที่สนใจเครื่องนี้</h2></div>
  <div class="table-wrap"><table class="table"><tbody>
    <?php foreach ($d['matches'] as $m): ?><tr><td><a href="<?= e(url('sales.view', ['id' => $m['sales_opportunity_id']])) ?>"><span class="ref"><?= e($m['so_ref']) ?></span></a> <?= e($m['buyer_name']) ?></td><td><?= badge('status', $m['status']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
<?php endif; ?>

<?php partial('tasks', ['parentType' => 'DEVICE_OPPORTUNITY', 'parentId' => $o['id'], 'tasks' => $d['tasks']]); ?>
<?php partial('activity', ['parentType' => 'DEVICE_OPPORTUNITY', 'parentId' => $o['id'], 'activities' => $d['activities'], 'followup' => $active && can('lead.edit')]); ?>
<?php partial('documents', ['parentType' => 'DEVICE_OPPORTUNITY', 'parentId' => $o['id'], 'documents' => $d['documents'], 'defaultCategory' => 'PHOTO']); ?>
