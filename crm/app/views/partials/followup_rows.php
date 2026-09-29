<?php /** @var array $rows @var bool $showOwner */ ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th>ดีล</th><th>ลูกค้า</th><th>เรื่อง</th><th>Next action</th><th>วันติดตาม</th><?php if (!empty($showOwner)): ?><th>ผู้รับผิดชอบ</th><?php endif; ?><th>สถานะ</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="7" class="empty">ไม่มีรายการ</td></tr><?php endif; ?>
  <?php foreach ($rows as $r):
      $late = $r['is_active'] && $r['next_follow_up_date'] && $r['next_follow_up_date'] < today(); ?>
    <tr class="<?= $late ? 'overdue' : '' ?>">
      <td class="nowrap"><a href="<?= e(url($r['kind'] === 'DO' ? 'acq.view' : 'sales.view', ['id' => $r['id']])) ?>"><span class="ref"><?= e($r['ref_no']) ?></span></a>
        <div class="muted"><?= $r['kind'] === 'DO' ? 'ดีลซื้อ' : 'ดีลขาย' ?></div></td>
      <td><a href="<?= e(url('customers.view', ['id' => $r['org_id']])) ?>"><?= e($r['org_name']) ?></a></td>
      <td><?= e($r['subject']) ?></td>
      <td><?= e($r['next_action'] ?? '—') ?></td>
      <td class="nowrap"><?= followup_badge($r['next_follow_up_date'], (bool) $r['is_active']) ?></td>
      <?php if (!empty($showOwner)): ?><td><?= e($r['owner_name'] ?? '—') ?></td><?php endif; ?>
      <td><?= badge('status', $r['status']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
