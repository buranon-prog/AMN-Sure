<div class="page-head">
  <div><h1>เครื่องทั้งหมด (Device Master)</h1><div class="sub">1 เครื่องจริง = 1 Device ID ถาวร ใช้ตามเครื่องตั้งแต่ซื้อเข้าจนขายออกและขายคืน</div></div>
  <div class="actions"><?php if (can('device.edit')): ?><a class="btn primary" href="<?= e(url('devices.new')) ?>">+ เพิ่มเครื่อง</a><?php endif; ?></div>
</div>
<form class="filters" method="get">
  <input type="hidden" name="r" value="devices">
  <div class="field"><input type="search" name="q" value="<?= e(get('q')) ?>" placeholder="DEV-, ยี่ห้อ, รุ่น, serial, เจ้าของ"></div>
  <div class="field"><select name="status"><option value="">ทุกสถานะ</option><?php foreach (labels('commercial') as $k => $v): ?><option value="<?= e($k) ?>" <?= get('status') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
  <button class="btn" type="submit">ค้นหา</button>
</form>
<div class="card flush">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Device ID</th><th>เครื่อง</th><th>Serial</th><th>ปีผลิต</th><th>เจ้าของปัจจุบัน</th><th>สถานะการค้า</th><th>สถานะเทคนิค</th></tr></thead>
    <tbody>
    <?php if (!$page['rows']): ?><tr><td colspan="7" class="empty">ไม่พบเครื่อง</td></tr><?php endif; ?>
    <?php foreach ($page['rows'] as $dv): ?>
      <tr>
        <td><a href="<?= e(url('devices.view', ['id' => $dv['id']])) ?>"><span class="ref"><?= e($dv['ref_no']) ?></span></a></td>
        <td><a href="<?= e(url('devices.view', ['id' => $dv['id']])) ?>"><b><?= e($dv['brand']) ?></b> <?= e($dv['model']) ?></a><?= $dv['category'] ? '<div class="muted">' . e($dv['category']) . '</div>' : '' ?></td>
        <td class="mono"><?= e($dv['serial_number'] ?? '—') ?></td>
        <td><?= e($dv['manufacture_year'] ?? '—') ?></td>
        <td><?= (int) $dv['owned_by_amn'] === 1 ? '<b>AMN Sure</b>' : e($dv['owner_name'] ?? '—') ?></td>
        <td><?= badge('commercial', $dv['commercial_status']) ?></td>
        <td><?= badge('technical', $dv['technical_status']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?= pager($page) ?>
