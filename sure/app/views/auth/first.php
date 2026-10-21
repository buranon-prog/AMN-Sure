<?php if ($sent): ?>
  <div class="auth-card">
    <div class="auth-head">
      <h1>ตรวจอีเมลของคุณ</h1>
      <p>ถ้าอีเมลนี้มีบัญชีที่ Manager สร้างไว้และยังไม่ได้เปิดใช้งาน ระบบส่งลิงก์ตั้งรหัสผ่านให้แล้ว (ดูในจดหมายขยะด้วย)</p>
    </div>
    <a class="btn block big" href="<?= e(url('auth.login')) ?>">กลับไปหน้าเข้าสู่ระบบ</a>
  </div>
<?php else: ?>
  <form method="post" action="<?= e(url('auth.first')) ?>" class="auth-card">
    <?= csrf_field() ?>
    <div class="auth-head">
      <h1>เข้าใช้ครั้งแรก</h1>
      <p>ใส่อีเมลที่ Manager ลงทะเบียนให้ ระบบจะส่งลิงก์สำหรับตั้งรหัสผ่าน</p>
    </div>
    <?= f_input('email', 'อีเมล', null, ['type' => 'email', 'required' => true, 'autocomplete' => 'email', 'attrs' => ['autofocus' => true, 'autocapitalize' => 'none', 'inputmode' => 'email']]) ?>
    <button type="submit" class="btn primary block big">ส่งลิงก์</button>
  </form>
  <p class="auth-links"><a href="<?= e(url('auth.login')) ?>">กลับไปหน้าเข้าสู่ระบบ</a></p>
<?php endif; ?>
