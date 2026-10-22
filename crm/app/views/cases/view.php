<div class="crumbs"><a href="<?= e(url('cases')) ?>">เคสบริการ</a> › <?= e($c['ref_no']) ?></div>
<div class="page-head">
  <div><h1>เคสบริการ <span class="ref"><?= e($c['ref_no']) ?></span></h1>
    <div class="sub"><?= badge('status', $c['status']) ?> <?= $c['under_warranty'] ? '<span class="badge blue">ในประกัน</span>' : '' ?>
      <span>เครื่อง <a href="<?= e(url('devices.view', ['id' => $device['id']])) ?>"><?= e($device['ref_no'] . ' ' . $device['brand'] . ' ' . $device['model']) ?></a></span>
      <?php if ($org): ?><span>ลูกค้า <a href="<?= e(url('customers.view', ['id' => $org['id']])) ?>"><?= e($org['name']) ?></a></span><?php endif; ?></div></div>
</div>
<?php if (can('service_case.edit')): ?>
<form method="post" action="<?= e(url('cases.save')) ?>" class="card">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($c['id']) ?>">
  <div class="form-grid">
    <?= f_textarea('issue', 'อาการ/ปัญหา', $c['issue'], ['rows' => 3, 'required' => true]) ?>
    <?= f_textarea('resolution', 'วิธีแก้ไข / ผลการดำเนินการ', $c['resolution'], ['rows' => 3]) ?>
    <?= f_select('status', 'สถานะ', array_intersect_key(LABELS['status'], array_flip(CASE_STATUSES)), $c['status']) ?>
    <?= f_select('assigned_to', 'ผู้รับผิดชอบ', user_options(['SERVICE_ENGINEER', 'SERVICE_DIRECTOR']), $c['assigned_to'], ['blank' => '—']) ?>
    <?= f_checkbox('under_warranty', 'อยู่ในระยะรับประกัน', (bool) $c['under_warranty']) ?>
  </div>
  <button class="btn primary" type="submit">บันทึก</button>
</form>
<?php else: ?>
<div class="card"><dl class="kv"><dt>ปัญหา</dt><dd class="pre"><?= e($c['issue']) ?></dd><dt>วิธีแก้ไข</dt><dd class="pre"><?= e($c['resolution'] ?? '—') ?></dd></dl></div>
<?php endif; ?>
<?php partial('tasks', ['parentType' => 'SERVICE_CASE', 'parentId' => $c['id'], 'tasks' => $tasks]); ?>
<?php partial('activity', ['parentType' => 'SERVICE_CASE', 'parentId' => $c['id'], 'activities' => $activities]); ?>
<?php partial('documents', ['parentType' => 'SERVICE_CASE', 'parentId' => $c['id'], 'documents' => $documents, 'defaultCategory' => 'PHOTO']); ?>
