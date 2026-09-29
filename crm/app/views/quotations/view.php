<?php
$isCurrent = (int) $v['version_no'] === (int) $q['current_version_no'];
$soOpen = $so['status'] === 'QUOTING';
$editable = $isCurrent && $v['status'] === 'DRAFT' && $soOpen && can('quotation.edit');
$devOpts = [];
foreach ($matched as $m) $devOpts[$m['device_id']] = $m['ref_no'] . ' ' . $m['brand'] . ' ' . $m['model'];
$rows = $lines ?: [['device_id' => null, 'description' => '', 'device_condition' => null, 'accessories' => null, 'qty' => 1, 'unit_price' => null]];
?>
<div class="crumbs"><a href="<?= e(url('quotations')) ?>">ใบเสนอราคา</a> › <a href="<?= e(url('sales.view', ['id' => $so['id']])) ?>"><?= e($so['ref_no']) ?></a> › <?= e($q['ref_no']) ?></div>
<div class="page-head">
  <div>
    <h1>ใบเสนอราคา <span class="ref"><?= e($q['ref_no']) ?></span> ฉบับที่ <?= (int) $v['version_no'] ?></h1>
    <div class="sub"><?= badge('status', $v['status']) ?><?= $v['status'] === 'DRAFT' && $v['approval_requested_at'] ? ' <span class="badge amber">ขออนุมัติแล้ว ' . e(dt($v['approval_requested_at'])) . '</span>' : '' ?>
      <span><?= e($buyer['name']) ?></span><?= !$isCurrent ? ' <span class="badge gray">ฉบับเก่า (อ่านอย่างเดียว)</span>' : '' ?></div>
  </div>
  <div class="actions no-print">
    <a class="btn" href="<?= e(url('quotations.print', ['id' => $q['id'], 'v' => $v['version_no']])) ?>" target="_blank">พิมพ์ / PDF</a>
    <?php if ($isCurrent && $soOpen && can('quotation.edit') && !in_array($v['status'], ['DRAFT', 'ACCEPTED'], true)): ?>
      <?= post_button('quotations.revise', ['id' => $q['id']], 'ออกฉบับแก้ไข', ['confirm' => 'ออกฉบับแก้ไข? ฉบับนี้จะถูกแทนที่และแก้ไขไม่ได้อีก']) ?>
    <?php endif; ?>
  </div>
</div>

<?php if ($below): ?><div class="flash warning">ราคาต่ำกว่าราคาขายขั้นต่ำ (จาก Valuation) ในรายการ: <?= e(implode(', ', $below)) ?> — ต้องให้ GM อนุมัติ</div><?php endif; ?>

<?php if ($isCurrent && $soOpen): ?>
<div class="card highlight no-print">
  <?php if ($v['status'] === 'DRAFT'): ?>
    <div class="next-action">
      <div><div class="what"><?= $v['approval_requested_at'] ? 'รออนุมัติจาก ' . ($below ? 'GM' : 'Sales Director / GM') : 'กรอกราคาและเงื่อนไข แล้วบันทึกและส่งขออนุมัติ' ?></div></div>
      <?php if (can('quotation.approve') && (float) $v['total'] > 0): ?><?= post_button('quotations.approve', ['version_id' => $v['id']], 'อนุมัติใบเสนอราคา', ['class' => 'primary', 'confirm' => 'อนุมัติใบเสนอราคานี้?']) ?><?php endif; ?>
    </div>
  <?php elseif ($v['status'] === 'APPROVED'): ?>
    <div class="next-action"><div><div class="what">อนุมัติแล้วโดย <?= e($v['approver_name'] ?? '—') ?> — ส่งให้ลูกค้า</div><div class="muted">พิมพ์/บันทึกเป็น PDF แล้วส่งให้ลูกค้า จากนั้นกดบันทึกว่าส่งแล้ว</div></div>
      <?php if (can('quotation.edit')): ?><?= post_button('quotations.send', ['version_id' => $v['id']], 'บันทึกว่าส่งลูกค้าแล้ว', ['class' => 'primary']) ?><?php endif; ?></div>
  <?php elseif ($v['status'] === 'SENT'): ?>
    <div class="what">ส่งให้ลูกค้าแล้วเมื่อ <?= e(dt($v['sent_at'])) ?> — บันทึกคำตอบของลูกค้า</div>
    <?php if (can('quotation.edit')): ?>
      <form method="post" action="<?= e(url('quotations.respond')) ?>" style="margin-top:10px">
        <?= csrf_field() ?><input type="hidden" name="version_id" value="<?= e($v['id']) ?>">
        <div class="form-grid"><?= f_input('note', 'หมายเหตุจากลูกค้า', null) ?></div>
        <button class="btn primary" type="submit" name="response" value="ACCEPTED" data-confirm-click="ลูกค้าตอบรับใบเสนอราคานี้?">ลูกค้าตอบรับ</button>
        <button class="btn danger" type="submit" name="response" value="REJECTED" data-confirm-click="ลูกค้าปฏิเสธใบเสนอราคานี้?">ลูกค้าปฏิเสธ</button>
      </form>
    <?php endif; ?>
  <?php elseif ($v['status'] === 'ACCEPTED'): ?>
    <div class="what">✅ ลูกค้าตอบรับแล้ว — ต่อด้วยรับมัดจำและทำสัญญาที่ <a href="<?= e(url('sales.view', ['id' => $so['id']])) ?>#deposits">หน้าดีลขาย</a></div>
  <?php else: ?>
    <div class="what"><?= e(label('status', $v['status'])) ?> — ออกฉบับแก้ไขเพื่อเสนอใหม่</div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($editable): ?>
<form method="post" action="<?= e(url('quotations.save')) ?>" class="card" data-warn-unsaved>
  <?= csrf_field() ?><input type="hidden" name="version_id" value="<?= e($v['id']) ?>">
  <h2>รายการ</h2>
  <div class="table-wrap">
    <table class="table line-items" id="qv-lines" data-rows>
      <thead><tr><th style="width:22%">เครื่อง</th><th>รายละเอียด</th><th style="width:12%">สภาพ</th><th style="width:8%">จำนวน</th><th class="num" style="width:16%">ราคา/หน่วย</th><th class="num" style="width:12%">รวม</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $i => $l): ?>
        <tr>
          <td><select name="lines[<?= $i ?>][device_id]"><option value="">— ไม่ผูกเครื่อง —</option><?php foreach ($devOpts as $k => $lbl): ?><option value="<?= e($k) ?>" <?= $l['device_id'] === $k ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?></select></td>
          <td><input type="text" name="lines[<?= $i ?>][description]" value="<?= e($l['description']) ?>"><input type="text" name="lines[<?= $i ?>][accessories]" value="<?= e($l['accessories']) ?>" placeholder="อุปกรณ์ที่รวม" style="margin-top:4px"></td>
          <td><select name="lines[<?= $i ?>][device_condition]"><option value="">—</option><?php foreach (labels('condition') as $k => $lbl): ?><option value="<?= e($k) ?>" <?= $l['device_condition'] === $k ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?></select></td>
          <td><input type="number" name="lines[<?= $i ?>][qty]" value="<?= (int) $l['qty'] ?>" min="1" data-qty></td>
          <td><input type="text" name="lines[<?= $i ?>][unit_price]" value="<?= $l['unit_price'] !== null ? e(number_format((float) $l['unit_price'], 2)) : '' ?>" data-money data-amount inputmode="decimal" class="right"></td>
          <td class="num" data-line-total></td>
          <td><button class="remove-row" title="ลบแถว">×</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><td colspan="5">ราคารวมก่อน VAT</td><td class="num" data-total-for="qv-lines">0.00</td><td></td></tr>
        <tr><td colspan="5">VAT <input type="text" id="vat_rate" name="vat_rate" value="<?= e(rtrim(rtrim((string) $v['vat_rate'], '0'), '.')) ?>" data-vat-rate style="width:60px;display:inline-block"> %</td><td class="num" data-total-for="qv-lines" data-vat="vat_rate" data-mode="vat">0.00</td><td></td></tr>
        <tr><td colspan="5">ยอดรวมทั้งสิ้น</td><td class="num" data-total-for="qv-lines" data-vat="vat_rate" data-mode="total">0.00</td><td></td></tr>
      </tfoot>
      <template>
        <tr>
          <td><select name="lines[__i][device_id]"><option value="">— ไม่ผูกเครื่อง —</option><?php foreach ($devOpts as $k => $lbl): ?><option value="<?= e($k) ?>"><?= e($lbl) ?></option><?php endforeach; ?></select></td>
          <td><input type="text" name="lines[__i][description]"><input type="text" name="lines[__i][accessories]" placeholder="อุปกรณ์ที่รวม" style="margin-top:4px"></td>
          <td><select name="lines[__i][device_condition]"><option value="">—</option><?php foreach (labels('condition') as $k => $lbl): ?><option value="<?= e($k) ?>"><?= e($lbl) ?></option><?php endforeach; ?></select></td>
          <td><input type="number" name="lines[__i][qty]" value="1" min="1" data-qty></td>
          <td><input type="text" name="lines[__i][unit_price]" data-money data-amount inputmode="decimal" class="right"></td>
          <td class="num" data-line-total></td>
          <td><button class="remove-row" title="ลบแถว">×</button></td>
        </tr>
      </template>
    </table>
  </div>
  <button type="button" class="btn sm" data-add-row="qv-lines">+ เพิ่มรายการ (เช่น ค่าติดตั้ง อุปกรณ์เสริม)</button>
  <h2 style="margin-top:16px">เงื่อนไข</h2>
  <div class="form-grid">
    <?= f_input('valid_until', 'ยืนราคาถึง', $v['valid_until'], ['type' => 'date', 'required' => true]) ?>
    <?= f_textarea('payment_terms', 'เงื่อนไขการชำระเงิน', $v['payment_terms'], ['rows' => 2, 'placeholder' => 'เช่น มัดจำ 30% ส่วนที่เหลือก่อนส่งมอบ']) ?>
    <?= f_textarea('warranty_terms', 'การรับประกัน', $v['warranty_terms'], ['rows' => 2]) ?>
    <?= f_textarea('delivery_terms', 'การส่งมอบ', $v['delivery_terms'], ['rows' => 2]) ?>
    <?= f_textarea('installation_terms', 'การติดตั้ง / สอนใช้งาน', $v['installation_terms'], ['rows' => 2]) ?>
    <?= f_textarea('notes', 'หมายเหตุ', $v['notes'], ['rows' => 2]) ?>
  </div>
  <div class="form-actions">
    <button class="btn" type="submit" name="action" value="draft">บันทึกร่าง</button>
    <button class="btn primary" type="submit" name="action" value="request">บันทึกและส่งขออนุมัติ</button>
  </div>
</form>
<?php else: ?>
<div class="card">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>รายการ</th><th>สภาพ</th><th>จำนวน</th><th class="num">ราคา/หน่วย</th><th class="num">รวม</th></tr></thead>
    <tbody><?php foreach ($lines as $l): ?>
      <tr><td><?= e($l['description']) ?><?= $l['accessories'] ? '<div class="muted">' . e($l['accessories']) . '</div>' : '' ?></td><td><?= e(label('condition', $l['device_condition'])) ?></td><td><?= (int) $l['qty'] ?></td><td class="num"><?= money($l['unit_price']) ?></td><td class="num"><?= money((float) $l['unit_price'] * (int) $l['qty']) ?></td></tr>
    <?php endforeach; ?></tbody>
    <tfoot>
      <tr><td colspan="4">ราคารวมก่อน VAT</td><td class="num"><?= money($v['subtotal']) ?></td></tr>
      <tr><td colspan="4">VAT <?= e(rtrim(rtrim((string) $v['vat_rate'], '0'), '.')) ?>%</td><td class="num"><?= money($v['vat_amount']) ?></td></tr>
      <tr><td colspan="4">ยอดรวมทั้งสิ้น</td><td class="num"><?= money($v['total']) ?></td></tr>
    </tfoot>
  </table></div>
  <dl class="kv" style="margin-top:12px">
    <dt>ยืนราคาถึง</dt><dd><?= e(d($v['valid_until'])) ?></dd>
    <dt>การชำระเงิน</dt><dd class="pre"><?= e($v['payment_terms'] ?? '—') ?></dd>
    <dt>การรับประกัน</dt><dd class="pre"><?= e($v['warranty_terms'] ?? '—') ?></dd>
    <dt>การส่งมอบ</dt><dd class="pre"><?= e($v['delivery_terms'] ?? '—') ?></dd>
    <dt>การติดตั้ง</dt><dd class="pre"><?= e($v['installation_terms'] ?? '—') ?></dd>
    <?php if ($v['notes']): ?><dt>หมายเหตุ</dt><dd class="pre"><?= e($v['notes']) ?></dd><?php endif; ?>
    <?php if ($v['response_note']): ?><dt>คำตอบลูกค้า</dt><dd class="pre"><?= e($v['response_note']) ?></dd><?php endif; ?>
  </dl>
</div>
<?php endif; ?>

<div class="card flush">
  <div class="card-head"><h2>ประวัติฉบับ (Version history)</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ฉบับ</th><th class="num">ยอดรวม</th><th>สร้าง</th><th>อนุมัติ</th><th>ส่ง</th><th>สถานะ</th></tr></thead>
    <tbody><?php foreach ($versions as $x): ?>
      <tr class="<?= (int) $x['version_no'] === (int) $v['version_no'] ? 'overdue' : '' ?>"><td><a href="<?= e(url('quotations.view', ['id' => $q['id'], 'v' => $x['version_no']])) ?>">ฉบับที่ <?= (int) $x['version_no'] ?></a></td>
        <td class="num"><?= money($x['total']) ?></td><td><?= e(dt($x['created_at'])) ?></td><td><?= e($x['approver_name'] ?? '—') ?></td><td><?= e(dt($x['sent_at'])) ?></td><td><?= badge('status', $x['status']) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</div>

<?php partial('tasks', ['parentType' => 'QUOTATION', 'parentId' => $q['id'], 'tasks' => $tasks]); ?>
<?php partial('documents', ['parentType' => 'QUOTATION', 'parentId' => $q['id'], 'documents' => $documents, 'defaultCategory' => 'QUOTATION']); ?>
