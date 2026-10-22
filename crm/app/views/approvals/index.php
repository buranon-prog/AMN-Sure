<div class="page-head"><div><h1>อนุมัติการซื้อ</h1><div class="sub">GM พิจารณาจากชุดข้อมูลเดียว: ผู้ขาย เครื่อง ผลตรวจ ประวัติซ่อม/MA ต้นทุน มูลค่า และการเจรจา</div></div></div>
<div class="card flush highlight">
  <div class="card-head"><h2>รอพิจารณา (<?= count($pending) ?>)</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ดีล</th><th>เครื่อง</th><th>ผู้ขาย</th><th class="num">ราคาที่ขอซื้อ</th><th>ผู้ขอ</th><th>ขอเมื่อ</th><th></th></tr></thead>
    <tbody>
    <?php if (!$pending): ?><tr><td colspan="7" class="empty">ไม่มีคำขอรออนุมัติ</td></tr><?php endif; ?>
    <?php foreach ($pending as $a): ?>
      <tr><td><a href="<?= e(url('acq.view', ['id' => $a['opp_id']])) ?>"><span class="ref"><?= e($a['ref_no']) ?></span></a></td>
        <td><?= e($a['brand'] . ' ' . $a['model']) ?> <span class="muted"><?= e($a['device_ref']) ?></span></td><td><?= e($a['seller_name']) ?></td>
        <td class="num"><b><?= money($a['final_negotiated_price']) ?></b></td><td><?= e($a['requester_name']) ?></td><td class="nowrap"><?= e(dt($a['requested_at'])) ?></td>
        <td class="right"><a class="btn sm primary" href="<?= e(url('approvals.view', ['id' => $a['id']])) ?>"><?= can('acquisition.approve') ? 'พิจารณา' : 'ดู' ?></a></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<div class="card flush">
  <div class="card-head"><h2>พิจารณาแล้วล่าสุด</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ดีล</th><th>เครื่อง</th><th>ผู้ขาย</th><th>ผล</th><th>โดย</th><th>เมื่อ</th></tr></thead>
    <tbody>
    <?php if (!$recent): ?><tr><td colspan="6" class="empty">ยังไม่มี</td></tr><?php endif; ?>
    <?php foreach ($recent as $a): ?>
      <tr><td><a href="<?= e(url('approvals.view', ['id' => $a['id']])) ?>"><span class="ref"><?= e($a['ref_no']) ?></span></a></td><td><?= e($a['brand'] . ' ' . $a['model']) ?></td><td><?= e($a['seller_name']) ?></td>
        <td><?= badge('approval', $a['decision']) ?><?= $a['approved_amount'] !== null ? ' ' . money($a['approved_amount']) : '' ?></td><td><?= e($a['approver_name'] ?? '—') ?></td><td class="nowrap"><?= e(dt($a['decided_at'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
