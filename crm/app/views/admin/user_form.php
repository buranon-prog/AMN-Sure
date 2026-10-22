<?php $assignable = assignable_roles(); $isEdit = (bool) $u; ?>
<div class="crumbs"><a href="<?= e(url('admin.users')) ?>">ผู้ใช้และสิทธิ์</a> › <?= $isEdit ? e($u['name']) : 'เพิ่มผู้ใช้' ?></div>
<div class="page-head"><div><h1><?= $isEdit ? e($u['name']) : 'เพิ่มผู้ใช้' ?></h1>
  <?php if ($isEdit): ?><div class="sub"><?= $u['active'] ? '<span class="badge green">ใช้งาน</span>' : '<span class="badge gray">ปิดแล้ว</span>' ?> เข้าระบบล่าสุด <?= e(dt($u['last_login_at'])) ?></div><?php endif; ?></div></div>

<form method="post" action="<?= e(url('admin.user_save')) ?>" class="card">
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($u['id']) ?>"><?php endif; ?>
  <div class="form-grid">
    <?= f_input('name', 'ชื่อ-นามสกุล', $u['name'] ?? null, ['required' => true]) ?>
    <?= f_input('username', 'ชื่อผู้ใช้ (ใช้เข้าสู่ระบบ)', $u['username'] ?? null, ['required' => true, 'hint' => 'a-z 0-9 . _ - เช่น somchai.s', 'attrs' => ['autocapitalize' => 'none']]) ?>
    <?= f_input('email', 'อีเมล (ไม่บังคับ — ใช้เข้าสู่ระบบแทนชื่อผู้ใช้ได้)', $u['email'] ?? null, ['type' => 'email']) ?>
    <?= f_input('phone', 'โทรศัพท์', $u['phone'] ?? null) ?>
    <?php if (!$isEdit): ?>
      <?= f_input('password', 'รหัสผ่านชั่วคราว', $temp, ['required' => true, 'hint' => 'ส่งให้พนักงาน — ระบบจะบังคับให้เปลี่ยนเองตอนเข้าครั้งแรก']) ?>
    <?php endif; ?>
    <?= f_checkbox('active', 'บัญชีใช้งานได้', $u ? (bool) $u['active'] : true) ?>
  </div>
  <fieldset>
    <legend>Role (เลือกได้หลาย role)</legend>
    <?php foreach (ROLE_CODES as $rc): $dis = !in_array($rc, $assignable, true); ?>
      <div class="field check"><label><input type="checkbox" name="roles[]" value="<?= e($rc) ?>" <?= in_array($rc, $roles, true) ? 'checked' : '' ?> <?= $dis ? 'disabled' : '' ?>> <?= e(role_name($rc)) ?><?= $dis ? ' <span class="muted">(เฉพาะ GM มอบได้)</span>' : '' ?></label></div>
      <?php if ($dis && in_array($rc, $roles, true)): ?><input type="hidden" name="roles[]" value="<?= e($rc) ?>"><?php endif; ?>
    <?php endforeach; ?>
  </fieldset>
  <div class="form-actions"><button class="btn primary" type="submit">บันทึก</button><a class="btn" href="<?= e(url('admin.users')) ?>">กลับ</a></div>
</form>

<?php if ($isEdit): ?>
<div class="grid-2">
  <form method="post" action="<?= e(url('admin.user_reset')) ?>" class="card" data-confirm="ตั้งรหัสผ่านชั่วคราวใหม่ให้ผู้ใช้นี้? ผู้ใช้จะถูกออกจากระบบทุกเครื่อง">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($u['id']) ?>">
    <h2>รีเซ็ตรหัสผ่าน</h2>
    <?= f_input('temp_password', 'รหัสผ่านชั่วคราวใหม่', $temp, ['hint' => 'อย่างน้อย 10 ตัว มีตัวอักษรและตัวเลข']) ?>
    <button class="btn warn" type="submit">ตั้งรหัสผ่านชั่วคราว</button>
  </form>
  <?php if ($u['id'] !== current_user_id()): ?>
  <form method="post" action="<?= e(url('admin.user_active')) ?>" class="card <?= $u['active'] ? 'danger' : '' ?>" data-confirm="<?= $u['active'] ? 'ปิดบัญชีนี้? ผู้ใช้จะถูกออกจากระบบทันที' : 'เปิดใช้บัญชีนี้อีกครั้ง?' ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($u['id']) ?>"><input type="hidden" name="active" value="<?= $u['active'] ? '0' : '1' ?>">
    <h2><?= $u['active'] ? 'ปิดบัญชี' : 'เปิดบัญชี' ?></h2>
    <p class="muted"><?= $u['active'] ? 'ใช้เมื่อพนักงานลาออก — ข้อมูลที่เคยบันทึกยังอยู่ครบ และออกจากระบบทันที' : 'ให้ผู้ใช้กลับมาเข้าระบบได้' ?></p>
    <button class="btn <?= $u['active'] ? 'danger' : 'primary' ?>" type="submit"><?= $u['active'] ? 'ปิดบัญชี' : 'เปิดบัญชี' ?></button>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>
