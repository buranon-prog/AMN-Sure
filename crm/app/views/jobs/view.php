<?php $open = !in_array($j['status'], ['DONE', 'CANCELLED'], true); ?>
<div class="crumbs"><a href="<?= e(url('jobs')) ?>">ใบงานช่าง</a> › <?= e($j['ref_no']) ?></div>
<div class="page-head">
  <div><h1><?= e($j['title']) ?></h1>
    <div class="sub"><span class="ref"><?= e($j['ref_no']) ?></span> <?= e(label('job_type', $j['type'])) ?> <?= badge('status', $j['status']) ?>
      <?php if ($device): ?><span>เครื่อง <a href="<?= e(url('devices.view', ['id' => $device['id']])) ?>"><?= e($device['ref_no'] . ' ' . $device['brand'] . ' ' . $device['model']) ?></a></span><?php endif; ?>
      <?php if ($j['parent_type'] && $j['parent_id'] && $j['parent_type'] !== 'DEVICE'): ?><span>จาก <a href="<?= e(parent_url($j['parent_type'], $j['parent_id'])) ?>"><?= e(parent_title($j['parent_type'], $j['parent_id'])) ?></a></span><?php endif; ?></div></div>
</div>
<div class="grid-2">
  <div class="card">
    <dl class="kv">
      <dt>ช่าง</dt><dd><?= e(user_name($j['assigned_engineer_id'])) ?></dd>
      <dt>วันนัด</dt><dd><?= e(d($j['scheduled_date'])) ?></dd>
      <dt>ชั่วโมงทำงาน</dt><dd><?= e($j['hours'] ?? '—') ?></dd>
      <dt>อะไหล่ที่ใช้</dt><dd class="pre"><?= e($j['parts_used'] ?? '—') ?></dd>
      <dt>ผลการทำงาน</dt><dd class="pre"><?= e($j['result_note'] ?? '—') ?></dd>
      <dt>เสร็จเมื่อ</dt><dd><?= e(dt($j['completed_at'])) ?></dd>
    </dl>
    <?php if ($j['type'] === 'INSPECTION' && $j['parent_type'] === 'INSPECTION'): ?><p><a class="btn" href="<?= e(url('acq.inspection', ['id' => $j['parent_id']])) ?>">ไปที่แบบฟอร์มตรวจเครื่อง</a></p><?php endif; ?>
  </div>
  <?php if ($open && can('job.edit') && $j['type'] !== 'INSPECTION'): ?>
  <form method="post" action="<?= e(url('jobs.update')) ?>" class="card">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($j['id']) ?>">
    <h2>อัปเดตใบงาน</h2>
    <div class="form-grid">
      <?= f_select('status', 'สถานะ', array_intersect_key(LABELS['status'], array_flip(JOB_STATUSES)), $j['status']) ?>
      <?= f_select('assigned_engineer_id', 'ช่าง', user_options(['SERVICE_ENGINEER', 'SERVICE_DIRECTOR']), $j['assigned_engineer_id'], ['blank' => '—']) ?>
      <?= f_input('scheduled_date', 'วันนัด', $j['scheduled_date'], ['type' => 'date']) ?>
      <?= f_input('hours', 'ชั่วโมงทำงาน', $j['hours']) ?>
      <?= f_textarea('parts_used', 'อะไหล่ที่ใช้', $j['parts_used'], ['rows' => 2]) ?>
      <?= f_textarea('result_note', 'ผลการทำงาน (บังคับเมื่อปิดงาน)', $j['result_note'], ['rows' => 2]) ?>
    </div>
    <button class="btn primary" type="submit">บันทึก</button>
    <p class="hint">ปิดงานซ่อม/ปรับสภาพแล้ว ระบบจะบันทึกเข้าประวัติซ่อมของเครื่องและเปลี่ยนสถานะเครื่องเป็น "พร้อมขาย"</p>
  </form>
  <?php endif; ?>
</div>
<?php partial('tasks', ['parentType' => 'TECHNICAL_JOB', 'parentId' => $j['id'], 'tasks' => $tasks]); ?>
<?php partial('activity', ['parentType' => 'TECHNICAL_JOB', 'parentId' => $j['id'], 'activities' => $activities]); ?>
<?php partial('documents', ['parentType' => 'TECHNICAL_JOB', 'parentId' => $j['id'], 'documents' => $documents, 'defaultCategory' => 'PHOTO']); ?>
