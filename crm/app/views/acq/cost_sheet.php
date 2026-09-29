<?php
$rows = $items ?: [['category' => 'ACQUISITION_ASSUMPTION', 'description' => 'ราคาซื้อที่คาดว่าจะตกลง', 'amount' => $opp['expected_price']], ['category' => 'REPAIR', 'description' => '', 'amount' => null]];
$newVersion = !$draft;
$catOptions = labels('cost_category');
?>
<div class="crumbs"><a href="<?= e(url('acq')) ?>">ดีลซื้อ</a> › <a href="<?= e(url('acq.view', ['id' => $opp['id']])) ?>"><?= e($opp['ref_no']) ?></a> › Cost Sheet</div>
<div class="page-head">
  <div><h1>Cost Sheet <?= $newVersion ? '(ฉบับใหม่)' : 'ฉบับที่ ' . (int) $draft['version_no'] . ' (ร่าง)' ?></h1>
    <div class="sub"><?= e($device['brand'] . ' ' . $device['model']) ?> <span class="ref"><?= e($device['ref_no']) ?></span> · ผู้ขายต้องการ <?= money($opp['expected_price']) ?> บาท</div></div>
</div>
<?php if ($base && $newVersion): ?><div class="flash info">ฉบับที่ <?= (int) $base['version_no'] ?> ส่งไปแล้ว — การบันทึกครั้งนี้จะสร้างฉบับใหม่ ฉบับเดิมยังเก็บไว้ครบ</div><?php endif; ?>

<form method="post" action="<?= e(url('acq.cost_sheet')) ?>" class="card" data-warn-unsaved>
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($opp['id']) ?>">
  <div class="form-grid">
    <?= f_select('inspection_id', 'อ้างอิงผลตรวจเครื่อง (BR-05)', array_column(array_map(function ($i) { return ['id' => $i['id'], 'n' => 'ตรวจเมื่อ ' . dt($i['completed_at']) . ' · ' . label('result', $i['overall_result'])]; }, $inspections), 'n', 'id'), $base['inspection_id'] ?? ($inspections[0]['id'] ?? null), ['required' => true]) ?>
  </div>
  <div class="table-wrap">
    <table class="table line-items" id="cs-items" data-rows>
      <thead><tr><th style="width:26%">หมวด</th><th>รายละเอียด</th><th class="num" style="width:20%">จำนวนเงิน (บาท)</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $i => $r): ?>
        <tr>
          <td><select name="items[<?= $i ?>][category]"><?php foreach ($catOptions as $k => $v): ?><option value="<?= e($k) ?>" <?= $r['category'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></td>
          <td><input type="text" name="items[<?= $i ?>][description]" value="<?= e($r['description']) ?>"></td>
          <td><input type="text" name="items[<?= $i ?>][amount]" value="<?= $r['amount'] !== null ? e(number_format((float) $r['amount'], 2)) : '' ?>" data-money data-amount inputmode="decimal" class="right"></td>
          <td><button class="remove-row" title="ลบแถว">×</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td colspan="2">ต้นทุนรวมโดยประมาณ (Estimated Total Cost)</td><td class="num" data-total-for="cs-items">0.00</td><td></td></tr></tfoot>
      <template>
        <tr>
          <td><select name="items[__i][category]"><?php foreach ($catOptions as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></td>
          <td><input type="text" name="items[__i][description]"></td>
          <td><input type="text" name="items[__i][amount]" data-money data-amount inputmode="decimal" class="right"></td>
          <td><button class="remove-row" title="ลบแถว">×</button></td>
        </tr>
      </template>
    </table>
  </div>
  <button type="button" class="btn sm" data-add-row="cs-items">+ เพิ่มรายการ</button>
  <?= f_textarea('notes', 'หมายเหตุ / สมมติฐาน', $base['notes'] ?? null, ['rows' => 2]) ?>
  <div class="form-actions">
    <button class="btn" type="submit" name="action" value="draft">บันทึกร่าง</button>
    <button class="btn primary" type="submit" name="action" value="submit" data-confirm-click="ส่ง Cost Sheet? ฉบับที่ส่งแล้วจะแก้ไม่ได้ (ต้องออกฉบับใหม่)">ส่ง Cost Sheet</button>
    <a class="btn" href="<?= e(url('acq.view', ['id' => $opp['id']])) ?>">ยกเลิก</a>
  </div>
</form>
