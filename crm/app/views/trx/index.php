<div class="page-head"><div><h1>ธุรกรรมขาย / ส่งมอบ</h1><div class="sub">หลังปิดการขาย: เช็กลิสต์ภายใน → QC → ส่งมอบ → ติดตั้ง → เสร็จสมบูรณ์</div></div></div>
<div class="subnav">
  <a class="<?= $status === 'open' ? 'active' : '' ?>" href="<?= e(url('trx', ['status' => 'open'])) ?>">กำลังดำเนินการ</a>
  <?php foreach (TRX_STATUSES as $s): ?><a class="<?= $status === $s ? 'active' : '' ?>" href="<?= e(url('trx', ['status' => $s])) ?>"><?= e(label('status', $s)) ?></a><?php endforeach; ?>
  <a class="<?= $status === 'all' ? 'active' : '' ?>" href="<?= e(url('trx', ['status' => 'all'])) ?>">ทั้งหมด</a>
</div>
<form class="filters" method="get"><input type="hidden" name="r" value="trx"><input type="hidden" name="status" value="<?= e($status) ?>">
  <div class="field"><input type="search" name="q" value="<?= e(get('q')) ?>" placeholder="TRX-, ผู้ซื้อ"></div><button class="btn" type="submit">ค้นหา</button></form>
<div class="card flush">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ธุรกรรม</th><th>ผู้ซื้อ</th><th>ดีล</th><th>ปิดการขาย</th><th class="num">มูลค่า</th><th>เช็กลิสต์ค้าง</th><th>สถานะ</th></tr></thead>
    <tbody>
    <?php if (!$page['rows']): ?><tr><td colspan="7" class="empty">ไม่มีธุรกรรม</td></tr><?php endif; ?>
    <?php foreach ($page['rows'] as $t): ?>
      <tr><td><a href="<?= e(url('trx.view', ['id' => $t['id']])) ?>"><span class="ref"><?= e($t['ref_no']) ?></span></a></td><td><?= e($t['buyer_name']) ?></td>
        <td><a href="<?= e(url('sales.view', ['id' => $t['sales_opportunity_id']])) ?>"><span class="ref"><?= e($t['so_ref']) ?></span></a></td>
        <td><?= e(d($t['won_at'])) ?></td><td class="num"><?= money($t['total_price']) ?></td>
        <td><?= in_array($t['status'], ['IN_PREPARATION'], true) ? ((int) $t['checklist_left'] ? (int) $t['checklist_left'] . ' รายการ' : '✅') : '—' ?></td><td><?= badge('status', $t['status']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?= pager($page) ?>
