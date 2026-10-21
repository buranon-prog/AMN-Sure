<?php
// Automated tests ระดับ service: เกณฑ์ตรวจรับสเปกหัวข้อ 12 ข้อ 1–8 + สิทธิ์ (ข้อ 9 ส่วนที่ทดสอบได้โดยไม่ผ่านเว็บ)
// วิธีรัน:  php tests/run.php   (ต้องมีฐานข้อมูลทดสอบว่าง ๆ — ดู tests/config.test.sample.php)
// ตารางทั้งหมดในฐานข้อมูลทดสอบจะถูกลบและสร้างใหม่ทุกครั้ง · ใช้ข้อมูลตัวอย่างหัวข้อ 11 · "วันนี้" = 21/10/2026

define('TESTING', true);
define('STORAGE_DIR', sys_get_temp_dir() . '/sure-test-storage');
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../tools/demo_data.php';
require __DIR__ . '/../app/controllers/dashboard.php';
require __DIR__ . '/../app/controllers/profit.php';
require __DIR__ . '/mailbox.php';
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    if ($severity & (E_DEPRECATED | E_USER_DEPRECATED)) return true;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$cfgFile = __DIR__ . '/config.test.php';
if (!is_file($cfgFile)) {
    fwrite(STDERR, "สร้างไฟล์ tests/config.test.php ก่อน (คัดลอกจาก tests/config.test.sample.php)\n");
    exit(2);
}
$GLOBALS['APP_CONFIG'] = require $cfgFile;
// อีเมลในการทดสอบเขียนเป็นไฟล์ (ไม่ส่งออกจริง)
$MAILDIR = sys_get_temp_dir() . '/sure-test-mail-' . getmypid();
@mkdir($MAILDIR, 0777, true);
$GLOBALS['APP_CONFIG']['mail'] = ['transport' => 'file', 'dir' => $MAILDIR, 'from' => 'noreply@example.test', 'from_name' => 'AMN SURE'];
$GLOBALS['APP_CONFIG']['base_url'] = 'https://sure.example.test/';
$GLOBALS['APP_CONFIG']['app_key'] = 'test-app-key';
$_SESSION = [];
$pdo = db_connect(config('db'));
db_set($pdo);
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $tbl) $pdo->exec('DROP TABLE `' . $tbl . '`');
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
install_schema($pdo);
seed_base_data();
$GLOBALS['SURE_TODAY'] = '2026-10-21';

// ---------------------------------------------------------------- mini test framework
$GLOBALS['__TESTS'] = ['pass' => 0, 'fail' => 0];

function ok($cond, string $msg): void
{
    if ($cond) { $GLOBALS['__TESTS']['pass']++; return; }
    $GLOBALS['__TESTS']['fail']++;
    echo "  ✗ $msg\n";
}

function eq($actual, $expected, string $msg): void
{
    if (is_float($actual) || is_float($expected)) $same = $actual !== null && $expected !== null && abs((float) $actual - (float) $expected) < 0.005;
    else $same = $actual === $expected;
    ok($same, $msg . ' (ได้ ' . var_export($actual, true) . ' ต้องการ ' . var_export($expected, true) . ')');
}

/** ต้องโยน exception ชนิดนี้ (และข้อความมีคำนี้ ถ้าระบุ) */
function throws(callable $fn, string $class, string $msg, ?string $contains = null): void
{
    try {
        $fn();
        ok(false, $msg . ' — ควรถูกปฏิเสธแต่ผ่าน');
    } catch (Throwable $e) {
        $good = $e instanceof $class && ($contains === null || mb_strpos($e->getMessage(), $contains) !== false);
        ok($good, $msg . ($good ? '' : ' — ได้ ' . get_class($e) . ': ' . $e->getMessage()));
        if (db()->inTransaction()) db()->rollBack();
        $GLOBALS['__TX_DEPTH'] = 0;
    }
}

function section(string $name): void
{
    echo "\n▶ $name\n";
}

function as_user(?string $id): void
{
    act_as($id);
    ds_reset();
}

// ---------------------------------------------------------------- users + demo data
$manager = create_user_raw('manager', 'manager@example.test', 'Manager', 'Passw0rd123', ROLE_MANAGER, true, true, false);
$staff = create_user_raw('staff', null, 'พนักงาน แก้ไขได้', 'Passw0rd123', ROLE_EMPLOYEE, true, true, false);
$viewer = create_user_raw('viewer', null, 'พนักงาน ดูอย่างเดียว', 'Passw0rd123', ROLE_EMPLOYEE, true, false, false);
$noperm = create_user_raw('noperm', null, 'พนักงาน ไม่มีสิทธิ์', 'Passw0rd123', ROLE_EMPLOYEE, false, false, false);
demo_seed($manager);
as_user($manager);

$base = [
    'C' => ['since' => '2026-10-21', 'name' => 'ลูกค้าทดสอบ', 'ctype' => 'คลินิกความงาม', 'contact' => 'คุณทดสอบ', 'prov' => 'ขอนแก่น', 'phone' => '080-000-0000'],
    'B' => ['cust' => 'SURE001', 'bname' => 'สาขาทดสอบ', 'since' => '2026-10-21', 'addr' => '1 ถ.ทดสอบ', 'prov' => 'กรุงเทพมหานคร'],
    'A' => ['date' => '2026-10-21', 'cust' => 'SURE001', 'topic' => 'สนใจซื้อเครื่อง', 'result' => SURE_FU_OPEN, 'by' => 'Sales Executive 1'],
    'D' => ['regdate' => '2026-10-21', 'cat' => 'Laser', 'brand' => 'Brand T', 'model' => 'T-1', 'serial' => 'TEST-SERIAL-1', 'acq' => 'ซื้อขาด'],
    'S' => ['date' => '2026-10-21', 'status' => 'ทำสัญญาแล้ว', 'dev' => 'SURE26007', 'cust' => 'SURE001', 'branch' => 'SURE001-01', 'channel' => 'SURE ขายตรง', 'sales' => 'Sales Executive 1', 'price' => '1,000,000'],
    'P' => ['date' => '2026-10-21', 'so' => 'SO26005', 'type' => 'ชำระงวด', 'method' => 'โอนเงิน', 'amount' => '1000', 'by' => 'Finance & Accounting 1'],
    'V' => ['date' => '2026-10-21', 'dev' => 'SURE26001', 'type' => 'ซ่อม', 'eng' => 'Service Engineer 1', 'jstatus' => 'รับเรื่อง'],
];

// ---------------------------------------------------------------- ข้อ 1 หน้าสรุป
section('ข้อ 1 — หน้าสรุป (ปีที่สรุป 2026)');
$dash = dashboard_data(ds(true), 2026, sure_today());
$k = $dash['k'];
eq($k['sold'], 5, 'ขายได้ 5 เครื่อง');
eq($k['sales'], 2790000.0, 'ยอดขาย ก่อน VAT 2,790,000');
eq($k['gp'], 870000.0, 'กำไรขั้นต้น 870,000');
eq(pct($k['gpp']), '31.2%', 'GP 31.2%');
eq($k['outstanding'], 1062200.0, 'ยอดค้างชำระรวม 1,062,200');
eq($k['stock_value'], 1445000.0, 'มูลค่าสต็อกตามต้นทุน 1,445,000');
eq($k['customers'], 8, 'ลูกค้าทั้งหมด 8');
eq($k['devices'], 8, 'เครื่องในระบบ 8');
eq($k['ready'], 1, 'เครื่องพร้อมขาย 1');
eq($k['waiting'], 1, 'ขายแล้ว – รอส่งมอบ 1');
eq($k['new_customers'], 8, 'ลูกค้าใหม่ปี 2026 = 8');
eq($k['received_month'], 500000.0, 'รับเงินเดือนนี้ (ต.ค. 2026) 500,000');
eq($k['svc_open'], 3, 'งานบริการค้าง 3 (SV26005, SV26006, SV26008)');
eq($k['fu_due'], 3, 'นัดติดตาม เลยกำหนด / วันนี้ = 3');
eq($k['expiring'], 1, 'ประกัน / MA ใกล้หมด ≤30 วัน = 1 (SURE26004)');
eq($k['leads'], 3, 'ลูกค้ามุ่งหวัง (Lead) 3 (SURE004, SURE006, SURE007)');
eq(array_sum($dash['byStatus']), 8, 'เครื่องตามสถานะ รวม 8');
eq($dash['byStatus']['ส่งมอบแล้ว'], 4, 'ส่งมอบแล้ว 4');
eq(array_sum($dash['byRegion']), 8, 'ลูกค้าตามภาค รวม 8');
eq($dash['byRegion']['กรุงเทพฯ และปริมณฑล'], 3, 'กรุงเทพฯ และปริมณฑล 3');
eq($dash['monthly'][9]['count'], 3, 'ก.ย. ขาย 3 เครื่อง');
eq($dash['monthly'][10]['received'], 500000.0, 'ต.ค. รับเงิน 500,000');
eq($dash['bySales']['Sales Executive 1']['sales'], 1690000.0, 'Sales Executive 1 ยอดขาย 1,690,000');
$dash2025 = dashboard_data(ds(true), 2025, sure_today());
eq($dash2025['k']['sold'], 0, 'ปี 2025 ขายได้ 0');

// ---------------------------------------------------------------- ข้อ 2 กำไรรายการขาย
section('ข้อ 2 — กำไรรายการขาย');
$pt = profit_totals(array_values(ds(true)['S']));
eq($pt['sales'], 2790000.0, 'ยอดขายรวม 2,790,000');
eq($pt['cost'], 1920000.0, 'ต้นทุนรวม 1,920,000');
eq($pt['gp'], 870000.0, 'กำไรรวม 870,000');
eq($pt['count'], 5, 'ไม่นับ SO26004 (ยกเลิก)');
eq(ds(true)['S']['SO26004']['gp'], null, 'SO26004 ไม่คิดกำไร');

// ---------------------------------------------------------------- ข้อ 3 การขาย
section('ข้อ 3 — การขาย');
$S = ds(true)['S'];
eq($S['SO26001']['paid'], 770400.0, 'SO26001 รับชำระแล้ว 770,400');
eq($S['SO26001']['paystatus'], 'ชำระครบ', 'SO26001 ชำระครบ');
eq($S['SO26001']['vat'], 50400.0, 'SO26001 VAT 50,400');
eq($S['SO26001']['total'], 770400.0, 'SO26001 ยอดรวม VAT 770,400');
eq($S['SO26001']['deposit'], 100000.0, 'SO26001 เงินมัดจำ 100,000');
eq($S['SO26001']['lastpay'], '2026-09-15', 'SO26001 รับเงินล่าสุด 15/09/2026');
eq($S['SO26005']['outstanding'], 816500.0, 'SO26005 ยอดคงค้าง 816,500');
eq($S['SO26005']['paystatus'], 'ชำระบางส่วน', 'SO26005 ชำระบางส่วน');
eq($S['SO26006']['outstanding'], 245700.0, 'SO26006 ยอดคงค้าง 245,700');
eq($S['SO26004']['outstanding'], 0.0, 'SO26004 ยอดคงค้าง 0');
eq($S['SO26004']['paystatus'], '–', 'SO26004 สถานะชำระ "–"');
eq($S['SO26004']['wstatus'], '–', 'SO26004 สถานะประกัน "–"');
eq($S['SO26005']['wstatus'], 'รอส่งมอบ', 'SO26005 รอส่งมอบ');
eq($S['SO26003']['wstatus'], 'ใกล้หมด (≤30 วัน)', 'SO26003 ประกันใกล้หมด (หมด 04/11/2026)');
eq($S['SO26002']['wstatus'], 'หมดประกัน', 'SO26002 หมดประกัน');
eq($S['SO26001']['wstatus'], 'ในประกัน', 'SO26001 ในประกัน');
eq($S['SO26005']['prov'], 'เชียงใหม่', 'จังหวัดที่ส่ง = จังหวัดสาขา');
eq($S['SO26005']['site'], '123 ถ.ห้วยแก้ว อาคาร B ชั้น 3 สุเทพ เมืองเชียงใหม่ เชียงใหม่ 50200', 'สถานที่ส่ง = ที่อยู่เต็มของสาขา');
eq($S['SO26001']['devname'], 'Brand A HF-300', 'ยี่ห้อ / รุ่น');
eq(ds(true)['P']['RC26-0008']['balance'], 816500.0, 'รับชำระเงิน: ยอดคงค้างของการขาย SO26005');
eq(ds(true)['P']['RC26-0007']['cust'], 'SURE004', 'รับชำระเงิน: รหัสลูกค้าจากการขาย');

// ---------------------------------------------------------------- ข้อ 4 เครื่อง
section('ข้อ 4 — เครื่อง');
$D = ds(true)['D'];
eq($D['SURE26001']['status'], 'ส่งมอบแล้ว', 'SURE26001 ส่งมอบแล้ว');
eq($D['SURE26001']['buyername'], 'คลินิกผิวสวย สุขุมวิท', 'SURE26001 ผู้ซื้อ คลินิกผิวสวย สุขุมวิท');
eq($D['SURE26001']['branch'], 'SURE001-01', 'SURE26001 สาขาที่ติดตั้ง SURE001-01');
eq(d($D['SURE26001']['wend']), '14/03/2027', 'SURE26001 วันหมดประกัน 14/03/2027');
eq($D['SURE26002']['status'], 'ขายแล้ว – รอส่งมอบ', 'SURE26002 ขายแล้ว – รอส่งมอบ');
eq($D['SURE26006']['soid'], 'SO26006', 'SURE26006 การขายล่าสุด = SO26006 (ไม่ใช่ SO26004)');
eq($D['SURE26005']['cover'], 'อยู่ใน MA', 'SURE26005 อยู่ใน MA');
eq(d($D['SURE26005']['maend']), '30/09/2027', 'SURE26005 MA ถึง 30/09/2027');
eq($D['SURE26003']['status'], 'กำลังซ่อม / Refurbish', 'เครื่องไม่มีการขาย → ใช้สถานะสต็อก');
eq($D['SURE26003']['cover'], '–', 'ยังไม่ส่งมอบ → ความคุ้มครอง "–"');
eq($D['SURE26004']['cover'], 'ประกันใกล้หมด', 'SURE26004 ประกันใกล้หมด');
eq($D['SURE26001']['nsvc'], 2, 'SURE26001 งานบริการ 2 ครั้ง');
eq($D['SURE26001']['lastsvc'], '2026-10-10', 'SURE26001 บริการล่าสุด 10/10/2026');
eq($D['SURE26001']['sellername'], 'ลลิน เลเซอร์คลินิก ชลบุรี', 'ชื่อผู้ขาย / เจ้าของเดิม');
eq($D['SURE26001']['gp'], 240000.0, 'SURE26001 กำไรขั้นต้น (Manager)');

// ---------------------------------------------------------------- ข้อ 5 ค้นหาลูกค้า
section('ข้อ 5 — ค้นหาลูกค้า SURE001');
$g = customer_summary('SURE001');
eq($g['rel'], 'ซื้อและขายเครื่อง', 'ซื้อและขายเครื่อง');
eq($g['nbranch'], 2, '2 สาขา');
eq(next_code('B', 'SURE001'), 'SURE001-03', 'รหัสสาขาถัดไป SURE001-03');
eq($g['nbuy'], 2, 'เครื่องที่ซื้อ 2');
eq($g['amt'], 1180000.0, 'ยอดซื้อรวม 1,180,000');
eq($g['due'], 0.0, 'ยอดค้างชำระ 0');
eq($g['nsell'], 1, 'เครื่องที่ขายให้ SURE 1');
eq($g['lastct'], '2026-10-12', 'ติดต่อล่าสุด 12/10/2026');
eq($g['nextfu'], '2026-10-20', 'นัดติดตามถัดไป 20/10/2026');
eq(customer_summary('SURE004')['rel'], 'ผู้สนใจ / Lead', 'SURE004 (ยกเลิกการซื้อ) = ผู้สนใจ / Lead');
eq(customer_summary('SURE003')['rel'], 'ผู้ขายเครื่องให้ SURE', 'SURE003 = ผู้ขายเครื่องให้ SURE');
eq(customer_summary('SURE002')['rel'], 'ผู้ซื้อ', 'SURE002 = ผู้ซื้อ');

// ---------------------------------------------------------------- ข้อ 6 บริการ
section('ข้อ 6 — บริการ');
$V = ds(true)['V'];
eq($V['SV26005']['cover'], 'เครื่องในสต็อก SURE', 'SV26005 เครื่องในสต็อก SURE');
eq($V['SV26004']['cover'], 'อยู่ใน MA', 'SV26004 อยู่ใน MA');
eq($V['SV26001']['cover'], 'ในประกัน', 'SV26001 ในประกัน (แจ้งก่อนหมดประกัน)');
eq($V['SV26006']['owner'], 'SURE001', 'SV26006 เจ้าของเครื่อง = ผู้ซื้อการขายล่าสุด');
eq($V['SV26006']['branchname'], 'สาขาสุขุมวิท 39 (สำนักงานใหญ่)', 'SV26006 สาขาที่ตั้งเครื่อง');
eq($V['SV26004']['days'], 0, 'SV26004 ใช้เวลาปิดงาน 0 วัน');
eq($V['SV26005']['days'], null, 'งานที่ยังไม่ปิด → ไม่มีจำนวนวัน');

// ---------------------------------------------------------------- ข้อ 7 ต้นทุนเครื่อง
section('ข้อ 7 — ต้นทุนเครื่อง');
$T = ds(true)['T'];
eq($T['SURE26001']['totalcost'], 480000.0, 'SURE26001 ต้นทุนรวม 480,000');
eq(pct($T['SURE26001']['listgp']), '36.0%', 'SURE26001 GP % ตามราคาตั้งขาย 36.0%');
eq($T['SURE26001']['gp'], 240000.0, 'SURE26001 กำไร 240,000');
eq(pct($T['SURE26001']['gpp']), '33.3%', 'SURE26001 GP % 33.3%');
eq($T['SURE26001']['seller'], 'ลลิน เลเซอร์คลินิก ชลบุรี', 'รับเครื่องจาก (ชื่อ)');
eq(count(array_diff_key(ds(true)['D'], ds(true)['T'])), 0, 'ยังไม่ใส่ต้นทุน 0 เครื่อง');
rec_delete('T', 'SURE26008');
eq(count(array_diff_key(ds(true)['D'], ds(true)['T'])), 1, 'ลบแถว SURE26008 → ยังไม่ใส่ต้นทุน 1');
eq(dashboard_data(ds(true), 2026, sure_today())['k']['stock_value'], 1025000.0, 'มูลค่าสต็อกเหลือ 1,025,000');
rec_save('T', null, ['dev' => 'SURE26008', 'cost' => '420000', 'refurb' => '0', 'other' => '0', 'note' => 'ราคารับซื้อเบื้องต้น รอผลตรวจ']);
eq(dashboard_data(ds(true), 2026, sure_today())['k']['stock_value'], 1445000.0, 'ใส่ต้นทุนกลับ → 1,445,000');

// ---------------------------------------------------------------- ข้อ 8 การปฏิเสธ
section('ข้อ 8 — การปฏิเสธ');
throws(function () use ($base) { rec_save('S', null, ['id' => next_code('S'), 'cust' => 'SURE001', 'branch' => 'SURE002-01'] + $base['S']); },
    AppError::class, 'การขายใช้สาขา SURE002-01 กับลูกค้า SURE001 → ถูกปฏิเสธ', '⚠ สาขานี้ไม่ใช่ของลูกค้านี้');
throws(function () use ($base) { rec_save('C', null, ['id' => 'SURE01'] + $base['C']); }, AppError::class, 'รหัสลูกค้า "SURE01" → ถูกปฏิเสธ', 'SURE + เลข 3 หลัก');
foreach (['SURE0001', 'SUR001', 'SURE00A', 'XURE001'] as $bad) {
    throws(function () use ($base, $bad) { rec_save('C', null, ['id' => $bad] + $base['C']); }, AppError::class, "รหัสลูกค้า \"$bad\" → ถูกปฏิเสธ");
}
throws(function () use ($base) { rec_save('C', null, ['id' => 'SURE001'] + $base['C']); }, AppError::class, 'รหัสลูกค้าซ้ำ → ถูกปฏิเสธ', 'ห้ามซ้ำ');
throws(function () use ($base) { rec_save('B', null, ['id' => 'SURE001-01'] + $base['B']); }, AppError::class, 'รหัสสาขาซ้ำ → ถูกปฏิเสธ', 'ห้ามซ้ำ');
throws(function () use ($base) { rec_save('D', null, ['id' => 'SURE26001', 'serial' => 'NEW-SN'] + $base['D']); }, AppError::class, 'รหัสเครื่องซ้ำ → ถูกปฏิเสธ', 'ห้ามซ้ำ');
throws(function () use ($base) { rec_save('S', null, ['id' => 'SO26001', 'dev' => 'SURE26007'] + $base['S']); }, AppError::class, 'เลขที่การขายซ้ำ → ถูกปฏิเสธ', 'ห้ามซ้ำ');
throws(function () use ($base) { rec_save('V', null, ['id' => 'SV26001'] + $base['V']); }, AppError::class, 'เลขที่งานบริการซ้ำ → ถูกปฏิเสธ', 'ห้ามซ้ำ');
throws(function () use ($base) { rec_save('P', null, ['receipt' => 'RC26-0001'] + $base['P']); }, AppError::class, 'เลขที่ใบเสร็จซ้ำ → ถูกปฏิเสธ', 'ห้ามซ้ำ');
throws(function () { rec_save('T', null, ['dev' => 'SURE26001', 'cost' => '1']); }, AppError::class, 'ต้นทุนเครื่องซ้ำ → ถูกปฏิเสธ', '1 เครื่อง = 1 แถว');
throws(function () use ($base) { rec_save('D', null, ['id' => next_code('D'), 'serial' => 'HF300-21A045'] + $base['D']); }, AppError::class, 'Serial ซ้ำ → ถูกปฏิเสธ', 'Serial Number นี้มีอยู่แล้ว');
throws(function () use ($base) { rec_save('D', null, ['id' => next_code('D'), 'serial' => 'hf300-21a045'] + $base['D']); }, AppError::class, 'Serial ซ้ำ (ตัวพิมพ์เล็ก) → ถูกปฏิเสธ', 'Serial Number นี้มีอยู่แล้ว');
throws(function () use ($base) { rec_save('S', null, ['id' => next_code('S'), 'dev' => 'SURE26001'] + $base['S']); }, AppError::class,
    'ขายเครื่องที่มีการขายไม่ยกเลิกอยู่แล้ว → ถูกปฏิเสธ', 'เครื่องนี้มีการขายที่ยังไม่ยกเลิกอยู่แล้ว');
throws(function () use ($base) { rec_save('S', null, ['id' => next_code('S'), 'dev' => 'SURE26006'] + $base['S']); }, AppError::class,
    'SURE26006 (SO26004 ยกเลิก แต่ SO26006 ยังอยู่) → ถูกปฏิเสธ', 'เครื่องนี้มีการขายที่ยังไม่ยกเลิกอยู่แล้ว');
throws(function () { rec_save('S', 'SO26004', ['status' => 'ทำสัญญาแล้ว'] + rec_find('S', 'SO26004')); }, AppError::class,
    'เปลี่ยน SO26004 กลับเป็นไม่ยกเลิก ขณะที่ SO26006 ยังอยู่ → ถูกปฏิเสธ', 'เครื่องนี้มีการขายที่ยังไม่ยกเลิกอยู่แล้ว');
foreach (['1999-12-31', '2100-01-01', '2026-02-30', '21/10/2026', 'abc'] as $bad) {
    throws(function () use ($base, $bad) { rec_save('C', null, ['id' => next_code('C'), 'since' => $bad] + $base['C']); }, AppError::class, "วันที่ \"$bad\" → ถูกปฏิเสธ");
}
throws(function () use ($base) { rec_save('S', 'SO26005', ['deliv' => '2100-01-01'] + rec_find('S', 'SO26005')); }, AppError::class, 'วันส่งมอบ 2100 → ถูกปฏิเสธ', '01/01/2000 ถึง 31/12/2099');
throws(function () use ($base) { rec_save('B', null, ['id' => 'SURE002-05'] + $base['B']); }, AppError::class, 'รหัสสาขา 7 ตัวแรกไม่ตรงลูกค้า → ถูกปฏิเสธ', '⚠ รหัสสาขาไม่ตรงกับรหัสลูกค้า');
throws(function () use ($base) { rec_save('A', null, ['branch' => 'SURE002-01'] + $base['A']); }, AppError::class, 'ติดตามลูกค้า: สาขาไม่ใช่ของลูกค้า → ถูกปฏิเสธ', '⚠ สาขานี้ไม่ใช่ของลูกค้านี้');
throws(function () use ($base) { rec_save('S', null, ['id' => next_code('S'), 'price' => '-1'] + $base['S']); }, AppError::class, 'ราคาติดลบ → ถูกปฏิเสธ', '≥ 0');
throws(function () use ($base) { rec_save('A', null, ['budget' => '-5'] + $base['A']); }, AppError::class, 'งบประมาณติดลบ → ถูกปฏิเสธ', '≥ 0');
throws(function () use ($base) { rec_save('S', null, ['id' => next_code('S'), 'wmonths' => '-1'] + $base['S']); }, AppError::class, 'ระยะประกันติดลบ → ถูกปฏิเสธ');
throws(function () use ($base) { rec_save('C', null, ['id' => next_code('C'), 'taxid' => '010556600010'] + $base['C']); }, AppError::class, 'เลขผู้เสียภาษี 12 หลัก → ถูกปฏิเสธ', '13 ตัว');
throws(function () use ($base) { rec_save('C', null, ['id' => next_code('C'), 'ctype' => 'ประเภทมั่ว'] + $base['C']); }, AppError::class, 'ค่าไม่อยู่ในรายการตัวเลือก → ถูกปฏิเสธ', 'ไม่อยู่ในรายการตัวเลือก');
throws(function () use ($base) { rec_save('C', null, ['id' => next_code('C'), 'prov' => 'ไม่มีจังหวัดนี้'] + $base['C']); }, AppError::class, 'จังหวัดนอก 77 จังหวัด → ถูกปฏิเสธ', 'ไม่อยู่ในรายการตัวเลือก');
throws(function () use ($base) { rec_save('S', null, ['id' => next_code('S'), 'dev' => 'SURE99999'] + $base['S']); }, AppError::class, 'FK รหัสเครื่องที่ไม่มีอยู่ → ถูกปฏิเสธ', 'ไม่พบ');
throws(function () use ($base) { $in = $base['C']; unset($in['name'], $in['phone']); rec_save('C', null, ['id' => next_code('C')] + $in); }, AppError::class, 'ไม่กรอกช่องบังคับ → ถูกปฏิเสธ', 'กรุณากรอก');
throws(function () { rec_save('T', null, ['dev' => 'SURE99999', 'cost' => '1']); }, AppError::class, 'ต้นทุน: รหัสเครื่องที่ไม่มี → ถูกปฏิเสธ', 'ไม่พบรหัสเครื่อง');

section('รับได้ (ค่าขอบ)');
$c = rec_save('C', null, ['id' => next_code('C'), 'since' => '2000-01-01', 'taxid' => '0105566000999'] + $base['C']);
eq($c, 'SURE009', 'บันทึกลูกค้าใหม่ได้ (รหัสถัดไป SURE009, วันที่ 01/01/2000)');
eq(rec_save('C', null, ['id' => 'sure010', 'since' => '2099-12-31'] + $base['C']), 'SURE010', 'พิมพ์รหัสตัวเล็ก → เก็บเป็นตัวใหญ่, วันที่ 31/12/2099 ได้');
eq(next_code('B', 'SURE009'), 'SURE009-01', 'สาขาแรกของลูกค้าใหม่ = -01');
eq(rec_save('B', null, ['id' => 'SURE009-01', 'cust' => 'SURE009'] + $base['B']), 'SURE009-01', 'เพิ่มสาขาแรก');
$pid = rec_save('P', null, ['receipt' => 'RF26-0001', 'type' => 'คืนเงิน (ใส่ยอดติดลบ)', 'amount' => '-1,000.50'] + $base['P']);
eq(ds(true)['P'][$pid]['amount'], -1000.5, 'รับชำระเงินยอดติดลบได้ (คืนเงิน)');
rec_delete('P', $pid);
$aid = rec_save('A', null, $base['A']);
ok(ctype_digit($aid), 'ติดตามลูกค้า: id อัตโนมัติ');
$ns = rec_save('S', null, ['id' => next_code('S')] + $base['S']);
eq($ns, 'SO26007', 'ขาย SURE26007 ได้ (เลขที่ SO26007)');
eq(ds(true)['D']['SURE26007']['status'], 'ขายแล้ว – รอส่งมอบ', 'บันทึกการขายแล้ว เครื่องเปลี่ยนสถานะเอง');
rec_save('S', $ns, ['status' => SURE_CANCELLED] + rec_find('S', $ns));
eq(ds(true)['D']['SURE26007']['status'], 'พร้อมขาย', 'ยกเลิกการขาย → เครื่องกลับไปใช้สถานะสต็อก');
$ns2 = rec_save('S', null, ['id' => next_code('S')] + $base['S']);
eq($ns2, 'SO26008', 'ขายใหม่ได้หลังยกเลิก');
eq(ds(true)['D']['SURE26007']['soid'], 'SO26008', 'การขายล่าสุด = รายการที่บันทึกล่าสุดที่ไม่ยกเลิก');
rec_save('S', $ns2, ['id' => 'SO99999', 'price' => '900000'] + rec_find('S', $ns2));
ok(rec_find('S', $ns2) !== null && rec_find('S', 'SO99999') === null, 'แก้ไขแล้วรหัสหลักไม่เปลี่ยน (ห้ามแก้หลังบันทึก)');
eq(rec_find('S', $ns2)['price'], 900000.0, 'แก้ราคาได้');
$keep = rec_find('C', 'SURE001');
rec_save('C', 'SURE001', ['region' => 'ภาคใต้', 'rel' => 'แก้เอง'] + $keep);
eq(ds(true)['C']['SURE001']['region'], 'กรุงเทพฯ และปริมณฑล', 'ฟิลด์อัตโนมัติไม่รับค่าจากผู้ใช้');

section('รหัสถัดไป');
eq(next_code('C'), 'SURE011', 'ลูกค้า = max+1');
eq(next_code('D'), 'SURE26009', 'เครื่อง = max+1 ของปีปัจจุบัน');
eq(next_code('V'), 'SV26009', 'บริการ = max+1 ของปีปัจจุบัน');
$GLOBALS['SURE_TODAY'] = '2027-01-05';
eq(next_code('D'), 'SURE27001', 'ขึ้นปีใหม่ → เครื่องเริ่ม 001');
eq(next_code('S'), 'SO27001', 'ขึ้นปีใหม่ → SO เริ่ม 001');
eq(next_code('C'), 'SURE011', 'ลูกค้าไม่เริ่มใหม่ทุกปี');
$GLOBALS['SURE_TODAY'] = '2026-10-21';

section('EDATE / ประกัน');
eq(edate('2026-01-31', 1), '2026-02-28', 'EDATE 31/01 + 1 เดือน = 28/02');
eq(edate('2024-02-29', 12), '2025-02-28', 'EDATE 29/02/2024 + 12 เดือน = 28/02/2025');
eq(edate('2026-09-15', 6), '2027-03-15', 'EDATE 15/09/2026 + 6');
eq(edate('2026-11-30', 3), '2027-02-28', 'EDATE ข้ามปี');
eq(followup_due(['result' => SURE_FU_OPEN, 'nextdate' => '2026-10-21'], '2026-10-21'), 'วันนี้', 'ครบกำหนดติดตาม: วันนี้');
eq(followup_due(['result' => SURE_FU_OPEN, 'nextdate' => '2026-10-20'], '2026-10-21'), 'เลยกำหนด', 'ครบกำหนดติดตาม: เลยกำหนด');
eq(followup_due(['result' => SURE_FU_OPEN, 'nextdate' => '2026-10-28'], '2026-10-21'), 'ภายใน 7 วัน', 'ครบกำหนดติดตาม: ภายใน 7 วัน');
eq(followup_due(['result' => SURE_FU_OPEN, 'nextdate' => '2026-10-29'], '2026-10-21'), null, 'ครบกำหนดติดตาม: เกิน 7 วัน → ว่าง');
eq(followup_due(['result' => 'ปิด – สำเร็จ', 'nextdate' => '2026-10-20'], '2026-10-21'), null, 'ปิดแล้ว → ว่าง');
eq(coverage_status('2026-10-21', '2026-11-20', null), 'ประกันใกล้หมด', 'ประกันเหลือ 30 วัน = ใกล้หมด');
eq(coverage_status('2026-10-21', '2026-11-21', null), 'ในประกัน', 'ประกันเหลือ 31 วัน = ในประกัน');
eq(coverage_status('2026-10-21', '2026-10-01', '2026-11-01'), 'MA ใกล้หมด', 'MA ใกล้หมด');
eq(coverage_status('2026-10-21', null, null), 'หมดประกัน / ไม่มี MA', 'หมดประกัน / ไม่มี MA');

section('VAT จากค่าตั้งต้น');
vat_set('10');
eq(ds(true)['S']['SO26001']['vat'], 72000.0, 'เปลี่ยน VAT เป็น 10% → คำนวณใหม่');
vat_set('7');
eq(ds(true)['S']['SO26001']['vat'], 50400.0, 'กลับเป็น 7%');
throws(function () { vat_set('abc'); }, AppError::class, 'VAT ไม่ใช่ตัวเลข → ถูกปฏิเสธ');
throws(function () { vat_set('101'); }, AppError::class, 'VAT เกิน 100 → ถูกปฏิเสธ');

section('รายการตัวเลือก — ค่าระบบลบ/เปลี่ยนชื่อไม่ได้');
$locked = 0;
foreach (option_locked() as $list => $vals) {
    foreach ($vals as $v) {
        $id = (int) val('SELECT id FROM sure_options WHERE list_key = ? AND value = ?', [$list, $v]);
        ok($id > 0 && (int) val('SELECT is_system FROM sure_options WHERE id = ?', [$id]) === 1, "ค่าระบบ $list: $v");
        throws(function () use ($id) { option_delete($id); }, AppError::class, "ลบ \"$v\" ไม่ได้", 'ลบไม่ได้');
        throws(function () use ($id) { option_update($id, 'ชื่อใหม่', 10); }, AppError::class, "เปลี่ยนชื่อ \"$v\" ไม่ได้", 'เปลี่ยนชื่อไม่ได้');
        $locked++;
    }
}
eq($locked, 10, 'ค่าระบบครบ 9 คำ (ยกเลิก อยู่ 2 รายการตัวเลือก รวม 10 แถว)');
eq((int) val('SELECT COUNT(*) FROM sure_options WHERE is_system = 1'), 10, 'ไม่มีค่าระบบอื่นนอกจากที่สเปกกำหนด');
$sid = (int) val("SELECT id FROM sure_options WHERE list_key = 'staff' AND value = 'Sales Executive 1'");
option_update($sid, 'สมชาย ขายเก่ง', 20);
ok(in_array('สมชาย ขายเก่ง', option_values('staff'), true), 'แก้ชื่อพนักงานเป็นชื่อจริงได้');
ok(rec_save('C', 'SURE001', rec_find('C', 'SURE001')) === 'SURE001', 'แถวเดิมที่ใช้ค่าเก่า ("Sales Executive 1") ยังบันทึกต่อได้');
throws(function () use ($base) { rec_save('C', null, ['id' => next_code('C'), 'sales' => 'Sales Executive 1'] + $base['C']); }, AppError::class, 'แถวใหม่ใช้ค่าที่ไม่อยู่ในรายการแล้วไม่ได้');
option_update($sid, 'Sales Executive 1', 20);
option_add('ctype', 'คลินิกทันตกรรม', null);
ok(in_array('คลินิกทันตกรรม', option_values('ctype'), true), 'เพิ่มตัวเลือกใหม่ได้');
throws(function () { option_add('ctype', 'คลินิกทันตกรรม', null); }, AppError::class, 'เพิ่มค่าซ้ำไม่ได้', 'มีอยู่ในรายการแล้ว');
option_delete((int) val("SELECT id FROM sure_options WHERE value = 'คลินิกทันตกรรม'"));
eq(count(provinces()), 77, 'จังหวัด 77 จังหวัด');
eq(count(array_unique(array_values(provinces()))), 7, '7 ภาค');

section('ลบแถว (Manager เท่านั้น)');
throws(function () { rec_delete('C', 'SURE001'); }, AppError::class, 'ลบลูกค้าที่มีข้อมูลอ้างถึง → ข้อความที่อ่านเข้าใจ', 'ลบไม่ได้ เพราะมีข้อมูลในตารางอื่นอ้างถึง');
ok(rec_find('C', 'SURE001') !== null, 'ลูกค้ายังอยู่');
rec_delete('C', 'SURE010');
ok(rec_find('C', 'SURE010') === null, 'Manager ลบลูกค้าที่ไม่มีข้อมูลอ้างถึงได้');

// ---------------------------------------------------------------- ข้อ 9 สิทธิ์ (ระดับ service)
section('ข้อ 9 — สิทธิ์ EMPLOYEE (ระดับ service)');
as_user($staff);
ok(!is_manager() && can_view_sure() && can_edit_sure(), 'staff = EMPLOYEE ดู+แก้');
$E = ds();
eq(count($E['T']), 0, 'EMPLOYEE: ไม่ได้แถวต้นทุนเครื่องเลย');
$mgrKeys = ['C' => ['rel', 'nbranch', 'nbuy', 'amt', 'last', 'due', 'nsell', 'lastct', 'nextfu'], 'D' => ['totalcost', 'gp', 'gpp'], 'S' => ['cost', 'gp', 'gpp']];
foreach ($mgrKeys as $t => $keys) {
    $leak = [];
    foreach ($E[$t] as $row) {
        foreach ($keys as $k2) if (array_key_exists($k2, $row)) $leak[$k2] = true;
    }
    ok(!$leak, "EMPLOYEE: ตาราง $t ไม่มีฟิลด์ Manager (" . implode(',', array_keys($leak)) . ')');
}
foreach (['C', 'D', 'S'] as $t) {
    $vis = array_keys(visible_fields($t));
    ok(!array_intersect($vis, $mgrKeys[$t]), "EMPLOYEE: visible_fields($t) ไม่มีฟิลด์ Manager");
}
// พิสูจน์ว่าไม่ query ตารางต้นทุนเลย: ซ่อนตารางไว้ชั่วคราว แล้วคำนวณชุดข้อมูลของพนักงาน
db()->exec('RENAME TABLE sure_costs TO sure_costs_hidden');
try {
    ds_reset();
    $E2 = ds();
    ok(count($E2['D']) === 8, 'EMPLOYEE: คำนวณข้อมูลได้ครบโดยไม่แตะตาราง sure_costs');
} catch (Throwable $e) {
    ok(false, 'EMPLOYEE: ds() แตะตาราง sure_costs — ' . $e->getMessage());
}
db()->exec('RENAME TABLE sure_costs_hidden TO sure_costs');
ds_reset();
throws(function () { require_table_view('T'); }, ForbiddenError::class, 'EMPLOYEE เปิดต้นทุนเครื่องไม่ได้');
throws(function () { rec_save('T', 'SURE26001', ['cost' => '1']); }, ForbiddenError::class, 'EMPLOYEE แก้ต้นทุนไม่ได้');
throws(function () { require_manager(); }, ForbiddenError::class, 'EMPLOYEE เปิดหน้า Manager (สรุป/กำไร) ไม่ได้');
foreach (['C' => 'SURE001', 'S' => 'SO26001', 'P' => 'RC26-0001', 'D' => 'SURE26008'] as $t => $pk) {
    throws(function () use ($t, $pk) { rec_delete($t, $pk); }, ForbiddenError::class, "EMPLOYEE ลบแถว $t ไม่ได้");
}
throws(function () { option_add('ctype', 'x', null); }, ForbiddenError::class, 'EMPLOYEE แก้รายการตัวเลือกไม่ได้');
throws(function () { vat_set('8'); }, ForbiddenError::class, 'EMPLOYEE แก้ VAT ไม่ได้');
throws(function () { user_save(null, ['email' => 'x1@example.test', 'name' => 'x', 'role' => ROLE_MANAGER]); }, ForbiddenError::class, 'EMPLOYEE สร้างบัญชีไม่ได้');
throws(function () { user_send_password_link('x'); }, ForbiddenError::class, 'EMPLOYEE ส่งลิงก์ตั้งรหัสผ่านไม่ได้');
$eid = rec_save('C', null, ['id' => next_code('C')] + $base['C']);
ok(rec_find('C', $eid) !== null, 'EMPLOYEE เพิ่มลูกค้าได้');
eq(ds()['C'][$eid]['_created_by'], $staff, 'audit: บันทึกโดย = พนักงานคนนี้');

as_user($viewer);
ok(can_view_sure() && !can_edit_sure(), 'viewer = ดูอย่างเดียว');
throws(function () use ($base) { rec_save('C', null, ['id' => next_code('C')] + $base['C']); }, ForbiddenError::class, 'ดูอย่างเดียว → เพิ่มข้อมูลไม่ได้');
throws(function () { rec_save('S', 'SO26005', rec_find('S', 'SO26005')); }, ForbiddenError::class, 'ดูอย่างเดียว → แก้ไขไม่ได้');

as_user($noperm);
ok(!can_view_sure(), 'noperm ไม่มีสิทธิ์ SURE');
throws(function () { require_sure_view(); }, ForbiddenError::class, 'ไม่มีสิทธิ์ SURE → เปิดหน้า SURE ไม่ได้');
throws(function () { require_table_view('C'); }, ForbiddenError::class, 'ไม่มีสิทธิ์ SURE → ดูตารางลูกค้าไม่ได้');

as_user($manager);
section('บัญชีผู้ใช้ + เปิดใช้งานทางอีเมล (สมัครเองไม่ได้)');
$t0 = microtime(true);
$r = user_save(null, ['email' => 'New.Staff@Example.test', 'name' => 'พนักงานใหม่', 'role' => ROLE_EMPLOYEE, 'active' => 1, 'can_view' => 1, 'can_edit' => 0]);
$nu = $r['id'];
ok($r['invite'], 'บัญชีใหม่ → ต้องส่งลิงก์เปิดใช้งาน');
$row = one('SELECT * FROM users WHERE id = ?', [$nu]);
eq($row['email'], 'new.staff@example.test', 'อีเมลเก็บเป็นตัวเล็ก');
eq($row['username'], 'new.staff', 'ชื่อผู้ใช้ภายในสร้างจากอีเมล');
ok($row['activated_at'] === null, 'บัญชีใหม่ยังไม่เปิดใช้งาน');
eq((int) val('SELECT can_edit FROM user_permissions WHERE user_id = ?', [$nu]), 0, 'สิทธิ์ SURE ตามที่กำหนด');
throws(function () { user_save(null, ['email' => 'new.staff@example.test', 'name' => 'ซ้ำ', 'role' => ROLE_EMPLOYEE]); }, AppError::class, 'อีเมลซ้ำไม่ได้', 'อีเมลนี้มีบัญชีอยู่แล้ว');
throws(function () { user_save(null, ['name' => 'ไม่มีอีเมล', 'role' => ROLE_EMPLOYEE]); }, AppError::class, 'อีเมลบังคับ', 'อีเมล');
throws(function () { user_save(null, ['email' => 'not-an-email', 'name' => 'x', 'role' => ROLE_EMPLOYEE]); }, AppError::class, 'อีเมลผิดรูปแบบ');
throws(function () use ($manager) { user_save($manager, ['email' => 'manager@example.test', 'name' => 'Manager', 'role' => ROLE_EMPLOYEE, 'active' => 1]); }, AppError::class, 'ลดสิทธิ์ตัวเองไม่ได้');
eq(password_step('new.staff@example.test', 'anything123')['error'] ?? null, 'อีเมลหรือรหัสผ่านไม่ถูกต้อง', 'ยังไม่เปิดใช้งาน → เข้าสู่ระบบไม่ได้');

issue_activation($row);
$mail = mailbox_latest($MAILDIR, 'new.staff@example.test', $t0);
eq($mail['subject'] ?? null, 'เปิดใช้งานบัญชี AMN SURE', 'ส่งอีเมลเปิดใช้งาน (หัวเรื่องภาษาไทยอ่านได้)');
$tok = mailbox_token($mail);
ok($tok && strpos($mail['text'], 'https://sure.example.test/index.php?r=auth.activate&t=' . $tok) !== false, 'ลิงก์ใช้ที่อยู่เว็บจากการตั้งค่า');
eq((int) val("SELECT COUNT(*) FROM auth_tokens WHERE secret_hash = ? OR secret_hash = ?", [$tok, hash('sha256', $tok)]), 0, 'ฐานข้อมูลไม่เก็บลิงก์ตัวจริง');
ok(find_activation($tok) !== null, 'ลิงก์ใช้ได้');
throws(function () use ($tok) { complete_activation($tok, 'short1', 'short1'); }, AppError::class, 'รหัสผ่านสั้น → ปฏิเสธ', 'อย่างน้อย 10');
throws(function () use ($tok) { complete_activation($tok, 'GoodPass123', 'Other1234567'); }, AppError::class, 'ยืนยันรหัสไม่ตรง → ปฏิเสธ', 'ไม่ตรงกัน');
$u = complete_activation($tok, 'GoodPass123', 'GoodPass123');
ok($u['activated_at'] !== null, 'ตั้งรหัสผ่านจากลิงก์ → เปิดใช้งานแล้ว');
throws(function () use ($tok) { complete_activation($tok, 'GoodPass123', 'GoodPass123'); }, AppError::class, 'ลิงก์ใช้ได้ครั้งเดียว', 'ใช้ไม่ได้แล้ว');
$ex = create_user_invited('expire@example.test', 'ทดสอบหมดอายุ', ROLE_EMPLOYEE, true, false);
$exRow = one('SELECT * FROM users WHERE id = ?', [$ex]);
issue_activation($exRow);
$tokA = mailbox_token(mailbox_latest($MAILDIR, 'expire@example.test'));
array_map('unlink', glob($MAILDIR . '/*.eml'));
issue_activation($exRow);
$tokB = mailbox_token(mailbox_latest($MAILDIR, 'expire@example.test'));
ok($tokA !== $tokB && find_activation($tokA) === null && find_activation($tokB) !== null, 'ออกลิงก์ใหม่ → ลิงก์เก่าใช้ไม่ได้');
q("UPDATE auth_tokens SET expires_at = ? WHERE user_id = ? AND purpose = 'activate'", ['2000-01-01 00:00:00', $ex]);
ok(find_activation($tokB) === null, 'ลิงก์เกิน ' . ACTIVATION_HOURS . ' ชั่วโมง → ใช้ไม่ได้');
ok(find_activation('abc') === null && find_activation(str_repeat('a', 43)) === null, 'ลิงก์มั่ว → ใช้ไม่ได้');

section('ยืนยันทางอีเมลทุกครั้งที่เข้าสู่ระบบ');
$_SESSION = [];
eq(password_step('new.staff@example.test', 'WrongPass123')['error'] ?? null, 'อีเมลหรือรหัสผ่านไม่ถูกต้อง', 'รหัสผ่านผิด → ปฏิเสธ');
$ps = password_step('NEW.STAFF@example.test', 'GoodPass123');
ok(isset($ps['user']), 'รหัสผ่านถูก (อีเมลตัวพิมพ์ใหญ่ก็ได้)');
array_map('unlink', glob($MAILDIR . '/*.eml'));
start_login_challenge($ps['user']);
ok(empty($_SESSION['uid']) && !empty($_SESSION['mfa']), 'รหัสผ่านถูกแล้วยังไม่เข้าสู่ระบบ — รอรหัสจากอีเมล');
$cmail = mailbox_latest($MAILDIR, 'new.staff@example.test');
$code = mailbox_code($cmail);
ok((bool) preg_match('/^\d{6}$/', (string) $code), 'ส่งรหัส 6 หลักทางอีเมล');
eq($cmail['subject'] ?? null, 'รหัสยืนยันเข้าสู่ระบบ AMN SURE', 'หัวเรื่องอีเมลรหัสยืนยัน');
$bad = $code === '000000' ? '111111' : '000000';
eq(verify_login_code($bad), 'รหัสไม่ถูกต้อง (ลองได้อีก 4 ครั้ง)', 'รหัสผิด → ปฏิเสธ');
ok(empty($_SESSION['uid']), 'รหัสผิดยังไม่เข้าสู่ระบบ');
throws(function () { resend_login_code(); }, AppError::class, 'ส่งรหัสใหม่ต้องรอ ' . LOGIN_CODE_RESEND_SECONDS . ' วินาที', 'ส่งรหัสใหม่ได้อีกครั้งใน');
eq(verify_login_code(substr($code, 0, 3) . ' ' . substr($code, 3)), null, 'รหัสถูก (มีช่องว่างก็ได้) → เข้าสู่ระบบ');
eq($_SESSION['uid'] ?? null, $nu, 'session เป็นของผู้ใช้คนนี้');
ok(empty($_SESSION['mfa']), 'ล้างสถานะรอยืนยัน');
eq(verify_login_code($code), 'หมดเวลายืนยัน กรุณาเข้าสู่ระบบใหม่', 'ใช้รหัสซ้ำไม่ได้');

$_SESSION = [];
start_login_challenge($u);
for ($i = 0; $i < LOGIN_CODE_MAX_TRIES - 1; $i++) verify_login_code($bad);
eq(verify_login_code($bad), 'ใส่รหัสผิดเกินกำหนด กรุณาเข้าสู่ระบบใหม่', 'รหัสผิดครบ ' . LOGIN_CODE_MAX_TRIES . ' ครั้ง → ต้องเริ่มใหม่');
ok(empty($_SESSION['mfa']) && empty($_SESSION['uid']), 'ล้างสถานะรอยืนยัน ไม่เข้าสู่ระบบ');
ok(strpos(password_step('new.staff@example.test', 'GoodPass123')['error'] ?? '', 'หลายครั้งเกินไป') !== false, 'รหัสยืนยันผิดนับรวมในการล็อกบัญชี 15 นาที');
q('DELETE FROM login_attempts');

$_SESSION = [];
array_map('unlink', glob($MAILDIR . '/*.eml'));
start_login_challenge($u);
$old = mailbox_code(mailbox_latest($MAILDIR, 'new.staff@example.test'));
array_map('unlink', glob($MAILDIR . '/*.eml'));
$_SESSION['mfa']['sent'] -= LOGIN_CODE_RESEND_SECONDS + 1;
resend_login_code();
$new = mailbox_code(mailbox_latest($MAILDIR, 'new.staff@example.test'));
ok($new !== null, 'ส่งรหัสใหม่ได้หลังรอครบ');
if ($old !== $new) ok(strpos((string) verify_login_code($old), 'รหัสไม่ถูกต้อง') === 0, 'รหัสเก่าใช้ไม่ได้หลังส่งรหัสใหม่');
eq(verify_login_code($new), null, 'รหัสใหม่ใช้ได้');

$_SESSION = [];
start_login_challenge($u);
$c3 = mailbox_code(mailbox_latest($MAILDIR, 'new.staff@example.test'));
q('UPDATE auth_tokens SET expires_at = ? WHERE id = ?', ['2000-01-01 00:00:00', $_SESSION['mfa']['tid']]);
eq(verify_login_code($c3), 'รหัสหมดอายุแล้ว — กด "ส่งรหัสใหม่"', 'รหัสเกิน ' . LOGIN_CODE_MINUTES . ' นาที → ใช้ไม่ได้');
$_SESSION['mfa']['started'] -= 31 * 60;
ok(pending_login() === null, 'รอใส่รหัสเกิน 30 นาที → ต้องเข้าสู่ระบบใหม่');
$_SESSION = [];
$GLOBALS['APP_CONFIG']['email_2fa'] = false;
ok(!email_2fa_enabled(), "ปิดชั่วคราวได้ด้วย 'email_2fa' => false ใน config");
$GLOBALS['APP_CONFIG']['email_2fa'] = true;
ok(email_2fa_enabled(), 'ค่าเริ่มต้น = เปิด');
throws(function () use ($staff) { start_login_challenge(one('SELECT * FROM users WHERE id = ?', [$staff])); }, AppError::class, 'บัญชีไม่มีอีเมล → แจ้งให้ติดต่อ Manager', 'ยังไม่มีอีเมล');
eq(mask_email('buranon@amn.co.th'), 'b•••••n@amn.co.th', 'ซ่อนอีเมลบางส่วนบนหน้าจอ');

section('หน้า "เข้าใช้ครั้งแรก"');
$_SESSION = [];
create_user_invited('invitee@example.test', 'ผู้ได้รับเชิญ', ROLE_EMPLOYEE, true, true);
array_map('unlink', glob($MAILDIR . '/*.eml'));
request_activation('nobody@example.test');
ok(mailbox_latest($MAILDIR, 'nobody@example.test') === null, 'อีเมลที่ไม่มีบัญชี → ไม่ส่ง (หน้าเว็บตอบเหมือนกันทุกกรณี)');
request_activation('new.staff@example.test');
ok(mailbox_latest($MAILDIR, 'new.staff@example.test') === null, 'บัญชีที่เปิดใช้งานแล้ว → ไม่ส่งลิงก์ (ลืมรหัสต้องให้ Manager ส่ง)');
request_activation('Invitee@Example.test');
ok(mailbox_token(mailbox_latest($MAILDIR, 'invitee@example.test')) !== null, 'อีเมลที่ Manager สร้างไว้และยังไม่เปิดใช้ → ส่งลิงก์');
request_activation('invitee@example.test');
request_activation('invitee@example.test');
throws(function () { request_activation('invitee@example.test'); }, AppError::class, 'ขอลิงก์เกิน 3 ครั้ง / 15 นาที → ปฏิเสธ', 'บ่อยเกินไป');
throws(function () { request_activation('not-email'); }, AppError::class, 'อีเมลผิดรูปแบบ → ปฏิเสธ');
q('DELETE FROM login_attempts');

section('Manager ส่งลิงก์ตั้งรหัสผ่านใหม่ / เปลี่ยนอีเมล / ปิดบัญชี');
as_user($manager);
$sv = (int) val('SELECT session_version FROM users WHERE id = ?', [$nu]);
array_map('unlink', glob($MAILDIR . '/*.eml'));
user_send_password_link($nu);
ok(isset(password_step('new.staff@example.test', 'GoodPass123')['error']), 'ส่งลิงก์ตั้งรหัสใหม่ → รหัสเดิมใช้ไม่ได้ทันที');
ok((int) val('SELECT session_version FROM users WHERE id = ?', [$nu]) > $sv, 'และหลุดออกจากระบบทุกเครื่อง');
$rmail = mailbox_latest($MAILDIR, 'new.staff@example.test');
eq($rmail['subject'] ?? null, 'ตั้งรหัสผ่านใหม่ AMN SURE', 'อีเมลตั้งรหัสผ่านใหม่');
complete_activation((string) mailbox_token($rmail), 'NewPass12345', 'NewPass12345');
ok(isset(password_step('new.staff@example.test', 'NewPass12345')['user']), 'ตั้งรหัสใหม่จากลิงก์แล้วเข้าสู่ระบบได้');
throws(function () use ($manager) { user_send_password_link($manager); }, AppError::class, 'ส่งลิงก์ให้ตัวเองไม่ได้ (ใช้หน้าเปลี่ยนรหัสผ่าน)', 'เปลี่ยนรหัสผ่าน');
$r2 = user_save($nu, ['email' => 'moved@example.test', 'name' => 'พนักงานใหม่', 'role' => ROLE_EMPLOYEE, 'active' => 1, 'can_view' => 1]);
ok($r2['invite'] && val('SELECT activated_at FROM users WHERE id = ?', [$nu]) === null, 'เปลี่ยนอีเมล → ต้องยืนยันอีเมลใหม่ก่อนใช้งาน');
ok(isset(password_step('moved@example.test', 'NewPass12345')['error']), 'เปลี่ยนอีเมลแล้วรหัสเดิมใช้ไม่ได้');
throws(function () use ($manager) { user_save($manager, ['email' => 'other@example.test', 'name' => 'Manager', 'role' => ROLE_MANAGER, 'active' => 1]); }, AppError::class, 'เปลี่ยนอีเมลของตัวเองไม่ได้', 'ของตัวเอง');
$sv = (int) val('SELECT session_version FROM users WHERE id = ?', [$nu]);
$r3 = user_save($nu, ['email' => 'moved@example.test', 'name' => 'พนักงานใหม่', 'role' => ROLE_EMPLOYEE, 'active' => 0, 'can_view' => 1]);
ok(!$r3['invite'] && (int) val('SELECT session_version FROM users WHERE id = ?', [$nu]) > $sv, 'ปิดบัญชี → session เดิมหลุดทันที ไม่ส่งอีเมล');
throws(function () use ($nu) { user_send_password_link($nu); }, AppError::class, 'บัญชีที่ปิดแล้วส่งลิงก์ไม่ได้', 'ถูกปิด');
q('DELETE FROM login_attempts');

section('GM — คุมสถานะบัญชีของทุกคน + ดูผู้ใช้ออนไลน์');
$gm = create_user_raw('buranon', GM_EMAIL, 'Buranon (GM)', 'Passw0rd123', ROLE_MANAGER, true, true, false);
q('UPDATE users SET is_gm = 1 WHERE id = ?', [$gm]);
$_SESSION = [];
as_user($manager);
ok(!is_gm(), 'Manager ทั่วไปไม่ใช่ GM');
throws(function () { gm_users(); }, ForbiddenError::class, 'Manager ทั่วไปเปิดหน้า GM ไม่ได้', 'GM เท่านั้น');
throws(function () use ($gm) { user_save($gm, ['email' => GM_EMAIL, 'name' => 'Buranon (GM)', 'role' => ROLE_MANAGER, 'active' => 0]); }, ForbiddenError::class, 'Manager ทั่วไปปิด/แก้บัญชี GM ไม่ได้', 'GM');
throws(function () use ($gm) { user_send_password_link($gm); }, ForbiddenError::class, 'Manager ทั่วไปรีเซ็ตรหัสผ่านของ GM ไม่ได้', 'GM');
eq((int) val('SELECT active FROM users WHERE id = ?', [$gm]), 1, 'บัญชี GM ยังใช้งานได้');
as_user($staff);
throws(function () use ($manager) { gm_logout_everywhere($manager); }, ForbiddenError::class, 'พนักงานใช้คำสั่ง GM ไม่ได้');

$sidStaff = session_record_start($staff);
$sidStaff2 = session_record_start($staff);
$sidViewer = session_record_start($viewer);
$ago = function ($min) { return date('Y-m-d H:i:s', time() - $min * 60); };
q('UPDATE user_sessions SET last_seen_at = ? WHERE id = ?', [$ago(20), $sidStaff2]);
q('UPDATE user_sessions SET last_seen_at = ? WHERE id = ?', [$ago(9 * 60), $sidViewer]);
as_user($gm);
ok(is_gm(), 'บัญชี ' . GM_EMAIL . ' เป็น GM');
$_SESSION['sid'] = session_record_start($gm);
$byId = array_column(gm_users(), null, 'id');
eq($byId[$staff]['presence'], 'online', 'ใช้งานภายใน 5 นาที → ออนไลน์');
eq($byId[$staff]['devices'], 2, 'นับเครื่องที่เปิดอยู่ 2 เครื่อง');
eq($byId[$viewer]['presence'], 'offline', 'ไม่ได้ใช้งานเกิน 8 ชั่วโมง (ระบบให้ออกแล้ว) → ออฟไลน์');
eq($byId[$noperm]['presence'], 'offline', 'ไม่เคยเข้าสู่ระบบ → ออฟไลน์');
eq($byId[$gm]['presence'], 'online', 'GM เห็นตัวเองออนไลน์');
q('UPDATE user_sessions SET last_seen_at = ? WHERE id = ?', [$ago(20), $sidStaff]);
eq(array_column(gm_users(), null, 'id')[$staff]['presence'], 'idle', 'ทุกเครื่องไม่ได้ใช้งานเกิน 5 นาที → เปิดค้างไว้');
ok(session_record_touch($sidStaff, $staff), 'เครื่องที่ยังไม่ถูกสั่งออก ใช้งานต่อได้');
eq(array_column(gm_users(), null, 'id')[$staff]['presence'], 'online', 'กลับมาใช้งาน → ออนไลน์อีกครั้ง');
$live = array_column(gm_sessions(), 'id');
ok(in_array($sidStaff, $live, true) && in_array($sidStaff2, $live, true) && !in_array($sidViewer, $live, true), 'รายการเครื่องที่เข้าสู่ระบบอยู่ (ไม่รวมเครื่องที่หมดเวลา)');

gm_kick_session($sidStaff2);
ok(!session_record_touch($sidStaff2, $staff), 'GM ให้ออกจากระบบรายเครื่อง → เครื่องนั้นหลุดทันที');
ok(session_record_touch($sidStaff, $staff), 'เครื่องอื่นของคนเดียวกันยังใช้ได้');
throws(function () { gm_kick_session($_SESSION['sid']); }, AppError::class, 'ให้เครื่องที่ตัวเองใช้อยู่ออกไม่ได้', 'เครื่องที่คุณใช้อยู่');
throws(function () use ($sidStaff2) { gm_kick_session($sidStaff2); }, AppError::class, 'เครื่องที่ออกไปแล้ว → แจ้ง', 'ออกจากระบบไปแล้ว');
$sv = (int) val('SELECT session_version FROM users WHERE id = ?', [$staff]);
gm_logout_everywhere($staff);
ok(!session_record_touch($sidStaff, $staff) && (int) val('SELECT session_version FROM users WHERE id = ?', [$staff]) === $sv + 1, 'GM ให้ออกจากระบบทุกเครื่อง');
eq(array_column(gm_users(), null, 'id')[$staff]['devices'], 0, 'ไม่เหลือเครื่องที่เปิดอยู่');
throws(function () use ($gm) { gm_logout_everywhere($gm); }, AppError::class, 'คำสั่ง GM ใช้กับบัญชีตัวเองไม่ได้', 'ตัวเอง');
throws(function () use ($gm) { gm_set_active($gm, false); }, AppError::class, 'GM ปิดบัญชีตัวเองไม่ได้', 'ตัวเอง');

$sidMgr = session_record_start($manager);
gm_set_active($manager, false);
ok((int) val('SELECT active FROM users WHERE id = ?', [$manager]) === 0 && !session_record_touch($sidMgr, $manager), 'GM ปิดบัญชี Manager คนอื่นได้ → ออกจากระบบทันที');
eq(val('SELECT end_reason FROM user_sessions WHERE id = ?', [$sidMgr]), 'revoked', 'บันทึกเหตุผล: บัญชีถูกปิด');
gm_set_active($manager, true);
eq((int) val('SELECT active FROM users WHERE id = ?', [$manager]), 1, 'GM เปิดบัญชีคืนได้');

q('DELETE FROM login_attempts');
for ($i = 0; $i < 5; $i++) db_insert('login_attempts', ['login' => 'manager@example.test', 'ip' => '10.0.0.9', 'success' => 0, 'created_at' => now()]);
ok(array_column(gm_users(), null, 'id')[$manager]['locked'], 'GM เห็นบัญชีที่ถูกล็อกจากใส่รหัสผิด');
ok(strpos((string) (password_step('manager@example.test', 'Passw0rd123')['error'] ?? ''), 'หลายครั้ง') !== false, 'บัญชีล็อกอยู่ → เข้าสู่ระบบไม่ได้');
gm_unlock($manager);
ok(!array_column(gm_users(), null, 'id')[$manager]['locked'] && isset(password_step('manager@example.test', 'Passw0rd123')['user']), 'GM ปลดล็อก → เข้าสู่ระบบได้ทันที');

password_step('manager@example.test', 'wrong-password1');
$kinds = array_unique(array_column(gm_activity(), 'kind'));
ok(in_array('login', $kinds, true) && in_array('end', $kinds, true) && in_array('fail', $kinds, true), 'ประวัติ: เข้าสู่ระบบ / ออกจากระบบ / เข้าไม่สำเร็จ');
$r = user_save($gm, ['email' => GM_EMAIL, 'name' => 'Buranon', 'role' => ROLE_MANAGER, 'active' => 1]);
eq(val('SELECT name FROM users WHERE id = ?', [$gm]), 'Buranon', 'GM แก้บัญชีตัวเองได้');
eq(ua_label('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36'), 'Chrome · Windows', 'อ่านชื่ออุปกรณ์: Chrome · Windows');
eq(ua_label('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'), 'Safari · iPhone', 'อ่านชื่ออุปกรณ์: Safari · iPhone');
$_SESSION = [];
q('DELETE FROM login_attempts');
as_user($manager);

section('อัปเกรดฐานข้อมูลจากเวอร์ชัน 1.0.0 อัตโนมัติ');
db()->exec('DROP TABLE auth_tokens');
db()->exec('DROP TABLE user_sessions');
db()->exec('ALTER TABLE users DROP COLUMN activated_at');
db()->exec('ALTER TABLE users DROP COLUMN is_gm');
setting_set('schema_version', '1');
schema_upgrade();
ok((int) val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'user_sessions'") === 1, 'สร้างตาราง user_sessions');
eq((int) val('SELECT is_gm FROM users WHERE email = ?', [GM_EMAIL]), 1, GM_EMAIL . ' เป็น GM หลังอัปเกรด');
eq((int) val('SELECT COUNT(*) FROM users WHERE is_gm = 1'), 1, 'มี GM คนเดียว');
ok((int) val("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'activated_at'") === 1, 'เพิ่มคอลัมน์ activated_at');
ok((int) val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'auth_tokens'") === 1, 'สร้างตาราง auth_tokens');
eq((int) val('SELECT COUNT(*) FROM users WHERE activated_at IS NULL'), 0, 'บัญชีเดิมถือว่าเปิดใช้งานแล้ว');
eq(setting('schema_version'), '3', 'schema_version = 3');
schema_upgrade();
ok(true, 'รันซ้ำได้ไม่ error');

section('ฐานข้อมูลแยก — ไม่ปนกับเว็บหลัก / ระบบอื่น');
eq(setting('app_id'), APP_DB_ID, 'ติดตั้งแล้วมีเครื่องหมาย app_id ของ AMN SURE');
$iso = db_isolation_problems($pdo);
ok((bool) array_filter($iso, function ($m) { return strpos($m, 'มีตารางอยู่แล้ว') !== false; }), 'ตัวติดตั้งปฏิเสธฐานข้อมูลที่มีตารางอยู่แล้ว');
$visible = array_diff($pdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN), ['information_schema', 'performance_schema', config('db')['name']]);
if ($visible) ok((bool) array_filter($iso, function ($m) { return strpos($m, 'เข้าถึงฐานข้อมูลอื่นได้') !== false; }), 'ตัวติดตั้งปฏิเสธผู้ใช้ฐานข้อมูลที่เข้าถึงฐานข้อมูลอื่นได้');
if (config('isolated_db')) {
    // ฐานข้อมูลว่าง + ผู้ใช้ที่มีสิทธิ์เฉพาะฐานข้อมูลนั้น (แบบที่คู่มือให้สร้างใน cPanel) → ผ่าน
    $isoPdo = db_connect(config('isolated_db'));
    foreach ($isoPdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $tbl) $isoPdo->exec('DROP TABLE `' . $tbl . '`');
    eq(db_isolation_problems($isoPdo), [], 'ฐานข้อมูลว่าง + ผู้ใช้เฉพาะฐานข้อมูลนี้ → ติดตั้งได้');
}
db_assert_ours();
ok(true, 'ฐานข้อมูลของ SURE ผ่านการตรวจทุก request');
q("DELETE FROM sure_settings WHERE setting_key = 'app_id'");
unset($GLOBALS['__SETTINGS']);
db_assert_ours();
unset($GLOBALS['__SETTINGS']);
eq(setting('app_id'), APP_DB_ID, 'ฐานข้อมูลจากเวอร์ชัน 1.0.0 (ไม่มีเครื่องหมาย) → ตรวจตารางแล้วใส่เครื่องหมายให้');
setting_set('app_id', 'wordpress');
unset($GLOBALS['__SETTINGS']);
throws(function () { schema_upgrade(); }, ForeignDatabaseError::class, 'เครื่องหมายไม่ใช่ของ SURE → หยุด ไม่อัปเกรด/แก้ตาราง');
setting_set('app_id', APP_DB_ID);
db()->exec('RENAME TABLE sure_settings TO sure_settings_hidden');
unset($GLOBALS['__SETTINGS']);
throws(function () { schema_upgrade(); }, ForeignDatabaseError::class, 'ฐานข้อมูลของเว็บอื่น (ไม่มี sure_settings) → หยุดก่อน ALTER ตาราง users ของเว็บนั้น');
db()->exec('RENAME TABLE sure_settings_hidden TO sure_settings');
unset($GLOBALS['__SETTINGS']);
db_assert_ours();
ok(true, 'คืนค่าแล้วกลับมาใช้งานได้');

section('ส่งอีเมลผ่าน SMTP (server ทดสอบ: ไม่เข้ารหัส / STARTTLS / SSL)');
$certFile = sys_get_temp_dir() . '/sure-test-cert-' . getmypid() . '.pem';
$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
$crt = openssl_csr_sign(openssl_csr_new(['commonName' => '127.0.0.1'], $key), null, $key, 1);
openssl_x509_export($crt, $crtPem);
openssl_pkey_export($key, $keyPem);
file_put_contents($certFile, $crtPem . $keyPem);
$smtpDir = sys_get_temp_dir() . '/sure-test-smtp-' . getmypid();
$p1 = random_int(20000, 29000);
$p2 = $p1 + 1;
$procs = [];
foreach ([[$p1, 'plain'], [$p2, 'ssl']] as [$port, $mode]) {
    $procs[] = proc_open([PHP_BINARY, __DIR__ . '/fake_smtp.php', (string) $port, $smtpDir, $mode, $certFile], [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pp);
}
for ($i = 0; $i < 30; $i++) {
    $a = @fsockopen('127.0.0.1', $p1, $en, $es, 0.2);
    $b = @fsockopen('127.0.0.1', $p2, $en, $es, 0.2);
    if ($a && $b) { fclose($a); fclose($b); break; }
    usleep(100000);
}
$smtp = ['transport' => 'smtp', 'host' => '127.0.0.1', 'from' => 'noreply@example.test', 'from_name' => 'AMN SURE', 'username' => 'noreply@example.test', 'password' => 'secret', 'verify_peer' => false];
foreach (['none' => $p1, 'tls' => $p1, 'ssl' => $p2] as $enc => $port) {
    $to = "smtp-$enc@example.test";
    try {
        send_mail($to, 'ทดสอบ SMTP ภาษาไทย', "บรรทัดแรก\nรหัส 123 456\n", '<p>ทดสอบ</p>', $smtp + ['encryption' => $enc, 'port' => $port]);
        $m = mailbox_latest($smtpDir, $to);
        ok($m && $m['subject'] === 'ทดสอบ SMTP ภาษาไทย' && mailbox_code($m) === '123456', "SMTP $enc: ส่งถึง หัวเรื่องและเนื้อหาภาษาไทยถูกต้อง");
    } catch (Throwable $e) {
        ok(false, "SMTP $enc: " . $e->getMessage());
    }
}
throws(function () use ($smtp, $p2) { send_mail('x@example.test', 's', 't', 'h', ['password' => 'wrong', 'encryption' => 'ssl', 'port' => $p2] + $smtp); }, MailError::class, 'รหัส SMTP ผิด → แจ้ง error', '535');
throws(function () use ($smtp, $p2) { send_mail('x@example.test', 's', 't', 'h', ['encryption' => 'ssl', 'port' => $p2, 'verify_peer' => true] + $smtp); }, MailError::class, 'ใบรับรอง SSL ไม่น่าเชื่อถือ → ไม่ส่ง (ตรวจใบรับรองเป็นค่าเริ่มต้น)', 'เชื่อมต่อ SMTP');
throws(function () use ($smtp, $p2) { send_mail('x@example.test', 's', 't', 'h', ['encryption' => 'none', 'port' => $p2 + 7] + $smtp); }, MailError::class, 'เชื่อมต่อ SMTP ไม่ได้ → แจ้ง error', 'เชื่อมต่อ SMTP');
throws(function () { send_mail('x@example.test', 's', 't', 'h', ['transport' => 'smtp']); }, MailError::class, 'ไม่ได้ตั้งค่าอีเมล → แจ้ง error', 'ยังไม่ได้ตั้งค่า');
foreach ($procs as $pr) { proc_terminate($pr); proc_close($pr); }
@unlink($certFile);

echo "\n" . str_repeat('─', 50) . "\nผ่าน {$GLOBALS['__TESTS']['pass']} · ไม่ผ่าน {$GLOBALS['__TESTS']['fail']}\n";
exit($GLOBALS['__TESTS']['fail'] > 0 ? 1 : 0);
