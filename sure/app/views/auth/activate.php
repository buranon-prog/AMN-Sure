<form method="post" action="<?= e(url('auth.activate')) ?>" class="auth-card">
  <?= csrf_field() ?>
  <input type="hidden" name="t" value="<?= e($token) ?>">
  <div class="auth-head">
    <h1>ตั้งรหัสผ่าน</h1>
    <p><?= e($name) ?> · <?= e($email) ?></p>
  </div>
  <?= f_input('new_password', 'รหัสผ่านใหม่', null, ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'hint' => 'อย่างน้อย 10 ตัว มีทั้งตัวอักษรและตัวเลข', 'attrs' => ['autofocus' => true]]) ?>
  <?= f_input('password_confirm', 'ยืนยันรหัสผ่าน', null, ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
  <button type="submit" class="btn primary block big">ตั้งรหัสผ่านและเข้าใช้งาน</button>
</form>
