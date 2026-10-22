<?php
$assumption = 0.0;
foreach ($csItems as $it) if ($it['category'] === 'ACQUISITION_ASSUMPTION') $assumption += (float) $it['amount'];
$p = $prev ?? [];
$age = $device['manufacture_year'] ? (int) date('Y') - (int) $device['manufacture_year'] : null;
?>
<div class="crumbs"><a href="<?= e(url('acq')) ?>">ดีลซื้อ</a> › <a href="<?= e(url('acq.view', ['id' => $opp['id']])) ?>"><?= e($opp['ref_no']) ?></a> › Valuation</div>
<div class="page-head">
  <div><h1>ประเมินมูลค่า (Valuation)</h1>
    <div class="sub"><?= e($device['brand'] . ' ' . $device['model']) ?> <span class="ref"><?= e($device['ref_no']) ?></span> · บันทึกเป็น record ใหม่ทุกครั้ง ไม่เขียนทับ Cost Sheet (BR-06)</div></div>
</div>

<div class="grid-3">
  <div class="stat"><div class="n"><?= money($cs['total_estimated_cost']) ?></div><div class="l">ต้นทุนรวม (Cost Sheet ฉบับที่ <?= (int) $cs['version_no'] ?>)</div></div>
  <div class="stat"><div class="n"><?= money($assumption) ?></div><div class="l">ในนั้นเป็นราคาซื้อสมมติฐาน</div></div>
  <div class="stat"><div class="n"><?= money($opp['expected_price']) ?></div><div class="l">ราคาที่ผู้ขายต้องการ (ตั้งไว้ <?= money($opp['asking_price']) ?>)</div></div>
</div>

<div class="grid-2" style="margin-top:16px">
  <div class="card">
    <h2>ข้อมูลประกอบ</h2>
    <dl class="kv">
      <dt>ผลตรวจ</dt><dd><?= $insp ? badge('result', $insp['overall_result']) . ' สภาพ ' . e(label('condition', $insp['overall_condition'])) . ' · ความเสี่ยง ' . badge('risk', $insp['technical_risk']) : '—' ?></dd>
      <dt>อายุเครื่อง</dt><dd><?= $age !== null ? $age . ' ปี (ผลิต ' . (int) $device['manufacture_year'] . ')' : '—' ?></dd>
      <dt>การใช้งาน</dt><dd><?= $device['usage_value'] !== null ? e(number_format((float) $device['usage_value']) . ' ' . label('usage_unit', $device['usage_unit'])) : '—' ?></dd>
      <dt>ราคาที่เคยขายรุ่นนี้</dt><dd><?php if ($history): foreach ($history as $h): ?><?= money($h['unit_price']) ?> <span class="muted">(<?= e(d($h['won_at'])) ?>)</span><br><?php endforeach; else: ?>ยังไม่มีประวัติในระบบ<?php endif; ?></dd>
    </dl>
    <table class="table" style="margin-top:10px"><tbody>
      <?php foreach ($csItems as $it): ?><tr><td><?= e(label('cost_category', $it['category'])) ?></td><td><?= e($it['description'] ?? '') ?></td><td class="num"><?= money($it['amount']) ?></td></tr><?php endforeach; ?>
    </tbody></table>
  </div>

  <form method="post" action="<?= e(url('acq.valuation')) ?>" class="card">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($opp['id']) ?>">
    <h2>ข้อมูลตลาด</h2>
    <div class="form-grid">
      <?= f_input('market_selling_price', 'ราคาขายในตลาด', $p['market_selling_price'] ?? null, ['type' => 'money']) ?>
      <?= f_input('historical_selling_price', 'ราคาที่เคยขายได้', $p['historical_selling_price'] ?? ($history[0]['unit_price'] ?? null), ['type' => 'money']) ?>
      <?= f_select('device_condition', 'สภาพ', labels('condition'), $p['device_condition'] ?? ($insp['overall_condition'] ?? null), ['blank' => '—']) ?>
      <?= f_select('demand', 'ความต้องการในตลาด', labels('demand'), $p['demand'] ?? null, ['blank' => '—']) ?>
      <?= f_select('technical_risk', 'ความเสี่ยงทางเทคนิค', labels('risk'), $p['technical_risk'] ?? ($insp['technical_risk'] ?? null), ['blank' => '—']) ?>
      <?= f_input('expected_days_to_sell', 'คาดว่าจะขายได้ใน (วัน)', $p['expected_days_to_sell'] ?? null, ['type' => 'number']) ?>
    </div>
    <h2>ผลการประเมิน (กรอกโดยผู้ประเมิน — ระบบไม่คำนวณแทน)</h2>
    <div class="form-grid">
      <?= f_input('fair_market_value', 'Fair Market Value', $p['fair_market_value'] ?? null, ['type' => 'money', 'required' => true]) ?>
      <?= f_input('recommended_acq_price', 'ราคาแนะนำให้ซื้อ', $p['recommended_acq_price'] ?? null, ['type' => 'money', 'required' => true]) ?>
      <?= f_input('max_acq_price', 'ราคาซื้อสูงสุด', $p['max_acq_price'] ?? null, ['type' => 'money', 'required' => true]) ?>
      <?= f_input('target_selling_price', 'ราคาขายเป้าหมาย', $p['target_selling_price'] ?? null, ['type' => 'money', 'required' => true]) ?>
      <?= f_input('min_selling_price', 'ราคาขายต่ำสุด', $p['min_selling_price'] ?? null, ['type' => 'money', 'required' => true, 'hint' => 'ใบเสนอราคาที่ต่ำกว่านี้ต้องให้ GM อนุมัติ']) ?>
      <?= f_textarea('notes', 'เหตุผลประกอบ', $p['notes'] ?? null, ['rows' => 2, 'class' => 'full']) ?>
    </div>
    <p class="hint">ระบบคำนวณ Expected GP = ราคาขายเป้าหมาย − ต้นทุนรวม (<?= money($cs['total_estimated_cost']) ?>) และ GP Margin = GP ÷ ราคาขายเป้าหมาย</p>
    <div class="form-actions"><button class="btn primary" type="submit">บันทึก Valuation</button><a class="btn" href="<?= e(url('acq.view', ['id' => $opp['id']])) ?>">ยกเลิก</a></div>
  </form>
</div>
