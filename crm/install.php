<?php
// ตัวติดตั้งผ่านเว็บ: สร้างตาราง, ข้อมูลตั้งต้น, บัญชี GM คนแรก และไฟล์ app/config.php
// ใช้ได้ครั้งเดียว — เมื่อมี app/config.php แล้วหน้านี้จะไม่ทำอะไรอีก

require __DIR__ . '/app/bootstrap.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; form-action 'self'");
ini_set('display_errors', '0');

session_boot();

function install_checks(): array
{
    $checks = [];
    $checks[] = ['PHP เวอร์ชัน ' . PHP_VERSION . ' (ต้อง ' . MIN_PHP . ' ขึ้นไป)', version_compare(PHP_VERSION, MIN_PHP, '>='), true];
    foreach (['pdo_mysql' => true, 'mbstring' => true, 'json' => true, 'fileinfo' => false] as $ext => $required) {
        $checks[] = ['PHP extension: ' . $ext . ($required ? '' : ' (แนะนำ)'), extension_loaded($ext), $required];
    }
    $checks[] = ['เขียนไฟล์ในโฟลเดอร์ app/ ได้ (สำหรับสร้าง config.php)', is_writable(APP_DIR), false];
    foreach (['storage/uploads', 'storage/logs'] as $d) {
        $path = ROOT_DIR . '/' . $d;
        if (!is_dir($path)) @mkdir($path, 0750, true);
        $checks[] = ['เขียนไฟล์ในโฟลเดอร์ ' . $d . ' ได้', is_dir($path) && is_writable($path), true];
    }
    return $checks;
}

function install_config_php(array $db): string
{
    return "<?php\n// สร้างโดยตัวติดตั้งเมื่อ " . date('Y-m-d H:i') . " — เก็บเป็นความลับ ห้ามเผยแพร่\nreturn " . var_export([
        'db' => $db,
        'timezone' => 'Asia/Bangkok',
        'debug' => false,
    ], true) . ";\n";
}

$done = null;
$manualConfig = null;
$errors = [];

if (app_installed()) {
    $done = 'already';
} elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        csrf_check();
        foreach (install_checks() as $c) {
            if ($c[2] && !$c[1]) throw new AppError('ยังไม่ผ่านการตรวจสอบ: ' . $c[0]);
        }
        $db = [
            'host' => s($_POST['db_host'] ?? '') ?? 'localhost',
            'port' => (int) (s($_POST['db_port'] ?? '') ?? 3306),
            'name' => s($_POST['db_name'] ?? ''),
            'user' => s($_POST['db_user'] ?? ''),
            'pass' => (string) ($_POST['db_pass'] ?? ''),
        ];
        if (!$db['name'] || !$db['user']) throw new AppError('กรุณากรอกชื่อฐานข้อมูลและชื่อผู้ใช้ฐานข้อมูล');
        $company = s($_POST['company_name'] ?? '') ?? 'AMN Sure';
        $gmName = s($_POST['gm_name'] ?? '');
        $gmUser = mb_strtolower((string) s($_POST['gm_username'] ?? ''));
        $gmEmail = v_email($_POST['gm_email'] ?? null, 'อีเมล GM');
        $pw = (string) ($_POST['password'] ?? '');
        if (!$gmName || !$gmUser) throw new AppError('กรุณากรอกชื่อและชื่อผู้ใช้ของ GM');
        if (!preg_match('/^[a-z0-9._-]{2,60}$/', $gmUser)) throw new AppError('ชื่อผู้ใช้ใช้ได้เฉพาะ a-z 0-9 . _ - ยาว 2–60 ตัว');
        password_policy($pw);
        if ($pw !== (string) ($_POST['password_confirm'] ?? '')) throw new AppError('ยืนยันรหัสผ่านไม่ตรงกัน');

        try {
            $pdo = db_connect($db);
        } catch (PDOException $e) {
            throw new AppError('เชื่อมต่อฐานข้อมูลไม่ได้: ตรวจชื่อฐานข้อมูล ชื่อผู้ใช้ และรหัสผ่าน (' . $e->getCode() . ')');
        }
        $existing = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'")->fetchColumn();
        if ($existing) throw new AppError('ฐานข้อมูลนี้มีตารางของระบบอยู่แล้ว — ถ้าต้องการติดตั้งใหม่ให้ลบตารางเดิมใน phpMyAdmin ก่อน หรือใช้ฐานข้อมูลใหม่');

        install_schema($pdo);
        db_set($pdo);
        seed_base_data();
        setting_set('company_name', $company);
        create_user_raw($gmUser, $gmEmail, $gmName, $pw, ['GM']);

        $php = install_config_php($db);
        if (@file_put_contents(APP_DIR . '/config.php', $php, LOCK_EX) === false) {
            $manualConfig = $php;
        } else {
            @chmod(APP_DIR . '/config.php', 0640);
        }
        $done = 'installed';
    } catch (AppError $e) {
        $errors[] = $e->getMessage();
    } catch (ForbiddenError $e) {
        $errors[] = $e->getMessage();
    } catch (Throwable $e) {
        app_log('INSTALL ERROR: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        $errors[] = 'ติดตั้งไม่สำเร็จ: ' . $e->getMessage();
    }
}

$checks = install_checks();
ob_start();
?>
<div class="login-brand">
  <div class="logo">A</div>
  <h1>ติดตั้ง AMN Sure CRM</h1>
  <div class="muted">เวอร์ชัน <?= e(APP_VERSION) ?></div>
</div>

<?php if ($done === 'already'): ?>
  <div class="card">
    <h2>ติดตั้งเรียบร้อยแล้ว</h2>
    <p>ระบบถูกติดตั้งไว้แล้ว หน้านี้จะไม่ทำงานซ้ำ</p>
    <a class="btn primary" href="index.php">ไปหน้าเข้าสู่ระบบ</a>
  </div>
<?php elseif ($done === 'installed'): ?>
  <div class="card">
    <h2>✅ ติดตั้งสำเร็จ</h2>
    <?php if ($manualConfig !== null): ?>
      <div class="flash warning">เขียนไฟล์ <code>app/config.php</code> อัตโนมัติไม่ได้ กรุณาสร้างไฟล์นี้ใน File Manager ของ cPanel แล้ววางข้อความด้านล่างลงไป จากนั้นเปิดหน้าเข้าสู่ระบบ</div>
      <textarea rows="12" readonly class="mono"><?= e($manualConfig) ?></textarea>
    <?php endif; ?>
    <ol>
      <li>เข้าสู่ระบบด้วยชื่อผู้ใช้ GM ที่เพิ่งตั้ง</li>
      <li>ไปที่เมนู <b>ผู้ใช้และสิทธิ์</b> เพื่อสร้างบัญชีให้พนักงานแต่ละคน และกำหนด role</li>
      <li>เปิด HTTPS ให้ subdomain (cPanel → SSL/TLS Status → Run AutoSSL) ก่อนให้พนักงานใช้งาน</li>
    </ol>
    <a class="btn primary" href="index.php">ไปหน้าเข้าสู่ระบบ</a>
  </div>
<?php else: ?>
  <?php foreach ($errors as $err): ?><div class="flash error"><?= e($err) ?></div><?php endforeach; ?>
  <div class="card">
    <h2>1. ตรวจสอบเซิร์ฟเวอร์</h2>
    <ul class="list-plain">
      <?php foreach ($checks as $c): ?>
        <li><?= $c[1] ? '✅' : ($c[2] ? '❌' : '⚠️') ?> <?= e($c[0]) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php if (!is_https()): ?>
      <div class="flash warning" style="margin-top:10px">ตอนนี้เปิดผ่าน http:// (ไม่เข้ารหัส) แนะนำให้เปิด SSL ใน cPanel แล้วติดตั้งผ่าน https://</div>
    <?php endif; ?>
  </div>
  <form method="post" action="install.php" class="card">
    <?= csrf_field() ?>
    <h2>2. ฐานข้อมูล MySQL</h2>
    <p class="muted">สร้างฐานข้อมูลและผู้ใช้ใน cPanel → <b>MySQL Database Wizard</b> ก่อน (ให้สิทธิ์ ALL PRIVILEGES)</p>
    <div class="form-grid">
      <?= f_input('db_host', 'Host', $_POST['db_host'] ?? 'localhost', ['required' => true]) ?>
      <?= f_input('db_port', 'Port', $_POST['db_port'] ?? '3306') ?>
      <?= f_input('db_name', 'ชื่อฐานข้อมูล', $_POST['db_name'] ?? null, ['required' => true, 'placeholder' => 'amnsureco_crm']) ?>
      <?= f_input('db_user', 'ชื่อผู้ใช้ฐานข้อมูล', $_POST['db_user'] ?? null, ['required' => true, 'placeholder' => 'amnsureco_crmuser']) ?>
      <?= f_input('db_pass', 'รหัสผ่านฐานข้อมูล', null, ['type' => 'password', 'autocomplete' => 'off']) ?>
    </div>
    <h2>3. บริษัทและบัญชี GM คนแรก</h2>
    <div class="form-grid">
      <?= f_input('company_name', 'ชื่อบริษัท', $_POST['company_name'] ?? 'AMN Sure', ['required' => true]) ?>
      <?= f_input('gm_name', 'ชื่อ-นามสกุล GM', $_POST['gm_name'] ?? null, ['required' => true]) ?>
      <?= f_input('gm_username', 'ชื่อผู้ใช้ (ใช้เข้าสู่ระบบ)', $_POST['gm_username'] ?? null, ['required' => true, 'placeholder' => 'เช่น gm', 'hint' => 'a-z 0-9 . _ - เท่านั้น']) ?>
      <?= f_input('gm_email', 'อีเมล (ไม่บังคับ)', $_POST['gm_email'] ?? null, ['type' => 'email']) ?>
      <?= f_input('password', 'รหัสผ่าน', null, ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'hint' => 'อย่างน้อย 10 ตัว มีตัวอักษรและตัวเลข']) ?>
      <?= f_input('password_confirm', 'ยืนยันรหัสผ่าน', null, ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
    </div>
    <div class="form-actions"><button class="btn primary" type="submit">ติดตั้ง</button></div>
  </form>
<?php endif;
$content = ob_get_clean();
$title = 'ติดตั้งระบบ';
$wide = true;
include APP_DIR . '/views/layout_plain.php';
