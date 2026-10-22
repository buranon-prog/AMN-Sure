<form method="post" action="<?= e(url('auth.verify')) ?>" class="auth-card">
  <?= csrf_field() ?>
  <div class="auth-head">
    <h1>ยืนยันทางอีเมล</h1>
    <p>ส่งรหัส 6 หลักไปที่ <b><?= e($email) ?></b> แล้ว</p>
  </div>
  <?= f_input('code', 'รหัสยืนยัน', null, ['required' => true, 'autocomplete' => 'one-time-code', 'class' => 'code-field', 'attrs' => ['inputmode' => 'numeric', 'maxlength' => '7', 'autofocus' => true]]) ?>
  <button type="submit" class="btn primary block big">ยืนยัน</button>
</form>
<div class="auth-links">
  <form method="post" action="<?= e(url('auth.resend')) ?>" class="inline"><?= csrf_field() ?><button type="submit" class="linkbtn">ส่งรหัสใหม่</button></form>
  <span aria-hidden="true">·</span>
  <a href="<?= e(url('auth.login')) ?>">ยกเลิก</a>
</div>
