<div class="login-brand">
  <div class="logo">S</div>
  <h1><?= e(APP_NAME) ?></h1>
  <div class="muted">ลูกค้า · สาขา · เครื่อง · การขาย · บริการ — เข้าได้เฉพาะผู้มีบัญชี</div>
</div>
<div class="card">
  <form method="post" action="<?= e(url('auth.login')) ?>">
    <?= csrf_field() ?>
    <?= f_input('login', 'ชื่อผู้ใช้ หรือ อีเมล', null, ['required' => true, 'autocomplete' => 'username', 'attrs' => ['autofocus' => true, 'autocapitalize' => 'none']]) ?>
    <?= f_input('password', 'รหัสผ่าน', null, ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
    <button type="submit" class="btn primary block">เข้าสู่ระบบ</button>
  </form>
</div>
<p class="muted center-text">ลืมรหัสผ่าน? ติดต่อ Manager เพื่อรีเซ็ตรหัสผ่าน</p>
