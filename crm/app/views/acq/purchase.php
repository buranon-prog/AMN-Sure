<div class="crumbs"><a href="<?= e(url('acq')) ?>">ดีลซื้อ</a> › <a href="<?= e(url('acq.view', ['id' => $opp['id']])) ?>"><?= e($opp['ref_no']) ?></a> › บันทึกการซื้อ</div>
<div class="page-head"><div><h1>บันทึกการซื้อ (Acquisition)</h1>
  <div class="sub"><?= e($device['brand'] . ' ' . $device['model']) ?> <span class="ref"><?= e($device['ref_no']) ?></span> จาก <?= e($seller['name']) ?></div></div></div>

<?php if (!$approval || !in_array($approval['decision'], ['APPROVED', 'APPROVED_WITH_CONDITION'], true)): ?>
  <div class="flash error">ยังไม่ได้รับอนุมัติจาก GM (BR-07)</div>
<?php else: ?>
<div class="card highlight">
  <dl class="kv">
    <dt>ผลการอนุมัติ</dt><dd><?= badge('approval', $approval['decision']) ?> โดย <?= e(user_name($approval['approver_id'])) ?> เมื่อ <?= e(dt($approval['decided_at'])) ?></dd>
    <dt>วงเงินที่อนุมัติ</dt><dd><b><?= money($approval['approved_amount']) ?> บาท</b> (ราคาซื้อจริงต้องไม่เกินนี้)</dd>
    <?php if ($approval['condition_text']): ?><dt>เงื่อนไข</dt><dd class="pre"><b><?= e($approval['condition_text']) ?></b></dd><?php endif; ?>
    <?php if ($approval['comment']): ?><dt>ความเห็น GM</dt><dd class="pre"><?= e($approval['comment']) ?></dd><?php endif; ?>
  </dl>
</div>
<form method="post" action="<?= e(url('acq.purchase')) ?>" class="card" data-confirm="ยืนยันบันทึกการซื้อ? เครื่องจะเปลี่ยนเป็นของ AMN Sure">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($opp['id']) ?>">
  <div class="form-grid three">
    <?= f_input('purchase_price', 'ราคาซื้อจริง (บาท)', $opp['final_negotiated_price'], ['type' => 'money', 'required' => true]) ?>
    <?= f_input('purchase_date', 'วันที่ซื้อ', today(), ['type' => 'date', 'required' => true]) ?>
    <?= f_select('payment_status', 'สถานะการจ่ายเงินให้ผู้ขาย', labels('payment_status'), 'UNPAID') ?>
  </div>
  <?php if ($approval['decision'] === 'APPROVED_WITH_CONDITION'): ?>
    <?= f_checkbox('conditions_confirmed', 'ยืนยันว่าได้ทำตามเงื่อนไขของ GM ครบแล้ว', false) ?>
  <?php endif; ?>
  <fieldset>
    <legend>เข้าสต็อก</legend>
    <?= f_checkbox('takes_stock', 'AMN Sure รับเครื่องเข้าสต็อก (สร้างรายการ Inventory)', true, ['hint' => 'ไม่ติ๊กถ้าส่งต่อให้ผู้ซื้อโดยตรงโดยไม่เข้าคลัง']) ?>
    <div class="form-grid" data-show-when="f_takes_stock=1">
      <?= f_input('storage_location', 'ที่เก็บ', null) ?>
      <?= f_input('list_price', 'ราคาตั้งขาย (ใช้เป็นราคาเริ่มต้นในใบเสนอราคา)', null, ['type' => 'money']) ?>
    </div>
  </fieldset>
  <?= f_textarea('notes', 'หมายเหตุ', null, ['rows' => 2]) ?>
  <div class="form-actions"><button class="btn primary" type="submit">บันทึกการซื้อ</button><a class="btn" href="<?= e(url('acq.view', ['id' => $opp['id']])) ?>">ยกเลิก</a></div>
</form>
<?php endif; ?>
