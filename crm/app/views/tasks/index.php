<div class="page-head">
  <div><h1>งานของฉัน</h1><div class="sub">งานที่มอบให้คุณโดยตรง และงานที่มอบให้ role ของคุณ (<?= e(implode(', ', array_map('role_name', current_user()['roles']))) ?>)</div></div>
</div>

<div class="subnav">
  <a class="<?= $scope === 'mine' ? 'active' : '' ?>" href="<?= e(url('tasks', ['scope' => 'mine', 'status' => $status])) ?>">งานของฉัน</a>
  <a class="<?= $scope === 'created' ? 'active' : '' ?>" href="<?= e(url('tasks', ['scope' => 'created', 'status' => $status])) ?>">งานที่ฉันมอบหมาย</a>
  <?php if (can('task.view_all')): ?><a class="<?= $scope === 'all' ? 'active' : '' ?>" href="<?= e(url('tasks', ['scope' => 'all', 'status' => $status])) ?>">งานทั้งหมด</a><?php endif; ?>
</div>

<div class="filters">
  <a class="btn sm <?= $status === 'open' ? 'primary' : '' ?>" href="<?= e(url('tasks', ['scope' => $scope, 'status' => 'open'])) ?>">ยังไม่เสร็จ</a>
  <a class="btn sm <?= $status === 'done' ? 'primary' : '' ?>" href="<?= e(url('tasks', ['scope' => $scope, 'status' => 'done'])) ?>">เสร็จ/ยกเลิก</a>
  <a class="btn sm <?= $status === 'all' ? 'primary' : '' ?>" href="<?= e(url('tasks', ['scope' => $scope, 'status' => 'all'])) ?>">ทั้งหมด</a>
</div>

<div class="card flush">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>งาน</th><th>เกี่ยวกับ</th><th>ผู้รับผิดชอบ</th><th>กำหนด</th><th>ความสำคัญ</th><th>สถานะ</th><th></th></tr></thead>
    <tbody>
    <?php if (!$page['rows']): ?><tr><td colspan="7" class="empty">ไม่มีงาน 🎉</td></tr><?php endif; ?>
    <?php foreach ($page['rows'] as $t):
        $open = in_array($t['status'], ['OPEN', 'IN_PROGRESS'], true);
        $late = $open && $t['due_date'] && $t['due_date'] < today(); ?>
      <tr class="<?= $late ? 'overdue' : '' ?>">
        <td><?= e($t['title']) ?><?php if ($t['description']): ?><div class="muted"><?= e($t['description']) ?></div><?php endif; ?></td>
        <td><?php if ($t['parent_type'] && $t['parent_id']): ?><a href="<?= e(parent_url($t['parent_type'], $t['parent_id'])) ?>"><?= e(parent_title($t['parent_type'], $t['parent_id'])) ?></a><?php else: ?>—<?php endif; ?></td>
        <td><?= e($t['assignee_name'] ?? ($t['assignee_role'] ? role_name($t['assignee_role']) : '—')) ?></td>
        <td class="nowrap"><?= e(d($t['due_date'])) ?><?= $late ? ' <span class="badge red">เลยกำหนด</span>' : '' ?></td>
        <td><?= badge('priority', $t['priority']) ?></td>
        <td><?= badge('status', $t['status']) ?></td>
        <td class="right"><div class="row-actions">
          <?php if ($open && task_can_act($t)): ?>
            <?php if ($t['status'] === 'OPEN'): ?><?= post_button('tasks.status', ['id' => $t['id'], 'status' => 'IN_PROGRESS'], 'รับงาน', ['class' => 'sm']) ?><?php endif; ?>
            <?= post_button('tasks.status', ['id' => $t['id'], 'status' => 'DONE'], 'เสร็จ', ['class' => 'sm primary']) ?>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?= pager($page) ?>

<div class="card">
  <h2>สร้างงานใหม่</h2>
  <form method="post" action="<?= e(url('tasks.create')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid">
      <?= f_input('title', 'งาน', null, ['required' => true]) ?>
      <?= f_input('due_date', 'กำหนดเสร็จ', add_days(today(), 1), ['type' => 'date']) ?>
      <?= f_select('assignee_id', 'มอบให้', user_options(), current_user_id(), ['blank' => '— หรือเลือก role ด้านขวา —', 'search' => true]) ?>
      <?= f_select('assignee_role', 'หรือมอบให้ทั้ง role', ROLE_NAMES, null, ['blank' => '—']) ?>
      <?= f_select('priority', 'ความสำคัญ', labels('priority'), 'MEDIUM') ?>
      <?= f_input('description', 'รายละเอียด', null) ?>
    </div>
    <button class="btn primary" type="submit">สร้างงาน</button>
  </form>
</div>
