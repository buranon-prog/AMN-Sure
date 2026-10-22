<?php
/** @var string $parentType @var string $parentId @var array $activities @var bool $followup (แสดงช่อง next action) */
$canAdd = parent_can_edit($parentType);
$followup = $followup ?? false;
?>
<div class="card" id="activities">
  <div class="card-head"><h2>ประวัติกิจกรรม</h2></div>
  <?php if ($canAdd): ?>
    <form method="post" action="<?= e(url('activities.add')) ?>" class="no-print" style="margin-bottom:14px">
      <?= csrf_field() ?>
      <input type="hidden" name="parent_type" value="<?= e($parentType) ?>">
      <input type="hidden" name="parent_id" value="<?= e($parentId) ?>">
      <div class="form-grid three">
        <?= f_select('type', 'ประเภท', array_diff_key(labels('activity'), ['SYSTEM' => 1]), 'CALL') ?>
        <?= f_input('occurred_on', 'วันที่', today(), ['type' => 'date']) ?>
        <?= f_input('outcome', 'ผลลัพธ์ (ถ้ามี)', null) ?>
        <?= f_textarea('summary', 'รายละเอียด', null, ['rows' => 2, 'required' => true, 'class' => 'full', 'placeholder' => 'คุยเรื่องอะไร ลูกค้าตอบว่าอย่างไร']) ?>
        <?php if ($followup): ?>
          <?= f_input('next_action', 'Next action ถัดไป', null, ['placeholder' => 'เว้นว่าง = ใช้ค่าเดิม']) ?>
          <?= f_input('next_follow_up_date', 'วันติดตามครั้งถัดไป', null, ['type' => 'date']) ?>
        <?php endif; ?>
      </div>
      <button class="btn primary sm" type="submit">บันทึกกิจกรรม</button>
    </form>
  <?php endif; ?>
  <?php if (!$activities): ?>
    <div class="empty-state">ยังไม่มีกิจกรรม</div>
  <?php else: ?>
    <ul class="timeline">
      <?php foreach ($activities as $a): ?>
        <li class="<?= $a['type'] === 'SYSTEM' ? 'system' : '' ?>">
          <div class="meta"><?= e(dt($a['occurred_at'])) ?> · <?= e(label('activity', $a['type'])) ?> · <?= e($a['user_name'] ?? 'ระบบ') ?></div>
          <div class="body"><?= e($a['summary']) ?></div>
          <?php if ($a['outcome']): ?><div class="muted">ผลลัพธ์: <?= e($a['outcome']) ?></div><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
