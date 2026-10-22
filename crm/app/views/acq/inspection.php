<?php
$editable = $insp['status'] === 'IN_PROGRESS' && can('inspection.edit');
$byCat = [];
foreach ($items as $it) $byCat[$it['category']][] = $it;
?>
<div class="crumbs"><a href="<?= e(url('acq')) ?>">ดีลซื้อ</a><?php if ($opp): ?> › <a href="<?= e(url('acq.view', ['id' => $opp['id']])) ?>"><?= e($opp['ref_no']) ?></a><?php endif; ?> › ตรวจเครื่อง</div>
<div class="page-head">
  <div>
    <h1>ตรวจเครื่อง: <?= e($device['brand'] . ' ' . $device['model']) ?></h1>
    <div class="sub"><span class="ref"><?= e($device['ref_no']) ?></span> S/N <?= e($device['serial_number'] ?? '—') ?> <?= badge('status', $insp['status']) ?>
      <span>ช่าง: <?= e(user_name($insp['engineer_id'])) ?></span></div>
  </div>
  <div class="actions no-print"><button class="btn" data-print>พิมพ์</button>
    <?php if ($insp['status'] === 'REQUESTED' && can('inspection.edit')): ?><?= post_button('acq.inspection_start', ['id' => $insp['id']], 'เริ่มตรวจเครื่อง', ['class' => 'primary']) ?><?php endif; ?></div>
</div>

<?php if ($insp['request_note'] || $insp['preferred_date']): ?>
  <div class="flash info">คำขอตรวจ: <?= e($insp['request_note'] ?? '') ?><?= $insp['preferred_date'] ? ' · วันที่สะดวก ' . e(d($insp['preferred_date'])) : '' ?></div>
<?php endif; ?>

<?php if ($insp['status'] === 'REQUESTED'): ?>
  <div class="card"><p>ยังไม่เริ่มตรวจ — กด "เริ่มตรวจเครื่อง" เพื่อสร้างรายการตรวจมาตรฐาน</p></div>
<?php else: ?>
<form method="post" action="<?= e(url('acq.inspection_save')) ?>" data-warn-unsaved>
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($insp['id']) ?>">
  <div class="card">
    <h2>รายการตรวจ</h2>
    <p class="muted">รายการที่มี <span class="req">*</span> ต้องมีผลก่อนปิดการตรวจ (BR-03)</p>
    <?php foreach (INSP_CATEGORIES as $cat): if (empty($byCat[$cat])) continue; ?>
      <h3><?= e(label('insp_category', $cat)) ?></h3>
      <div class="table-wrap"><table class="table">
        <tbody>
        <?php foreach ($byCat[$cat] as $it): ?>
          <tr>
            <td style="width:34%"><?= e($it['item_name']) ?><?= $it['is_mandatory'] ? ' <span class="req">*</span>' : '' ?></td>
            <td style="width:36%">
              <?php if ($editable): ?>
                <div class="result-pick">
                  <?php foreach (INSP_ITEM_RESULTS as $r): ?>
                    <label><input type="radio" name="items[<?= e($it['id']) ?>][result]" value="<?= e($r) ?>" <?= $it['result'] === $r ? 'checked' : '' ?>><span><?= e(label('result', $r)) ?></span></label>
                  <?php endforeach; ?>
                </div>
              <?php else: ?><?= badge('result', $it['result']) ?><?php endif; ?>
            </td>
            <td><?php if ($editable): ?><input type="text" name="items[<?= e($it['id']) ?>][note]" value="<?= e($it['note']) ?>" placeholder="หมายเหตุ"><?php else: ?><?= e($it['note'] ?? '') ?><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endforeach; ?>
    <?php if ($editable): ?>
      <h3>รายการเพิ่มเติม</h3>
      <div class="form-grid three">
        <?= f_select('new_items[0][category]', 'หมวด', labels('insp_category'), 'PARTS') ?>
        <?= f_input('new_items[0][item_name]', 'ชื่อรายการ', null) ?>
        <?= f_select('new_items[0][result]', 'ผล', array_intersect_key(LABELS['result'], array_flip(INSP_ITEM_RESULTS)), null, ['blank' => '—']) ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Technical summary</h2>
    <?php if ($editable): ?>
      <div class="form-grid three">
        <?= f_select('overall_result', 'ผลตรวจรวม', array_intersect_key(LABELS['result'], array_flip(INSP_OVERALL)), $insp['overall_result'], ['blank' => '— เลือก —']) ?>
        <?= f_select('overall_condition', 'สภาพโดยรวม', labels('condition'), $insp['overall_condition'], ['blank' => '— เลือก —']) ?>
        <?= f_select('technical_risk', 'ความเสี่ยงทางเทคนิค', labels('risk'), $insp['technical_risk'], ['blank' => '— เลือก —']) ?>
        <?= f_input('usage_reading', 'ค่าการใช้งานที่อ่านได้', $insp['usage_reading'], ['placeholder' => 'เช่น 125,300 pulses']) ?>
        <?= f_input('est_repair_days', 'ระยะเวลาซ่อมโดยประมาณ (วัน)', $insp['est_repair_days'], ['type' => 'number']) ?>
        <?= f_input('missing_accessories', 'อุปกรณ์ที่ขาด', $insp['missing_accessories']) ?>
        <?= f_textarea('issues', 'ปัญหาที่พบ', $insp['issues'], ['rows' => 3]) ?>
        <?= f_textarea('required_repair', 'ต้องซ่อม (จำเป็น)', $insp['required_repair'], ['rows' => 3]) ?>
        <?= f_textarea('recommended_repair', 'แนะนำให้ซ่อม', $insp['recommended_repair'], ['rows' => 3]) ?>
      </div>
    <?php else: ?>
      <dl class="kv">
        <dt>ผลตรวจรวม</dt><dd><?= badge('result', $insp['overall_result']) ?></dd>
        <dt>สภาพโดยรวม</dt><dd><?= e(label('condition', $insp['overall_condition'])) ?></dd>
        <dt>ความเสี่ยง</dt><dd><?= $insp['technical_risk'] ? badge('risk', $insp['technical_risk']) : '—' ?></dd>
        <dt>ค่าการใช้งาน</dt><dd><?= e($insp['usage_reading'] ?? '—') ?></dd>
        <dt>ปัญหาที่พบ</dt><dd class="pre"><?= e($insp['issues'] ?? '—') ?></dd>
        <dt>ต้องซ่อม</dt><dd class="pre"><?= e($insp['required_repair'] ?? '—') ?></dd>
        <dt>แนะนำให้ซ่อม</dt><dd class="pre"><?= e($insp['recommended_repair'] ?? '—') ?></dd>
        <dt>อุปกรณ์ที่ขาด</dt><dd><?= e($insp['missing_accessories'] ?? '—') ?></dd>
        <dt>ระยะเวลาซ่อม</dt><dd><?= $insp['est_repair_days'] !== null ? (int) $insp['est_repair_days'] . ' วัน' : '—' ?></dd>
        <dt>ตรวจเสร็จเมื่อ</dt><dd><?= e(dt($insp['completed_at'])) ?></dd>
      </dl>
    <?php endif; ?>
  </div>
  <?php if ($editable): ?>
    <div class="card no-print">
      <div class="actions">
        <button class="btn" type="submit" name="action" value="draft">บันทึกร่าง</button>
        <button class="btn primary" type="submit" name="action" value="complete" data-confirm-click="ปิดการตรวจ? แก้ไขผลไม่ได้หลังจากนี้">ปิดการตรวจ (ส่งผล)</button>
      </div>
      <p class="hint">แนบรูป/วิดีโอด้านล่าง (บันทึกร่างก่อนแนบไฟล์ เพื่อไม่ให้ข้อมูลที่กรอกหาย)</p>
    </div>
  <?php endif; ?>
</form>
<?php endif; ?>

<?php partial('documents', ['parentType' => 'INSPECTION', 'parentId' => $insp['id'], 'documents' => $documents, 'defaultCategory' => 'PHOTO']); ?>
