<?php $fin = can('finance.view'); ?>
<div class="page-head">
  <div><h1>สต็อก</h1><div class="sub">เครื่องที่ AMN Sure ซื้อเข้ามาแล้ว (Inventory)</div></div>
</div>
<?php if ($totals): ?>
<div class="grid-3" style="margin-bottom:16px">
  <div class="stat"><div class="n"><?= number_format((int) $totals['n']) ?></div><div class="l">เครื่องในสต็อก + จองแล้ว</div></div>
  <div class="stat"><div class="n"><?= money0($totals['cost']) ?></div><div class="l">ต้นทุนบัญชีรวม (บาท) 🔒</div></div>
  <div class="stat"><div class="n"><?= money0($totals['list']) ?></div><div class="l">ราคาตั้งขายรวม (บาท)</div></div>
</div>
<?php endif; ?>
<div class="subnav">
  <?php foreach (['IN_STOCK' => 'อยู่ในสต็อก', 'RESERVED' => 'จองแล้ว', 'SOLD' => 'ขายแล้ว', 'ALL' => 'ทั้งหมด'] as $k => $v): ?>
    <a class="<?= $status === $k ? 'active' : '' ?>" href="<?= e(url('inventory', ['status' => $k])) ?>"><?= e($v) ?></a>
  <?php endforeach; ?>
</div>
<form class="filters" method="get">
  <input type="hidden" name="r" value="inventory"><input type="hidden" name="status" value="<?= e($status) ?>">
  <div class="field"><input type="search" name="q" value="<?= e(get('q')) ?>" placeholder="INV-, DEV-, ยี่ห้อ, รุ่น, serial"></div>
  <button class="btn" type="submit">ค้นหา</button>
</form>
<div class="card flush">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>สต็อก</th><th>เครื่อง</th><th>รับเข้า</th><th>อยู่มาแล้ว</th><th>ที่เก็บ</th><th>สภาพ</th><th class="num">ราคาตั้งขาย</th><?php if ($fin): ?><th class="num">ต้นทุนบัญชี</th><?php endif; ?><th>สถานะ</th><?php if (can('inventory.edit')): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php if (!$page['rows']): ?><tr><td colspan="10" class="empty">ไม่มีรายการ</td></tr><?php endif; ?>
    <?php foreach ($page['rows'] as $i): ?>
      <tr>
        <td><span class="ref"><?= e($i['ref_no']) ?></span><div class="muted"><?= e(label('inventory_source', $i['source'])) ?></div></td>
        <td><a href="<?= e(url('devices.view', ['id' => $i['device_id']])) ?>"><b><?= e($i['brand']) ?></b> <?= e($i['model']) ?></a><div class="muted"><?= e($i['device_ref']) ?><?= $i['serial_number'] ? ' · S/N ' . e($i['serial_number']) : '' ?></div></td>
        <td class="nowrap"><?= e(d($i['received_date'])) ?></td>
        <td><?= in_array($i['status'], ['IN_STOCK', 'RESERVED'], true) ? (int) $i['days_in_stock'] . ' วัน' : '—' ?></td>
        <td><?= e($i['storage_location'] ?? '—') ?></td>
        <td><?= badge('technical', $i['technical_status']) ?></td>
        <td class="num"><?= money($i['list_price']) ?></td>
        <?php if ($fin): ?><td class="num"><?= money($i['book_cost'] ?? null) ?></td><?php endif; ?>
        <td><?= badge('status', $i['status']) ?><?= $i['trx_ref'] ? '<div class="muted">' . e($i['trx_ref']) . '</div>' : '' ?></td>
        <?php if (can('inventory.edit')): ?>
          <td><?php if (in_array($i['status'], ['IN_STOCK', 'RESERVED'], true)): ?>
            <details><summary class="btn sm">แก้ไข</summary>
              <form method="post" action="<?= e(url('inventory.update')) ?>" style="min-width:220px;margin-top:6px">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($i['id']) ?>">
                <?= f_input('list_price', 'ราคาตั้งขาย', $i['list_price'], ['type' => 'money']) ?>
                <?= f_input('storage_location', 'ที่เก็บ', $i['storage_location']) ?>
                <?= f_input('notes', 'หมายเหตุ', $i['notes']) ?>
                <button class="btn sm primary" type="submit">บันทึก</button>
              </form>
            </details>
          <?php endif; ?></td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?= pager($page) ?>
