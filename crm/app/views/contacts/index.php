<div class="page-head">
  <div><h1>ผู้ติดต่อ</h1></div>
  <div class="actions"><?php if (can('customer.edit')): ?><a class="btn primary" href="<?= e(url('contacts.new')) ?>">+ เพิ่มผู้ติดต่อ</a><?php endif; ?></div>
</div>
<form class="filters" method="get">
  <input type="hidden" name="r" value="contacts">
  <div class="field"><input type="search" name="q" value="<?= e(get('q')) ?>" placeholder="ชื่อ, เบอร์โทร, อีเมล, LINE, องค์กร"></div>
  <button class="btn" type="submit">ค้นหา</button>
</form>
<div class="card flush">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ชื่อ</th><th>องค์กร</th><th>ตำแหน่ง</th><th>โทร</th><th>LINE</th><th>อีเมล</th></tr></thead>
    <tbody>
    <?php if (!$page['rows']): ?><tr><td colspan="6" class="empty">ไม่พบผู้ติดต่อ</td></tr><?php endif; ?>
    <?php foreach ($page['rows'] as $c): ?>
      <tr>
        <td><a href="<?= e(url('contacts.view', ['id' => $c['id']])) ?>"><?= e($c['name']) ?></a><?= $c['is_primary'] ? ' <span class="badge blue">หลัก</span>' : '' ?></td>
        <td><?php if ($c['organization_id']): ?><a href="<?= e(url('customers.view', ['id' => $c['organization_id']])) ?>"><?= e($c['org_name']) ?></a><?php else: ?>—<?php endif; ?></td>
        <td><?= e($c['position'] ?? '—') ?></td><td><?= e($c['phone'] ?? '—') ?></td><td><?= e($c['line_id'] ?? '—') ?></td><td><?= e($c['email'] ?? '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?= pager($page) ?>
