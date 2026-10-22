<div class="card narrow">
  <h1>เปลี่ยนรหัสผ่าน</h1>
  <?php if ($forced): ?>
    <div class="flash warning">ผู้ดูแลระบบตั้งรหัสผ่านชั่วคราวให้คุณ กรุณาตั้งรหัสผ่านใหม่ของคุณเองก่อนใช้งาน</div>
  <?php endif; ?>
  <form method="post" action="<?= e(url('auth.password')) ?>">
    <?= csrf_field() ?>
    <?= f_input('current_password', 'รหัสผ่านปัจจุบัน', null, ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
    <?= f_input('new_password', 'รหัสผ่านใหม่', null, ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'hint' => 'อย่างน้อย 10 ตัวอักษร มีทั้งตัวอักษรและตัวเลข']) ?>
    <?= f_input('password_confirm', 'ยืนยันรหัสผ่านใหม่', null, ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
    <div class="form-actions"><button class="btn primary" type="submit">บันทึกรหัสผ่านใหม่</button></div>
  </form>
</div>
