<div class="page-head">
  <div><h1>ดีลขาย (ผู้ซื้อ)</h1><div class="sub">ลีดผู้ซื้อ → ความต้องการ → จับคู่เครื่อง → ใบเสนอราคา → มัดจำ → สัญญา → WON</div></div>
  <div class="actions"><?php if (can('lead.edit')): ?><a class="btn primary" href="<?= e(url('leads.new', ['type' => 'BUYER'])) ?>">+ ลีดผู้ซื้อ</a><?php endif; ?></div>
</div>
<div class="subnav">
  <a class="<?= $status === 'open' ? 'active' : '' ?>" href="<?= e(url('sales', ['status' => 'open'])) ?>">กำลังดำเนินการ</a>
  <?php foreach (SO_STATUSES as $s): ?>
    <a class="<?= $status === $s ? 'active' : '' ?>" href="<?= e(url('sales', ['status' => $s])) ?>"><?= e(label('status', $s)) ?> <span class="muted">(<?= (int) ($counts[$s] ?? 0) ?>)</span></a>
  <?php endforeach; ?>
  <a class="<?= $status === 'all' ? 'active' : '' ?>" href="<?= e(url('sales', ['status' => 'all'])) ?>">ทั้งหมด</a>
</div>
<form class="filters" method="get">
  <input type="hidden" name="r" value="sales"><input type="hidden" name="status" value="<?= e($status) ?>">
  <div class="field"><input type="search" name="q" value="<?= e(get('q')) ?>" placeholder="SO-, ผู้ซื้อ, เครื่องที่สนใจ"></div>
  <div class="field"><select name="owner"><option value="">ทุกคน</option><option value="me" <?= get('owner') === 'me' ? 'selected' : '' ?>>ของฉัน</option></select></div>
  <button class="btn" type="submit">ค้นหา</button>
</form>
<div class="card flush">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ดีล</th><th>ผู้ซื้อ</th><th>ต้องการ</th><th class="num">งบ</th><th>ผู้รับผิดชอบ</th><th>วันติดตาม</th><th>สถานะ</th></tr></thead>
    <tbody>
    <?php if (!$page['rows']): ?><tr><td colspan="7" class="empty">ไม่มีดีล</td></tr><?php endif; ?>
    <?php foreach ($page['rows'] as $s): $active = !in_array($s['status'], SO_CLOSED, true); ?>
      <tr class="<?= $active && $s['next_follow_up_date'] && $s['next_follow_up_date'] < today() ? 'overdue' : '' ?>">
        <td><a href="<?= e(url('sales.view', ['id' => $s['id']])) ?>"><span class="ref"><?= e($s['ref_no']) ?></span></a></td>
        <td><?= e($s['buyer_name']) ?></td>
        <td><?= e(trim(($s['req_brand'] ?? '') . ' ' . ($s['req_model'] ?? '') . ' ' . ($s['req_technology'] ?? '')) ?: $s['interested_device']) ?></td>
        <td class="num"><?= money($s['budget_max']) ?></td>
        <td><?= e($s['owner_name']) ?></td>
        <td class="nowrap"><?= followup_badge($s['next_follow_up_date'], $active) ?></td>
        <td><?= badge('status', $s['status']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?= pager($page) ?>
