<div class="page-head">
  <div><h1>ดีลซื้อ (จากผู้ขาย)</h1><div class="sub">ลีดผู้ขาย → ตรวจเครื่อง → Cost Sheet → Valuation → เจรจา → GM อนุมัติ → ซื้อ</div></div>
  <div class="actions"><?php if (can('lead.edit')): ?><a class="btn primary" href="<?= e(url('leads.new', ['type' => 'SELLER'])) ?>">+ ลีดผู้ขาย</a><?php endif; ?></div>
</div>
<div class="subnav">
  <a class="<?= $status === 'open' ? 'active' : '' ?>" href="<?= e(url('acq', ['status' => 'open'])) ?>">กำลังดำเนินการ</a>
  <?php foreach (['WAITING_INSPECTION', 'INSPECTED', 'COSTED', 'NEGOTIATING', 'PENDING_APPROVAL', 'APPROVED', 'PURCHASED', 'LOST'] as $s): ?>
    <a class="<?= $status === $s ? 'active' : '' ?>" href="<?= e(url('acq', ['status' => $s])) ?>"><?= e(label('status', $s)) ?> <span class="muted">(<?= (int) ($counts[$s] ?? 0) ?>)</span></a>
  <?php endforeach; ?>
  <a class="<?= $status === 'all' ? 'active' : '' ?>" href="<?= e(url('acq', ['status' => 'all'])) ?>">ทั้งหมด</a>
</div>
<form class="filters" method="get">
  <input type="hidden" name="r" value="acq"><input type="hidden" name="status" value="<?= e($status) ?>">
  <div class="field"><input type="search" name="q" value="<?= e(get('q')) ?>" placeholder="DO-, ผู้ขาย, ยี่ห้อ, รุ่น, serial"></div>
  <div class="field"><select name="owner"><option value="">ทุกคน</option><option value="me" <?= get('owner') === 'me' ? 'selected' : '' ?>>ของฉัน</option></select></div>
  <button class="btn" type="submit">ค้นหา</button>
</form>
<div class="card flush">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ดีล</th><th>เครื่อง</th><th>ผู้ขาย</th><?php if (can('negotiation.view')): ?><th class="num">ราคาที่ผู้ขายต้องการ</th><?php endif; ?><th>ผู้รับผิดชอบ</th><th>วันติดตาม</th><th>สถานะ</th></tr></thead>
    <tbody>
    <?php if (!$page['rows']): ?><tr><td colspan="7" class="empty">ไม่มีดีล</td></tr><?php endif; ?>
    <?php foreach ($page['rows'] as $o): $active = !in_array($o['status'], DO_CLOSED, true); ?>
      <tr class="<?= $active && $o['next_follow_up_date'] && $o['next_follow_up_date'] < today() ? 'overdue' : '' ?>">
        <td><a href="<?= e(url('acq.view', ['id' => $o['id']])) ?>"><span class="ref"><?= e($o['ref_no']) ?></span></a></td>
        <td><b><?= e($o['brand']) ?></b> <?= e($o['model']) ?><div class="muted"><?= e($o['device_ref']) ?><?= $o['serial_number'] ? ' · S/N ' . e($o['serial_number']) : '' ?></div></td>
        <td><?= e($o['seller_name']) ?></td>
        <?php if (can('negotiation.view')): ?><td class="num"><?= money($o['expected_price']) ?></td><?php endif; ?>
        <td><?= e($o['owner_name']) ?></td>
        <td class="nowrap"><?= followup_badge($o['next_follow_up_date'], $active) ?></td>
        <td><?= badge('status', $o['status']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?= pager($page) ?>
