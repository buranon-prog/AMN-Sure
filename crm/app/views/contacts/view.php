<div class="crumbs"><a href="<?= e(url('contacts')) ?>">ผู้ติดต่อ</a> › <?= e($c['name']) ?></div>
<div class="page-head">
  <div><h1><?= e($c['name']) ?></h1>
    <div class="sub"><?= e($c['position'] ?? '') ?><?php if ($org): ?> · <a href="<?= e(url('customers.view', ['id' => $org['id']])) ?>"><?= e($org['name']) ?></a><?php endif; ?>
      <?php if ($c['archived_at']): ?><span class="badge red">นำออกแล้ว</span><?php endif; ?></div></div>
  <div class="actions"><?php if (can('customer.edit')): ?><a class="btn primary" href="<?= e(url('contacts.edit', ['id' => $c['id']])) ?>">แก้ไข</a><?php endif; ?></div>
</div>
<div class="card">
  <dl class="kv">
    <dt>โทรศัพท์</dt><dd><?= e($c['phone'] ?? '—') ?></dd>
    <dt>LINE</dt><dd><?= e($c['line_id'] ?? '—') ?></dd>
    <dt>อีเมล</dt><dd><?= e($c['email'] ?? '—') ?></dd>
    <?php if ($c['notes']): ?><dt>หมายเหตุ</dt><dd class="pre"><?= e($c['notes']) ?></dd><?php endif; ?>
  </dl>
</div>
<?php if ($leads): ?>
<div class="card flush">
  <div class="card-head"><h2>ลีดที่เกี่ยวข้อง</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ลีด</th><th>ประเภท</th><th>ผู้รับผิดชอบ</th><th>สถานะ</th></tr></thead>
    <tbody><?php foreach ($leads as $l): ?>
      <tr><td><a href="<?= e(url('leads.view', ['id' => $l['id']])) ?>"><span class="ref"><?= e($l['ref_no']) ?></span></a></td><td><?= e(label('lead_type', $l['type'])) ?></td><td><?= e($l['owner_name']) ?></td><td><?= badge('status', $l['status']) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</div>
<?php endif; ?>
<?php partial('activity', ['parentType' => 'CONTACT', 'parentId' => $c['id'], 'activities' => $activities]); ?>
