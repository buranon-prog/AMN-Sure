<div class="crumbs"><a href="<?= e(url('devices.view', ['id' => $device['id']])) ?>"><?= e($device['ref_no']) ?></a> › เปิดเคสบริการ</div>
<div class="page-head"><h1>เปิดเคสบริการ: <?= e($device['brand'] . ' ' . $device['model']) ?></h1></div>
<form method="post" action="<?= e(url('cases.save')) ?>" class="card">
  <?= csrf_field() ?><input type="hidden" name="device_id" value="<?= e($device['id']) ?>">
  <div class="form-grid">
    <?= f_input('reported_at', 'วันที่ลูกค้าแจ้ง', today(), ['type' => 'date']) ?>
    <?= f_select('assigned_to', 'ผู้รับผิดชอบ', user_options(['SERVICE_ENGINEER', 'SERVICE_DIRECTOR']), null, ['blank' => '— มอบให้ทีม Service Engineering —']) ?>
    <?= f_textarea('issue', 'อาการ/ปัญหาที่ลูกค้าแจ้ง', null, ['rows' => 3, 'required' => true, 'class' => 'full']) ?>
    <?= f_checkbox('under_warranty', 'อยู่ในระยะรับประกัน', false) ?>
  </div>
  <div class="form-actions"><button class="btn primary" type="submit">เปิดเคส</button></div>
</form>
