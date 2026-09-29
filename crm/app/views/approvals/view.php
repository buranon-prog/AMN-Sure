<?php
$op = $pkg['opportunity'] ?? [];
$dv = $pkg['device'] ?? [];
$in = $pkg['inspection'] ?? [];
$val = $pkg['valuation'] ?? [];
$cs = $pkg['cost_sheet'] ?? null;
$fp = $pkg['at_final_price'] ?? null;
$pending = $a['decision'] === 'PENDING';
?>
<div class="crumbs"><a href="<?= e(url('approvals')) ?>">อนุมัติการซื้อ</a> › <?= e($opp['ref_no']) ?></div>
<div class="page-head">
  <div>
    <h1>ชุดข้อมูลขออนุมัติซื้อ <span class="ref"><?= e($opp['ref_no']) ?></span></h1>
    <div class="sub"><?= badge('approval', $a['decision']) ?> ขอโดย <?= e(user_name($a['requested_by'])) ?> เมื่อ <?= e(dt($a['requested_at'])) ?> · ข้อมูล ณ เวลาที่ส่งขออนุมัติ</div>
  </div>
  <div class="actions no-print"><button class="btn" data-print>พิมพ์</button><a class="btn" href="<?= e(url('acq.view', ['id' => $opp['id']])) ?>">ไปหน้าดีล</a></div>
</div>

<?php if (!empty($pkg['flags'])): ?>
  <div class="flash warning"><b>ข้อควรระวัง:</b> <?= e(implode(' · ', $pkg['flags'])) ?></div>
<?php endif; ?>
<?php if ($a['request_note']): ?><div class="flash info">ข้อความจากผู้ขอ: <?= e($a['request_note']) ?></div><?php endif; ?>

<div class="grid-4">
  <div class="stat"><div class="n"><?= money($op['final_negotiated_price'] ?? null) ?></div><div class="l">ราคาที่ตกลงกับผู้ขาย</div></div>
  <?php if (isset($val['recommended_acq_price'])): ?><div class="stat"><div class="n"><?= money($val['recommended_acq_price']) ?></div><div class="l">ราคาแนะนำให้ซื้อ</div></div><?php endif; ?>
  <?php if (isset($val['max_acq_price'])): ?><div class="stat"><div class="n"><?= money($val['max_acq_price']) ?></div><div class="l">ราคาซื้อสูงสุด</div></div><?php endif; ?>
  <?php if ($fp): ?><div class="stat <?= (float) $fp['gp'] < 0 ? 'alert' : '' ?>"><div class="n"><?= money($fp['gp']) ?></div><div class="l">GP ที่ราคาตกลง (<?= pct($fp['margin']) ?>)</div></div><?php endif; ?>
</div>

<div class="grid-2" style="margin-top:16px">
  <div class="card">
    <h2>ผู้ขายและเครื่อง</h2>
    <dl class="kv">
      <dt>ผู้ขาย</dt><dd><?= e($pkg['seller']['name'] ?? '—') ?> <span class="muted"><?= e($pkg['seller']['ref_no'] ?? '') ?> · <?= e($pkg['seller']['phone'] ?? '') ?></span></dd>
      <dt>ผู้ติดต่อ</dt><dd><?= e(($pkg['contact']['name'] ?? '—') . (isset($pkg['contact']['phone']) ? ' · ' . $pkg['contact']['phone'] : '')) ?></dd>
      <dt>เครื่อง</dt><dd><?= e(($dv['brand'] ?? '') . ' ' . ($dv['model'] ?? '')) ?> <span class="ref"><?= e($dv['ref_no'] ?? '') ?></span></dd>
      <dt>Serial / ปี</dt><dd><?= e($dv['serial_number'] ?? '—') ?> · ผลิต <?= e($dv['manufacture_year'] ?? '—') ?> · ติดตั้ง <?= e($dv['installation_year'] ?? '—') ?></dd>
      <dt>การใช้งาน</dt><dd><?= e($dv['usage'] ?? '—') ?></dd>
      <dt>อุปกรณ์เสริม</dt><dd class="pre"><?= e($dv['accessories'] ?? '—') ?></dd>
      <dt>ผู้ขายต้องการ</dt><dd><?= money($op['expected_price'] ?? null) ?> (ตั้งไว้ <?= money($op['asking_price'] ?? null) ?>) · เหตุผล: <?= e($op['reason_for_sale'] ?? '—') ?></dd>
      <dt>ผู้รับผิดชอบดีล</dt><dd><?= e($op['owner'] ?? '—') ?></dd>
    </dl>
  </div>
  <div class="card">
    <h2>ผลตรวจและความเสี่ยง</h2>
    <dl class="kv">
      <dt>ผลตรวจรวม</dt><dd><?= badge('result', $in['overall_result'] ?? null) ?> สภาพ <?= e(label('condition', $in['overall_condition'] ?? null)) ?></dd>
      <dt>ความเสี่ยงทางเทคนิค</dt><dd><?= badge('risk', $in['technical_risk'] ?? null) ?></dd>
      <dt>ตรวจโดย</dt><dd><?= e($in['engineer'] ?? '—') ?> เมื่อ <?= e(dt($in['completed_at'] ?? null)) ?></dd>
      <dt>สรุปผลรายการ</dt><dd><?php foreach (($in['counts'] ?? []) as $r => $n): ?><?= badge('result', $r === 'NOT_CHECKED' ? null : $r) ?> ×<?= (int) $n ?> <?php endforeach; ?></dd>
      <dt>ปัญหาที่พบ</dt><dd class="pre"><?= e($in['issues'] ?? '—') ?></dd>
      <dt>ต้องซ่อม</dt><dd class="pre"><?= e($in['required_repair'] ?? '—') ?></dd>
      <dt>แนะนำให้ซ่อม</dt><dd class="pre"><?= e($in['recommended_repair'] ?? '—') ?></dd>
      <dt>ซ่อมโดยประมาณ</dt><dd><?= isset($in['est_repair_days']) ? (int) $in['est_repair_days'] . ' วัน' : '—' ?></dd>
    </dl>
    <?php if (!empty($in['problems'])): ?>
      <table class="table" style="margin-top:8px"><tbody>
        <?php foreach ($in['problems'] as $p): ?><tr><td><?= e(label('insp_category', $p['category'])) ?>: <?= e($p['item']) ?></td><td><?= badge('result', $p['result']) ?></td><td><?= e($p['note'] ?? '') ?></td></tr><?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <h2>ประวัติซ่อม / MA</h2>
    <?php if (empty($pkg['service_history']) && empty($pkg['ma'])): ?><div class="empty-state">ไม่มีประวัติ</div><?php endif; ?>
    <ul class="list-plain">
      <?php foreach ($pkg['service_history'] ?? [] as $s): ?>
        <li><?= e(d($s['service_date'])) ?> · <?= e(label('service_type', $s['type'])) ?><?= !empty($s['is_repeat_failure']) ? ' <span class="badge red">เสียซ้ำ</span>' : '' ?> — <?= e($s['description']) ?><?= array_key_exists('cost', $s) && $s['cost'] !== null ? ' · ' . money($s['cost']) . ' บาท' : '' ?></li>
      <?php endforeach; ?>
      <?php foreach ($pkg['ma'] ?? [] as $m): ?>
        <li>MA: <?= e($m['provider']) ?> ถึง <?= e(d($m['end_date'])) ?> <?= $m['end_date'] >= today() ? '<span class="badge green">มีผล</span>' : '<span class="badge gray">หมดอายุ</span>' ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div class="card">
    <h2>ประวัติการเจรจา</h2>
    <ul class="timeline">
      <?php foreach ($pkg['negotiations'] ?? [] as $n): ?>
        <li><div class="meta"><?= e(dt($n['at'])) ?> · <?= e($n['by']) ?></div><div class="body"><b><?= e(label('negotiation_party', $n['party'])) ?>: <?= money($n['amount']) ?></b><?= $n['note'] ? ' — ' . e($n['note']) : '' ?></div></li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>

<?php if ($cs): ?>
<div class="grid-2">
  <div class="card">
    <h2>Cost Sheet ฉบับที่ <?= (int) $cs['version_no'] ?> 🔒</h2>
    <table class="table"><tbody>
      <?php foreach ($cs['items'] as $it): ?><tr><td><?= e(label('cost_category', $it['category'])) ?></td><td><?= e($it['description'] ?? '') ?></td><td class="num"><?= money($it['amount']) ?></td></tr><?php endforeach; ?>
    </tbody><tfoot>
      <tr><td colspan="2">ต้นทุนรวม (ตามสมมติฐานราคาซื้อ <?= money($cs['acquisition_assumption']) ?>)</td><td class="num"><?= money($cs['total']) ?></td></tr>
      <?php if ($fp): ?><tr><td colspan="2">ต้นทุนรวมที่ราคาตกลงจริง</td><td class="num"><?= money($fp['cost']) ?></td></tr><?php endif; ?>
    </tfoot></table>
  </div>
  <div class="card">
    <h2>Valuation 🔒</h2>
    <dl class="kv">
      <?php foreach (['fair_market_value' => 'Fair Market Value', 'recommended_acq_price' => 'ราคาแนะนำให้ซื้อ', 'max_acq_price' => 'ราคาซื้อสูงสุด', 'target_selling_price' => 'ราคาขายเป้าหมาย',
                         'min_selling_price' => 'ราคาขายต่ำสุด', 'expected_gp' => 'GP ที่คาด (ตามสมมติฐาน)', 'market_selling_price' => 'ราคาตลาด', 'historical_selling_price' => 'ราคาที่เคยขายได้'] as $k => $lbl):
          if (!array_key_exists($k, $val)) continue; ?>
        <dt><?= e($lbl) ?></dt><dd><?= money($val[$k]) ?><?= $k === 'expected_gp' ? ' (' . pct($val['expected_gp_margin'] ?? null) . ')' : '' ?></dd>
      <?php endforeach; ?>
      <?php if (isset($val['demand'])): ?><dt>ความต้องการตลาด</dt><dd><?= e(label('demand', $val['demand'])) ?> · คาดขายได้ใน <?= e($val['expected_days_to_sell'] ?? '—') ?> วัน</dd><?php endif; ?>
    </dl>
  </div>
</div>
<?php endif; ?>

<?php if ($pending && can('acquisition.approve')): ?>
  <form method="post" action="<?= e(url('approvals.decide')) ?>" class="card highlight no-print" data-confirm="ยืนยันผลการพิจารณา? แก้ไขภายหลังไม่ได้">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($a['id']) ?>">
    <h2>ผลการพิจารณาของ GM</h2>
    <div class="field"><label>ผล <span class="req">*</span></label>
      <div class="radio-row">
        <?php foreach (APPROVAL_DECISIONS as $dec): ?><label><input type="radio" name="decision" id="dec_<?= e($dec) ?>" value="<?= e($dec) ?>" required> <?= e(label('approval', $dec)) ?></label><?php endforeach; ?>
      </div>
    </div>
    <div class="form-grid">
      <?= f_input('approved_amount', 'วงเงินที่อนุมัติ (ราคาซื้อจริงต้องไม่เกินนี้)', $op['final_negotiated_price'] ?? null, ['type' => 'money']) ?>
      <?= f_select('return_to', 'กรณีให้แก้ไข: ส่งกลับไปขั้น', ['NEGOTIATION' => 'เจรจาใหม่', 'COST_SHEET' => 'ทำ Cost Sheet ใหม่'], 'NEGOTIATION') ?>
      <?= f_textarea('condition_text', 'เงื่อนไข (กรณีอนุมัติแบบมีเงื่อนไข)', null, ['rows' => 2]) ?>
      <?= f_textarea('comment', 'ความเห็น / เหตุผล (บังคับกรณีให้แก้ไขหรือไม่อนุมัติ)', null, ['rows' => 2]) ?>
    </div>
    <button class="btn primary" type="submit">บันทึกผลการพิจารณา</button>
  </form>
<?php elseif (!$pending): ?>
  <div class="card">
    <h2>ผลการพิจารณา</h2>
    <dl class="kv">
      <dt>ผล</dt><dd><?= badge('approval', $a['decision']) ?> โดย <?= e(user_name($a['approver_id'])) ?> เมื่อ <?= e(dt($a['decided_at'])) ?></dd>
      <?php if ($a['approved_amount'] !== null): ?><dt>วงเงิน</dt><dd><?= money($a['approved_amount']) ?></dd><?php endif; ?>
      <?php if ($a['condition_text']): ?><dt>เงื่อนไข</dt><dd class="pre"><?= e($a['condition_text']) ?></dd><?php endif; ?>
      <?php if ($a['comment']): ?><dt>ความเห็น</dt><dd class="pre"><?= e($a['comment']) ?></dd><?php endif; ?>
    </dl>
  </div>
<?php endif; ?>
