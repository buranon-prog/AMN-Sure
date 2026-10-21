<form method="post" action="<?= e(url('auth.login')) ?>" class="auth-card">
  <?= csrf_field() ?>
  <?= f_input('login', 'อีเมล', null, ['type' => 'email', 'required' => true, 'autocomplete' => 'username', 'attrs' => ['autofocus' => true, 'autocapitalize' => 'none', 'inputmode' => 'email']]) ?>
  <?= f_input('password', 'รหัสผ่าน', null, ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
  <button type="submit" class="btn primary block big">เข้าสู่ระบบ</button>
</form>
<p class="auth-links"><a href="<?= e(url('auth.first')) ?>">เข้าใช้ครั้งแรก</a></p>
