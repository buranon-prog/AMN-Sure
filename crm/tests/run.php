<?php
// Automated tests: สิทธิ์ (RBAC) และการเปลี่ยนสถานะสำคัญ ตาม Scenario A–E ของสเปก
// วิธีรัน:  php tests/run.php   (ต้องมีฐานข้อมูลทดสอบว่าง ๆ — ดู tests/config.test.php)
// ตารางทั้งหมดในฐานข้อมูลทดสอบจะถูกลบและสร้างใหม่ทุกครั้ง

define('TESTING', true);
define('STORAGE_DIR', sys_get_temp_dir() . '/amncrm-test-storage');
require __DIR__ . '/../app/bootstrap.php';
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false; // ถูกปิดด้วย @
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

// ล้างฐานข้อมูลทดสอบ
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) $pdo->exec('DROP TABLE `' . $t . '`');
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
install_schema($pdo);
seed_base_data();
@mkdir(STORAGE_DIR . '/uploads', 0777, true);

// ---------------------------------------------------------------- mini test framework
$GLOBALS['T'] = ['pass' => 0, 'fail' => 0, 'failures' => []];

function ok($cond, string $msg): void
{
    if ($cond) { $GLOBALS['T']['pass']++; return; }
    $GLOBALS['T']['fail']++;
    $GLOBALS['T']['failures'][] = $msg;
    echo "  ✗ $msg\n";
}

function eq($actual, $expected, string $msg): void
{
    ok($actual == $expected, $msg . ' (ได้ ' . var_export($actual, true) . ' ต้องการ ' . var_export($expected, true) . ')');
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
        if ($GLOBALS['__TX_DEPTH'] ?? 0) db_set(db()); // กันกรณี transaction ค้าง
    }
}

function section(string $name): void { echo "\n▶ $name\n"; }

function as_user(string $key): void { act_as($GLOBALS['U'][$key]); }

function fake_upload(string $name, string $content): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'up');
    file_put_contents($tmp, $content);
    return ['name' => $name, 'tmp_name' => $tmp, 'size' => strlen($content), 'error' => UPLOAD_ERR_OK, 'type' => 'application/octet-stream'];
}

function pdf_bytes(): string { return "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n"; }

// ---------------------------------------------------------------- users per role
$U = [];
foreach ([
    'gm' => ['GM'], 'coord' => ['SALES_COORDINATOR'], 'exec' => ['SALES_EXECUTIVE'], 'sdir' => ['SALES_DIRECTOR'],
    'eng' => ['SERVICE_ENGINEER'], 'svdir' => ['SERVICE_DIRECTOR'], 'mkt' => ['MARKETING'], 'mgmt' => ['MANAGEMENT'], 'admin' => ['ADMIN'],
] as $key => $roles) {
    act_as(null);
    $U[$key] = create_user_raw($key, $key . '@test.local', 'User ' . strtoupper($key), 'Passw0rd!123', $roles);
}
$GLOBALS['U'] = $U;

// ================================================================ Scenario C — follow-up
section('Scenario C: ลีด + follow-up + OVERDUE + activity');
as_user('coord');
throws(function () {
    lead_create_seller(['new_org_name' => 'คลินิกทดสอบ', 'new_org_phone' => '081-111-1111', 'source' => 'FACEBOOK', 'brand' => 'Candela', 'model' => 'GentleMax Pro',
        'expected_price' => '1,200,000', 'location' => 'กรุงเทพฯ', 'next_action' => '', 'next_follow_up_date' => '']);
}, AppError::class, 'BR-01: ลีดที่ไม่มี next action / วันติดตาม ต้องถูกปฏิเสธ', 'BR-01');

throws(function () {
    lead_create_seller(['new_org_name' => 'คลินิกไม่มีเบอร์', 'source' => 'FACEBOOK', 'brand' => 'Candela', 'model' => 'X',
        'expected_price' => '100', 'location' => 'BKK', 'next_action' => 'โทร', 'next_follow_up_date' => today()]);
}, AppError::class, 'ลีดต้องมีช่องทางติดต่ออย่างน้อย 1 ช่องทาง', 'ช่องทางติดต่อ');
eq((int) val("SELECT COUNT(*) FROM organizations WHERE name = 'คลินิกไม่มีเบอร์'"), 0, 'rollback: ลูกค้าที่สร้างระหว่างลีดที่ล้มเหลวต้องไม่ค้างในระบบ');

$oppA = lead_create_seller([
    'new_org_name' => 'คลินิกผิวสวย', 'new_org_type' => 'CLINIC', 'new_org_phone' => '081-234-5678',
    'new_contact_name' => 'คุณเอ', 'new_contact_phone' => '089-999-9999',
    'source' => 'FACEBOOK', 'owner_id' => $U['coord'],
    'brand' => 'Candela', 'model' => 'GentleMax Pro', 'serial_number' => 'GMX-2020-001', 'manufacture_year' => '2020',
    'expected_price' => '1,200,000', 'asking_price' => '1,300,000', 'location' => 'สุขุมวิท กรุงเทพฯ', 'reason_for_sale' => 'ปิดสาขา',
    'next_action' => 'โทรนัดตรวจเครื่อง', 'next_follow_up_date' => add_days(today(), -2),
]);
$opp = db_get('device_opportunities', $oppA);
eq($opp['status'], 'NEW_SELLER_LEAD', 'ลีดผู้ขายใหม่ได้สถานะ NEW_SELLER_LEAD');
eq(db_get('devices', $opp['device_id'])['commercial_status'], 'UNDER_OFFER', 'เครื่องเปลี่ยนเป็น UNDER_OFFER');
ok(preg_match('/^DO-\d{4}-0001$/', $opp['ref_no']) === 1, 'เลขอ้างอิงดีลซื้อรูปแบบ DO-ปี-0001');
ok(preg_match('/^DEV-000001$/', db_get('devices', $opp['device_id'])['ref_no']) === 1, 'Device ID รูปแบบ DEV-000001');

$fq = followup_query(['owner' => 'me', 'overdue' => true]);
$over = all('SELECT x.* ' . $fq['sql'], $fq['params']);
ok(in_array($oppA, array_column($over, 'id'), true), 'ดีลที่เลยวันติดตามแสดงใน OVERDUE ของเจ้าของ');
$dash = dashboard_data(false);
ok(in_array($oppA, array_column($dash['overdue'], 'id'), true), 'Dashboard แสดงรายการ OVERDUE');

activity_add('DEVICE_OPPORTUNITY', $oppA, ['type' => 'CALL', 'summary' => 'โทรคุยแล้ว ผู้ขายสะดวกวันพฤหัส', 'next_action' => 'ส่งช่างเข้าตรวจ', 'next_follow_up_date' => add_days(today(), 3)]);
$opp = db_get('device_opportunities', $oppA);
eq($opp['next_follow_up_date'], add_days(today(), 3), 'บันทึกกิจกรรมแล้วเลื่อนวันติดตาม');
$fq = followup_query(['owner' => 'me', 'overdue' => true]);
ok(!in_array($oppA, array_column(all('SELECT x.* ' . $fq['sql'], $fq['params']), 'id'), true), 'ไม่ OVERDUE แล้วหลังเลื่อนวันติดตาม');
ok((int) val("SELECT COUNT(*) FROM activities WHERE parent_id = ? AND type = 'CALL'", [$oppA]) === 1, 'ประวัติกิจกรรมบันทึกการโทร');

// duplicate device warning
throws(function () use ($U) {
    lead_create_seller(['new_org_name' => 'อีกคลินิก', 'new_org_phone' => '02-555-5555', 'source' => 'REFERRAL', 'brand' => 'Candela', 'model' => 'GentleMax Pro',
        'serial_number' => 'gmx 2020-001', 'expected_price' => '1', 'location' => 'X', 'next_action' => 'a', 'next_follow_up_date' => today()]);
}, AppError::class, 'Serial ซ้ำ (ต่างแค่ตัวพิมพ์/ช่องว่าง) ต้องถูกบล็อก', 'Serial นี้มีในระบบแล้ว');
$orgA = $opp['seller_org_id'];
throws(function () use ($orgA) {
    lead_create_seller(['organization_id' => $orgA, 'source' => 'REFERRAL', 'brand' => 'candela', 'model' => 'gentlemax pro',
        'expected_price' => '1', 'location' => 'สุขุมวิท', 'next_action' => 'a', 'next_follow_up_date' => today()]);
}, AppError::class, 'เครื่องยี่ห้อ/รุ่นเดียวกันที่เจ้าของเดียวกันต้องเตือนว่าอาจซ้ำ', 'อาจเป็นเครื่องซ้ำ');

// ================================================================ Scenario A — acquisition
section('Scenario A: ลีดผู้ขาย → ตรวจ → Cost sheet → Valuation → เจรจา → GM อนุมัติ → ซื้อ → สต็อก');
as_user('eng');
throws(function () use ($oppA) { acq_request_inspection($oppA, []); }, ForbiddenError::class, 'Service Engineer ขอตรวจเครื่องเองไม่ได้');
as_user('coord');
$inspId = acq_request_inspection($oppA, ['usage_value' => '120000', 'usage_unit' => 'PULSE', 'preferred_date' => add_days(today(), 1)]);
eq(db_get('device_opportunities', $oppA)['status'], 'WAITING_INSPECTION', 'ขอตรวจแล้ว → WAITING_INSPECTION');
ok((int) val("SELECT COUNT(*) FROM tasks WHERE parent_type = 'INSPECTION' AND parent_id = ? AND assignee_role = 'SERVICE_ENGINEER'", [$inspId]) === 1, 'BR-02: สร้างงานให้ Service Engineering');
ok((int) val("SELECT COUNT(*) FROM technical_jobs WHERE parent_type = 'INSPECTION' AND parent_id = ?", [$inspId]) === 1, 'BR-02: สร้างใบงานช่าง');
throws(function () use ($inspId) { inspection_start($inspId); }, ForbiddenError::class, 'Sales Coordinator เริ่มตรวจเครื่องไม่ได้');

as_user('eng');
inspection_start($inspId);
eq(db_get('device_opportunities', $oppA)['status'], 'INSPECTION_IN_PROGRESS', 'เริ่มตรวจ → INSPECTION_IN_PROGRESS');
$items = all('SELECT * FROM inspection_items WHERE inspection_id = ? ORDER BY sort', [$inspId]);
ok(count($items) >= 10, 'สร้างรายการตรวจจาก template');
throws(function () use ($inspId) { inspection_save($inspId, ['overall_result' => 'PASS', 'overall_condition' => 'GOOD', 'technical_risk' => 'LOW'], true); },
    AppError::class, 'BR-03: ปิดการตรวจไม่ได้ถ้ารายการบังคับยังไม่มีผล', 'BR-03');
$res = [];
foreach ($items as $it) $res[$it['id']] = ['result' => $it['category'] === 'HANDPIECE' ? 'MINOR_ISSUE' : 'PASS', 'note' => $it['category'] === 'HANDPIECE' ? 'สายหัวทำงานมีรอย' : null];
throws(function () use ($inspId, $res) { inspection_save($inspId, ['items' => $res], true); }, AppError::class, 'BR-03: ปิดการตรวจไม่ได้ถ้าไม่มีผลรวม', 'overall');
inspection_save($inspId, ['items' => $res, 'overall_result' => 'MINOR_ISSUE', 'overall_condition' => 'GOOD', 'technical_risk' => 'LOW',
    'issues' => 'สายหัวทำงานมีรอย', 'recommended_repair' => 'เปลี่ยนสาย handpiece', 'est_repair_days' => '3'], true);
eq(db_get('inspections', $inspId)['status'], 'COMPLETED', 'ปิดการตรวจได้เมื่อครบ');
eq(db_get('device_opportunities', $oppA)['status'], 'INSPECTED', 'ตรวจเสร็จ → INSPECTED');
ok((int) val("SELECT COUNT(*) FROM tasks WHERE parent_id = ? AND type = 'INSPECTION_DONE' AND assignee_id = ?", [$oppA, $U['coord']]) === 1, 'BR-04: แจ้งงานคืนเจ้าของดีลฝั่งการค้า');

// Service history & MA
service_history_add($opp['device_id'], ['service_date' => '2024-05-01', 'type' => 'REPAIR', 'description' => 'เปลี่ยนหลอด', 'cost' => '50000']);
eq(val('SELECT cost FROM device_service_history WHERE device_id = ?', [$opp['device_id']]), null, 'วิศวกร (ไม่มี finance.view) บันทึกต้นทุนงานซ่อมไม่ได้');
ma_record_add($opp['device_id'], ['provider' => 'Candela Thailand', 'end_date' => '2025-12-31']);

// cost sheet
throws(function () use ($oppA) { cost_sheet_save($oppA, ['items' => [['category' => 'REPAIR', 'amount' => '1000']]], true); }, ForbiddenError::class, 'Service Engineer ทำ Cost Sheet ไม่ได้');
as_user('svdir');
throws(function () use ($oppA) { cost_sheet_save($oppA, ['items' => []], true); }, AppError::class, 'Cost sheet ต้องมีรายการ');
$cs1 = cost_sheet_save($oppA, ['items' => [
    ['category' => 'ACQUISITION_ASSUMPTION', 'description' => 'ราคาซื้อที่คาด', 'amount' => '1,100,000'],
    ['category' => 'REPAIR', 'description' => 'เปลี่ยนสาย handpiece', 'amount' => '35,000'],
    ['category' => 'TRANSPORT', 'amount' => '15000'],
]], false);
eq(db_get('cost_sheets', $cs1)['status'], 'DRAFT', 'บันทึกร่าง Cost Sheet');
cost_sheet_save($oppA, ['items' => [
    ['category' => 'ACQUISITION_ASSUMPTION', 'description' => 'ราคาซื้อที่คาด', 'amount' => '1,100,000'],
    ['category' => 'REPAIR', 'description' => 'เปลี่ยนสาย handpiece', 'amount' => '35,000'],
    ['category' => 'TRANSPORT', 'amount' => '15000'],
    ['category' => 'WARRANTY_PROVISION', 'amount' => '20000'],
]], true);
$cs = latest_cost_sheet($oppA);
eq($cs['id'], $cs1, 'ส่งร่างเดิม (ไม่สร้างฉบับใหม่)');
eq((float) $cs['total_estimated_cost'], 1170000.0, 'Total cost คำนวณถูก');
eq($cs['inspection_id'], $inspId, 'BR-05: Cost sheet อ้างอิงผลตรวจ');
eq(db_get('device_opportunities', $oppA)['status'], 'COSTED', 'ส่ง Cost sheet → COSTED');

// valuation
throws(function () use ($oppA) { valuation_create($oppA, []); }, ForbiddenError::class, 'Service Director ทำ Valuation ไม่ได้ (ตามข้อเสนอ Q7)');
as_user('sdir');
throws(function () use ($oppA) { valuation_create($oppA, ['fair_market_value' => '1500000', 'recommended_acq_price' => '1200000', 'max_acq_price' => '1100000',
    'target_selling_price' => '1500000', 'min_selling_price' => '1400000']); }, AppError::class, 'ราคาซื้อสูงสุดต้อง ≥ ราคาแนะนำ');
$valId = valuation_create($oppA, ['fair_market_value' => '1,500,000', 'recommended_acq_price' => '1,050,000', 'max_acq_price' => '1,150,000',
    'target_selling_price' => '1,550,000', 'min_selling_price' => '1,400,000', 'demand' => 'HIGH', 'expected_days_to_sell' => '45']);
$val = db_get('valuations', $valId);
eq((float) $val['expected_gp'], 380000.0, 'Expected GP = ราคาขายเป้าหมาย − ต้นทุนรวม');
eq(round((float) $val['expected_gp_margin'], 4), round(380000 / 1550000, 4), 'GP margin คำนวณถูก');
eq($val['cost_sheet_id'], $cs['id'], 'BR-06: Valuation อ้างอิง cost sheet');
eq((float) db_get('cost_sheets', $cs['id'])['total_estimated_cost'], 1170000.0, 'BR-06: Valuation ไม่เขียนทับ Cost sheet');
eq(db_get('device_opportunities', $oppA)['status'], 'VALUED', 'Valuation → VALUED');

// redaction
as_user('coord');
$red = redact('valuations', $val);
ok(!array_key_exists('max_acq_price', $red) && !array_key_exists('expected_gp', $red), 'Sales Coordinator ไม่ได้รับข้อมูลราคาซื้อสูงสุด/GP');
as_user('exec');
$red = redact('valuations', $val);
ok(array_key_exists('recommended_acq_price', $red) && !array_key_exists('max_acq_price', $red), 'Sales Executive เห็นราคาแนะนำ แต่ไม่เห็นราคาสูงสุด (Q5b)');
$d360 = device_360($opp['device_id']);
ok(!array_key_exists('cost', $d360['service'][0]), 'Device 360: ตัดต้นทุนงานซ่อมออกสำหรับผู้ไม่มีสิทธิ์');

// negotiation
as_user('exec');
negotiation_add($oppA, ['party' => 'AMN_OFFER', 'amount' => '1,000,000']);
eq(db_get('device_opportunities', $oppA)['status'], 'NEGOTIATING', 'บันทึกข้อเสนอ → NEGOTIATING');
negotiation_add($oppA, ['party' => 'SELLER_COUNTER', 'amount' => '1,150,000']);
throws(function () use ($oppA) { approval_submit($oppA, []); }, AppError::class, 'ส่งขออนุมัติไม่ได้ถ้ายังไม่มีราคาที่ตกลง', 'ราคาที่ตกลง');
negotiation_add($oppA, ['party' => 'AGREED', 'amount' => '1,080,000', 'note' => 'ตกลงที่ 1.08 ล้าน']);
eq((float) db_get('device_opportunities', $oppA)['final_negotiated_price'], 1080000.0, 'ราคาที่ตกลงถูกบันทึกเป็น final negotiated price');
ok((int) val('SELECT COUNT(*) FROM negotiations WHERE device_opportunity_id = ?', [$oppA]) === 3, 'เก็บประวัติการเจรจาทุกครั้ง');

// approval
as_user('coord');
throws(function () use ($oppA) { approval_submit($oppA, []); }, ForbiddenError::class, 'Sales Coordinator ส่งขออนุมัติไม่ได้');
as_user('exec');
$apprId = approval_submit($oppA, ['note' => 'ขออนุมัติซื้อ']);
eq(db_get('device_opportunities', $oppA)['status'], 'PENDING_APPROVAL', 'ส่งขออนุมัติ → PENDING_APPROVAL');
$pkg = json_decode(db_get('approvals', $apprId)['package_snapshot'], true);
ok(isset($pkg['inspection'], $pkg['cost_sheet'], $pkg['valuation'], $pkg['negotiations'], $pkg['service_history'], $pkg['ma'], $pkg['seller'], $pkg['device']), 'Approval package มีข้อมูลครบ (ผู้ขาย เครื่อง ตรวจ ประวัติ cost valuation เจรจา)');
eq((float) $pkg['at_final_price']['gp'], 1550000 - (1170000 - 1100000 + 1080000), 'GP ที่ราคาตกลงจริง คำนวณถูก');
throws(function () use ($oppA) { approval_submit($oppA, []); }, AppError::class, 'ส่งขออนุมัติซ้ำไม่ได้');

foreach (['coord', 'exec', 'sdir', 'svdir', 'mgmt', 'admin'] as $k) {
    as_user($k);
    throws(function () use ($apprId) { approval_decide($apprId, ['decision' => 'APPROVED']); }, ForbiddenError::class, "Scenario E: $k อนุมัติการซื้อไม่ได้");
}
as_user('coord');
throws(function () use ($oppA) { acquisition_create($oppA, ['purchase_price' => '1080000', 'purchase_date' => today()]); }, AppError::class, 'BR-07: สร้าง Acquisition ก่อนอนุมัติไม่ได้');

as_user('gm');
throws(function () use ($apprId) { approval_decide($apprId, ['decision' => 'REVISION_REQUIRED']); }, AppError::class, 'ให้แก้ไขต้องมีเหตุผล');
approval_decide($apprId, ['decision' => 'REVISION_REQUIRED', 'comment' => 'ลองต่อรองให้ต่ำกว่า 1.05 ล้าน']);
eq(db_get('device_opportunities', $oppA)['status'], 'NEGOTIATING', 'REVISION_REQUIRED → กลับไปเจรจา');
throws(function () use ($apprId) { approval_decide($apprId, ['decision' => 'APPROVED']); }, AppError::class, 'ตัดสินคำขอเดิมซ้ำไม่ได้');

as_user('exec');
negotiation_add($oppA, ['party' => 'AGREED', 'amount' => '1,040,000']);
$apprId2 = approval_submit($oppA, []);
as_user('gm');
throws(function () use ($apprId2) { approval_decide($apprId2, ['decision' => 'APPROVED_WITH_CONDITION']); }, AppError::class, 'อนุมัติแบบมีเงื่อนไขต้องระบุเงื่อนไข');
approval_decide($apprId2, ['decision' => 'APPROVED_WITH_CONDITION', 'condition_text' => 'ผู้ขายต้องส่งคู่มือและ key ครบ']);
eq(db_get('device_opportunities', $oppA)['status'], 'APPROVED', 'GM อนุมัติ → APPROVED');
ok((int) val("SELECT COUNT(*) FROM audit_logs WHERE entity_type = 'APPROVAL' AND entity_id = ? AND action = 'APPROVE'", [$apprId2]) === 1, 'Scenario E: การอนุมัติถูกบันทึกใน audit log');

as_user('coord');
throws(function () use ($oppA) { acquisition_create($oppA, ['purchase_price' => '1040000', 'purchase_date' => today()]); }, AppError::class, 'ต้องยืนยันว่าทำตามเงื่อนไขแล้ว', 'เงื่อนไข');
throws(function () use ($oppA) { acquisition_create($oppA, ['purchase_price' => '1,100,000', 'purchase_date' => today(), 'conditions_confirmed' => '1']); }, AppError::class, 'ราคาซื้อจริงเกินวงเงินอนุมัติไม่ได้', 'สูงกว่าวงเงิน');
$acqId = acquisition_create($oppA, ['purchase_price' => '1,040,000', 'purchase_date' => today(), 'conditions_confirmed' => '1', 'takes_stock' => '1', 'storage_location' => 'คลังบางนา', 'list_price' => '1,590,000']);
$opp = db_get('device_opportunities', $oppA);
$dev = db_get('devices', $opp['device_id']);
eq($opp['status'], 'PURCHASED', 'ซื้อแล้ว → PURCHASED');
eq($dev['commercial_status'], 'IN_INVENTORY', 'BR-08: เครื่องเข้าสต็อก → IN_INVENTORY');
eq((int) $dev['owned_by_amn'], 1, 'เจ้าของเครื่องเปลี่ยนเป็น AMN Sure');
eq((int) val("SELECT COUNT(*) FROM inventory WHERE device_id = ? AND status = 'IN_STOCK'", [$dev['id']]), 1, 'สร้างรายการสต็อก');
eq((int) val('SELECT COUNT(*) FROM device_ownerships WHERE device_id = ?', [$dev['id']]), 2, 'ประวัติเจ้าของ: ผู้ขาย → AMN Sure');
eq(db_get('leads', $opp['lead_id'])['status'], 'CLOSED', 'ลีดปิดเองเมื่อดีลทุกตัวปิด');
ok((int) val("SELECT COUNT(*) FROM audit_logs WHERE entity_type = 'DEVICE' AND entity_id = ? AND changes LIKE '%owned_by_amn%'", [$dev['id']]) >= 1, 'Scenario E: การเปลี่ยนเจ้าของบันทึกใน audit log');
$deviceA = $dev['id'];

// ================================================================ Scenario B — sales
section('Scenario B: ผู้ซื้อ → ความต้องการ → จับคู่ → ใบเสนอราคา → มัดจำ → สัญญา → WON → Checklist → QC → ส่ง → ติดตั้ง');
as_user('exec');
$soId = lead_create_buyer(['new_org_name' => 'โรงพยาบาลความงาม', 'new_org_type' => 'HOSPITAL', 'new_org_line' => '@beautyhosp', 'source' => 'LINE_OA',
    'interested_device' => 'เลเซอร์กำจัดขน Candela', 'budget_max' => '1,700,000', 'next_action' => 'ส่งรายละเอียดเครื่อง', 'next_follow_up_date' => add_days(today(), 1)]);
eq(db_get('sales_opportunities', $soId)['status'], 'NEW_BUYER_LEAD', 'ลีดผู้ซื้อ → NEW_BUYER_LEAD');
throws(function () use ($soId, $deviceA) {
    $inv = val("SELECT id FROM inventory WHERE device_id = ?", [$deviceA]);
    match_add($soId, 'INVENTORY', $inv, null);
}, AppError::class, 'ต้องมีความต้องการ (requirement) ก่อนจับคู่เครื่อง');
as_user('sdir');
requirement_save($soId, ['req_brand' => 'Candela', 'req_technology' => 'Laser', 'budget_max' => '1,700,000', 'expected_purchase_date' => add_days(today(), 30), 'installation_required' => '1']);
eq(db_get('sales_opportunities', $soId)['status'], 'REQUIREMENT_DEFINED', 'บันทึกความต้องการ → REQUIREMENT_DEFINED');
$cands = match_candidates($soId);
ok(in_array($deviceA, array_column($cands['inventory'], 'id'), true), 'จับคู่: พบเครื่องในสต็อก (source A)');

// source B: เครื่องที่ผู้ขายกำลังเสนอ
as_user('coord');
$oppB = lead_create_seller(['new_org_name' => 'คลินิกขายเครื่อง 2', 'new_org_phone' => '02-111-2222', 'source' => 'REFERRAL',
    'brand' => 'Candela', 'model' => 'GentleLase', 'serial_number' => 'GL-777', 'expected_price' => '500000', 'location' => 'เชียงใหม่',
    'next_action' => 'นัดตรวจ', 'next_follow_up_date' => add_days(today(), 1)]);
as_user('sdir');
$cands = match_candidates($soId);
ok(in_array(db_get('device_opportunities', $oppB)['device_id'], array_column($cands['seller'], 'id'), true), 'BR-09: จับคู่กับเครื่องที่ผู้ขายกำลังเสนอได้ (source B)');

$invA = val('SELECT id FROM inventory WHERE device_id = ?', [$deviceA]);
$m1 = match_add($soId, 'INVENTORY', $invA, 'ตรงความต้องการ');
eq(db_get('sales_opportunities', $soId)['status'], 'MATCHING', 'จับคู่แล้ว → MATCHING');
throws(function () use ($soId, $invA) { match_add($soId, 'INVENTORY', $invA, null); }, AppError::class, 'จับคู่เครื่องเดิมซ้ำไม่ได้');
as_user('coord');
throws(function () use ($soId) { quotation_create($soId); }, AppError::class, 'ออกใบเสนอราคาไม่ได้ถ้ายังไม่ได้เลือกเครื่อง', 'เลือกเครื่อง');
as_user('sdir');
match_set_status($m1, 'SELECTED');
as_user('coord');
$qId = quotation_create($soId);
$qv = quotation_current_version($qId);
eq($qv['status'], 'DRAFT', 'ใบเสนอราคาฉบับที่ 1 เป็นร่าง');
eq((float) $qv['subtotal'], 1590000.0, 'ใช้ราคาตั้งขายจากสต็อกเป็นค่าเริ่มต้น');
eq((float) $qv['vat_amount'], round(1590000 * 0.07, 2), 'VAT 7% คำนวณถูก');
eq(db_get('sales_opportunities', $soId)['status'], 'QUOTING', 'ออกใบเสนอราคา → QUOTING');

// ราคาต่ำกว่าราคาขายขั้นต่ำ → ต้อง GM
$line = one('SELECT * FROM quotation_version_lines WHERE quotation_version_id = ?', [$qv['id']]);
qv_save($qv['id'], ['lines' => [['device_id' => $line['device_id'], 'description' => $line['description'], 'qty' => '1', 'unit_price' => '1,350,000']],
    'valid_until' => add_days(today(), 30), 'vat_rate' => '7', 'payment_terms' => 'มัดจำ 30% ส่วนที่เหลือก่อนส่งมอบ']);
qv_request_approval($qv['id']);
ok((int) val("SELECT COUNT(*) FROM tasks WHERE parent_id = ? AND type = 'QUOTATION_APPROVAL' AND assignee_role = 'GM'", [$qId]) === 1, 'ราคาต่ำกว่าขั้นต่ำ → ขออนุมัติจาก GM');
throws(function () use ($qv) { qv_approve($qv['id']); }, ForbiddenError::class, 'Sales Coordinator อนุมัติใบเสนอราคาไม่ได้');
as_user('sdir');
throws(function () use ($qv) { qv_approve($qv['id']); }, ForbiddenError::class, 'Sales Director อนุมัติราคาต่ำกว่าขั้นต่ำไม่ได้ (Q14)', 'GM');
as_user('coord');
qv_save($qv['id'], ['lines' => [['device_id' => $line['device_id'], 'description' => $line['description'], 'qty' => '1', 'unit_price' => '1,500,000']],
    'valid_until' => add_days(today(), 30), 'vat_rate' => '7', 'payment_terms' => 'มัดจำ 30%']);
as_user('sdir');
qv_approve($qv['id']);
eq(db_get('quotation_versions', $qv['id'])['status'], 'APPROVED', 'Sales Director อนุมัติราคาปกติได้');
as_user('coord');
throws(function () use ($qv) { qv_save($qv['id'], ['lines' => [['description' => 'x', 'unit_price' => '1']], 'valid_until' => add_days(today(), 5)]); }, AppError::class, 'BR-10: แก้ใบเสนอราคาที่อนุมัติแล้วไม่ได้');
qv_send($qv['id']);
qv_respond($qv['id'], 'REJECTED', 'ขอส่วนลดเพิ่ม');
$v2 = quotation_revise($qId);
eq(db_get('quotation_versions', $qv['id'])['status'], 'SUPERSEDED', 'BR-10: ฉบับเดิมถูกแทนที่ (อ่านอย่างเดียว)');
eq((int) db_get('quotations', $qId)['current_version_no'], 2, 'ฉบับใหม่เป็นฉบับที่ 2');
throws(function () use ($qv) { qv_save($qv['id'], ['lines' => [['description' => 'x', 'unit_price' => '1']], 'valid_until' => add_days(today(), 5)]); }, AppError::class, 'BR-10: แก้ฉบับเก่าไม่ได้');
qv_save($v2, ['lines' => [['device_id' => $line['device_id'], 'description' => $line['description'], 'qty' => '1', 'unit_price' => '1,450,000']], 'valid_until' => add_days(today(), 30), 'vat_rate' => '7']);
as_user('sdir');
qv_approve($v2);
as_user('coord');
qv_send($v2);
$r = qv_respond($v2, 'ACCEPTED', 'ลูกค้าตกลง');
ok(!$r['won'], 'ยังไม่ WON หลังลูกค้าตอบรับอย่างเดียว');
$dep = one('SELECT * FROM deposits WHERE sales_opportunity_id = ?', [$soId]);
ok($dep && $dep['status'] === 'WAITING_DEPOSIT', 'ระบบสร้างรายการรอรับมัดจำอัตโนมัติ');
throws(function () use ($dep) { deposit_receive($dep['id'], ['received_amount' => $dep['required_amount'], 'received_date' => today()], null); }, AppError::class, 'รับมัดจำต้องแนบหลักฐาน', 'หลักฐาน');
throws(function () use ($dep) { deposit_receive($dep['id'], ['received_amount' => '1000', 'received_date' => today()], fake_upload('slip.pdf', pdf_bytes())); }, AppError::class, 'ยอดมัดจำน้อยกว่าที่กำหนดไม่ได้');
$r = deposit_receive($dep['id'], ['received_amount' => $dep['required_amount'], 'received_date' => today()], fake_upload('slip.pdf', pdf_bytes()));
ok(!$r['won'] && in_array('ลงนามสัญญา', $r['missing'], true), 'ยังไม่ WON จนกว่าจะลงนามสัญญา');
$ctrId = contract_save($soId, null, ['contract_no' => 'AMN-CT-001']);
throws(function () use ($ctrId) { contract_sign($ctrId, ['signed_date' => today()], null); }, AppError::class, 'ลงนามสัญญาต้องแนบไฟล์สัญญา');
$r = contract_sign($ctrId, ['signed_date' => today()], fake_upload('contract.pdf', pdf_bytes()));
ok($r['won'], 'BR-11: ตอบรับ + มัดจำ + สัญญา → WON อัตโนมัติ');
eq(db_get('sales_opportunities', $soId)['status'], 'WON', 'ดีลขาย → WON');
$trx = one('SELECT * FROM sales_transactions WHERE sales_opportunity_id = ?', [$soId]);
ok($trx && $trx['status'] === 'IN_PREPARATION', 'สร้าง Sales Transaction สถานะเตรียมส่งมอบ');
eq((float) $trx['total_price'], round(1450000 * 1.07, 2), 'มูลค่าธุรกรรม = ยอดใบเสนอราคาที่ลูกค้าตอบรับ');
eq(db_get('devices', $deviceA)['commercial_status'], 'RESERVED', 'เครื่องถูกจองให้ผู้ซื้อ');
eq((int) val('SELECT COUNT(*) FROM checklists WHERE sales_transaction_id = ?', [$trx['id']]), 4, 'สร้าง checklist 4 หมวด');

// ขายเครื่องเดียวกันซ้ำไม่ได้
as_user('exec');
$so2 = lead_create_buyer(['new_org_name' => 'คลินิกผู้ซื้อ 2', 'new_org_phone' => '02-333-4444', 'source' => 'WEBSITE', 'interested_device' => 'Candela',
    'next_action' => 'x', 'next_follow_up_date' => add_days(today(), 1)]);
as_user('sdir');
requirement_save($so2, ['req_brand' => 'Candela', 'budget_max' => '2,000,000', 'expected_purchase_date' => add_days(today(), 10)]);
ok(!in_array($deviceA, array_column(match_candidates($so2)['inventory'], 'id'), true), 'เครื่องที่ถูกจองแล้วไม่แสดงในรายการจับคู่');

// fulfillment
section('Fulfillment: checklist + QC (BR-12) + ส่งมอบ (BR-13) + ติดตั้ง');
as_user('eng');
throws(function () use ($trx) { trx_mark_ready($trx['id']); }, AppError::class, 'BR-12: พร้อมส่งไม่ได้ก่อน checklist/QC ครบ', 'QC');
$tcl = val("SELECT id FROM checklists WHERE sales_transaction_id = ? AND section = 'COMMERCIAL'", [$trx['id']]);
$citem = val('SELECT id FROM checklist_items WHERE checklist_id = ? LIMIT 1', [$tcl]);
throws(function () use ($citem) { checklist_item_set($citem, true, null); }, ForbiddenError::class, 'วิศวกรติ๊กเช็กลิสต์หมวด Commercial ไม่ได้');
$checksFail = [];
foreach (qc_items() as $it) $checksFail[$it['code']] = ['result' => 'PASS'];
$first = qc_items()[2]['code'];
$checksFail[$first] = ['result' => 'FAIL', 'note' => 'output ต่ำ'];
qc_record($trx['id'], $deviceA, ['checks' => $checksFail]);
ok((int) val("SELECT COUNT(*) FROM technical_jobs WHERE parent_id = ? AND type = 'REPAIR'", [$trx['id']]) === 1, 'QC ไม่ผ่าน → เปิดใบงานซ่อม');
$checksPass = array_map(function () { return ['result' => 'PASS']; }, $checksFail);
qc_record($trx['id'], $deviceA, ['checks' => $checksPass]);
eq(latest_qc($trx['id'], $deviceA)['overall_result'], 'PASS', 'QC ครั้งใหม่ผ่าน');
throws(function () use ($trx) { trx_mark_ready($trx['id']); }, AppError::class, 'พร้อมส่งไม่ได้ถ้า checklist ยังไม่ครบ', 'เช็กลิสต์');
foreach (all('SELECT ci.id, c.section FROM checklist_items ci JOIN checklists c ON c.id = ci.checklist_id WHERE c.sales_transaction_id = ?', [$trx['id']]) as $ci) {
    as_user(['COMMERCIAL' => 'coord', 'SALES' => 'coord', 'MARKETING' => 'mkt', 'TECHNICAL' => 'eng'][$ci['section']]);
    checklist_item_set($ci['id'], true, null);
}
eq((int) val("SELECT COUNT(*) FROM checklists WHERE sales_transaction_id = ? AND status = 'DONE'", [$trx['id']]), 4, 'checklist ครบทุกหมวด');
as_user('eng');
trx_mark_ready($trx['id']);
eq(db_get('sales_transactions', $trx['id'])['status'], 'READY_FOR_DELIVERY', 'QC PASS + checklist ครบ → READY_FOR_DELIVERY');
throws(function () use ($trx, $deviceA) { delivery_record($trx['id'], $deviceA, ['serial_confirmed' => 'WRONG-1', 'receiving_person' => 'คุณบี', 'delivered_date' => today()], null); },
    AppError::class, 'BR-13: serial ไม่ตรงห้ามส่งมอบ', 'BR-13');
delivery_record($trx['id'], $deviceA, ['serial_confirmed' => 'gmx-2020 001', 'receiving_person' => 'คุณบี', 'delivered_date' => today(), 'address' => 'รพ.ความงาม'], null);
eq(db_get('sales_transactions', $trx['id'])['status'], 'DELIVERED', 'ส่งมอบครบ → DELIVERED');
installation_record($trx['id'], $deviceA, ['installed_date' => today(), 'result' => 'PARTIAL', 'customer_accepted' => '0'], null);
eq(db_get('sales_transactions', $trx['id'])['status'], 'DELIVERED', 'ติดตั้งยังไม่สำเร็จ → ยังไม่ปิดธุรกรรม');
throws(function () use ($trx, $deviceA) { installation_record($trx['id'], $deviceA, ['installed_date' => today(), 'result' => 'SUCCESS', 'customer_accepted' => '1'], null); },
    AppError::class, 'ลูกค้าตรวจรับต้องระบุชื่อผู้ตรวจรับ');
installation_record($trx['id'], $deviceA, ['installed_date' => today(), 'result' => 'SUCCESS', 'customer_accepted' => '1', 'accepted_by_name' => 'พญ.ซี', 'training_done' => '1'], null);
$trx = db_get('sales_transactions', $trx['id']);
eq($trx['status'], 'TRANSACTION_COMPLETED', 'ติดตั้งสำเร็จ + ลูกค้าตรวจรับ → TRANSACTION_COMPLETED');
$dev = db_get('devices', $deviceA);
eq($dev['current_owner_org_id'], $trx['buyer_org_id'], 'เจ้าของเครื่องเปลี่ยนเป็นผู้ซื้อ');
eq($dev['commercial_status'], 'INSTALLED_AT_CUSTOMER', 'เครื่อง → ติดตั้งที่ลูกค้าแล้ว');
eq(val('SELECT status FROM inventory WHERE device_id = ?', [$deviceA]), 'SOLD', 'สต็อก → SOLD');

// ================================================================ Scenario D — Device 360
section('Scenario D: Device 360 ดึงประวัติครบจากหน้าเดียว');
as_user('gm');
$d360 = device_360($deviceA);
eq(count($d360['ownerships']), 3, 'ประวัติเจ้าของ 3 ช่วง (ผู้ขาย → AMN Sure → ผู้ซื้อ)');
ok(count($d360['inspections']) === 1 && count($d360['service']) >= 1 && count($d360['ma']) === 1, 'มีผลตรวจ ประวัติซ่อม และ MA');
ok(count($d360['cost_sheets']) === 1 && count($d360['valuations']) === 1, 'มี cost sheet และ valuation (สำหรับ GM)');
ok(count($d360['acquisitions']) === 1 && count($d360['matches']) === 1 && count($d360['transactions']) === 1, 'มีการซื้อ การจับคู่ผู้ซื้อ และธุรกรรมขาย');
ok(count($d360['deliveries']) === 1 && count($d360['installations']) === 2 && count($d360['qc']) === 2, 'มี QC การส่งมอบ และการติดตั้ง');
ok(count($d360['documents']) >= 0 && count($d360['activities']) > 5, 'มี timeline กิจกรรม');
as_user('mkt');
$d360m = device_360($deviceA);
ok(!$d360m['cost_sheets'] && !$d360m['valuations'], 'Marketing ไม่เห็น cost sheet / valuation ใน Device 360');

// ================================================================ Scenario E — control
section('Scenario E: สิทธิ์และ audit log');
as_user('mgmt');
throws(function () use ($orgA) { org_update($orgA, ['name' => 'เปลี่ยนชื่อ']); }, ForbiddenError::class, 'Management (ดูอย่างเดียว) แก้ข้อมูลลูกค้าไม่ได้');
as_user('mkt');
throws(function () use ($oppB) { negotiation_add($oppB, ['party' => 'AMN_OFFER', 'amount' => '1']); }, ForbiddenError::class, 'Marketing บันทึกการเจรจาไม่ได้');
as_user('eng');
throws(function () use ($oppB) { valuation_create($oppB, []); }, ForbiddenError::class, 'วิศวกรแก้ valuation ไม่ได้');
as_user('coord');
org_update($orgA, ['name' => 'คลินิกผิวสวย (สาขาใหม่)', 'type' => 'CLINIC', 'phone' => '081-234-5678']);
$log = one("SELECT * FROM audit_logs WHERE entity_type = 'ORGANIZATION' AND entity_id = ? AND action = 'UPDATE' ORDER BY created_at DESC LIMIT 1", [$orgA]);
ok($log && strpos($log['changes'], 'คลินิกผิวสวย (สาขาใหม่)') !== false && strpos($log['changes'], '"old"') !== false, 'Audit log เก็บค่าเก่า/ใหม่');
ok((int) val("SELECT COUNT(*) FROM audit_logs WHERE entity_type = 'VALUATION'") >= 1 && (int) val("SELECT COUNT(*) FROM audit_logs WHERE entity_type = 'COST_SHEET'") >= 2, 'การเปลี่ยนแปลงราคา/ต้นทุนมี audit log');

// ================================================================ อื่น ๆ
section('บัญชีผู้ใช้และการเข้าสู่ระบบ');
act_as(null);
eq(attempt_login('coord', 'Passw0rd!123'), null, 'เข้าสู่ระบบด้วยชื่อผู้ใช้ได้');
act_as(null);
eq(attempt_login('coord@test.local', 'Passw0rd!123'), null, 'เข้าสู่ระบบด้วยอีเมลได้');
act_as(null);
for ($i = 0; $i < 5; $i++) attempt_login('exec', 'wrong-password');
ok(strpos((string) attempt_login('exec', 'Passw0rd!123'), 'ผิดหลายครั้ง') !== false, 'ใส่รหัสผิดเกินกำหนด → ล็อกชั่วคราว (แม้รหัสถูก)');
as_user('admin');
throws(function () use ($U) { user_save($U['exec'], ['username' => 'exec', 'name' => 'x', 'roles' => ['GM'], 'active' => 1]); }, AppError::class, 'Admin มอบ role GM ไม่ได้');
$newId = user_save(null, ['username' => 'staff01', 'name' => 'พนักงานใหม่', 'roles' => ['SALES_EXECUTIVE'], 'active' => 1, 'password' => 'Temp12345abc']);
eq((int) db_get('users', $newId)['must_change_password'], 1, 'ผู้ใช้ใหม่ต้องเปลี่ยนรหัสผ่านตอนเข้าครั้งแรก');
user_set_active($newId, false);
act_as($newId);
ok(current_user() !== null, '(act_as ใน test โหลดผู้ใช้ได้)');
eq((int) db_get('users', $newId)['active'], 0, 'ปิดบัญชีแล้ว active = 0');
ok((int) db_get('users', $newId)['session_version'] > 1, 'ปิดบัญชี → session เดิมถูกเพิกถอน');
as_user('gm');
throws(function () use ($U) { user_set_active($U['gm'], false); }, AppError::class, 'ปิดบัญชีตัวเองไม่ได้');

section('ยกเลิก/ปิดดีล');
as_user('coord');
throws(function () use ($oppB) { acq_mark_lost($oppB, ''); }, AppError::class, 'ปิดดีลต้องมีเหตุผล');
acq_mark_lost($oppB, 'ผู้ขายขายให้คนอื่นแล้ว');
eq(db_get('device_opportunities', $oppB)['status'], 'LOST', 'ดีลซื้อ → LOST');
eq(db_get('devices', db_get('device_opportunities', $oppB)['device_id'])['commercial_status'], 'EXTERNAL', 'เครื่องกลับเป็นเครื่องภายนอก');
throws(function () use ($oppB) { acq_request_inspection($oppB, []); }, AppError::class, 'ดีลที่ปิดแล้วทำต่อไม่ได้');

// ---------------------------------------------------------------- summary
echo "\n" . str_repeat('─', 60) . "\n";
echo 'ผ่าน ' . $GLOBALS['T']['pass'] . ' / ไม่ผ่าน ' . $GLOBALS['T']['fail'] . "\n";
exit($GLOBALS['T']['fail'] ? 1 : 0);
