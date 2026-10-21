<?php $isEdit = (bool) $u; ?>
<div class="crumbs"><a href="<?= e(url('users')) ?>">บัญชีผู้ใช้</a> › <?= $isEdit ? e($u['name']) : 'เพิ่มผู้ใช้' ?></div>
<div class="page-head"><div><h1><?= $isEdit ? e($u['name']) : 'เพิ่มผู้ใช้' ?></h1>
  <?php if ($isEdit): ?><div class="sub"><?= $u['active'] ? '<span class="badge green">ใช้งาน</span>' : '<span class="badge gray">ปิดแล้ว</span>' ?> เข้าระบบล่าสุด <?= e(dt($u['last_login_at'])) ?></div><?php endif; ?></div></div>

<form method="post" action="<?= e(url('users.save')) ?>" class="card">
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($u['id']) ?>"><?php endif; ?>
  <div class="form-grid">
    <?= f_input('name', 'ชื่อ-นามสกุล', $u['name'] ?? null, ['required' => true]) ?>
    <?= f_input('username', 'ชื่อผู้ใช้ (ใช้เข้าสู่ระบบ)', $u['username'] ?? null, ['required' => true, 'hint' => 'a-z 0-9 . _ - เช่น somchai.s', 'attrs' => ['autocapitalize' => 'none']]) ?>
    <?= f_input('email', 'อีเมล (ไม่บังคับ — ใช้เข้าสู่ระบบแทนชื่อผู้ใช้ได้)', $u['email'] ?? null, ['type' => 'email']) ?>
    <?= f_select('role', 'ประเภทบัญชี', [ROLE_EMPLOYEE => 'พนักงาน (EMPLOYEE)', ROLE_MANAGER => 'Manager (เห็นและทำได้ทุกอย่าง)'], $u['role'] ?? ROLE_EMPLOYEE, ['required' => true]) ?>
    <?php if (!$isEdit): ?>
      <?= f_input('password', 'รหัสผ่านชั่วคราว', $temp, ['required' => true, 'hint' => 'ส่งให้ผู้ใช้ — ระบบจะบังคับให้เปลี่ยนเองตอนเข้าครั้งแรก']) ?>
    <?php endif; ?>
    <?= f_checkbox('active', 'บัญชีใช้งานได้ (เอาเครื่องหมายออก = ปิดบัญชี ผู้ใช้ถูกออกจากระบบทันที)', $u ? (bool) $u['active'] : true) ?>
  </div>
  <fieldset data-show-when="f_role=EMPLOYEE">
    <legend>สิทธิ์โมดูล SURE (สำหรับพนักงาน)</legend>
    <?= f_checkbox('can_view', 'ดู (canView) — ตาราง ลูกค้า สาขา ติดตามลูกค้า เครื่อง การขาย รับชำระเงิน บริการ + หน้าค้นหา + export', $u ? (bool) $u['can_view'] : true) ?>
    <?= f_checkbox('can_edit', 'เพิ่ม/แก้ไข (canEdit) — เพิ่ม/แก้ไขตารางข้างต้น (ต้องมีสิทธิ์ดูด้วย) · ลบแถวไม่ได้', $u ? (bool) $u['can_edit'] : true) ?>
    <p class="hint">พนักงานไม่เห็นต้นทุน กำไร GP หน้าสรุป และหน้ากำไรรายการขายในทุกกรณี</p>
  </fieldset>
  <div class="form-actions"><button class="btn primary" type="submit">บันทึก</button><a class="btn" href="<?= e(url('users')) ?>">กลับ</a></div>
</form>

<?php if ($isEdit): ?>
  <form method="post" action="<?= e(url('users.reset')) ?>" class="card narrow-left" data-confirm="ตั้งรหัสผ่านชั่วคราวใหม่ให้ผู้ใช้นี้? ผู้ใช้จะถูกออกจากระบบทุกเครื่อง">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($u['id']) ?>">
    <h2>รีเซ็ตรหัสผ่าน</h2>
    <?= f_input('temp_password', 'รหัสผ่านชั่วคราวใหม่', $temp, ['hint' => 'อย่างน้อย 10 ตัว มีตัวอักษรและตัวเลข']) ?>
    <button class="btn warn" type="submit">ตั้งรหัสผ่านชั่วคราว</button>
  </form>
<?php endif; ?>
