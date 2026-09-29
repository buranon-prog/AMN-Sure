<div class="head">
  <div>
    <h1><?= e(setting('company_name', 'AMN Sure')) ?></h1>
    <div class="muted">ใบเสนอราคา / Quotation</div>
  </div>
  <div class="right">
    <div><b>เลขที่</b> <?= e($q['ref_no']) ?>-R<?= (int) $v['version_no'] ?></div>
    <div><b>วันที่</b> <?= e(d($v['created_at'])) ?></div>
    <div><b>ยืนราคาถึง</b> <?= e(d($v['valid_until'])) ?></div>
    <?php if ($v['status'] !== 'APPROVED' && $v['status'] !== 'SENT' && $v['status'] !== 'ACCEPTED'): ?><div class="badge red">ฉบับร่าง — ยังไม่ได้อนุมัติ</div><?php endif; ?>
  </div>
</div>
<p><b>เรียน</b> <?= e($buyer['name']) ?><?= $contact ? ' (คุณ' . e($contact['name']) . ')' : '' ?><br>
  <span class="muted"><?= e(trim(($buyer['address'] ?? '') . ' ' . ($buyer['province'] ?? ''))) ?><?= $buyer['tax_id'] ? ' · เลขผู้เสียภาษี ' . e($buyer['tax_id']) : '' ?></span></p>
<table class="table">
  <thead><tr><th style="width:5%">#</th><th>รายการ</th><th style="width:12%">สภาพ</th><th style="width:8%">จำนวน</th><th class="num" style="width:16%">ราคา/หน่วย</th><th class="num" style="width:16%">จำนวนเงิน</th></tr></thead>
  <tbody><?php foreach ($lines as $i => $l): ?>
    <tr><td><?= $i + 1 ?></td><td><?= e($l['description']) ?><?= $l['accessories'] ? '<div class="muted">รวม: ' . e($l['accessories']) . '</div>' : '' ?></td><td><?= e(label('condition', $l['device_condition'])) ?></td><td><?= (int) $l['qty'] ?></td>
      <td class="num"><?= money($l['unit_price']) ?></td><td class="num"><?= money((float) $l['unit_price'] * (int) $l['qty']) ?></td></tr>
  <?php endforeach; ?></tbody>
  <tfoot>
    <tr><td colspan="5">รวมเป็นเงิน</td><td class="num"><?= money($v['subtotal']) ?></td></tr>
    <tr><td colspan="5">ภาษีมูลค่าเพิ่ม <?= e(rtrim(rtrim((string) $v['vat_rate'], '0'), '.')) ?>%</td><td class="num"><?= money($v['vat_amount']) ?></td></tr>
    <tr><td colspan="5">ยอดรวมทั้งสิ้น (บาท)</td><td class="num"><?= money($v['total']) ?></td></tr>
  </tfoot>
</table>
<dl class="kv" style="margin-top:16px">
  <dt>การชำระเงิน</dt><dd class="pre"><?= e($v['payment_terms'] ?? '—') ?></dd>
  <dt>การรับประกัน</dt><dd class="pre"><?= e($v['warranty_terms'] ?? '—') ?></dd>
  <dt>การส่งมอบ</dt><dd class="pre"><?= e($v['delivery_terms'] ?? '—') ?></dd>
  <dt>การติดตั้ง</dt><dd class="pre"><?= e($v['installation_terms'] ?? '—') ?></dd>
  <?php if ($v['notes']): ?><dt>หมายเหตุ</dt><dd class="pre"><?= e($v['notes']) ?></dd><?php endif; ?>
</dl>
<div class="sign">
  <div>ผู้เสนอราคา<br><br><?= e(user_name($so['owner_id'])) ?></div>
  <div>ผู้อนุมัติ<br><br><?= e($v['approver_name'] ?? '') ?></div>
</div>
