<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();

    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    // หน่วงเวลาเล็กน้อยเพื่อลดความเสี่ยงจากการสุ่มรหัสผ่าน (brute force)
    usleep(300000);

    if (hash_equals(ADMIN_USERNAME, $username) && password_verify($password, ADMIN_PASSWORD_HASH)) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        header('Location: index.php');
        exit;
    }

    $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
}

$pageTitle = 'เข้าสู่ระบบ';
require __DIR__ . '/_layout_head.php';
?>

<div class="card" style="max-width:380px;margin:60px auto 0;">
  <h1 style="font-size:19px;color:var(--green-dark);margin-bottom:20px;">เข้าสู่ระบบแอดมิน</h1>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <div class="form-field">
      <label for="username">ชื่อผู้ใช้</label>
      <input type="text" id="username" name="username" required autofocus>
    </div>
    <div class="form-field">
      <label for="password">รหัสผ่าน</label>
      <input type="password" id="password" name="password" required>
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">เข้าสู่ระบบ</button>
  </form>
</div>

<?php require __DIR__ . '/_layout_foot.php'; ?>
