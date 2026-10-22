<?php $isEdit = (bool) $u; $pending = $isEdit && $u['activated_at'] === null; ?>
<div class="crumbs"><a href="<?= e(url('users')) ?>">บัญชีผู้ใช้</a> › <?= $isEdit ? e($u['name']) : 'เพิ่มผู้ใช้' ?></div>
<div class="page-head"><div><h1><?= $isEdit ? e($u['name']) : 'เพิ่มผู้ใช้' ?></h1>
  <?php if ($isEdit): ?><div class="sub"><?= $u['active'] ? '<span class="badge green">ใช้งาน</span>' : '<span class="badge gray">ปิดแล้ว</span>' ?><?= $pending ? ' <span class="badge amber">รอเปิดใช้งาน</span>' : '' ?> เข้าระบบล่าสุด <?= e(dt($u['last_login_at'])) ?></div><?php endif; ?></div></div>

<form method="post" action="<?= e(url('users.save')) ?>" class="card">
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($u['id']) ?>"><?php endif; ?>
  <div class="form-grid">
    <?= f_input('name', 'ชื่อ-นามสกุล', $u['name'] ?? null, ['required' => true]) ?>
    <?= f_input('email', 'อีเมล (ใช้เข้าสู่ระบบและรับรหัสยืนยัน)', $u['email'] ?? null, ['type' => 'email', 'required' => true, 'attrs' => ['autocapitalize' => 'none'],
        'hint' => $isEdit ? 'เปลี่ยนอีเมล = รหัสผ่านเดิมใช้ไม่ได้ ระบบส่งลิงก์ตั้งรหัสผ่านไปที่อีเมลใหม่' : 'บันทึกแล้วระบบส่งลิงก์ตั้งรหัสผ่านไปที่อีเมลนี้ทันที (ใช้ได้ ' . ACTIVATION_HOURS . ' ชั่วโมง)']) ?>
    <?= f_select('role', 'ประเภทบัญชี', [ROLE_EMPLOYEE => 'พนักงาน (EMPLOYEE)', ROLE_MANAGER => 'Manager (เห็นและทำได้ทุกอย่าง)'], $u['role'] ?? ROLE_EMPLOYEE, ['required' => true]) ?>
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

<?php if ($isEdit && $u['active'] && $u['id'] !== current_user_id()): ?>
  <form method="post" action="<?= e(url('users.reset')) ?>" class="card narrow-left"<?= $pending ? '' : ' data-confirm="ส่งลิงก์ตั้งรหัสผ่านใหม่? รหัสผ่านเดิมของผู้ใช้นี้จะใช้ไม่ได้ทันที และถูกออกจากระบบทุกเครื่อง"' ?>>
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($u['id']) ?>">
    <?php if ($pending): ?>
      <h2>ยังไม่ได้เปิดใช้งาน</h2>
      <p class="muted">ผู้ใช้ยังไม่ได้กดลิงก์ในอีเมลเพื่อตั้งรหัสผ่าน (ลิงก์ใช้ได้ <?= ACTIVATION_HOURS ?> ชั่วโมง) — ส่งลิงก์ใหม่ไปที่ <?= e($u['email']) ?> ได้</p>
      <button class="btn" type="submit">ส่งลิงก์เปิดใช้งานอีกครั้ง</button>
    <?php else: ?>
      <h2>ลืมรหัสผ่าน</h2>
      <p class="muted">ส่งลิงก์ไปที่ <?= e($u['email']) ?> ให้ผู้ใช้ตั้งรหัสผ่านใหม่เอง — รหัสผ่านเดิมใช้ไม่ได้ทันที</p>
      <button class="btn warn" type="submit">ส่งลิงก์ตั้งรหัสผ่านใหม่</button>
    <?php endif; ?>
  </form>
<?php endif; ?>
