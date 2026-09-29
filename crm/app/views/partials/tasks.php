<?php
/** @var string $parentType @var string $parentId @var array $tasks */
?>
<div class="card" id="tasks">
  <div class="card-head"><h2>งานที่เกี่ยวข้อง</h2></div>
  <?php if ($tasks): ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>งาน</th><th>ผู้รับผิดชอบ</th><th>กำหนด</th><th>สถานะ</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($tasks as $t): ?>
        <tr class="<?= in_array($t['status'], ['OPEN', 'IN_PROGRESS'], true) && $t['due_date'] && $t['due_date'] < today() ? 'overdue' : '' ?>">
          <td><?= e($t['title']) ?></td>
          <td><?= e($t['assignee_name'] ?? ($t['assignee_role'] ? role_name($t['assignee_role']) : '—')) ?></td>
          <td class="nowrap"><?= e(d($t['due_date'])) ?></td>
          <td><?= badge('status', $t['status']) ?></td>
          <td class="right"><?php if (in_array($t['status'], ['OPEN', 'IN_PROGRESS'], true) && task_can_act($t)): ?>
            <?= post_button('tasks.status', ['id' => $t['id'], 'status' => 'DONE'], 'เสร็จ', ['class' => 'sm']) ?>
          <?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php else: ?>
    <div class="empty-state">ไม่มีงาน</div>
  <?php endif; ?>
  <details class="no-print" style="margin-top:10px">
    <summary class="btn sm">+ มอบหมายงานเพิ่ม</summary>
    <form method="post" action="<?= e(url('tasks.create')) ?>" style="margin-top:10px">
      <?= csrf_field() ?>
      <input type="hidden" name="parent_type" value="<?= e($parentType) ?>">
      <input type="hidden" name="parent_id" value="<?= e($parentId) ?>">
      <div class="form-grid three">
        <?= f_input('title', 'งาน', null, ['required' => true, 'class' => 'full']) ?>
        <?= f_select('assignee_id', 'มอบให้', user_options(), current_user_id(), ['blank' => '— หรือเลือก role —']) ?>
        <?= f_select('assignee_role', 'หรือมอบให้ทั้ง role', ROLE_NAMES, null, ['blank' => '—']) ?>
        <?= f_input('due_date', 'กำหนดเสร็จ', add_days(today(), 1), ['type' => 'date']) ?>
      </div>
      <button class="btn sm primary" type="submit">มอบหมาย</button>
    </form>
  </details>
</div>
