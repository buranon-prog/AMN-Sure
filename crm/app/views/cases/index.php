<div class="page-head"><div><h1>เคสบริการหลังการขาย</h1><div class="sub">ลูกค้าแจ้งปัญหาเครื่องหลังส่งมอบ / งานรับประกัน</div></div></div>
<div class="subnav">
  <a class="<?= $status === 'open' ? 'active' : '' ?>" href="<?= e(url('cases', ['status' => 'open'])) ?>">ยังไม่ปิด</a>
  <?php foreach (CASE_STATUSES as $s): ?><a class="<?= $status === $s ? 'active' : '' ?>" href="<?= e(url('cases', ['status' => $s])) ?>"><?= e(label('status', $s)) ?></a><?php endforeach; ?>
  <a class="<?= $status === 'all' ? 'active' : '' ?>" href="<?= e(url('cases', ['status' => 'all'])) ?>">ทั้งหมด</a>
</div>
<p class="muted">เปิดเคสใหม่ได้จากหน้าเครื่อง (Device 360) → ปุ่ม "+ เคสบริการ"</p>
<div class="card flush">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>เคส</th><th>ลูกค้า</th><th>เครื่อง</th><th>ปัญหา</th><th>ประกัน</th><th>ผู้รับผิดชอบ</th><th>สถานะ</th></tr></thead>
    <tbody>
    <?php if (!$page['rows']): ?><tr><td colspan="7" class="empty">ไม่มีเคส</td></tr><?php endif; ?>
    <?php foreach ($page['rows'] as $c): ?>
      <tr><td><a href="<?= e(url('cases.view', ['id' => $c['id']])) ?>"><span class="ref"><?= e($c['ref_no']) ?></span></a><div class="muted"><?= e(d($c['reported_at'])) ?></div></td>
        <td><?= e($c['org_name'] ?? '—') ?></td><td><?= e($c['device_ref'] . ' ' . $c['brand'] . ' ' . $c['model']) ?></td><td><?= e(mb_substr($c['issue'], 0, 100)) ?></td>
        <td><?= $c['under_warranty'] ? '<span class="badge blue">ในประกัน</span>' : '—' ?></td><td><?= e($c['assignee_name'] ?? '—') ?></td><td><?= badge('status', $c['status']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?= pager($page) ?>
