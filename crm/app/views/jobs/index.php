<div class="page-head"><div><h1>ใบงานช่าง</h1><div class="sub">ตรวจเครื่อง ซ่อม ปรับสภาพ และงานบำรุงรักษา</div></div></div>
<div class="subnav">
  <a class="<?= $status === 'open' ? 'active' : '' ?>" href="<?= e(url('jobs', ['status' => 'open'])) ?>">งานที่ยังเปิด</a>
  <a class="<?= get('mine') ? 'active' : '' ?>" href="<?= e(url('jobs', ['status' => 'open', 'mine' => 1])) ?>">งานของฉัน</a>
  <a class="<?= $status === 'DONE' ? 'active' : '' ?>" href="<?= e(url('jobs', ['status' => 'DONE'])) ?>">เสร็จแล้ว</a>
  <a class="<?= $status === 'all' ? 'active' : '' ?>" href="<?= e(url('jobs', ['status' => 'all'])) ?>">ทั้งหมด</a>
</div>
<div class="card flush">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ใบงาน</th><th>ประเภท</th><th>งาน</th><th>เครื่อง</th><th>ช่าง</th><th>นัด</th><th>สถานะ</th></tr></thead>
    <tbody>
    <?php if (!$page['rows']): ?><tr><td colspan="7" class="empty">ไม่มีใบงาน</td></tr><?php endif; ?>
    <?php foreach ($page['rows'] as $j): ?>
      <tr><td><a href="<?= e(url('jobs.view', ['id' => $j['id']])) ?>"><span class="ref"><?= e($j['ref_no']) ?></span></a></td><td><?= e(label('job_type', $j['type'])) ?></td>
        <td><?= e($j['title']) ?></td><td><?= $j['device_id'] ? '<a href="' . e(url('devices.view', ['id' => $j['device_id']])) . '">' . e($j['device_ref']) . '</a>' : '—' ?></td>
        <td><?= e($j['engineer_name'] ?? '—') ?></td><td><?= e(d($j['scheduled_date'])) ?></td><td><?= badge('status', $j['status']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?= pager($page) ?>
