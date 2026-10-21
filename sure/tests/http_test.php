<?php
// End-to-end test ผ่าน HTTP จริง (สเปกหัวข้อ 12 ข้อ 9 — ตรวจ payload จริง ไม่ใช่แค่หน้าจอ)
// เตรียม: ติดตั้งระบบ (install.php) + ใส่ข้อมูลตัวอย่าง (php tools/seed-demo.php <manager>) แล้วรัน
//   php tests/http_test.php http://127.0.0.1:8090 <manager-email> <manager-password> <โฟลเดอร์อีเมล>
// <โฟลเดอร์อีเมล> = ที่ server เขียนอีเมลที่ส่ง ('mail' => ['transport' => 'file', 'dir' => …] หรือ tests/fake_smtp.php)
// test จะสร้างบัญชีพนักงาน 3 แบบผ่านหน้า "บัญชีผู้ใช้" (เปิดใช้งานด้วยลิงก์ในอีเมล เข้าสู่ระบบด้วยรหัสทางอีเมล)
// แล้วตรวจทุกหน้า ทุก action และทุก export ในนามแต่ละบัญชี

require __DIR__ . '/mailbox.php';
if ($argc < 5) {
    fwrite(STDERR, "วิธีใช้: php tests/http_test.php <base-url> <manager-email> <manager-password> <โฟลเดอร์อีเมล>\n");
    exit(2);
}
$base = rtrim($argv[1], '/');
$mUser = $argv[2];
$mPass = $argv[3];
$MAILDIR = $argv[4];
$tmp = sys_get_temp_dir() . '/sure-http-' . getmypid();
date_default_timezone_set('Asia/Bangkok');
$today = date('Y-m-d');
@mkdir($tmp);

$pass = 0;
$fail = 0;
function ok($c, $m) { global $pass, $fail; if ($c) { $pass++; return; } $fail++; echo "  ✗ $m\n"; }
function section($n) { echo "\n▶ $n\n"; }

class Client
{
    public $base; public $jar; public $csrf = ''; public $name;
    public $last = ['status' => 0, 'body' => '', 'location' => null, 'headers' => ''];
    function __construct($base, $jar, $name) { $this->base = $base; $this->jar = $jar; $this->name = $name; @unlink($jar); }
    function req($method, $path, $data = null)
    {
        $ch = curl_init($this->base . '/' . ltrim($path, '/'));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        $raw = curl_exec($ch);
        $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $headers = substr($raw, 0, $hs);
        $body = substr($raw, $hs);
        $loc = preg_match('/^Location:\s*(.+)$/mi', $headers, $m) ? trim($m[1]) : null;
        if (preg_match('/name="_csrf" value="([a-f0-9]+)"/', $body, $m)) $this->csrf = $m[1];
        $this->last = ['status' => $status, 'body' => $body, 'location' => $loc, 'headers' => $headers];
        return $this->last;
    }
    function get($route, $params = []) { return $this->req('GET', 'index.php?' . http_build_query(['r' => $route] + $params)); }
    function post($route, $data)
    {
        if (!$this->csrf) $this->get('home');
        $data['_csrf'] = $this->csrf;
        return $this->req('POST', 'index.php?r=' . $route, $data);
    }
    /** ตาม redirect จนถึงหน้าที่แสดงผล (สูงสุด 4 ครั้ง) */
    function follow()
    {
        for ($i = 0; $i < 4 && $this->last['location']; $i++) {
            $l = $this->last['location'];
            $this->req('GET', (strpos($l, '?') === 0 ? 'index.php' : '') . preg_replace('#^https?://[^/]+/#', '', $l));
        }
        return $this->last;
    }
    function flashError() { return preg_match('/class="flash error">([^<]*)/', $this->last['body'], $m) ? html_entity_decode($m[1]) : null; }
    function flashSuccess() { return preg_match('/class="flash success">([^<]*)/', $this->last['body'], $m) ? html_entity_decode($m[1]) : null; }
    /** เข้าสู่ระบบ 2 ขั้นตอน: อีเมล + รหัสผ่าน → อ่านรหัส 6 หลักจากอีเมล → ใส่รหัส */
    function login($email, $pw)
    {
        global $MAILDIR;
        $this->get('auth.login');
        $t = microtime(true);
        $r = $this->post('auth.login', ['login' => $email, 'password' => $pw]);
        if (strpos((string) $r['location'], 'r=auth.verify') === false) return false;
        $code = mailbox_code(mailbox_latest($MAILDIR, mb_strtolower($email), $t));
        if (!$code) return false;
        $this->get('auth.verify');
        $r = $this->post('auth.verify', ['code' => $code]);
        if (strpos((string) $r['location'], 'r=home') === false) return false;
        $this->follow();
        return true;
    }
    /** เปิดใช้งานบัญชีจากลิงก์ในอีเมล: ตั้งรหัสผ่าน → เข้าสู่ระบบทันที */
    function activate($token, $pw)
    {
        $this->req('GET', 'index.php?r=auth.activate&t=' . $token);
        if (strpos($this->last['body'], 'name="new_password"') === false) return false;
        $r = $this->post('auth.activate', ['t' => $token, 'new_password' => $pw, 'password_confirm' => $pw]);
        if (strpos((string) $r['location'], 'r=home') === false) return false;
        $this->follow();
        return true;
    }
    function logout()
    {
        $this->get('settings');
        $this->post('auth.logout', []);
        $this->csrf = '';
    }
    /** หน้าเรียบร้อย: 200 และไม่มีข้อความ error ของระบบ */
    function okPage($label)
    {
        $b = $this->last['body'];
        $good = $this->last['status'] === 200 && strpos($b, 'ระบบขัดข้อง') === false && strpos($b, 'Fatal error') === false && strpos($b, 'Warning:') === false;
        ok($good, "[{$this->name}] $label → {$this->last['status']}" . (preg_match('/ระบบขัดข้อง[^<]*/u', $b, $m) ? ' ' . $m[0] : ''));
        return $good;
    }
}

/** ใช้ตอนต้องรู้ค่าที่พนักงาน "ต้องไม่เห็น" — ตัวเลขที่มีเฉพาะในข้อมูลต้นทุน/กำไรของชุดข้อมูลตัวอย่าง */
// (ไม่ใส่ 35,000 = VAT ของ SO26004, 20,000/40,000 = รหัสไปรษณีย์ 20000/40000, 300,000/420,000/480,000 = ค่าที่พนักงานเห็นได้)
const COST_ONLY_NUMBERS = ['450,000', '30,000', '600,000', '250,000', '90,000', '380,000', '700,000',
    '650,000', '285,000', '320,000', '740,000', '1,920,000', '870,000', '1,445,000', '1,025,000', '240,000', '60,000', '140,000', '130,000'];
const COST_ONLY_TEXT = ['ราคารับซื้อเบื้องต้น รอผลตรวจ', 'เทิร์นเครื่อง — หักจากราคาขายเครื่องใหม่', 'ฝากขาย — จ่ายเจ้าของเมื่อขายได้'];
const MANAGER_LABELS = ['ต้นทุน', 'กำไร', 'GP %', 'GP&nbsp;%', 'ความสัมพันธ์กับ SURE', 'จำนวนสาขา', 'เครื่องที่ซื้อ (เครื่อง)', 'ยอดซื้อรวม ก่อน VAT',
    'ซื้อล่าสุด', 'ยอดค้างชำระ (บาท)', 'เครื่องที่ขาย/ฝากขายให้ SURE', 'ติดต่อล่าสุด', 'นัดติดตามถัดไป', 'Manager เท่านั้น'];
const MANAGER_LINKS = ['r=dashboard', 'r=profit', 'r=costs', 'r=users'];

/**
 * ตรวจว่า response ไม่มีข้อมูลต้องห้ามของ EMPLOYEE
 * $allowSummary = หน้าค้นหา (สรุปลูกค้าอนุญาต) · $errorPage = หน้า 403 (ข้อความ "สำหรับ Manager เท่านั้น" เป็นข้อความแจ้ง ไม่ใช่ข้อมูล)
 */
function no_leak(Client $c, string $label, bool $allowSummary = false, bool $errorPage = false): void
{
    $b = $c->last['body'];
    $found = [];
    foreach (COST_ONLY_NUMBERS as $n) {
        $raw = str_replace(',', '', $n);
        if (preg_match('/(?<![\d,.])' . preg_quote($n, '/') . '(?![\d,])/', $b) || preg_match('/(?<![\d.,])' . $raw . '(\.0+)?(?![\d,])/', $b)) $found[] = $n;
    }
    if (preg_match('/\d+\.\d%/', $b, $m)) $found[] = 'percent ' . $m[0];
    foreach (COST_ONLY_TEXT as $t) if (strpos($b, $t) !== false) $found[] = $t;
    foreach (MANAGER_LINKS as $l) if (strpos($b, $l) !== false) $found[] = $l;
    $labels = $errorPage ? ['ต้นทุน', 'กำไร', 'GP %'] : MANAGER_LABELS;
    if ($allowSummary) $labels = ['ต้นทุน', 'กำไร', 'GP %', 'GP&nbsp;%', 'ความสัมพันธ์กับ SURE', 'Manager เท่านั้น'];
    foreach ($labels as $l) if (strpos($b, $l) !== false) $found[] = $l;
    ok(!$found, "[{$c->name}] $label ไม่มีข้อมูลต้องห้าม" . ($found ? ' — พบ: ' . implode(' | ', array_unique($found)) : ''));
}

// ================================================================ Manager
section('Manager: เข้าสู่ระบบ + สร้างบัญชีพนักงาน 3 แบบผ่านหน้าเว็บ');
$m = new Client($base, "$tmp/m.txt", 'manager');
ok($m->login($mUser, $mPass), 'manager เข้าสู่ระบบได้');
$m->get('home');
ok(strpos((string) $m->last['location'], 'r=dashboard') !== false, 'Manager เข้าแล้วไปหน้าสรุป');
$suffix = substr((string) time(), -6);
$accounts = [];
foreach ([['staff', 1, 1], ['viewer', 1, 0], ['noperm', 0, 0]] as [$kind, $v, $ed]) {
    $u = $kind . $suffix . '@example.test';
    $pw = 'Pass' . $suffix . 'abc';
    $data = ['name' => "ทดสอบ $kind", 'email' => $u, 'role' => 'EMPLOYEE', 'active' => '1'];
    if ($v) $data['can_view'] = '1';
    if ($ed) $data['can_edit'] = '1';
    $t = microtime(true);
    $m->post('users.save', $data);
    ok(strpos((string) $m->last['location'], 'users.edit') !== false, "สร้างบัญชี $kind ด้วยอีเมล");
    $m->follow();
    ok(strpos((string) $m->flashSuccess(), 'ส่งลิงก์ตั้งรหัสผ่านไปที่ ' . $u) !== false, "ส่งลิงก์เปิดใช้งานให้ $kind แล้ว");
    $tok = mailbox_token(mailbox_latest($MAILDIR, $u, $t));
    ok($tok !== null, "$kind ได้รับลิงก์เปิดใช้งานทางอีเมล");
    $accounts[$kind] = [$u, $pw, $tok];
}

section('Manager: ทุกหน้าเปิดได้ และตัวเลขตรงเกณฑ์');
$pages = ['dashboard', 'profit', 'costs', 'costs.new', 'customers', 'branches', 'followups', 'devices', 'sales', 'payments', 'services',
    'settings', 'export', 'users', 'users.new', 'customers.new', 'branches.new', 'followups.new', 'devices.new', 'sales.new', 'payments.new', 'services.new'];
foreach ($pages as $p) { $m->get($p); $m->okPage($p); }
$m->get('dashboard', ['year' => '2026']);
foreach (['2,790,000.00', '870,000.00', '31.2%', '1,062,200.00', '1,445,000.00'] as $n) ok(strpos($m->last['body'], $n) !== false, "หน้าสรุปแสดง $n");
$m->get('profit');
foreach (['2,790,000.00', '1,920,000.00', '870,000.00'] as $n) ok(strpos($m->last['body'], $n) !== false, "หน้ากำไรแสดง $n");
ok(strpos($m->last['body'], 'row-cancelled') !== false, 'แถวยกเลิกสีเทา');
$m->get('costs');
ok(strpos($m->last['body'], 'ยังไม่ใส่ต้นทุน 0 เครื่อง') !== false, 'หน้าต้นทุน: ยังไม่ใส่ต้นทุน 0 เครื่อง');
$m->get('costs.edit', ['id' => 'SURE26001']);
ok(strpos($m->last['body'], '480,000.00') !== false && strpos($m->last['body'], '36.0%') !== false && strpos($m->last['body'], '240,000.00') !== false, 'SURE26001 ต้นทุน 480,000 · 36.0% · กำไร 240,000');
$m->get('search', ['mode' => 'customer', 'code' => 'SURE001']);
ok(strpos($m->last['body'], 'SURE001-03') !== false && strpos($m->last['body'], '1,180,000.00') !== false && strpos($m->last['body'], 'ซื้อและขายเครื่อง') !== false, 'ค้นหาลูกค้า SURE001');
$m->get('sales.edit', ['id' => 'SO26001']);
ok(strpos($m->last['body'], '770,400.00') !== false && strpos($m->last['body'], 'ชำระครบ') !== false, 'SO26001 รับชำระแล้ว 770,400 ชำระครบ');
$m->get('devices.edit', ['id' => 'SURE26001']);
ok(strpos($m->last['body'], '14/03/2027') !== false, 'SURE26001 วันหมดประกัน 14/03/2027');
$m->get('export.csv', ['t' => 'T']);
ok($m->last['status'] === 200 && strpos($m->last['body'], "\xEF\xBB\xBF") === 0 && strpos($m->last['body'], 'ต้นทุนรวม (บาท)') !== false, 'Manager export ต้นทุนเครื่องได้ (มี BOM)');
$m->get('export.csv', ['t' => 'S']);
ok(strpos($m->last['body'], 'กำไรขั้นต้น (บาท)') !== false, 'Manager export การขายมีคอลัมน์ Manager');

// ================================================================ EMPLOYEE ดู+แก้
// ================================================================ ยืนยันตัวตนทางอีเมล
section('เข้าใช้ครั้งแรกด้วยลิงก์ในอีเมล + เข้าสู่ระบบด้วยรหัสทางอีเมล');
[$su, $sp, $stok] = $accounts['staff'];
$e = new Client($base, "$tmp/e.txt", 'staff');
$e->get('auth.login');
ok(strpos($e->last['body'], 'สมัคร') === false && strpos($e->last['body'], 'r=auth.first') !== false, 'หน้าเข้าสู่ระบบไม่มีปุ่มสมัคร มีเฉพาะ "เข้าใช้ครั้งแรก"');
$e->post('auth.login', ['login' => $su, 'password' => $sp]);
$e->follow();
ok($e->flashError() === 'อีเมลหรือรหัสผ่านไม่ถูกต้อง', 'ก่อนเปิดใช้งาน → เข้าสู่ระบบไม่ได้');
$e->req('GET', 'index.php?r=auth.activate&t=' . $stok);
ok(strpos($e->last['body'], 'ตั้งรหัสผ่าน') !== false && strpos($e->last['body'], 'ทดสอบ staff') !== false, 'ลิงก์ในอีเมลเปิดหน้าตั้งรหัสผ่าน');
$e->post('auth.activate', ['t' => $stok, 'new_password' => 'short1', 'password_confirm' => 'short1']);
$e->follow();
ok(strpos((string) $e->flashError(), 'อย่างน้อย 10') !== false, 'รหัสผ่านสั้น → ปฏิเสธ');
ok($e->activate($stok, $sp), 'ตั้งรหัสผ่านจากลิงก์ → เข้าสู่ระบบทันที');
$e->req('GET', 'index.php?r=auth.activate&t=' . $stok);
ok(strpos($e->last['body'], 'ลิงก์นี้ใช้ไม่ได้แล้ว') !== false, 'ลิงก์ใช้ซ้ำไม่ได้');
$e->logout();
$e->get('auth.login');
$t = microtime(true);
$e->post('auth.login', ['login' => $su, 'password' => $sp]);
ok(strpos((string) $e->last['location'], 'r=auth.verify') !== false, 'รหัสผ่านถูก → ไปหน้ายืนยันทางอีเมล (ยังไม่เข้าสู่ระบบ)');
$e->get('customers');
ok($e->last['status'] === 303 && strpos((string) $e->last['location'], 'auth.login') !== false, 'ระหว่างรอรหัส เปิดหน้าข้อมูลไม่ได้');
$e->get('auth.verify');
ok(strpos($e->last['body'], 's•') !== false && strpos($e->last['body'], '@example.test') !== false && strpos($e->last['body'], $su) === false, 'หน้ายืนยันแสดงอีเมลแบบซ่อนบางส่วน');
$code = mailbox_code(mailbox_latest($MAILDIR, $su, $t));
ok($code !== null, 'ได้รับรหัส 6 หลักทางอีเมล');
$e->post('auth.verify', ['code' => $code === '000000' ? '111111' : '000000']);
$e->follow();
ok(strpos((string) $e->flashError(), 'รหัสไม่ถูกต้อง') === 0, 'รหัสผิด → ปฏิเสธ');
$e->post('auth.resend', []);
$e->follow();
ok(strpos((string) $e->flashError(), 'ส่งรหัสใหม่ได้อีกครั้งใน') === 0, 'ส่งรหัสใหม่ทันทีไม่ได้ (ต้องรอ 60 วินาที)');
$e->post('auth.verify', ['code' => $code]);
ok(strpos((string) $e->last['location'], 'r=home') !== false, 'รหัสถูก → เข้าสู่ระบบ');
$e->follow();
$e->logout();
$e->get('auth.verify');
ok($e->last['status'] === 303 && strpos((string) $e->last['location'], 'auth.login') !== false, 'ไม่มีการเข้าสู่ระบบค้างอยู่ → หน้ายืนยันพาไปหน้าเข้าสู่ระบบ');
$x0 = new Client($base, "$tmp/x0.txt", 'anon');
$x0->get('auth.first');
$x0->post('auth.first', ['email' => 'nobody' . $suffix . '@example.test']);
$a1 = $x0->last['location'];
$x0->post('auth.first', ['email' => $su]);
ok($a1 !== null && $a1 === $x0->last['location'] && strpos((string) $a1, 'sent=1') !== false, 'หน้าเข้าใช้ครั้งแรกตอบเหมือนกันทุกอีเมล (ไม่บอกว่าอีเมลไหนมีบัญชี)');
ok(mailbox_latest($MAILDIR, 'nobody' . $suffix . '@example.test') === null, 'อีเมลที่ไม่มีบัญชี → ไม่ได้รับอะไร (สมัครเองไม่ได้)');

section('EMPLOYEE (ดู+แก้): หน้า Manager ถูกปฏิเสธ');
ok($e->login($su, $sp), 'พนักงานเข้าสู่ระบบด้วยรหัสทางอีเมลได้');
$e->get('home');
ok(strpos((string) $e->last['location'], 'r=search') !== false, 'พนักงานเข้าแล้วไปหน้าค้นหา');
foreach (['costs', 'costs.new', 'profit', 'dashboard', 'users', 'users.new'] as $p) {
    $e->get($p);
    ok($e->last['status'] === 403, "เปิด $p → 403");
    no_leak($e, "403 $p", false, true);
}
$e->get('costs.edit', ['id' => 'SURE26001']);
ok($e->last['status'] === 403, 'เปิด costs.edit → 403');
no_leak($e, '403 costs.edit', false, true);
$e->get('export.csv', ['t' => 'T']);
ok($e->last['status'] === 403, 'export ต้นทุนเครื่อง → 403');
$e->get('dashboard', ['year' => '2026']);
ok($e->last['status'] === 403, 'สรุป (เลือกปี) → 403');

section('EMPLOYEE: ทุกหน้า/ทุก export ไม่มีต้นทุน กำไร GP หรือคอลัมน์สรุปลูกค้า');
$tables = ['customers' => 'C', 'branches' => 'B', 'followups' => 'A', 'devices' => 'D', 'sales' => 'S', 'payments' => 'P', 'services' => 'V'];
foreach (array_keys($tables) as $p) {
    foreach ([[], ['cols' => 'all'], ['cols' => 'all', 'sort' => 'gp', 'dir' => 'desc'], ['cols' => 'all', 'sort' => 'totalcost'], ['f' => ['rel' => 'ผู้ซื้อ']]] as $params) {
        $e->get($p, $params);
        $e->okPage("$p " . http_build_query($params));
        no_leak($e, "$p " . urldecode(http_build_query($params)));
    }
    // ค้นหาด้วยตัวเลขต้นทุน ต้องไม่เจอแถวใด (ค้นได้เฉพาะฟิลด์ที่พนักงานเห็น)
    foreach (['450000', '480000.00', '1920000'] as $qq) {
        $e->get($p, ['q' => $qq, 'cols' => 'all']);
        ok(strpos($e->last['body'], 'ไม่พบข้อมูล') !== false || ($p === 'devices' && $qq === '480000.00'), "$p ค้นหา \"$qq\" ไม่เจอแถวจากข้อมูลต้นทุน");
    }
    $e->get($p . '.new');
    $e->okPage("$p.new");
    no_leak($e, "$p.new");
    // ทุกแถว: เปิดหน้ารายละเอียดของทุกรหัสที่อยู่ในรายการ
    $e->get($p, ['cols' => 'all']);
    preg_match_all('/r=' . $p . '\.edit&amp;id=([^"&]+)/', $e->last['body'], $mm);
    $ids = array_unique(array_map('urldecode', $mm[1]));
    ok(count($ids) > 0, "$p มีแถวให้ตรวจ");
    foreach ($ids as $id) {
        $e->get($p . '.edit', ['id' => $id]);
        $e->okPage("$p.edit $id");
        no_leak($e, "$p.edit $id");
    }
    $e->get('export.csv', ['t' => $tables[$p]]);
    ok($e->last['status'] === 200 && strpos($e->last['headers'], 'text/csv') !== false, "export $p ได้");
    no_leak($e, "export.csv $p");
}
foreach (['SURE26001', 'SURE26002', 'SURE26003', 'SURE26005', 'SURE26006', 'SURE26008'] as $code) {
    $e->get('search', ['code' => $code]);
    $e->okPage("ค้นหาเครื่อง $code");
    no_leak($e, "ค้นหาเครื่อง $code");
}
foreach (['SURE001', 'SURE002', 'SURE003', 'SURE008'] as $code) {
    $e->get('search', ['mode' => 'customer', 'code' => $code]);
    $e->okPage("ค้นหาลูกค้า $code");
    no_leak($e, "ค้นหาลูกค้า $code", true);
}
$e->get('search', ['mode' => 'customer', 'code' => 'SURE001']);
ok(strpos($e->last['body'], '1,180,000.00') !== false && strpos($e->last['body'], 'ซื้อและขายเครื่อง') !== false, 'หน้าค้นหาลูกค้า: พนักงานเห็นสรุปกับ SURE (ตามสเปก 7.2)');
foreach (['settings', 'export', 'home', 'auth.password'] as $p) {
    $e->get($p);
    if ($p === 'home') { $e->follow(); }
    $e->okPage($p);
    no_leak($e, $p);
}
$e->get('settings');
ok(strpos($e->last['body'], 'settings.add') === false && strpos($e->last['body'], 'settings.update') === false, 'หน้ารายการตัวเลือก: พนักงานไม่มีฟอร์มแก้ไข');

section('EMPLOYEE: action ที่ห้าม');
$e->get('customers');
foreach (['settings.add' => ['list' => 'ctype', 'value' => 'แอบเพิ่ม'], 'settings.vat' => ['vat' => '9'], 'settings.delete' => ['id' => '1'], 'settings.update' => ['id' => '1', 'value' => 'x', 'sort' => '1']] as $route => $data) {
    $e->post($route, $data);
    ok($e->last['status'] === 403, "POST $route → 403");
}
foreach (['customers' => 'SURE001', 'sales' => 'SO26001', 'payments' => 'RC26-0001', 'devices' => 'SURE26008', 'followups' => '1', 'branches' => 'SURE001-02', 'services' => 'SV26008'] as $p => $id) {
    $e->post($p . '.delete', ['_pk' => $id]);
    ok($e->last['status'] === 403, "ลบ $p $id → 403");
}
$e->post('costs.save', ['_pk' => 'SURE26001', 'cost' => '1']);
ok($e->last['status'] === 403, 'แก้ต้นทุน → 403');
$e->post('costs.delete', ['_pk' => 'SURE26001']);
ok($e->last['status'] === 403, 'ลบต้นทุน → 403');
$e->post('users.save', ['name' => 'แฮก', 'email' => 'hack' . $suffix . '@example.test', 'role' => 'MANAGER', 'active' => '1']);
ok($e->last['status'] === 403, 'สร้างบัญชี Manager → 403');
$m->get('sales.edit', ['id' => 'SO26001']);
ok(strpos($m->last['body'], 'SO26001') !== false, 'ข้อมูลยังอยู่ครบหลังพนักงานพยายามลบ');
$m->get('costs.edit', ['id' => 'SURE26001']);
ok(strpos($m->last['body'], '480,000.00') !== false, 'ต้นทุนไม่ถูกแก้');

section('EMPLOYEE: กรอกข้อมูลผ่านฟอร์ม C → B → D → S → P / V / A');
$e->get('customers.new');
preg_match('/id="f_id"[^>]*value="(SURE\d{3})"/', $e->last['body'], $mm);
$cust = $mm[1] ?? null;
ok($cust !== null, 'ฟอร์มลูกค้าแสดงรหัสถัดไป ' . $cust);
$e->post('customers.save', ['id' => $cust, 'since' => '2026-10-21', 'name' => 'คลินิกทดสอบ <script>alert(1)</script>', 'ctype' => 'คลินิกความงาม', 'contact' => 'คุณทดสอบ',
    'prov' => 'ขอนแก่น', 'phone' => '080-000-0000', 'taxid' => '', 'status' => 'ใช้งาน']);
ok(strpos((string) $e->last['location'], 'customers.edit') !== false, 'เพิ่มลูกค้าได้');
$e->follow();
ok(strpos($e->last['body'], '<script>alert(1)</script>') === false && strpos($e->last['body'], '&lt;script&gt;') !== false, 'XSS: ชื่อถูก escape');
ok(strpos($e->last['body'], 'ภาคตะวันออกเฉียงเหนือ') !== false, 'ภาคขึ้นอัตโนมัติจากจังหวัด');
ok(strpos($e->last['body'], 'บันทึกโดย ทดสอบ staff') !== false, 'แสดง "บันทึกโดย / แก้ไขล่าสุดโดย"');
$e->get('branches.new', ['cust' => $cust]);
ok(strpos($e->last['body'], 'value="' . $cust . '-01"') !== false, 'ฟอร์มสาขา: รหัสถัดไป ' . $cust . '-01');
$e->post('branches.save', ['id' => $cust . '-01', 'cust' => $cust, 'bname' => 'สาขาแรก', 'since' => '2026-10-21', 'addr' => '1 ถ.ทดสอบ', 'prov' => 'ขอนแก่น']);
ok(strpos((string) $e->last['location'], 'branches.edit') !== false, 'เพิ่มสาขาได้');
$e->post('branches.save', ['id' => 'SURE001-09', 'cust' => $cust, 'bname' => 'ผิด', 'since' => '2026-10-21', 'addr' => '1', 'prov' => 'ขอนแก่น']);
$e->follow();
ok($e->flashError() === '⚠ รหัสสาขาไม่ตรงกับรหัสลูกค้า', 'สาขารหัสไม่ตรงลูกค้า → ถูกปฏิเสธ');
$e->get('devices.new');
preg_match('/id="f_id"[^>]*value="(SURE\d{5})"/', $e->last['body'], $mm);
$dev = $mm[1] ?? null;
ok($dev !== null, 'ฟอร์มเครื่องแสดงรหัสถัดไป ' . $dev);
$serial = 'HTTP-' . $suffix;
$e->post('devices.save', ['id' => $dev, 'regdate' => '2026-10-21', 'cat' => 'Laser', 'brand' => 'Brand H', 'model' => 'H-1', 'serial' => $serial, 'acq' => 'ซื้อขาด', 'listprice' => '555,000', 'stock' => 'พร้อมขาย']);
ok(strpos((string) $e->last['location'], 'devices.edit') !== false, 'เพิ่มเครื่องได้');
$e->get('devices.new');
preg_match('/id="f_id"[^>]*value="(SURE\d{5})"/', $e->last['body'], $mm);
$e->post('devices.save', ['id' => $mm[1] ?? '', 'regdate' => '2026-10-21', 'cat' => 'Laser', 'brand' => 'B', 'model' => 'M', 'serial' => $serial, 'acq' => 'ซื้อขาด']);
$e->follow();
ok(strpos((string) $e->flashError(), 'Serial Number นี้มีอยู่แล้ว') !== false, 'Serial ซ้ำ → ถูกปฏิเสธ');
$e->get('sales.new');
preg_match('/id="f_id"[^>]*value="(SO\d{5})"/', $e->last['body'], $mm);
$so = $mm[1] ?? null;
$sale = ['id' => $so, 'date' => '2026-10-21', 'status' => 'มัดจำ / จองแล้ว', 'dev' => $dev, 'cust' => $cust, 'branch' => 'SURE002-01', 'channel' => 'SURE ขายตรง', 'sales' => 'Sales Executive 1', 'price' => '400,000.00', 'wmonths' => '6'];
$e->post('sales.save', $sale);
$e->follow();
ok($e->flashError() === '⚠ สาขานี้ไม่ใช่ของลูกค้านี้', 'การขาย: สาขาไม่ใช่ของลูกค้า → ถูกปฏิเสธ');
$e->post('sales.save', ['date' => '1999-12-31', 'branch' => $cust . '-01'] + $sale);
$e->follow();
ok(strpos((string) $e->flashError(), '01/01/2000 ถึง 31/12/2099') !== false, 'การขาย: วันที่นอกช่วง → ถูกปฏิเสธ');
$e->post('sales.save', ['branch' => $cust . '-01'] + $sale);
ok(strpos((string) $e->last['location'], 'sales.edit') !== false, 'บันทึกการขายได้ ' . $so);
$e->follow();
ok(strpos($e->last['body'], '428,000.00') !== false && strpos($e->last['body'], 'ยังไม่ชำระ') !== false, 'การขาย: ยอดรวม VAT 428,000 · ยังไม่ชำระ');
no_leak($e, 'หน้าการขายที่พนักงานเพิ่งบันทึก');
$e->get('sales.new');
preg_match('/id="f_id"[^>]*value="(SO\d{5})"/', $e->last['body'], $mm);
$e->post('sales.save', ['id' => $mm[1] ?? '', 'branch' => $cust . '-01'] + $sale);
$e->follow();
ok($e->flashError() === 'เครื่องนี้มีการขายที่ยังไม่ยกเลิกอยู่แล้ว (' . $so . ')', 'ขายเครื่องซ้ำ → ถูกปฏิเสธ');
$e->post('payments.save', ['receipt' => 'HT' . $suffix, 'date' => '2026-10-21', 'so' => $so, 'type' => 'มัดจำ', 'method' => 'โอนเงิน', 'amount' => '100,000', 'by' => 'Finance & Accounting 1']);
ok(strpos((string) $e->last['location'], 'payments.edit') !== false, 'บันทึกรับชำระเงินได้');
$e->follow();
ok(strpos($e->last['body'], '328,000.00') !== false, 'ยอดคงค้างของการขายนี้ 328,000');
$e->post('payments.save', ['receipt' => 'HT' . $suffix, 'date' => '2026-10-21', 'so' => $so, 'type' => 'มัดจำ', 'method' => 'โอนเงิน', 'amount' => '1', 'by' => 'Finance & Accounting 1']);
$e->follow();
ok(strpos((string) $e->flashError(), 'ห้ามซ้ำ') !== false, 'เลขที่ใบเสร็จซ้ำ → ถูกปฏิเสธ');
$e->get('services.new');
preg_match('/id="f_id"[^>]*value="(SV\d{5})"/', $e->last['body'], $mm);
$sv = $mm[1] ?? '';
$e->post('services.save', ['id' => $sv, 'date' => '2026-10-21', 'dev' => $dev, 'type' => 'ซ่อม', 'eng' => 'Service Engineer 1', 'jstatus' => 'รับเรื่อง']);
ok(strpos((string) $e->last['location'], 'services.edit') !== false, 'เปิดงานบริการได้');
$e->follow();
ok(strpos($e->last['body'], 'เครื่องในสต็อก SURE') !== false, 'บริการ: เครื่องยังไม่ส่งมอบ → เครื่องในสต็อก SURE');
$e->post('followups.save', ['date' => '2026-10-21', 'cust' => $cust, 'branch' => $cust . '-01', 'topic' => 'สนใจซื้อเครื่อง', 'result' => 'เปิด / รอติดตาม', 'nextdate' => $today, 'by' => 'Sales Executive 1']);
ok(strpos((string) $e->last['location'], 'followups.edit') !== false, 'บันทึกติดตามลูกค้าได้');
$fu = preg_match('/id=(\d+)/', (string) $e->last['location'], $mm) ? $mm[1] : '';
$e->follow();
ok(strpos($e->last['body'], '>วันนี้<') !== false || strpos($e->last['body'], 'วันนี้</span>') !== false, 'ครบกำหนดติดตาม: นัดวันนี้ → วันนี้');
$e->post('sales.save', array_merge($sale, ['_pk' => $so, 'id' => 'SO99999', 'branch' => $cust . '-01', 'deliv' => '2026-10-21', 'status' => 'ส่งมอบแล้ว', 'vat' => '0', 'paid' => '9']));
ok(strpos((string) $e->last['location'], 'id=' . $so) !== false, 'แก้ไขการขาย: รหัสหลักไม่เปลี่ยน');
$e->follow();
ok(strpos($e->last['body'], '28,000.00') !== false, 'ฟิลด์อัตโนมัติ (VAT) ไม่รับค่าจากฟอร์ม');
$e->get('devices.edit', ['id' => $dev]);
ok(strpos($e->last['body'], 'ส่งมอบแล้ว') !== false, 'ใส่วันที่ส่งมอบ → เครื่องเปลี่ยนเป็น ส่งมอบแล้ว');
no_leak($e, 'หน้าเครื่องหลังขาย');

// ================================================================ ดูอย่างเดียว
section('EMPLOYEE ดูอย่างเดียว');
[$vu, $vp, $vtok] = $accounts['viewer'];
$v = new Client($base, "$tmp/v.txt", 'viewer');
ok($v->activate($vtok, $vp), 'viewer เปิดใช้งานจากลิงก์ในอีเมลได้');
$v->logout();
ok($v->login($vu, $vp), 'viewer เข้าสู่ระบบได้');
$v->get('customers');
$v->okPage('customers');
ok(strpos($v->last['body'], 'customers.new') === false, 'ไม่มีปุ่ม + เพิ่ม');
no_leak($v, 'customers');
$v->get('customers.new');
ok($v->last['status'] === 403, 'เปิดฟอร์มเพิ่ม → 403');
$v->get('customers.edit', ['id' => 'SURE001']);
$v->okPage('customers.edit');
ok(strpos($v->last['body'], 'customers.save') === false, 'หน้ารายละเอียดเป็นแบบอ่านอย่างเดียว');
$v->post('customers.save', ['_pk' => 'SURE001', 'name' => 'แก้']);
ok($v->last['status'] === 403, 'POST แก้ไข → 403');
$v->get('export.csv', ['t' => 'S']);
ok($v->last['status'] === 200, 'export ได้');
no_leak($v, 'export sales');

// ================================================================ ไม่มีสิทธิ์ SURE
section('EMPLOYEE ที่ไม่มีสิทธิ์ SURE');
[$nu, $np, $ntok] = $accounts['noperm'];
$n = new Client($base, "$tmp/n.txt", 'noperm');
ok($n->activate($ntok, $np), 'noperm เปิดใช้งานจากลิงก์ในอีเมลได้');
$n->logout();
ok($n->login($nu, $np), 'noperm เข้าสู่ระบบได้');
$n->get('home');
$n->okPage('home');
ok(strpos($n->last['body'], 'ยังไม่ได้รับสิทธิ์') !== false, 'แสดงข้อความยังไม่ได้รับสิทธิ์');
foreach (array_keys($tables) as $p) ok(strpos($n->last['body'], 'r=' . $p) === false, "ไม่เห็นเมนู $p");
ok(strpos($n->last['body'], 'r=search') === false && strpos($n->last['body'], 'r=settings') === false && strpos($n->last['body'], 'r=export') === false, 'ไม่เห็นเมนูค้นหา/ตัวเลือก/export');
foreach (array_merge(array_keys($tables), ['search', 'settings', 'export', 'costs', 'profit', 'dashboard', 'users', 'customers.new']) as $p) {
    $n->get($p);
    ok($n->last['status'] === 403, "เปิด $p → 403");
}
$n->get('customers.edit', ['id' => 'SURE001']);
ok($n->last['status'] === 403, 'เปิดรายละเอียดลูกค้า → 403');
$n->get('export.csv', ['t' => 'C']);
ok($n->last['status'] === 403, 'export → 403');
$n->get('search', ['code' => 'SURE26001']);
ok($n->last['status'] === 403 && strpos($n->last['body'], 'HF300-21A045') === false, 'ค้นหา → 403 ไม่มีข้อมูลเครื่อง');

// ================================================================ ความปลอดภัยอื่น ๆ
section('ความปลอดภัย');
$x = new Client($base, "$tmp/x.txt", 'anon');
foreach (['customers', 'costs', 'dashboard', 'export.csv'] as $p) {
    $x->get($p, ['t' => 'T']);
    ok($x->last['status'] === 303 && strpos((string) $x->last['location'], 'auth.login') !== false, "ไม่ล็อกอิน: $p → ไปหน้าเข้าสู่ระบบ");
}
$e->req('POST', 'index.php?r=customers.save', ['id' => 'SURE999', 'name' => 'x']);
ok($e->last['status'] === 403, 'POST ไม่มี CSRF token → 403');
$m->get('users');
preg_match('/r=users\.edit&amp;id=([0-9a-f-]{36})"><b>ทดสอบ viewer<\/b><\/a><\/td><td>' . preg_quote($vu, '/') . '</', $m->last['body'], $mm);
$vid = $mm[1] ?? '';
$m->post('users.save', ['id' => $vid, 'name' => 'ทดสอบ viewer', 'email' => $vu, 'role' => 'EMPLOYEE', 'can_view' => '1']);
$v->get('customers');
ok($v->last['status'] === 303 && strpos((string) $v->last['location'], 'auth.login') !== false, 'Manager ปิดบัญชี → ผู้ใช้ถูกออกจากระบบทันที');
$m->get('users');
preg_match('/r=users\.edit&amp;id=([0-9a-f-]{36})"><b>ทดสอบ noperm<\/b><\/a><\/td><td>' . preg_quote($nu, '/') . '</', $m->last['body'], $mm);
$m->post('users.reset', ['id' => $mm[1] ?? '']);
$m->follow();
ok(strpos((string) $m->flashSuccess(), 'ส่งลิงก์ตั้งรหัสผ่านไปที่ ' . $nu) !== false, 'Manager ส่งลิงก์ตั้งรหัสผ่านใหม่ทางอีเมล');
$n->get('home');
ok($n->last['status'] === 303 && strpos((string) $n->last['location'], 'auth.login') !== false, 'ส่งลิงก์ตั้งรหัสใหม่ → ผู้ใช้ถูกออกจากระบบทันที');
ok(!$n->login($nu, $np), 'รหัสผ่านเดิมใช้ไม่ได้แล้ว');
$e->get('customers');
ok(preg_match("/Content-Security-Policy: default-src 'self'/i", $e->last['headers']) === 1, 'มี Content-Security-Policy');
ok(stripos($e->last['headers'], 'X-Frame-Options: DENY') !== false, 'มี X-Frame-Options');
$m->post('customers.delete', ['_pk' => 'SURE001']);
$m->follow();
ok(strpos((string) $m->flashError(), 'ลบไม่ได้ เพราะมีข้อมูลในตารางอื่นอ้างถึง') !== false, 'Manager ลบลูกค้าที่มีข้อมูลอ้างถึง → ข้อความที่อ่านเข้าใจ');

// ================================================================ GM
section('GM: ผู้ใช้ออนไลน์ + คุมสถานะบัญชีของทุกคน');
$m->get('gm');
if ($m->last['status'] !== 200) {
    ok($m->last['status'] === 403, 'Manager ที่ไม่ใช่ GM เปิดหน้า GM → 403');
    echo "  (บัญชี $mUser ไม่ใช่ GM — ข้ามส่วนที่ทดสอบในนาม GM)\n";
} else {
    $m->okPage('gm');
    ok(strpos($m->last['body'], 'r=gm"') !== false, 'GM เห็นเมนู "ผู้ใช้ออนไลน์ (GM)"');
    $userRow = function ($body, $email) {
        $q = preg_quote(htmlspecialchars($email), '/');
        return preg_match('/presence (on|idle|off)">[^<]*<\/span><\/td>\s*<td><b>[^<]*<\/b>(?:\s*<span[^>]*>[^<]*<\/span>)?<div class="muted small">' . $q . '<\/div>(.*?)<\/tr>/s', $body, $mm) ? ['presence' => $mm[1], 'rest' => $mm[2]] : null;
    };
    $sessionSid = function ($body, $email) {
        $q = preg_quote(htmlspecialchars($email), '/');
        return preg_match('/<div class="muted small">' . $q . '<\/div><\/td>.*?name="sid" value="([0-9a-f]{32})"/s', $body, $mm) ? $mm[1] : null;
    };
    $e->get('customers');
    $m->get('gm');
    $row = $userRow($m->last['body'], $su);
    ok($row && $row['presence'] === 'on', 'พนักงานที่กำลังใช้งาน → ออนไลน์');
    ok($row && preg_match('/<td class="num">1<\/td>/', $row['rest']), 'นับเครื่องที่เปิดอยู่ 1 เครื่อง');
    $nrow = $userRow($m->last['body'], $nu);
    ok($nrow && $nrow['presence'] === 'off', 'บัญชีที่ถูกรีเซ็ตรหัส → ออฟไลน์');
    ok(strpos($m->last['body'], 'เครื่องนี้') !== false, 'เห็นเครื่องที่ตัวเองใช้อยู่');
    ok(strpos($m->last['body'], 'data-autorefresh="30"') !== false, 'หน้ารีเฟรชเองทุก 30 วินาที');

    $sid = $sessionSid($m->last['body'], $su);
    ok($sid !== null, 'รายการเครื่องที่เข้าสู่ระบบอยู่ มีเครื่องของพนักงาน');
    $m->post('gm.kick', ['sid' => (string) $sid]);
    $m->follow();
    ok(strpos((string) $m->flashSuccess(), 'ออกจากระบบบนเครื่องนั้นแล้ว') !== false, 'GM ให้ออกจากระบบรายเครื่อง');
    $e->get('customers');
    ok($e->last['status'] === 303 && strpos((string) $e->last['location'], 'auth.login') !== false, 'เครื่องที่ถูกสั่งออก → หลุดทันที');
    ok($e->login($su, $sp), 'เข้าสู่ระบบใหม่ได้');

    $m->get('gm');
    preg_match('/<div class="muted small">' . preg_quote($su, '/') . '<\/div>.*?name="id" value="([0-9a-f-]{36})"/s', $m->last['body'], $mm);
    $sid2 = $mm[1] ?? '';
    $m->post('gm.logout_all', ['id' => $sid2]);
    $m->follow();
    ok(strpos((string) $m->flashSuccess(), 'ออกจากระบบทุกเครื่องแล้ว') !== false, 'GM ให้ออกจากระบบทุกเครื่อง');
    $e->get('customers');
    ok($e->last['status'] === 303, 'พนักงานหลุดจากทุกเครื่อง');
    $m->post('gm.active', ['id' => $sid2, 'active' => '0']);
    $m->follow();
    ok(strpos((string) $m->flashSuccess(), 'ปิดบัญชี') === 0, 'GM ปิดบัญชีจากหน้า GM');
    ok(!$e->login($su, $sp), 'บัญชีที่ถูกปิด → เข้าสู่ระบบไม่ได้');
    $m->post('gm.active', ['id' => $sid2, 'active' => '1']);
    $m->follow();
    ok($e->login($su, $sp), 'GM เปิดบัญชีคืน → เข้าสู่ระบบได้');
    $m->get('gm');
    ok(strpos($m->last['body'], 'GM สั่งออกจากระบบ') !== false && strpos($m->last['body'], 'เข้าไม่สำเร็จ') !== false, 'ประวัติแสดงการถูกสั่งออก และการเข้าไม่สำเร็จ');

    // Manager คนที่ 2 (ไม่ใช่ GM)
    $mu2 = 'mgr' . $suffix . '@example.test';
    $mp2 = 'Mgr' . $suffix . 'pass';
    $t = microtime(true);
    $m->post('users.save', ['name' => 'ทดสอบ manager2', 'email' => $mu2, 'role' => 'MANAGER', 'active' => '1']);
    $m2 = new Client($base, "$tmp/m2.txt", 'manager2');
    ok($m2->activate((string) mailbox_token(mailbox_latest($MAILDIR, $mu2, $t)), $mp2), 'Manager คนที่ 2 เปิดใช้งานบัญชี');
    $m2->get('gm');
    ok($m2->last['status'] === 403, 'Manager ที่ไม่ใช่ GM เปิดหน้า GM → 403');
    $m2->get('users');
    ok(strpos($m2->last['body'], 'r=gm"') === false, 'Manager ที่ไม่ใช่ GM ไม่เห็นเมนู GM');
    $m->get('users');
    preg_match('/r=users\.edit&amp;id=([0-9a-f-]{36})"><b>[^<]*<\/b><\/a><\/td><td>' . preg_quote(mb_strtolower($mUser), '/') . '</', $m->last['body'], $mm);
    $gmId = $mm[1] ?? '';
    ok($gmId !== '' && strpos($m2->last['body'], 'id=' . $gmId) === false, 'Manager คนอื่นไม่มีลิงก์แก้บัญชี GM');
    $m2->get('users.edit', ['id' => $gmId]);
    ok($m2->last['status'] === 403, 'Manager คนอื่นเปิดหน้าแก้บัญชี GM → 403');
    $m2->post('users.save', ['id' => $gmId, 'name' => 'x', 'email' => $mUser, 'role' => 'MANAGER']);
    ok($m2->last['status'] === 403, 'Manager คนอื่นปิดบัญชี GM → 403');
    $m2->post('users.reset', ['id' => $gmId]);
    ok($m2->last['status'] === 403, 'Manager คนอื่นรีเซ็ตรหัสผ่าน GM → 403');
    $m2->post('gm.logout_all', ['id' => $gmId]);
    ok($m2->last['status'] === 403, 'Manager คนอื่นสั่ง GM ออกจากระบบ → 403');
    $e->get('gm');
    ok($e->last['status'] === 403, 'พนักงานเปิดหน้า GM → 403');
    $m->get('home');
    ok(strpos((string) $m->last['location'], 'auth.login') === false, 'GM ยังอยู่ในระบบ');
    $m->get('gm');
    preg_match('/<div class="muted small">' . preg_quote($mu2, '/') . '<\/div>.*?name="id" value="([0-9a-f-]{36})"/s', $m->last['body'], $mm);
    $m->post('gm.active', ['id' => $mm[1] ?? '', 'active' => '0']);
    $m2->get('users');
    ok($m2->last['status'] === 303, 'GM ปิดบัญชี Manager คนอื่นได้ → Manager นั้นหลุดทันที');
}

section('ล้างข้อมูลที่ test สร้าง (Manager ลบ) — รันซ้ำได้');
foreach ([['payments', 'HT' . $suffix], ['services', $sv], ['followups', $fu], ['sales', $so], ['branches', $cust . '-01'], ['devices', $dev], ['customers', $cust]] as [$p, $id]) {
    $m->post($p . '.delete', ['_pk' => $id]);
    ok(strpos((string) $m->last['location'], 'r=' . $p) !== false && strpos((string) $m->last['location'], '.edit') === false, "ลบ $p $id");
}
$m->get('dashboard', ['year' => '2026']);
ok(strpos($m->last['body'], '2,790,000.00') !== false, 'ข้อมูลกลับเป็นชุดตัวอย่างเดิม');

echo "\n" . str_repeat('─', 50) . "\nผ่าน $pass · ไม่ผ่าน $fail\n";
exit($fail > 0 ? 1 : 0);
