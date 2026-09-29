<div class="crumbs"><a href="<?= e(url('devices.view', ['id' => $device['id']])) ?>"><?= e($device['ref_no']) ?></a> › เปิดใบงาน</div>
<div class="page-head"><h1>เปิดใบงานช่าง: <?= e($device['brand'] . ' ' . $device['model']) ?></h1></div>
<form method="post" action="<?= e(url('jobs.create')) ?>" class="card">
  <?= csrf_field() ?><input type="hidden" name="device_id" value="<?= e($device['id']) ?>">
  <div class="form-grid">
    <?= f_select('type', 'ประเภทงาน', ['REPAIR' => 'ซ่อม', 'REFURBISH' => 'ปรับสภาพ', 'PM' => 'บำรุงรักษา'], 'REPAIR') ?>
    <?= f_input('title', 'งาน', null, ['required' => true, 'placeholder' => 'เช่น เปลี่ยนสาย handpiece']) ?>
    <?= f_select('assigned_engineer_id', 'ช่างผู้รับผิดชอบ', user_options(['SERVICE_ENGINEER', 'SERVICE_DIRECTOR']), null, ['blank' => '— มอบให้ทีม Service Engineering —']) ?>
    <?= f_input('scheduled_date', 'วันนัด', null, ['type' => 'date']) ?>
  </div>
  <div class="form-actions"><button class="btn primary" type="submit">เปิดใบงาน</button></div>
</form>
