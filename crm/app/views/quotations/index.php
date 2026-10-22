<div class="page-head"><div><h1>ใบเสนอราคา</h1><div class="sub">แสดงฉบับล่าสุดของแต่ละใบ (ฉบับเก่าดูได้ในหน้าใบเสนอราคา)</div></div></div>
<div class="subnav">
  <a class="<?= !$status ? 'active' : '' ?>" href="<?= e(url('quotations')) ?>">ทั้งหมด</a>
  <?php foreach (['DRAFT', 'APPROVED', 'SENT', 'ACCEPTED', 'REJECTED', 'EXPIRED'] as $s): ?><a class="<?= $status === $s ? 'active' : '' ?>" href="<?= e(url('quotations', ['status' => $s])) ?>"><?= e(label('status', $s)) ?></a><?php endforeach; ?>
</div>
<form class="filters" method="get"><input type="hidden" name="r" value="quotations"><input type="hidden" name="status" value="<?= e($status) ?>">
  <div class="field"><input type="search" name="q" value="<?= e(get('q')) ?>" placeholder="QTN-, SO-, ลูกค้า"></div><button class="btn" type="submit">ค้นหา</button></form>
<div class="card flush">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ใบเสนอราคา</th><th>ลูกค้า</th><th>ดีล</th><th class="num">ยอดรวม</th><th>ยืนราคาถึง</th><th>สถานะ</th></tr></thead>
    <tbody>
    <?php if (!$page['rows']): ?><tr><td colspan="6" class="empty">ไม่มีใบเสนอราคา</td></tr><?php endif; ?>
    <?php foreach ($page['rows'] as $q): ?>
      <tr><td><a href="<?= e(url('quotations.view', ['id' => $q['id']])) ?>"><span class="ref"><?= e($q['ref_no']) ?></span></a> <span class="muted">ฉบับที่ <?= (int) $q['current_version_no'] ?></span></td>
        <td><?= e($q['buyer_name']) ?></td><td><a href="<?= e(url('sales.view', ['id' => $q['so_id']])) ?>"><span class="ref"><?= e($q['so_ref']) ?></span></a></td>
        <td class="num"><?= money($q['total']) ?></td><td><?= e(d($q['valid_until'])) ?></td>
        <td><?= badge('status', $q['v_status']) ?><?= $q['v_status'] === 'DRAFT' && $q['approval_requested_at'] ? ' <span class="badge amber">รออนุมัติ</span>' : '' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?= pager($page) ?>
