<?php
// ตัวติดตั้งผ่านเว็บ: สร้างตาราง, รายการตัวเลือก + VAT 7%, บัญชี Manager, การส่งอีเมล และไฟล์ app/config.php
// บัญชี Manager สร้างด้วยอีเมลเท่านั้น — ระบบส่งลิงก์ให้แต่ละคนตั้งรหัสผ่านเอง (ไม่มีใครรู้รหัสของคนอื่น)
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
    foreach (['storage/logs'] as $d) {
        $path = ROOT_DIR . '/' . $d;
        if (!is_dir($path)) @mkdir($path, 0750, true);
        $checks[] = ['เขียนไฟล์ในโฟลเดอร์ ' . $d . ' ได้', is_dir($path) && is_writable($path), true];
    }
    return $checks;
}

// บัญชี Manager ที่กำหนดไว้ (แก้ได้ในหน้าติดตั้ง) — ผู้ใช้สมัครเองไม่ได้
const INSTALL_MANAGERS = [['buranon@amn.co.th', 'Buranon'], ['pongsak@astracom.co.th', 'Pongsak']];

function install_config_array(array $db, string $baseUrl, array $mail): array
{
    return [
        'db' => $db,
        'timezone' => 'Asia/Bangkok',
        'debug' => false,
        'base_url' => $baseUrl,
        'app_key' => bin2hex(random_bytes(32)),
        'mail' => $mail,
        // ยืนยันทางอีเมลทุกครั้งที่เข้าสู่ระบบ — ตั้งเป็น false ชั่วคราวได้เฉพาะเมื่อส่งอีเมลไม่ได้
        'email_2fa' => true,
    ];
}

function install_config_php(array $cfg): string
{
    return "<?php\n// สร้างโดยตัวติดตั้งเมื่อ " . date('Y-m-d H:i') . " — เก็บเป็นความลับ ห้ามเผยแพร่ (มีรหัสฐานข้อมูลและรหัสอีเมล)\nreturn " . var_export($cfg, true) . ";\n";
}

/** ที่อยู่เว็บปัจจุบัน (ค่าเริ่มต้นในหน้าติดตั้ง) */
function install_guess_url(): string
{
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return (is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir . '/';
}

$done = null;
$manualConfig = null;
$errors = [];
$sent = [];

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
        $baseUrl = rtrim((string) s($_POST['base_url'] ?? ''), '/') . '/';
        if (!preg_match('#^https?://[^/\s]+(/[^\s]*)?$#i', $baseUrl)) throw new AppError('ที่อยู่เว็บต้องขึ้นต้นด้วย https:// เช่น https://crmsure.amnsure.co.th/');
        $transport = v_in($_POST['mail_transport'] ?? null, ['smtp', 'mail'], 'วิธีส่งอีเมล');
        $mail = [
            'transport' => $transport,
            'from' => v_email($_POST['mail_from'] ?? null, 'อีเมลผู้ส่ง', true),
            'from_name' => s($_POST['mail_from_name'] ?? '') ?? APP_NAME,
        ];
        if ($transport === 'smtp') {
            $mail += [
                'host' => s($_POST['smtp_host'] ?? ''),
                'port' => (int) (s($_POST['smtp_port'] ?? '') ?? 465),
                'encryption' => v_in($_POST['smtp_encryption'] ?? null, ['ssl', 'tls', 'none'], 'การเข้ารหัส SMTP'),
                'username' => s($_POST['smtp_user'] ?? ''),
                'password' => (string) ($_POST['smtp_pass'] ?? ''),
            ];
            if (!$mail['host']) throw new AppError('กรุณากรอก SMTP host');
        }
        $managers = [];
        foreach ([1, 2] as $i) {
            $em = v_email($_POST['mgr_email_' . $i] ?? null, 'อีเมล Manager ' . $i);
            $nm = s($_POST['mgr_name_' . $i] ?? '');
            if ($em === null) continue;
            if (!$nm) throw new AppError('กรุณากรอกชื่อของ Manager ' . $i);
            if (isset($managers[$em])) throw new AppError('อีเมล Manager ซ้ำกัน');
            $managers[$em] = $nm;
        }
        if (!$managers) throw new AppError('กรุณากรอกอีเมล Manager อย่างน้อย 1 คน');

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
        $cfg = install_config_array($db, $baseUrl, $mail);
        $GLOBALS['APP_CONFIG'] = $cfg;
        $ids = [];
        foreach ($managers as $em => $nm) $ids[] = create_user_invited($em, $nm, ROLE_MANAGER, true, true);

        $php = install_config_php($cfg);
        if (@file_put_contents(APP_DIR . '/config.php', $php, LOCK_EX) === false) {
            $manualConfig = $php;
        } else {
            @chmod(APP_DIR . '/config.php', 0640);
        }
        $done = 'installed';
        // ส่งลิงก์ตั้งรหัสผ่านให้ Manager แต่ละคน (ส่งไม่สำเร็จ → ขอใหม่ได้ที่หน้า "เข้าใช้ครั้งแรก" หลังแก้การตั้งค่าอีเมล)
        foreach ($ids as $uid) {
            $u = one('SELECT * FROM users WHERE id = ?', [$uid]);
            try {
                issue_activation($u);
                $sent[] = [$u['email'], null];
            } catch (Throwable $e) {
                app_log('INSTALL MAIL ' . $u['email'] . ': ' . $e->getMessage());
                $sent[] = [$u['email'], $e->getMessage()];
            }
        }
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
  <div class="logo">S</div>
  <h1>ติดตั้ง <?= e(APP_NAME) ?></h1>
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
    <h3>ส่งลิงก์ตั้งรหัสผ่านให้ Manager</h3>
    <ul class="list-plain">
      <?php foreach ($sent as [$em, $err]): ?>
        <li><?= $err === null ? '✅' : '❌' ?> <?= e($em) ?><?= $err === null ? ' — ส่งแล้ว' : ' — ส่งไม่สำเร็จ: ' . e($err) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php if (array_filter($sent, function ($x) { return $x[1] !== null; })): ?>
      <div class="flash warning">แก้ส่วน <code>'mail'</code> ใน <code>app/config.php</code> (File Manager ของ cPanel) แล้วเปิดหน้า "เข้าใช้ครั้งแรก" เพื่อขอลิงก์ใหม่</div>
    <?php endif; ?>
    <ol>
      <li>Manager แต่ละคนเปิดอีเมล กดลิงก์ แล้วตั้งรหัสผ่านเอง (ลิงก์ใช้ได้ <?= ACTIVATION_HOURS ?> ชั่วโมง — ไม่ได้รับให้ขอใหม่ที่หน้า "เข้าใช้ครั้งแรก")</li>
      <li>ครั้งต่อไปเข้าสู่ระบบด้วยอีเมล + รหัสผ่าน แล้วใส่รหัส 6 หลักที่ระบบส่งไปทางอีเมล</li>
      <li>ไปที่เมนู <b>รายการตัวเลือก &amp; ค่าตั้งต้น</b> แก้รายชื่อพนักงาน (แก้เป็นชื่อจริง)</li>
      <li>ไปที่เมนู <b>บัญชีผู้ใช้</b> เพื่อสร้างบัญชีให้พนักงานแต่ละคน และกำหนดสิทธิ์ SURE (ดู / เพิ่ม-แก้ไข)</li>
      <li>เปิด HTTPS ให้ subdomain (cPanel → SSL/TLS Status → Run AutoSSL) ก่อนให้พนักงานใช้งาน</li>
      <li>พนักงาน: Manager สร้างบัญชีด้วยอีเมลของพนักงาน ระบบส่งลิงก์ให้ตั้งรหัสผ่านเอง</li>
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
      <?= f_input('db_name', 'ชื่อฐานข้อมูล', $_POST['db_name'] ?? null, ['required' => true, 'placeholder' => 'amnsureco_sure']) ?>
      <?= f_input('db_user', 'ชื่อผู้ใช้ฐานข้อมูล', $_POST['db_user'] ?? null, ['required' => true, 'placeholder' => 'amnsureco_sureuser']) ?>
      <?= f_input('db_pass', 'รหัสผ่านฐานข้อมูล', null, ['type' => 'password', 'autocomplete' => 'off']) ?>
    </div>
    <h2>3. ที่อยู่เว็บ</h2>
    <p class="muted">ใช้สร้างลิงก์ในอีเมล — ควรเป็น https://</p>
    <div class="form-grid">
      <?= f_input('base_url', 'ที่อยู่เว็บของระบบ', $_POST['base_url'] ?? install_guess_url(), ['required' => true, 'class' => 'full']) ?>
    </div>
    <h2>4. การส่งอีเมล (ลิงก์ตั้งรหัสผ่าน + รหัสยืนยันเข้าสู่ระบบ)</h2>
    <p class="muted">แนะนำ SMTP: สร้างกล่องอีเมล เช่น <code>noreply@amnsure.co.th</code> ใน cPanel → <b>Email Accounts</b> แล้วดูค่าที่ <b>Connect Devices</b> (Outgoing Server)</p>
    <div class="form-grid">
      <?= f_select('mail_transport', 'วิธีส่งอีเมล', ['smtp' => 'SMTP (แนะนำ)', 'mail' => 'PHP mail() ของโฮสต์'], $_POST['mail_transport'] ?? 'smtp', ['required' => true]) ?>
      <?= f_input('mail_from', 'อีเมลผู้ส่ง', $_POST['mail_from'] ?? null, ['type' => 'email', 'required' => true, 'placeholder' => 'noreply@amnsure.co.th']) ?>
      <?= f_input('mail_from_name', 'ชื่อผู้ส่ง', $_POST['mail_from_name'] ?? APP_NAME) ?>
    </div>
    <div class="form-grid" data-show-when="f_mail_transport=smtp">
      <?= f_input('smtp_host', 'SMTP host', $_POST['smtp_host'] ?? null, ['placeholder' => 'mail.amnsure.co.th']) ?>
      <?= f_input('smtp_port', 'Port', $_POST['smtp_port'] ?? '465') ?>
      <?= f_select('smtp_encryption', 'การเข้ารหัส', ['ssl' => 'SSL (port 465)', 'tls' => 'STARTTLS (port 587)', 'none' => 'ไม่เข้ารหัส'], $_POST['smtp_encryption'] ?? 'ssl') ?>
      <?= f_input('smtp_user', 'ชื่อผู้ใช้ SMTP (ปกติคืออีเมลเต็ม)', $_POST['smtp_user'] ?? null, ['placeholder' => 'noreply@amnsure.co.th', 'autocomplete' => 'off']) ?>
      <?= f_input('smtp_pass', 'รหัสผ่านอีเมล', null, ['type' => 'password', 'autocomplete' => 'off']) ?>
    </div>
    <h2>5. บัญชี Manager</h2>
    <p class="muted">สร้างได้เฉพาะอีเมลที่ระบุ (สมัครเองไม่ได้) — ระบบส่งลิงก์ไปให้แต่ละคนตั้งรหัสผ่านเอง</p>
    <div class="form-grid">
      <?php foreach ([1, 2] as $i): $d = INSTALL_MANAGERS[$i - 1]; ?>
        <?= f_input('mgr_email_' . $i, 'อีเมล Manager ' . $i, $_POST['mgr_email_' . $i] ?? $d[0], ['type' => 'email', 'required' => $i === 1]) ?>
        <?= f_input('mgr_name_' . $i, 'ชื่อ Manager ' . $i, $_POST['mgr_name_' . $i] ?? $d[1]) ?>
      <?php endforeach; ?>
    </div>
    <div class="form-actions"><button class="btn primary" type="submit">ติดตั้ง</button></div>
  </form>
<?php endif;
$content = ob_get_clean();
$title = 'ติดตั้งระบบ';
$wide = true;
include APP_DIR . '/views/layout_plain.php';
