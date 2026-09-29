<?php $u = current_user(); $canTeam = can('task.view_all') || has_role('SALES_DIRECTOR') || has_role('SERVICE_DIRECTOR'); ?>
<div class="page-head">
  <div>
    <h1>สวัสดี <?= e($u['name']) ?></h1>
    <div class="sub"><?= e(d(today())) ?> · <?= e(implode(', ', array_map('role_name', $u['roles']))) ?></div>
  </div>
  <div class="actions">
    <?php if ($canTeam): ?>
      <a class="btn sm <?= !$team ? 'primary' : '' ?>" href="<?= e(url('dashboard')) ?>">ของฉัน</a>
      <a class="btn sm <?= $team ? 'primary' : '' ?>" href="<?= e(url('dashboard', ['view' => 'team'])) ?>">ทั้งทีม</a>
    <?php endif; ?>
    <?php if (can('lead.edit')): ?>
      <a class="btn primary" href="<?= e(url('leads.new', ['type' => 'SELLER'])) ?>">+ ลีดผู้ขาย</a>
      <a class="btn primary" href="<?= e(url('leads.new', ['type' => 'BUYER'])) ?>">+ ลีดผู้ซื้อ</a>
    <?php endif; ?>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><h2>งานวันนี้ (<?= count($d['tasks']) ?>)</h2><a href="<?= e(url('tasks')) ?>">ดูทั้งหมด →</a></div>
    <?php if (!$d['tasks']): ?>
      <div class="empty-state">ไม่มีงานที่ถึงกำหนดวันนี้<?= $d['tasks_upcoming'] ? ' · มีงานข้างหน้าอีก ' . (int) $d['tasks_upcoming'] . ' งาน' : '' ?></div>
    <?php else: ?>
      <ul class="list-plain">
        <?php foreach ($d['tasks'] as $t): $late = $t['due_date'] && $t['due_date'] < today(); ?>
          <li>
            <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
              <div>
                <?php if ($t['parent_type'] && $t['parent_id']): ?><a href="<?= e(parent_url($t['parent_type'], $t['parent_id'])) ?>"><?= e($t['title']) ?></a><?php else: ?><?= e($t['title']) ?><?php endif; ?>
                <div class="muted"><?= e($t['due_date'] ? d($t['due_date']) : 'ไม่กำหนดวัน') ?><?= $late ? ' · <span class="badge red">เลยกำหนด</span>' : '' ?> <?= $t['priority'] === 'HIGH' ? '<span class="badge red">ด่วน</span>' : '' ?>
                  <?= !$t['assignee_id'] && $t['assignee_role'] ? ' · งานของ ' . e(role_name($t['assignee_role'])) : '' ?></div>
              </div>
              <?php if (task_can_act($t)): ?><?= post_button('tasks.status', ['id' => $t['id'], 'status' => 'DONE'], 'เสร็จ', ['class' => 'sm']) ?><?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="card <?= $d['overdue'] ? 'danger' : '' ?>">
    <div class="card-head"><h2>ติดตามที่เลยกำหนด (<?= count($d['overdue']) ?>)</h2><a href="<?= e(url('leads', ['overdue' => 1, 'owner' => $team ? '' : 'me'])) ?>">ดูทั้งหมด →</a></div>
    <?php if (!$d['overdue'] && !$d['due_today']): ?>
      <div class="empty-state">ไม่มีดีลที่เลยกำหนดติดตาม 👍</div>
    <?php else: ?>
      <ul class="list-plain">
        <?php foreach (array_merge($d['overdue'], $d['due_today']) as $r): ?>
          <li>
            <a href="<?= e(url($r['kind'] === 'DO' ? 'acq.view' : 'sales.view', ['id' => $r['id']])) ?>"><span class="ref"><?= e($r['ref_no']) ?></span> <?= e($r['org_name']) ?></a>
            <div class="muted"><?= e($r['next_action'] ?? '—') ?> · <?= followup_badge($r['next_follow_up_date']) ?><?= $team ? ' · ' . e($r['owner_name']) : '' ?></div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php if ($d['no_followup']): ?>
      <div class="flash warning" style="margin:10px 0 0">มี <?= count($d['no_followup']) ?> ดีลที่ยังไม่มีวันติดตาม (BR-01): <?php foreach ($d['no_followup'] as $r): ?><a href="<?= e(url($r['kind'] === 'DO' ? 'acq.view' : 'sales.view', ['id' => $r['id']])) ?>"><?= e($r['ref_no']) ?></a> <?php endforeach; ?></div>
    <?php endif; ?>
  </div>
</div>

<?php if ($d['approvals'] || $d['quote_approvals']): ?>
<div class="card highlight">
  <div class="card-head"><h2>รออนุมัติ</h2><?php if (can('acquisition.approve')): ?><a href="<?= e(url('approvals')) ?>">กล่องอนุมัติ →</a><?php endif; ?></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ประเภท</th><th>รายการ</th><th>ผู้ขอ / ลูกค้า</th><th class="num">มูลค่า</th><th>ขอเมื่อ</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($d['approvals'] as $a): ?>
      <tr>
        <td>อนุมัติซื้อ</td>
        <td><span class="ref"><?= e($a['ref_no']) ?></span> <?= e($a['brand'] . ' ' . $a['model']) ?><div class="muted">จาก <?= e($a['seller_name']) ?></div></td>
        <td><?= e($a['requester_name']) ?></td>
        <td class="num"><?= money($a['final_negotiated_price']) ?></td>
        <td class="nowrap"><?= e(dt($a['requested_at'])) ?></td>
        <td class="right"><a class="btn sm primary" href="<?= e(url('approvals.view', ['id' => $a['id']])) ?>">พิจารณา</a></td>
      </tr>
    <?php endforeach; ?>
    <?php foreach ($d['quote_approvals'] as $qv): ?>
      <tr>
        <td>อนุมัติใบเสนอราคา</td>
        <td><span class="ref"><?= e($qv['ref_no']) ?></span> ฉบับที่ <?= (int) $qv['version_no'] ?></td>
        <td><?= e($qv['buyer_name']) ?></td>
        <td class="num"><?= money($qv['total']) ?></td>
        <td class="nowrap"><?= e(dt($qv['approval_requested_at'])) ?></td>
        <td class="right"><a class="btn sm primary" href="<?= e(url('quotations.view', ['id' => $qv['quotation_id']])) ?>">พิจารณา</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<div class="card flush">
  <div class="card-head"><h2>ดีลที่กำลังดำเนินการ (<?= (int) $d['active_count'] ?>)</h2><a href="<?= e(url('leads', ['owner' => $team ? '' : 'me'])) ?>">ดูทั้งหมด →</a></div>
  <?php partial('followup_rows', ['rows' => $d['active'], 'showOwner' => $team]); ?>
</div>

<?php if ($d['trx_open']): ?>
<div class="card flush">
  <div class="card-head"><h2>ธุรกรรมที่รอส่งมอบ/ติดตั้ง</h2><a href="<?= e(url('trx')) ?>">ดูทั้งหมด →</a></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ธุรกรรม</th><th>ผู้ซื้อ</th><th>ปิดการขายเมื่อ</th><th>สถานะ</th></tr></thead>
    <tbody>
    <?php foreach ($d['trx_open'] as $t): ?>
      <tr><td><a href="<?= e(url('trx.view', ['id' => $t['id']])) ?>"><span class="ref"><?= e($t['ref_no']) ?></span></a></td><td><?= e($t['buyer_name']) ?></td><td><?= e(d($t['won_at'])) ?></td><td><?= badge('status', $t['status']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<div class="grid-4">
  <?php if ($d['stats']['buy_deals'] !== null): ?><div class="stat"><div class="n"><?= number_format($d['stats']['buy_deals']) ?></div><div class="l">ดีลซื้อที่ยังเปิด</div></div><?php endif; ?>
  <?php if ($d['stats']['sell_deals'] !== null): ?><div class="stat"><div class="n"><?= number_format($d['stats']['sell_deals']) ?></div><div class="l">ดีลขายที่ยังเปิด</div></div><?php endif; ?>
  <?php if ($d['stats']['in_stock'] !== null): ?><div class="stat"><div class="n"><?= number_format($d['stats']['in_stock']) ?></div><div class="l">เครื่องในสต็อก</div></div><?php endif; ?>
  <?php if ($d['stats']['won_month'] !== null): ?><div class="stat"><div class="n"><?= number_format($d['stats']['won_month']) ?></div><div class="l">ปิดการขายเดือนนี้<?= $d['stats']['won_value_month'] !== null ? ' · ' . money0($d['stats']['won_value_month']) . ' บาท' : '' ?></div></div><?php endif; ?>
</div>
