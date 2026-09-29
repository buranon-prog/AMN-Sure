<div class="page-head">
  <div><h1>ลูกค้า / องค์กร</h1><div class="sub">คลินิก โรงพยาบาล ดีลเลอร์ และบุคคล ทั้งฝั่งผู้ขายและผู้ซื้อ</div></div>
  <div class="actions"><?php if (can('customer.edit')): ?><a class="btn primary" href="<?= e(url('customers.new')) ?>">+ เพิ่มลูกค้า</a><?php endif; ?></div>
</div>
<form class="filters" method="get">
  <input type="hidden" name="r" value="customers">
  <div class="field"><input type="search" name="q" value="<?= e(get('q')) ?>" placeholder="ชื่อ, เลข ORG-, เบอร์โทร, จังหวัด"></div>
  <div class="field"><select name="type"><option value="">ทุกประเภท</option><?php foreach (labels('org_type') as $k => $v): ?><option value="<?= e($k) ?>" <?= get('type') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
  <div class="field"><select name="owner"><option value="">ทุกผู้ดูแล</option><option value="me" <?= get('owner') === 'me' ? 'selected' : '' ?>>ลูกค้าที่ฉันดูแล</option></select></div>
  <button class="btn" type="submit">ค้นหา</button>
  <a class="btn link" href="<?= e(url('customers', ['archived' => get('archived') ? null : 1])) ?>"><?= get('archived') ? '← กลับรายการปกติ' : 'ดูที่เก็บเข้าคลัง' ?></a>
</form>
<div class="card flush">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ลูกค้า</th><th>ประเภท</th><th>โทร / LINE</th><th>จังหวัด</th><th>ผู้ดูแล</th><th>ดีลที่เปิด</th><th>กิจกรรมล่าสุด</th></tr></thead>
    <tbody>
    <?php if (!$page['rows']): ?><tr><td colspan="7" class="empty">ไม่พบลูกค้า</td></tr><?php endif; ?>
    <?php foreach ($page['rows'] as $o): ?>
      <tr>
        <td><a href="<?= e(url('customers.view', ['id' => $o['id']])) ?>"><b><?= e($o['name']) ?></b></a><div class="muted"><?= e($o['ref_no']) ?><?= $o['branch'] ? ' · ' . e($o['branch']) : '' ?></div></td>
        <td><?= e(label('org_type', $o['type'])) ?></td>
        <td><?= e($o['phone'] ?? '') ?><?= $o['line_id'] ? '<div class="muted">LINE ' . e($o['line_id']) . '</div>' : '' ?></td>
        <td><?= e($o['province'] ?? '—') ?></td>
        <td><?= e($o['owner_name'] ?? '—') ?></td>
        <td><?= (int) $o['active_deals'] ?: '—' ?></td>
        <td class="nowrap"><?= e(d($o['last_activity'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?= pager($page) ?>
