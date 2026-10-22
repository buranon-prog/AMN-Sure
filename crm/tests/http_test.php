<?php
// End-to-end test ผ่าน HTTP จริง: ล็อกอินแต่ละ role แล้วกรอกฟอร์มตาม Scenario A + B จนจบ และเปิดทุกหน้าเพื่อหา error
// วิธีรัน: ติดตั้งระบบก่อน (install.php) แล้ว  php tests/http_test.php http://127.0.0.1:8080 <gm-username> <gm-password>

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8080', '/');
$gmUser = $argv[2] ?? 'gm';
$gmPass = $argv[3] ?? 'StrongPass123';
$tmp = sys_get_temp_dir() . '/amncrm-http-' . getmypid();
@mkdir($tmp);

$pass = 0;
$fail = 0;
function ok($c, $m) { global $pass, $fail; if ($c) { $pass++; return; } $fail++; echo "  ✗ $m\n"; }
function section($n) { echo "\n▶ $n\n"; }

class Client
{
    public $base; public $jar; public $csrf = ''; public $name;
    public $last = ['status' => 0, 'body' => '', 'location' => null];
    function __construct($base, $jar, $name) { $this->base = $base; $this->jar = $jar; $this->name = $name; @unlink($jar); }
    function req($method, $path, $data = null, $multipart = false)
    {
        $ch = curl_init($this->base . '/' . ltrim($path, '/'));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart ? $data : http_build_query($data));
        }
        $raw = curl_exec($ch);
        $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $headers = substr($raw, 0, $hs);
        $body = substr($raw, $hs);
        $loc = preg_match('/^Location:\s*(.+)$/mi', $headers, $m) ? trim($m[1]) : null;
        if (preg_match('/name="_csrf" value="([a-f0-9]+)"/', $body, $m)) $this->csrf = $m[1];
        $this->last = ['status' => $status, 'body' => $body, 'location' => $loc];
        return $this->last;
    }
    function get($route, $params = []) { return $this->req('GET', 'index.php?' . http_build_query(['r' => $route] + $params)); }
    function post($route, $data, $files = [])
    {
        if (!$this->csrf) $this->get('dashboard');
        $data['_csrf'] = $this->csrf;
        if ($files) {
            $flat = [];
            foreach (self::flatten($data) as $k => $v) $flat[$k] = $v;
            foreach ($files as $k => $f) $flat[$k] = $f;
            return $this->req('POST', 'index.php?r=' . $route, $flat, true);
        }
        return $this->req('POST', 'index.php?r=' . $route, $data);
    }
    static function flatten($a, $prefix = '')
    {
        $out = [];
        foreach ($a as $k => $v) {
            $key = $prefix === '' ? $k : $prefix . '[' . $k . ']';
            if (is_array($v)) $out += self::flatten($v, $key); else $out[$key] = $v;
        }
        return $out;
    }
    function follow() { $l = $this->last['location']; if (!$l) return $this->last; return $this->req('GET', (strpos($l, '?') === 0 ? 'index.php' : '') . $l); }
    function flashError() { return preg_match('/class="flash error">([^<]*)/', $this->last['body'], $m) ? $m[1] : null; }
    function login($user, $pw)
    {
        $this->get('auth.login');
        $r = $this->post('auth.login', ['login' => $user, 'password' => $pw]);
        if (strpos((string) $r['location'], 'dashboard') === false) return false;
        $this->follow();
        if (strpos((string) $this->last['location'], 'auth.password') !== false) {
            $this->follow();
            $new = $pw . 'X9';
            $this->post('auth.password', ['current_password' => $pw, 'new_password' => $new, 'password_confirm' => $new]);
            $this->follow();
        }
        return true;
    }
}

function idFrom($loc) { return preg_match('/[?&]id=([0-9a-f-]{36})/', (string) $loc, $m) ? $m[1] : null; }
function expectRedirectOk(Client $c, $msg)
{
    $loc = $c->last['location'];
    ok($c->last['status'] === 303 && $loc, $msg . ' (HTTP ' . $c->last['status'] . ')');
    $c->follow();
    $err = $c->flashError();
    ok($err === null, $msg . ($err ? ' — ' . $err : ''));
    return idFrom($loc);
}
function pageOk(Client $c, $route, $params = [], $allow403 = false)
{
    $r = $c->get($route, $params);
    $good = $r['status'] === 200 || ($allow403 && $r['status'] === 403);
    $bad = strpos($r['body'], 'เกิดข้อผิดพลาด') !== false || stripos($r['body'], 'Warning:') !== false || stripos($r['body'], 'Fatal error') !== false || stripos($r['body'], 'Notice:') !== false;
    ok($good && !$bad, "[{$c->name}] GET $route " . json_encode($params, JSON_UNESCAPED_UNICODE) . " → {$r['status']}" . ($bad ? ' มี error ในหน้า: ' . substr(strip_tags($r['body']), 0, 300) : ''));
    return $r['body'];
}

$pdf = $tmp . '/slip.pdf';
file_put_contents($pdf, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");

// ------------------------------------------------------------------ users
section('GM สร้างบัญชีพนักงานแต่ละ role');
$gm = new Client($base, "$tmp/gm.txt", 'gm');
ok($gm->login($gmUser, $gmPass), 'GM เข้าสู่ระบบได้');
$roles = ['coord' => 'SALES_COORDINATOR', 'exec' => 'SALES_EXECUTIVE', 'sdir' => 'SALES_DIRECTOR', 'eng' => 'SERVICE_ENGINEER',
    'svdir' => 'SERVICE_DIRECTOR', 'mkt' => 'MARKETING', 'mgmt' => 'MANAGEMENT', 'admin' => 'ADMIN'];
$C = ['gm' => $gm];
$suffix = substr(md5((string) microtime(true)), 0, 5);
foreach ($roles as $key => $role) {
    $uname = $key . $suffix;
    $gm->get('admin.user_new');
    $gm->post('admin.user_save', ['name' => 'ทดสอบ ' . $key . ' ' . $suffix, 'username' => $uname, 'password' => 'Temp12345ab', 'roles' => [$role], 'active' => '1']);
    expectRedirectOk($gm, "สร้างผู้ใช้ $key");
    $C[$key] = new Client($base, "$tmp/$key.txt", $key);
    ok($C[$key]->login($uname, 'Temp12345ab'), "$key เข้าสู่ระบบ + เปลี่ยนรหัสครั้งแรก");
}

// ------------------------------------------------------------------ Scenario A via forms
section('Scenario A ผ่านหน้าเว็บ');
$coord = $C['coord'];
$coord->get('leads.new', ['type' => 'SELLER']);
$serial = 'HTTP-' . strtoupper($suffix);
$coord->post('leads.create', [
    'type' => 'SELLER', 'new_org_name' => 'คลินิกทดสอบ HTTP ' . $suffix, 'new_org_type' => 'CLINIC', 'new_org_phone' => '08' . rand(10000000, 99999999),
    'new_contact_name' => 'คุณทดสอบ', 'new_contact_phone' => '0899999999',
    'brand' => 'Lumenis', 'model' => 'M22', 'category' => 'IPL', 'serial_number' => $serial, 'manufacture_year' => '2019',
    'expected_price' => '850,000', 'location' => 'สีลม กรุงเทพฯ ' . $suffix, 'reason_for_sale' => 'อัปเกรดเครื่อง',
    'source' => 'FACEBOOK', 'owner_id' => '', 'next_action' => 'นัดตรวจเครื่อง', 'next_follow_up_date' => date('Y-m-d', strtotime('+1 day')), 'summary' => 'ทดสอบ',
]);
$oppId = expectRedirectOk($coord, 'สร้างลีดผู้ขาย');
ok(strpos($coord->last['body'], 'ขอตรวจเครื่อง') !== false, 'หน้าดีลซื้อแสดงขั้นตอนถัดไป: ขอตรวจเครื่อง');

$coord->post('acq.request_inspection', ['id' => $oppId, 'brand' => 'Lumenis', 'model' => 'M22', 'category' => 'IPL', 'serial_number' => $serial, 'manufacture_year' => '2019',
    'current_location' => 'สีลม กรุงเทพฯ ' . $suffix, 'usage_value' => '50000', 'usage_unit' => 'SHOT', 'asking_price' => '900,000', 'preferred_date' => date('Y-m-d', strtotime('+1 day'))]);
expectRedirectOk($coord, 'ขอตรวจเครื่อง');

$eng = $C['eng'];
$body = pageOk($eng, 'acq.view', ['id' => $oppId]);
preg_match('/name="id" value="([0-9a-f-]{36})"><button type="submit" class="btn primary">เริ่มตรวจเครื่อง/', $body, $m);
$inspId = $m[1] ?? null;
ok($inspId !== null, 'วิศวกรเห็นปุ่มเริ่มตรวจเครื่อง');
$eng->post('acq.inspection_start', ['id' => $inspId]);
expectRedirectOk($eng, 'เริ่มตรวจเครื่อง');
preg_match_all('/name="items\[([0-9a-f-]{36})\]\[result\]" value="PASS"/', $eng->last['body'], $mm);
$items = [];
foreach (array_unique($mm[1]) as $iid) $items[$iid] = ['result' => 'PASS', 'note' => ''];
ok(count($items) >= 10, 'ฟอร์มตรวจมีรายการตรวจ ' . count($items) . ' รายการ');
$eng->post('acq.inspection_save', ['id' => $inspId, 'items' => $items, 'overall_result' => 'PASS', 'overall_condition' => 'GOOD', 'technical_risk' => 'LOW', 'issues' => 'ไม่มี', 'action' => 'complete']);
expectRedirectOk($eng, 'ปิดการตรวจเครื่อง');
$docTmp = $tmp . '/photo.png';
file_put_contents($docTmp, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
$eng->post('docs.upload', ['parent_type' => 'INSPECTION', 'parent_id' => $inspId, 'category' => 'PHOTO', 'title' => 'รูปเครื่อง'], ['file' => new CURLFile($docTmp, 'image/png', 'device.png')]);
expectRedirectOk($eng, 'แนบรูปผลตรวจ');

$sv = $C['svdir'];
$body = pageOk($sv, 'acq.cost_sheet', ['id' => $oppId]);
$sv->post('acq.cost_sheet', ['id' => $oppId, 'inspection_id' => $inspId, 'items' => [
    ['category' => 'ACQUISITION_ASSUMPTION', 'description' => 'ราคาซื้อ', 'amount' => '820,000'],
    ['category' => 'REFURBISHMENT', 'description' => 'ทำความสะอาด', 'amount' => '10,000'],
], 'action' => 'submit']);
expectRedirectOk($sv, 'ส่ง Cost Sheet');

$sd = $C['sdir'];
pageOk($sd, 'acq.valuation', ['id' => $oppId]);
$sd->post('acq.valuation', ['id' => $oppId, 'fair_market_value' => '1,100,000', 'recommended_acq_price' => '800,000', 'max_acq_price' => '840,000',
    'target_selling_price' => '1,150,000', 'min_selling_price' => '1,050,000', 'demand' => 'MEDIUM']);
expectRedirectOk($sd, 'บันทึก Valuation');

$ex = $C['exec'];
$body = pageOk($ex, 'acq.view', ['id' => $oppId]);
ok(strpos($body, '800,000.00') !== false && strpos($body, '840,000.00') === false, 'Sales Executive เห็นราคาแนะนำแต่ไม่เห็นราคาสูงสุด');
$ex->post('acq.negotiation', ['id' => $oppId, 'party' => 'AMN_OFFER', 'amount' => '780,000']);
expectRedirectOk($ex, 'บันทึกข้อเสนอ');
$ex->post('acq.negotiation', ['id' => $oppId, 'party' => 'AGREED', 'amount' => '815,000']);
expectRedirectOk($ex, 'บันทึกราคาที่ตกลง');
$ex->post('acq.submit_approval', ['id' => $oppId, 'note' => 'ขออนุมัติ']);
expectRedirectOk($ex, 'ส่งขออนุมัติ GM');

$body = pageOk($gm, 'approvals');
preg_match('/r=approvals\.view&amp;id=([0-9a-f-]{36})/', $body, $m);
$apprId = $m[1] ?? null;
ok($apprId !== null, 'GM เห็นคำขอในกล่องอนุมัติ');
$body = pageOk($gm, 'approvals.view', ['id' => $apprId]);
ok(strpos($body, 'Cost Sheet') !== false && strpos($body, 'ประวัติการเจรจา') !== false, 'ชุดข้อมูลอนุมัติครบ');
$C['coord']->post('approvals.decide', ['id' => $apprId, 'decision' => 'APPROVED']);
ok($C['coord']->last['status'] === 403, 'Scenario E: Sales Coordinator อนุมัติผ่านหน้าเว็บไม่ได้ (403)');
$gm->post('approvals.decide', ['id' => $apprId, 'decision' => 'APPROVED', 'approved_amount' => '815,000', 'return_to' => 'NEGOTIATION']);
expectRedirectOk($gm, 'GM อนุมัติ');

pageOk($coord, 'acq.purchase', ['id' => $oppId]);
$coord->post('acq.purchase', ['id' => $oppId, 'purchase_price' => '815,000', 'purchase_date' => date('Y-m-d'), 'payment_status' => 'PAID', 'takes_stock' => '1', 'storage_location' => 'คลัง A', 'list_price' => '1,190,000']);
expectRedirectOk($coord, 'บันทึกการซื้อ + เข้าสต็อก');
ok(strpos($coord->last['body'], 'ซื้อแล้ว') !== false, 'สถานะดีลเป็น "ซื้อแล้ว"');

// ------------------------------------------------------------------ Scenario B via forms
section('Scenario B ผ่านหน้าเว็บ');
$ex->get('leads.new', ['type' => 'BUYER']);
$ex->post('leads.create', ['type' => 'BUYER', 'new_org_name' => 'โรงพยาบาล HTTP ' . $suffix, 'new_org_type' => 'HOSPITAL', 'new_org_email' => "buyer$suffix@example.com",
    'interested_device' => 'IPL Lumenis', 'budget_max' => '1,300,000', 'source' => 'WEBSITE', 'next_action' => 'ส่งสเปก', 'next_follow_up_date' => date('Y-m-d', strtotime('+2 day'))]);
$soId = expectRedirectOk($ex, 'สร้างลีดผู้ซื้อ');
$sd->post('sales.requirement', ['id' => $soId, 'req_brand' => 'Lumenis', 'req_technology' => 'IPL', 'budget_max' => '1,300,000', 'expected_purchase_date' => date('Y-m-d', strtotime('+20 day')), 'installation_required' => '1']);
expectRedirectOk($sd, 'บันทึกความต้องการ');
$body = pageOk($sd, 'sales.match', ['id' => $soId]);
ok(preg_match('/name="source" value="INVENTORY"><input type="hidden" name="ref_id" value="([0-9a-f-]{36})"/', $body, $m) === 1, 'พบเครื่องในสต็อกที่ตรงความต้องการ');
$sd->post('sales.match_add', ['id' => $soId, 'source' => 'INVENTORY', 'ref_id' => $m[1] ?? '']);
expectRedirectOk($sd, 'จับคู่เครื่อง');
preg_match('/name="match_id" value="([0-9a-f-]{36})"/', $sd->last['body'], $m);
$sd->post('sales.match_status', ['match_id' => $m[1] ?? '', 'status' => 'SELECTED']);
ok($sd->last['status'] === 303, 'เลือกเครื่อง');
$coord->post('sales.quotation', ['id' => $soId]);
$qId = expectRedirectOk($coord, 'ออกใบเสนอราคา');
preg_match('/name="version_id" value="([0-9a-f-]{36})"/', $coord->last['body'], $m);
$vId = $m[1] ?? null;
preg_match('/name="lines\[0\]\[device_id\]"><option value="">[^<]*<\/option><option value="([0-9a-f-]{36})"/', $coord->last['body'], $m);
$devId = $m[1] ?? null;
ok($vId && $devId, 'ฟอร์มใบเสนอราคามีรายการเครื่อง');
$coord->post('quotations.save', ['version_id' => $vId, 'lines' => [['device_id' => $devId, 'description' => 'Lumenis M22 IPL', 'qty' => '1', 'unit_price' => '1,150,000'],
    ['device_id' => '', 'description' => 'ค่าติดตั้งและอบรม', 'qty' => '1', 'unit_price' => '20,000']],
    'vat_rate' => '7', 'valid_until' => date('Y-m-d', strtotime('+30 day')), 'payment_terms' => 'มัดจำ 30%', 'action' => 'request']);
expectRedirectOk($coord, 'บันทึกและขออนุมัติใบเสนอราคา');
pageOk($coord, 'quotations.print', ['id' => $qId]);
$sd->post('quotations.approve', ['version_id' => $vId]);
expectRedirectOk($sd, 'Sales Director อนุมัติใบเสนอราคา');
$coord->post('quotations.send', ['version_id' => $vId]);
expectRedirectOk($coord, 'ส่งใบเสนอราคา');
$coord->post('quotations.respond', ['version_id' => $vId, 'response' => 'ACCEPTED', 'note' => 'ok']);
expectRedirectOk($coord, 'ลูกค้าตอบรับ');
$body = pageOk($coord, 'sales.view', ['id' => $soId]);
preg_match('/name="deposit_id" value="([0-9a-f-]{36})"/', $body, $m);
$depId = $m[1] ?? null;
ok($depId !== null, 'มีรายการรอรับมัดจำ');
preg_match('/name="received_amount"[^>]*value="([0-9.,]+)"/', $body, $m);
$coord->post('sales.deposit_receive', ['deposit_id' => $depId, 'received_amount' => $m[1] ?? '0', 'received_date' => date('Y-m-d')], ['evidence' => new CURLFile($pdf, 'application/pdf', 'slip.pdf')]);
expectRedirectOk($coord, 'รับมัดจำ + แนบสลิป');
$coord->post('sales.contract', ['id' => $soId, 'contract_no' => 'CT-' . $suffix]);
expectRedirectOk($coord, 'ร่างสัญญา');
preg_match('/name="contract_id" value="([0-9a-f-]{36})"/', $coord->last['body'], $m);
$coord->post('sales.contract_sign', ['contract_id' => $m[1] ?? '', 'signed_date' => date('Y-m-d')], ['signed_file' => new CURLFile($pdf, 'application/pdf', 'contract.pdf')]);
expectRedirectOk($coord, 'ลงนามสัญญา');
ok(strpos($coord->last['body'], 'ปิดการขายได้') !== false, 'WON อัตโนมัติ');
preg_match('/r=trx\.view&amp;id=([0-9a-f-]{36})/', $coord->last['body'], $m);
$trxId = $m[1] ?? null;
ok($trxId !== null, 'สร้างธุรกรรมขาย');

section('Fulfillment ผ่านหน้าเว็บ');
foreach (['coord', 'mkt', 'eng'] as $k) {
    $body = pageOk($C[$k], 'trx.view', ['id' => $trxId]);
    preg_match_all('/name="item_id" value="([0-9a-f-]{36})"><input type="hidden" name="done" value="1"/', $body, $mm);
    foreach ($mm[1] as $iid) { $C[$k]->post('trx.checklist', ['item_id' => $iid, 'done' => '1']); ok($C[$k]->last['status'] === 303, "$k ติ๊กเช็กลิสต์"); }
}
$body = pageOk($eng, 'trx.view', ['id' => $trxId]);
preg_match_all('/name="checks\[([A-Z0-9_]+)\]\[result\]" value="PASS"/', $body, $mm);
$checks = [];
foreach (array_unique($mm[1]) as $code) $checks[$code] = ['result' => 'PASS'];
preg_match('/name="device_id" value="([0-9a-f-]{36})"/', $body, $m);
$trxDev = $m[1] ?? '';
$eng->post('trx.qc', ['id' => $trxId, 'device_id' => $trxDev, 'checks' => $checks]);
expectRedirectOk($eng, 'QC ผ่าน');
$eng->post('trx.ready', ['id' => $trxId]);
expectRedirectOk($eng, 'พร้อมส่งมอบ');
$eng->post('trx.delivery', ['id' => $trxId, 'device_id' => $trxDev, 'serial_confirmed' => 'WRONG', 'delivered_date' => date('Y-m-d'), 'receiving_person' => 'x']);
$eng->follow();
ok($eng->flashError() !== null && strpos($eng->flashError(), 'BR-13') !== false, 'BR-13: serial ผิดถูกปฏิเสธผ่านหน้าเว็บ');
$eng->post('trx.delivery', ['id' => $trxId, 'device_id' => $trxDev, 'serial_confirmed' => strtolower($serial), 'delivered_date' => date('Y-m-d'), 'receiving_person' => 'คุณรับของ'], ['evidence' => new CURLFile($docTmp, 'image/png', 'receipt.png')]);
expectRedirectOk($eng, 'บันทึกส่งมอบ');
$eng->post('trx.installation', ['id' => $trxId, 'device_id' => $trxDev, 'installed_date' => date('Y-m-d'), 'result' => 'SUCCESS', 'customer_accepted' => '1', 'accepted_by_name' => 'พญ.ทดสอบ', 'training_done' => '1']);
expectRedirectOk($eng, 'บันทึกติดตั้ง');
ok(strpos($eng->last['body'], 'ธุรกรรมเสร็จสมบูรณ์') !== false, 'ธุรกรรมเสร็จสมบูรณ์');

// ------------------------------------------------------------------ crawl every page as every role
section('เปิดทุกหน้าในทุก role (ต้องไม่มี error)');
$dev = null;
preg_match('/r=devices\.view&amp;id=([0-9a-f-]{36})/', $gm->get('acq.view', ['id' => $oppId])['body'], $m);
$dev = $m[1] ?? null;
preg_match('/r=customers\.view&amp;id=([0-9a-f-]{36})/', $gm->last['body'], $m);
$org = $m[1] ?? null;
$pages = [
    ['dashboard'], ['dashboard', ['view' => 'team']], ['tasks'], ['tasks', ['scope' => 'all']], ['search', ['q' => 'Lumenis']], ['search', ['q' => $serial]],
    ['customers'], ['customers.view', ['id' => $org]], ['customers.new'], ['customers.edit', ['id' => $org]], ['contacts'], ['contacts.new'],
    ['leads'], ['leads', ['overdue' => 1]], ['leads.new', ['type' => 'SELLER']], ['leads.new', ['type' => 'BUYER', 'org' => $org]],
    ['devices'], ['devices.view', ['id' => $dev]], ['devices.new'], ['devices.edit', ['id' => $dev]], ['inventory'], ['inventory', ['status' => 'ALL']],
    ['acq'], ['acq', ['status' => 'all']], ['acq.view', ['id' => $oppId]], ['acq.inspection', ['id' => $inspId]],
    ['approvals'], ['approvals.view', ['id' => $apprId]],
    ['sales'], ['sales.view', ['id' => $soId]], ['sales.match', ['id' => $soId]], ['quotations'], ['quotations.view', ['id' => $qId]], ['quotations.view', ['id' => $qId, 'v' => 1]], ['quotations.print', ['id' => $qId]],
    ['trx'], ['trx', ['status' => 'all']], ['trx.view', ['id' => $trxId]], ['jobs'], ['jobs', ['status' => 'all']], ['cases'],
    ['admin.users'], ['admin.user_new'], ['admin.master'], ['admin.master', ['type' => 'INSPECTION_ITEM']], ['admin.master', ['type' => 'CHECKLIST_ITEM']], ['admin.settings'],
    ['audit'], ['audit', ['entity' => 'DEVICE']], ['auth.password'],
];
foreach ($C as $k => $client) {
    foreach ($pages as $p) pageOk($client, $p[0], $p[1] ?? [], true);
}
$jobs = $gm->get('jobs', ['status' => 'all'])['body'];
if (preg_match('/r=jobs\.view&amp;id=([0-9a-f-]{36})/', $jobs, $m)) pageOk($gm, 'jobs.view', ['id' => $m[1]]);
pageOk($gm, 'jobs.new', ['device' => $dev]);
pageOk($gm, 'cases.new', ['device' => $dev]);
$gm->post('cases.save', ['device_id' => $dev, 'issue' => 'เครื่องแจ้ง error E12', 'under_warranty' => '1']);
$caseId = expectRedirectOk($gm, 'เปิดเคสบริการ');
pageOk($C['eng'], 'cases.view', ['id' => $caseId]);

section('ความปลอดภัย');
$anon = new Client($base, "$tmp/anon.txt", 'anon');
$r = $anon->get('dashboard');
ok($r['status'] === 303 && strpos((string) $r['location'], 'auth.login') !== false, 'ไม่ล็อกอิน → ถูกส่งไปหน้าเข้าสู่ระบบ');
$r = $anon->get('customers.view', ['id' => $org]);
ok($r['status'] === 303, 'ไม่ล็อกอิน → เปิดข้อมูลลูกค้าไม่ได้');
$r = $C['mkt']->get('approvals.view', ['id' => $apprId]);
ok($r['status'] === 403, 'Marketing เปิดชุดข้อมูลอนุมัติไม่ได้ (403)');
$r = $C['mkt']->get('acq.cost_sheet', ['id' => $oppId]);
ok($r['status'] === 403, 'Marketing เปิด Cost Sheet ไม่ได้ (403)');
$body = $C['coord']->get('devices.view', ['id' => $dev])['body'];
ok(strpos($body, 'Cost Sheet 🔒') === false && strpos($body, 'ต้นทุนบัญชี') === false, 'Sales Coordinator ไม่เห็น cost sheet / ต้นทุนบัญชีในหน้าเครื่อง');
$body = $C['mkt']->get('acq.view', ['id' => $oppId])['body'];
$leaks = array_filter(['830,000', '1,150,000', '1,100,000', '840,000', '815,000', '780,000', '820,000'], function ($n) use ($body) { return strpos($body, $n) !== false; });
ok(!$leaks, 'Marketing ไม่เห็นตัวเลขต้นทุน/valuation/ราคาเจรจาในหน้าดีลซื้อ (รวม timeline)' . ($leaks ? ' — หลุด: ' . implode(', ', $leaks) : ''));
$body = $C['eng']->get('acq.view', ['id' => $oppId])['body'];
$leaks = array_filter(['830,000', '1,150,000', '840,000', '815,000', '780,000'], function ($n) use ($body) { return strpos($body, $n) !== false; });
ok(!$leaks, 'วิศวกรไม่เห็นตัวเลขต้นทุน/ราคาเจรจา' . ($leaks ? ' — หลุด: ' . implode(', ', $leaks) : ''));
$csrfSave = $C['coord']->csrf;
$C['coord']->csrf = 'bad';
$r = $C['coord']->post('customers.create', ['name' => 'ไม่ควรถูกสร้าง']);
ok($r['status'] === 403, 'POST ที่ไม่มี CSRF token ที่ถูกต้องถูกปฏิเสธ (403)');
$C['coord']->csrf = $csrfSave;
$docBody = $gm->get('acq.inspection', ['id' => $inspId])['body'];
preg_match('/r=docs\.download&amp;id=([0-9a-f-]{36})/', $docBody, $m);
$docId = $m[1] ?? null;
ok($docId !== null, 'มีลิงก์ดาวน์โหลดเอกสาร');
$r = $gm->get('docs.download', ['id' => $docId]);
ok($r['status'] === 200 && strlen($r['body']) > 10, 'GM ดาวน์โหลดไฟล์แนบได้');
$r = $C['admin']->get('docs.download', ['id' => $docId]);
ok($r['status'] === 403, 'Admin (ไม่มีสิทธิ์ดูผลตรวจ) ดาวน์โหลดไฟล์แนบของการตรวจไม่ได้ (403)');
$r = $anon->req('GET', 'app/config.php');
ok($r['status'] !== 200 || $r['body'] === '', 'config.php ไม่แสดงเนื้อหา (' . $r['status'] . ')');
$r = $gm->post('admin.user_active', ['id' => '00000000-0000-4000-8000-000000000000', 'active' => '0']);
ok($r['status'] === 404, 'id ที่ไม่มีอยู่จริง → 404');

// XSS: ชื่อที่มีสคริปต์ต้องถูก escape ทุกหน้า
$gm->post('customers.create', ['name' => '<script>alert(1)</script>คลินิก XSS ' . $suffix, 'type' => 'CLINIC', 'phone' => '02-000-' . rand(1000, 9999), 'confirm_duplicate' => '1']);
$xssOrg = expectRedirectOk($gm, 'สร้างลูกค้าชื่อแปลก');
$body = $gm->get('customers.view', ['id' => $xssOrg])['body'];
ok(strpos($body, '<script>alert(1)</script>') === false && strpos($body, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false, 'XSS: ชื่อลูกค้าถูก escape ในหน้า Customer 360');
$body = $gm->get('search', ['q' => 'alert'])['body'];
ok(strpos($body, '<script>alert(1)</script>') === false, 'XSS: ชื่อลูกค้าถูก escape ในหน้าค้นหา');
$body = $gm->get('customers', ['q' => '"><img src=x onerror=alert(1)>'])['body'];
ok(strpos($body, '<img src=x onerror') === false, 'XSS: คำค้นที่มี HTML ถูก escape');

// ปิดบัญชีแล้วถูกออกจากระบบทันที
$body = $gm->get('admin.users')['body'];
preg_match('/r=admin\.user&amp;id=([0-9a-f-]{36})">(<b>)?ทดสอบ mkt ' . $suffix . '/', $body, $m);
$gm->post('admin.user_active', ['id' => $m[1] ?? '', 'active' => '0']);
expectRedirectOk($gm, 'GM ปิดบัญชี Marketing');
$r = $C['mkt']->get('dashboard');
ok($r['status'] === 303 && strpos((string) $r['location'], 'auth.login') !== false, 'บัญชีที่ถูกปิดถูกออกจากระบบทันที (ไม่ต้องรอ 30 วัน)');

echo "\n" . str_repeat('─', 60) . "\nผ่าน $pass / ไม่ผ่าน $fail\n";
exit($fail ? 1 : 0);
