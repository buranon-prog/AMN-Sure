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
throws(function () { user_save(null, ['username' => 'x1', 'name' => 'x', 'role' => ROLE_MANAGER, 'password' => 'Passw0rd123']); }, ForbiddenError::class, 'EMPLOYEE สร้างบัญชีไม่ได้');
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
section('บัญชีผู้ใช้');
$nu = user_save(null, ['username' => 'NewStaff', 'name' => 'พนักงานใหม่', 'role' => ROLE_EMPLOYEE, 'active' => 1, 'can_view' => 1, 'can_edit' => 0, 'password' => 'Temp12345678']);
$row = one('SELECT * FROM users WHERE id = ?', [$nu]);
eq($row['username'], 'newstaff', 'ชื่อผู้ใช้เก็บเป็นตัวเล็ก');
eq((int) $row['must_change_password'], 1, 'บัญชีใหม่ต้องเปลี่ยนรหัสตอนเข้าครั้งแรก');
eq((int) val('SELECT can_edit FROM user_permissions WHERE user_id = ?', [$nu]), 0, 'สิทธิ์ SURE ตามที่กำหนด');
throws(function () { user_save(null, ['username' => 'newstaff', 'name' => 'ซ้ำ', 'role' => ROLE_EMPLOYEE, 'password' => 'Temp12345678']); }, AppError::class, 'ชื่อผู้ใช้ซ้ำไม่ได้');
throws(function () { user_save(null, ['username' => 'weak', 'name' => 'x', 'role' => ROLE_EMPLOYEE, 'password' => 'short']); }, AppError::class, 'รหัสผ่านสั้นไม่ได้');
throws(function () use ($manager) { user_save($manager, ['username' => 'manager', 'name' => 'Manager', 'role' => ROLE_EMPLOYEE, 'active' => 1]); }, AppError::class, 'ลดสิทธิ์ตัวเองไม่ได้');
$sv = (int) val('SELECT session_version FROM users WHERE id = ?', [$nu]);
user_save($nu, ['username' => 'newstaff', 'name' => 'พนักงานใหม่', 'role' => ROLE_EMPLOYEE, 'active' => 0, 'can_view' => 1]);
ok((int) val('SELECT session_version FROM users WHERE id = ?', [$nu]) > $sv, 'ปิดบัญชี → session เดิมหลุดทันที');
user_reset_password($nu, 'Reset1234567');
eq((int) val('SELECT must_change_password FROM users WHERE id = ?', [$nu]), 1, 'รีเซ็ตรหัส → ต้องเปลี่ยนรหัสใหม่');

echo "\n" . str_repeat('─', 50) . "\nผ่าน {$GLOBALS['__TESTS']['pass']} · ไม่ผ่าน {$GLOBALS['__TESTS']['fail']}\n";
exit($GLOBALS['__TESTS']['fail'] > 0 ? 1 : 0);
