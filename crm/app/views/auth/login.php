<div class="login-brand">
  <div class="logo">A</div>
  <h1><?= e(setting('company_name', 'AMN Sure')) ?> CRM</h1>
  <div class="muted">ระบบหลังบ้านสำหรับพนักงาน · เข้าได้เฉพาะผู้มีบัญชี</div>
</div>
<div class="card">
  <form method="post" action="<?= e(url('auth.login')) ?>">
    <?= csrf_field() ?>
    <?= f_input('login', 'ชื่อผู้ใช้ หรือ อีเมล', null, ['required' => true, 'autocomplete' => 'username', 'attrs' => ['autofocus' => true, 'autocapitalize' => 'none']]) ?>
    <?= f_input('password', 'รหัสผ่าน', null, ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
    <button type="submit" class="btn primary" style="width:100%;justify-content:center">เข้าสู่ระบบ</button>
  </form>
</div>
<p class="muted center" style="text-align:center;font-size:12.5px">ลืมรหัสผ่าน? ติดต่อ GM หรือผู้ดูแลระบบเพื่อรีเซ็ตรหัสผ่าน</p>
