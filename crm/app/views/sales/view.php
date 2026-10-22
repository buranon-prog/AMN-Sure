<?php
$s = $d['so'];
$st = $s['status'];
$active = !in_array($st, SO_CLOSED, true);
$steps = [];
foreach (SO_STEPS as $x) $steps[$x] = label('status', $x);
$selected = array_filter($d['matches'], function ($m) { return $m['status'] === 'SELECTED'; });
$openDeposit = null;
foreach ($d['deposits'] as $dep) {
    if (in_array($dep['status'], ['WAITING_DEPOSIT', 'DEPOSIT_RECEIVED'], true)) { $openDeposit = $dep; break; }
}
$openContract = null;
foreach ($d['contracts'] as $c) {
    if ($c['status'] !== 'VOID') { $openContract = $c; break; }
}
?>
<div class="crumbs"><a href="<?= e(url('sales')) ?>">ดีลขาย</a> › <?= e($s['ref_no']) ?></div>
<div class="page-head">
  <div>
    <h1><?= e($d['buyer']['name']) ?> <span class="ref"><?= e($s['ref_no']) ?></span></h1>
    <div class="sub"><?= badge('status', $st) ?> <span>ลีด: <a href="<?= e(url('leads.view', ['id' => $d['lead']['id']])) ?>"><?= e($d['lead']['ref_no']) ?></a></span>
      <span>สนใจ: <?= e($s['interested_device'] ?? '—') ?></span></div>
  </div>
  <div class="actions no-print">
    <?php if ($active && can('lead.edit')): ?>
      <details><summary class="btn danger">ปิดดีล (ไม่สำเร็จ)</summary>
        <form method="post" action="<?= e(url('sales.lost')) ?>" class="card" style="position:absolute;right:24px;z-index:5;min-width:320px" data-confirm="ปิดดีลนี้เป็น 'แพ้/ยกเลิก'?">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($s['id']) ?>">
          <?= f_input('reason', 'เหตุผล', null, ['required' => true, 'placeholder' => 'เช่น ลูกค้าซื้อเครื่องใหม่ / งบไม่พอ']) ?>
          <button class="btn danger" type="submit">ยืนยันปิดดีล</button>
        </form>
      </details>
    <?php endif; ?>
  </div>
</div>

<?php partial('stepper', ['steps' => $steps, 'current' => $st, 'stopped' => ['LOST']]); ?>

<div class="card highlight" id="next">
<?php if ($st === 'NEW_BUYER_LEAD' || $st === 'REQUIREMENT_DEFINED' && isset($_GET['edit_req'])): ?>
  <div class="what"><?= $st === 'NEW_BUYER_LEAD' ? 'ขั้นต่อไป: บันทึกความต้องการของผู้ซื้อ (Sales Director)' : 'แก้ไขความต้องการ' ?></div>
  <?php if (can('sales.edit')): ?>
    <form method="post" action="<?= e(url('sales.requirement')) ?>" style="margin-top:12px">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($s['id']) ?>">
      <div class="form-grid three">
        <?= f_input('req_brand', 'ยี่ห้อ', $s['req_brand']) ?>
        <?= f_input('req_model', 'รุ่น', $s['req_model']) ?>
        <?= f_input('req_technology', 'เทคโนโลยี / ประเภท', $s['req_technology'], ['placeholder' => 'เช่น Laser กำจัดขน']) ?>
        <?= f_input('budget_min', 'งบขั้นต่ำ', $s['budget_min'], ['type' => 'money']) ?>
        <?= f_input('budget_max', 'งบสูงสุด', $s['budget_max'], ['type' => 'money', 'required' => true]) ?>
        <?= f_input('expected_purchase_date', 'คาดว่าจะซื้อภายใน', $s['expected_purchase_date'], ['type' => 'date', 'required' => true]) ?>
        <?= f_select('preferred_condition', 'สภาพที่ต้องการ', labels('condition'), $s['preferred_condition'], ['blank' => 'ไม่ระบุ']) ?>
        <?= f_input('warranty_required', 'การรับประกันที่ต้องการ', $s['warranty_required']) ?>
        <?= f_checkbox('installation_required', 'ต้องติดตั้งให้ลูกค้า', (bool) $s['installation_required']) ?>
        <?= f_textarea('accessories_required', 'อุปกรณ์เสริมที่ต้องการ', $s['accessories_required'], ['rows' => 2]) ?>
        <?= f_textarea('requirement_note', 'รายละเอียดอื่น', $s['requirement_note'], ['rows' => 2, 'class' => 'span-2']) ?>
      </div>
      <button class="btn primary" type="submit">บันทึกความต้องการ</button>
    </form>
  <?php else: ?><p class="muted">รอ Sales Director บันทึกความต้องการ</p><?php endif; ?>

<?php elseif (in_array($st, ['REQUIREMENT_DEFINED', 'MATCHING'], true)): ?>
  <div class="next-action">
    <div><div class="what"><?= $st === 'REQUIREMENT_DEFINED' ? 'ขั้นต่อไป: หาเครื่องที่ตรงความต้องการ' : 'เลือกเครื่องที่จะเสนอ แล้วออกใบเสนอราคา' ?></div>
      <div class="muted">เลือกแล้ว <?= count($selected) ?> เครื่อง จากที่จับคู่ไว้ <?= count($d['matches']) ?> เครื่อง</div></div>
    <div class="actions">
      <?php if (can('sales.edit')): ?><a class="btn" href="<?= e(url('sales.match', ['id' => $s['id']])) ?>">ค้นหา/จับคู่เครื่อง</a><?php endif; ?>
      <?php if (can('quotation.edit') && $st === 'MATCHING'): ?><?= post_button('sales.quotation', ['id' => $s['id']], 'ออกใบเสนอราคา', ['class' => 'primary']) ?><?php endif; ?>
    </div>
  </div>

<?php elseif ($st === 'QUOTING'): ?>
  <div class="what">ปิดการขาย (WON) อัตโนมัติเมื่อครบเงื่อนไข</div>
  <ul class="list-plain" style="margin-top:8px">
    <?php $qvOk = $d['qv'] && $d['qv']['status'] === 'ACCEPTED'; ?>
    <li><?= $qvOk ? '✅' : '⬜' ?> ลูกค้าตอบรับใบเสนอราคา <?php if ($d['quotation']): ?><a href="<?= e(url('quotations.view', ['id' => $d['quotation']['id']])) ?>"><span class="ref"><?= e($d['quotation']['ref_no']) ?></span> ฉบับที่ <?= (int) $d['qv']['version_no'] ?></a> <?= badge('status', $d['qv']['status']) ?><?php endif; ?></li>
    <li><?= $openDeposit && $openDeposit['status'] === 'DEPOSIT_RECEIVED' ? '✅' : '⬜' ?> ได้รับมัดจำ <?= $openDeposit ? badge('status', $openDeposit['status']) . ' ' . money($openDeposit['required_amount']) . ' บาท' : '' ?><?= (int) setting('won_require_deposit_verified', 0) === 1 ? ' (ต้องให้ GM ยืนยันยอด)' : '' ?></li>
    <li><?= $openContract && $openContract['status'] === 'CONTRACT_SIGNED' ? '✅' : '⬜' ?> ลงนามสัญญา <?= $openContract ? e($openContract['contract_no']) . ' ' . badge('status', $openContract['status']) : '' ?></li>
  </ul>
  <?php if ($d['won_missing']): ?><p class="muted">ยังขาด: <?= e(implode(', ', $d['won_missing'])) ?></p><?php endif; ?>

<?php elseif ($st === 'WON' && $d['transaction']): ?>
  <div class="next-action"><div><div class="what">🎉 ปิดการขายได้ → ธุรกรรม <span class="ref"><?= e($d['transaction']['ref_no']) ?></span> <?= badge('status', $d['transaction']['status']) ?></div>
    <div class="muted">ขั้นต่อไป: เช็กลิสต์ภายใน → QC → ส่งมอบ → ติดตั้ง</div></div>
    <a class="btn primary" href="<?= e(url('trx.view', ['id' => $d['transaction']['id']])) ?>">ไปที่ธุรกรรม</a></div>

<?php else: ?>
  <div class="what"><?= e(label('status', $st)) ?><?= $s['lost_reason'] ? ': ' . e($s['lost_reason']) : '' ?></div>
<?php endif; ?>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><h2>ผู้ซื้อและความต้องการ</h2><?php if ($st === 'REQUIREMENT_DEFINED' || $st === 'MATCHING' || $st === 'QUOTING'): ?><?php if (can('sales.edit') && $st === 'REQUIREMENT_DEFINED'): ?><a class="btn sm" href="<?= e(url('sales.view', ['id' => $s['id'], 'edit_req' => 1])) ?>">แก้ไข</a><?php endif; ?><?php endif; ?></div>
    <dl class="kv">
      <dt>ผู้ซื้อ</dt><dd><a href="<?= e(url('customers.view', ['id' => $d['buyer']['id']])) ?>"><?= e($d['buyer']['name']) ?></a> · <?= e($d['buyer']['phone'] ?? $d['buyer']['line_id'] ?? '') ?></dd>
      <dt>ผู้ติดต่อ</dt><dd><?= $d['contact'] ? e($d['contact']['name'] . ' · ' . ($d['contact']['phone'] ?? $d['contact']['line_id'] ?? '')) : '—' ?></dd>
      <dt>ต้องการ</dt><dd><?= e(trim(($s['req_brand'] ?? '') . ' ' . ($s['req_model'] ?? '')) ?: '—') ?><?= $s['req_technology'] ? ' · ' . e($s['req_technology']) : '' ?></dd>
      <dt>งบประมาณ</dt><dd><?= $s['budget_min'] !== null ? money0($s['budget_min']) . ' – ' : '' ?><?= money0($s['budget_max']) ?></dd>
      <dt>คาดว่าจะซื้อ</dt><dd><?= e(d($s['expected_purchase_date'])) ?><?= $s['timeline'] ? ' · ' . e($s['timeline']) : '' ?></dd>
      <dt>สภาพ / ประกัน</dt><dd><?= e(label('condition', $s['preferred_condition'])) ?> · <?= e($s['warranty_required'] ?? '—') ?></dd>
      <dt>ติดตั้ง</dt><dd><?= $s['installation_required'] ? 'ต้องติดตั้ง' : 'ไม่ต้องติดตั้ง' ?></dd>
      <dt>อุปกรณ์เสริม</dt><dd class="pre"><?= e($s['accessories_required'] ?? '—') ?></dd>
      <?php if ($s['requirement_note']): ?><dt>รายละเอียด</dt><dd class="pre"><?= e($s['requirement_note']) ?></dd><?php endif; ?>
    </dl>
  </div>
  <div class="card">
    <h2>การติดตาม</h2>
    <?php if ($active && can('lead.edit')): ?>
      <form method="post" action="<?= e(url('leads.followup')) ?>">
        <?= csrf_field() ?><input type="hidden" name="kind" value="SO"><input type="hidden" name="id" value="<?= e($s['id']) ?>">
        <?= f_select('owner_id', 'ผู้รับผิดชอบ', user_options(), $s['owner_id'], ['required' => true]) ?>
        <?= f_input('next_action', 'Next action', $s['next_action'], ['required' => true]) ?>
        <div class="field"><label for="f_nfd">วันติดตามครั้งถัดไป <span class="req">*</span></label><input type="date" id="f_nfd" name="next_follow_up_date" value="<?= e(old('next_follow_up_date', $s['next_follow_up_date'])) ?>" required>
          <div class="hint"><?= followup_badge($s['next_follow_up_date']) ?></div></div>
        <button class="btn sm" type="submit">อัปเดตการติดตาม</button>
      </form>
    <?php else: ?>
      <dl class="kv"><dt>ผู้รับผิดชอบ</dt><dd><?= e(user_name($s['owner_id'])) ?></dd><dt>Next action</dt><dd><?= e($s['next_action'] ?? '—') ?></dd><dt>วันติดตาม</dt><dd><?= followup_badge($s['next_follow_up_date'], $active) ?></dd></dl>
    <?php endif; ?>
  </div>
</div>

<?php if ($d['matches']): ?>
<div class="card flush" id="matches">
  <div class="card-head"><h2>เครื่องที่จับคู่</h2><?php if (can('sales.edit') && in_array($st, ['REQUIREMENT_DEFINED', 'MATCHING', 'QUOTING'], true)): ?><a class="btn sm" href="<?= e(url('sales.match', ['id' => $s['id']])) ?>">+ จับคู่เพิ่ม</a><?php endif; ?></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>เครื่อง</th><th>แหล่ง</th><th>สถานะเครื่อง</th><th class="num">ราคาตั้งขาย</th><th>การจับคู่</th><th></th></tr></thead>
    <tbody><?php foreach ($d['matches'] as $m): ?>
      <tr>
        <td><a href="<?= e(url('devices.view', ['id' => $m['device_id']])) ?>"><b><?= e($m['brand']) ?></b> <?= e($m['model']) ?></a><div class="muted"><?= e($m['device_ref']) ?><?= $m['serial_number'] ? ' · S/N ' . e($m['serial_number']) : '' ?></div><?= $m['note'] ? '<div class="muted">' . e($m['note']) . '</div>' : '' ?></td>
        <td><?= e(label('match_source', $m['source'])) ?><div class="muted"><?= e($m['inv_ref'] ?? $m['opp_ref'] ?? '') ?> <?= $m['opp_status'] ? badge('status', $m['opp_status']) : '' ?></div></td>
        <td><?= badge('commercial', $m['commercial_status']) ?> <?= badge('technical', $m['technical_status']) ?></td>
        <td class="num"><?= money($m['list_price']) ?></td>
        <td><?= badge('status', $m['status']) ?></td>
        <td class="right"><?php if (can('sales.edit') && in_array($st, ['REQUIREMENT_DEFINED', 'MATCHING', 'QUOTING'], true)): ?>
          <form method="post" action="<?= e(url('sales.match_status')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="match_id" value="<?= e($m['id']) ?>">
            <select name="status" data-autosubmit aria-label="สถานะการจับคู่"><?php foreach (MATCH_STATUSES as $ms): ?><option value="<?= e($ms) ?>" <?= $m['status'] === $ms ? 'selected' : '' ?>><?= e(label('status', $ms)) ?></option><?php endforeach; ?></select>
            <noscript><button class="btn sm">บันทึก</button></noscript>
          </form>
        <?php endif; ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php if (array_filter($d['matches'], function ($m) { return $m['source'] === 'SELLER_OPPORTUNITY'; })): ?>
    <p class="hint" style="padding:0 18px 12px">เครื่องจากผู้ขายที่ AMN Sure ยังไม่ได้ซื้อ ต้องผ่านการอนุมัติและบันทึกการซื้อก่อนจึงจะส่งมอบได้</p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($d['quotation']): ?>
<div class="card" id="quotation">
  <div class="card-head"><h2>ใบเสนอราคา</h2><a class="btn sm" href="<?= e(url('quotations.view', ['id' => $d['quotation']['id']])) ?>">เปิดใบเสนอราคา</a></div>
  <p><span class="ref"><?= e($d['quotation']['ref_no']) ?></span> ฉบับที่ <?= (int) $d['qv']['version_no'] ?> <?= badge('status', $d['qv']['status']) ?> · ยอดรวม <b><?= money($d['qv']['total']) ?></b> บาท · ยืนราคาถึง <?= e(d($d['qv']['valid_until'])) ?></p>
</div>
<?php endif; ?>

<?php if (can('deposit.view') && ($d['deposits'] || $st === 'QUOTING')): ?>
<div class="card" id="deposits">
  <h2>มัดจำ</h2>
  <?php foreach ($d['deposits'] as $dep): ?>
    <div class="card" style="box-shadow:none">
      <div class="card-head"><div><b><?= money($dep['required_amount']) ?> บาท</b> <?= badge('status', $dep['status']) ?> <span class="muted">กำหนดชำระ <?= e(d($dep['due_date'])) ?></span>
        <?= $dep['verified_at'] ? ' <span class="badge green">GM ยืนยันแล้ว</span>' : '' ?></div></div>
      <?php if ($dep['status'] === 'DEPOSIT_RECEIVED'): ?><p class="muted">รับ <?= money($dep['received_amount']) ?> บาท เมื่อ <?= e(d($dep['received_date'])) ?><?= $dep['verified_at'] ? ' · ยืนยันโดย ' . e(user_name($dep['verified_by'])) : '' ?></p><?php endif; ?>
      <?php if ($dep['status_reason']): ?><p class="muted">เหตุผล: <?= e($dep['status_reason']) ?></p><?php endif; ?>
      <?php $evid = documents_for('DEPOSIT', $dep['id']); if ($evid): ?><p>หลักฐาน: <?php foreach ($evid as $ev): ?><a href="<?= e(url('docs.download', ['id' => $ev['id']])) ?>" target="_blank"><?= e($ev['title']) ?></a> <?php endforeach; ?></p><?php endif; ?>
      <div class="actions">
        <?php if ($dep['status'] === 'WAITING_DEPOSIT' && can('deposit.edit') && $st === 'QUOTING'): ?>
          <details><summary class="btn sm primary">บันทึกรับมัดจำ</summary>
            <form method="post" action="<?= e(url('sales.deposit_receive')) ?>" enctype="multipart/form-data" style="margin-top:8px">
              <?= csrf_field() ?><input type="hidden" name="deposit_id" value="<?= e($dep['id']) ?>">
              <div class="form-grid three">
                <?= f_input('received_amount', 'ยอดที่ได้รับ', $dep['required_amount'], ['type' => 'money', 'required' => true]) ?>
                <?= f_input('received_date', 'วันที่รับเงิน', today(), ['type' => 'date', 'required' => true]) ?>
                <div class="field"><label for="f_evidence">หลักฐานการโอน <span class="req">*</span></label><input type="file" name="evidence" id="f_evidence" accept="image/*,application/pdf"></div>
              </div>
              <button class="btn primary sm" type="submit">บันทึก</button>
            </form>
          </details>
          <details><summary class="btn sm">แก้ยอด/กำหนดชำระ</summary>
            <form method="post" action="<?= e(url('sales.deposit')) ?>" style="margin-top:8px">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($s['id']) ?>"><input type="hidden" name="deposit_id" value="<?= e($dep['id']) ?>">
              <div class="form-grid"><?= f_input('required_amount', 'ยอดมัดจำ', $dep['required_amount'], ['type' => 'money', 'required' => true]) ?><?= f_input('due_date', 'กำหนดชำระ', $dep['due_date'], ['type' => 'date']) ?></div>
              <button class="btn sm" type="submit">บันทึก</button>
            </form>
          </details>
        <?php endif; ?>
        <?php if ($dep['status'] === 'DEPOSIT_RECEIVED' && !$dep['verified_at'] && can('deposit.verify')): ?><?= post_button('sales.deposit_verify', ['deposit_id' => $dep['id']], 'GM ยืนยันยอด', ['class' => 'sm']) ?><?php endif; ?>
        <?php if (in_array($dep['status'], ['WAITING_DEPOSIT', 'DEPOSIT_RECEIVED'], true) && can('record.void') && $st !== 'WON'): ?>
          <details><summary class="btn sm danger">คืน/ริบมัดจำ</summary>
            <form method="post" action="<?= e(url('sales.deposit_close')) ?>" style="margin-top:8px" data-confirm="ยืนยัน?">
              <?= csrf_field() ?><input type="hidden" name="deposit_id" value="<?= e($dep['id']) ?>">
              <div class="form-grid"><?= f_select('status', 'ผล', ['REFUNDED' => 'คืนมัดจำ', 'FORFEITED' => 'ริบมัดจำ'], 'REFUNDED') ?><?= f_input('reason', 'เหตุผล', null, ['required' => true]) ?></div>
              <button class="btn sm danger" type="submit">บันทึก</button>
            </form>
          </details>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$openDeposit && $st === 'QUOTING' && can('deposit.edit')): ?>
    <form method="post" action="<?= e(url('sales.deposit')) ?>">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($s['id']) ?>">
      <div class="form-grid three">
        <?= f_input('required_amount', 'ยอดมัดจำที่ต้องชำระ', $d['qv'] ? round((float) $d['qv']['total'] * (float) setting('deposit_default_pct', 30) / 100, 2) : null, ['type' => 'money', 'required' => true]) ?>
        <?= f_input('due_date', 'กำหนดชำระ', add_days(today(), 7), ['type' => 'date']) ?>
      </div>
      <button class="btn sm" type="submit">กำหนดมัดจำ</button>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if (can('contract.view') && ($d['contracts'] || $st === 'QUOTING')): ?>
<div class="card" id="contracts">
  <h2>สัญญา</h2>
  <?php foreach ($d['contracts'] as $c): ?>
    <div class="card" style="box-shadow:none">
      <div class="card-head"><div><b><?= e($c['contract_no']) ?></b> <?= badge('status', $c['status']) ?> · มูลค่า <?= money($c['price']) ?> บาท<?= $c['signed_date'] ? ' · ลงนาม ' . e(d($c['signed_date'])) : '' ?></div></div>
      <dl class="kv">
        <dt>การชำระเงิน</dt><dd class="pre"><?= e($c['payment_terms'] ?? '—') ?></dd>
        <dt>รับประกัน</dt><dd class="pre"><?= e($c['warranty_terms'] ?? '—') ?></dd>
        <dt>ส่งมอบ / ติดตั้ง</dt><dd class="pre"><?= e(trim(($c['delivery_terms'] ?? '') . "\n" . ($c['installation_terms'] ?? '')) ?: '—') ?></dd>
        <?php if ($c['void_reason']): ?><dt>เหตุผลยกเลิก</dt><dd><?= e($c['void_reason']) ?></dd><?php endif; ?>
      </dl>
      <?php $cdocs = documents_for('CONTRACT', $c['id']); if ($cdocs): ?><p>ไฟล์: <?php foreach ($cdocs as $cd): ?><a href="<?= e(url('docs.download', ['id' => $cd['id']])) ?>" target="_blank"><?= e($cd['title']) ?></a> <?php endforeach; ?></p><?php endif; ?>
      <div class="actions">
        <?php if ($c['status'] === 'DRAFT' && can('contract.edit') && $st === 'QUOTING'): ?>
          <details><summary class="btn sm primary">บันทึกการลงนาม</summary>
            <form method="post" action="<?= e(url('sales.contract_sign')) ?>" enctype="multipart/form-data" style="margin-top:8px">
              <?= csrf_field() ?><input type="hidden" name="contract_id" value="<?= e($c['id']) ?>">
              <div class="form-grid">
                <?= f_input('signed_date', 'วันที่ลงนาม', today(), ['type' => 'date', 'required' => true]) ?>
                <div class="field"><label for="f_signed">ไฟล์สัญญาที่ลงนามแล้ว <span class="req">*</span></label><input type="file" name="signed_file" id="f_signed" accept="image/*,application/pdf"></div>
              </div>
              <button class="btn sm primary" type="submit">บันทึก</button>
            </form>
          </details>
          <details><summary class="btn sm">แก้ไขร่างสัญญา</summary>
            <form method="post" action="<?= e(url('sales.contract')) ?>" style="margin-top:8px">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($s['id']) ?>"><input type="hidden" name="contract_id" value="<?= e($c['id']) ?>">
              <div class="form-grid">
                <?= f_input('contract_no', 'เลขที่สัญญา', $c['contract_no']) ?>
                <?= f_input('price', 'มูลค่าสัญญา', $c['price'], ['type' => 'money']) ?>
                <?= f_textarea('payment_terms', 'เงื่อนไขการชำระ', $c['payment_terms'], ['rows' => 2]) ?>
                <?= f_textarea('warranty_terms', 'การรับประกัน', $c['warranty_terms'], ['rows' => 2]) ?>
                <?= f_textarea('delivery_terms', 'การส่งมอบ', $c['delivery_terms'], ['rows' => 2]) ?>
                <?= f_textarea('installation_terms', 'การติดตั้ง', $c['installation_terms'], ['rows' => 2]) ?>
              </div>
              <button class="btn sm" type="submit">บันทึก</button>
            </form>
          </details>
        <?php endif; ?>
        <?php if ($c['status'] !== 'VOID' && can('record.void') && $st !== 'WON'): ?>
          <details><summary class="btn sm danger">ยกเลิกสัญญา</summary>
            <form method="post" action="<?= e(url('sales.contract_void')) ?>" style="margin-top:8px" data-confirm="ยกเลิกสัญญานี้?">
              <?= csrf_field() ?><input type="hidden" name="contract_id" value="<?= e($c['id']) ?>">
              <?= f_input('reason', 'เหตุผล', null, ['required' => true]) ?>
              <button class="btn sm danger" type="submit">ยืนยัน</button>
            </form>
          </details>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$openContract && $st === 'QUOTING' && can('contract.edit') && $d['quotation']): ?>
    <form method="post" action="<?= e(url('sales.contract')) ?>">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($s['id']) ?>">
      <div class="form-grid three">
        <?= f_input('contract_no', 'เลขที่สัญญา (เว้นว่าง = ระบบออกเลขให้)', null) ?>
        <?= f_input('price', 'มูลค่าสัญญา (เว้นว่าง = ยอดใบเสนอราคา)', null, ['type' => 'money']) ?>
      </div>
      <button class="btn sm" type="submit">ร่างสัญญาจากใบเสนอราคา</button>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php partial('tasks', ['parentType' => 'SALES_OPPORTUNITY', 'parentId' => $s['id'], 'tasks' => $d['tasks']]); ?>
<?php partial('activity', ['parentType' => 'SALES_OPPORTUNITY', 'parentId' => $s['id'], 'activities' => $d['activities'], 'followup' => $active && can('lead.edit')]); ?>
<?php partial('documents', ['parentType' => 'SALES_OPPORTUNITY', 'parentId' => $s['id'], 'documents' => $d['documents'], 'defaultCategory' => 'OTHER']); ?>
