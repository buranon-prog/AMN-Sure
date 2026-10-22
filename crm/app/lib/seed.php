<?php
// ข้อมูลตั้งต้น: role, ข้อมูลหลัก (master data) และการตั้งค่า — ใช้ตอนติดตั้งและใน test

function install_schema(PDO $pdo): void
{
    $sql = file_get_contents(APP_DIR . '/sql/schema.sql');
    // ตัด comment บรรทัดเดียวออก แล้วแยกคำสั่งด้วย ; ท้ายบรรทัด
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (preg_split('/;\s*$/m', $sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt !== '') $pdo->exec($stmt);
    }
}

function default_settings(): array
{
    return [
        'company_name' => 'AMN Sure',
        'vat_rate' => '7',
        'quotation_valid_days' => '30',
        'deposit_default_pct' => '30',
        // BR-11: เงื่อนไขปิดการขาย (WON) ตั้งค่าได้ — ค่าเริ่มต้นตามสเปก
        'won_require_quotation_accepted' => '1',
        'won_require_deposit_received' => '1',
        'won_require_deposit_verified' => '0',
        'won_require_contract_signed' => '1',
        'max_upload_mb' => '10',
        'login_max_attempts' => '5',
        'login_lock_minutes' => '15',
        'session_idle_minutes' => '480',
    ];
}

function default_master_data(): array
{
    $md = [];
    $sort = 0;
    foreach (['FACEBOOK' => 'Facebook', 'LINE_OA' => 'LINE Official Account', 'WEBSITE' => 'เว็บไซต์', 'PHONE_IN' => 'โทรเข้า',
                 'REFERRAL' => 'ลูกค้าแนะนำ', 'EXHIBITION' => 'งานแสดงสินค้า', 'DEALER' => 'ตัวแทน/ดีลเลอร์',
                 'EXISTING_CUSTOMER' => 'ลูกค้าเดิม', 'OTHER' => 'อื่น ๆ'] as $code => $label) {
        $md[] = ['LEAD_SOURCE', $code, $label, null, $sort += 10];
    }
    $sort = 0;
    foreach (['LASER' => 'Laser', 'IPL' => 'IPL', 'HIFU' => 'HIFU', 'RF' => 'RF / Microneedling RF',
                 'BODY' => 'Body contouring', 'ULTRASOUND' => 'Ultrasound / Imaging', 'OTHER' => 'อื่น ๆ'] as $code => $label) {
        $md[] = ['DEVICE_CATEGORY', $code, $label, null, $sort += 10];
    }
    // รายการตรวจเครื่องมาตรฐาน [category, ชื่อ, บังคับ]
    $insp = [
        ['EXTERIOR', 'สภาพตัวเครื่องภายนอก (รอยแตก บุบ สนิม)', 1],
        ['EXTERIOR', 'หน้าจอ/แผงควบคุม', 1],
        ['FUNCTIONAL', 'เปิดเครื่องและบูตระบบได้ปกติ', 1],
        ['FUNCTIONAL', 'ทดสอบการทำงานของระบบ (system test)', 1],
        ['FUNCTIONAL', 'ทดสอบ output / พลังงาน', 0],
        ['HANDPIECE', 'สภาพหัวทำงาน (handpiece) และสายเชื่อมต่อ', 0],
        ['ACCESSORIES', 'อุปกรณ์เสริมครบตามรายการ', 0],
        ['ERRORS', 'ไม่มี error code ค้าง / ประวัติ error', 1],
        ['USAGE', 'จำนวน shot/pulse/ชั่วโมงใช้งาน', 0],
        ['CONSUMABLES', 'วัสดุสิ้นเปลือง (tip, filter, gel ฯลฯ)', 0],
        ['SAFETY', 'ระบบความปลอดภัย / ปุ่มหยุดฉุกเฉิน', 1],
        ['SAFETY', 'สายไฟ ปลั๊ก และการต่อลงดิน', 1],
        ['SOFTWARE', 'เวอร์ชันซอฟต์แวร์ / license', 0],
        ['PARTS', 'สภาพชิ้นส่วนสำคัญ (ระบบทำความเย็น ปั๊ม ฯลฯ)', 0],
    ];
    $sort = 0;
    foreach ($insp as $i => $it) {
        $md[] = ['INSPECTION_ITEM', 'I' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT), $it[1],
            json_encode(['category' => $it[0], 'mandatory' => $it[2]]), $sort += 10];
    }
    // เช็กลิสต์ภายในหลังปิดการขาย [section, ชื่อ, บังคับ]
    $cl = [
        ['COMMERCIAL', 'ใบเสนอราคาที่ลูกค้าตอบรับ', 1],
        ['COMMERCIAL', 'ได้รับเงินมัดจำ', 1],
        ['COMMERCIAL', 'สัญญาลงนามแล้ว', 1],
        ['COMMERCIAL', 'ยอดค้างชำระ / แผนการชำระ', 1],
        ['COMMERCIAL', 'ใบแจ้งหนี้ / ใบกำกับภาษี', 1],
        ['SALES', 'ยืนยันกับลูกค้าแล้ว', 1],
        ['SALES', 'ที่อยู่จัดส่ง', 1],
        ['SALES', 'วันส่งมอบ', 1],
        ['SALES', 'ผู้รับสินค้าหน้างาน', 1],
        ['MARKETING', 'สื่อ/เอกสารที่ต้องใช้', 0],
        ['MARKETING', 'การสื่อสารกับลูกค้า', 1],
        ['TECHNICAL', 'เตรียมเครื่อง', 1],
        ['TECHNICAL', 'อุปกรณ์เสริมครบ', 1],
        ['TECHNICAL', 'ซ่อม/ปรับสภาพเสร็จ', 1],
        ['TECHNICAL', 'ทำความสะอาด', 1],
        ['TECHNICAL', 'ซอฟต์แวร์', 1],
        ['TECHNICAL', 'เอกสาร/คู่มือ', 1],
    ];
    $sort = 0;
    foreach ($cl as $i => $it) {
        $md[] = ['CHECKLIST_ITEM', 'C' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT), $it[1],
            json_encode(['section' => $it[0], 'mandatory' => $it[2]]), $sort += 10];
    }
    $qc = ['Serial ตรงกับเอกสาร', 'สภาพภายนอก', 'ฟังก์ชันการทำงาน', 'Output / พลังงาน', 'Handpiece', 'อุปกรณ์เสริม',
        'ซอฟต์แวร์', 'ไม่มี error', 'ความปลอดภัย', 'ความสะอาด', 'การบรรจุหีบห่อ'];
    $sort = 0;
    foreach ($qc as $i => $label) {
        $md[] = ['QC_ITEM', 'Q' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT), $label, null, $sort += 10];
    }
    return $md;
}

/** ใส่ role, master data, settings (ข้ามรายการที่มีอยู่แล้ว) */
function seed_base_data(): void
{
    tx(function () {
        foreach (ROLE_CODES as $code) {
            if (!val('SELECT id FROM roles WHERE code = ?', [$code])) {
                db_insert('roles', ['code' => $code, 'name' => ROLE_NAMES[$code]]);
            }
        }
        foreach (default_master_data() as $m) {
            if (!val('SELECT id FROM master_data WHERE type = ? AND code = ?', [$m[0], $m[1]])) {
                db_insert('master_data', ['type' => $m[0], 'code' => $m[1], 'label' => $m[2], 'meta' => $m[3], 'sort' => $m[4], 'active' => 1]);
            }
        }
        foreach (default_settings() as $k => $v) {
            if (val('SELECT COUNT(*) FROM app_settings WHERE setting_key = ?', [$k]) == 0) setting_set($k, $v);
        }
    });
}

/** สร้างผู้ใช้พร้อม role (ใช้ตอนติดตั้งและใน test) */
function create_user_raw(string $username, ?string $email, string $name, string $password, array $roles): string
{
    return tx(function () use ($username, $email, $name, $password, $roles) {
        $id = db_insert('users', [
            'username' => mb_strtolower($username),
            'email' => $email ? mb_strtolower($email) : null,
            'name' => $name,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'active' => 1,
        ]);
        foreach ($roles as $code) {
            $rid = val('SELECT id FROM roles WHERE code = ?', [$code]);
            if (!$rid) throw new AppError('ไม่พบ role ' . $code);
            db_insert('user_roles', ['user_id' => $id, 'role_id' => $rid, 'created_at' => now()]);
        }
        audit_create('USER', $id, ['username' => $username, 'email' => $email, 'name' => $name, 'roles' => implode(',', $roles)]);
        return $id;
    });
}
