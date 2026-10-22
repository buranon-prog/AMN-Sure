<?php
// ข้อมูลหลัก (master data) และการตั้งค่าระบบ

const MASTER_TYPES = [
    'LEAD_SOURCE' => 'แหล่งที่มาของลีด',
    'DEVICE_CATEGORY' => 'ประเภทเครื่อง / เทคโนโลยี',
    'INSPECTION_ITEM' => 'รายการตรวจเครื่อง (template)',
    'CHECKLIST_ITEM' => 'เช็กลิสต์หลังปิดการขาย (template)',
    'QC_ITEM' => 'รายการ QC ก่อนส่งมอบ',
];

function master_save(?string $id, string $type, array $d): string
{
    require_cap('admin.master_data');
    v_in($type, array_keys(MASTER_TYPES), 'ประเภทข้อมูล');
    $label = s($d['label'] ?? null);
    if (!$label) throw new AppError('กรุณากรอกชื่อรายการ');
    $meta = null;
    if ($type === 'INSPECTION_ITEM') {
        $meta = json_encode(['category' => v_in($d['category'] ?? null, INSP_CATEGORIES, 'หมวด'), 'mandatory' => !empty($d['mandatory']) && $d['mandatory'] !== '0' ? 1 : 0]);
    } elseif ($type === 'CHECKLIST_ITEM') {
        $meta = json_encode(['section' => v_in($d['section'] ?? null, CHECKLIST_SECTIONS, 'หมวด'), 'mandatory' => !empty($d['mandatory']) && $d['mandatory'] !== '0' ? 1 : 0]);
    }
    $data = [
        'label' => v_maxlen($label, 255, 'ชื่อรายการ'),
        'meta' => $meta,
        'sort' => v_int($d['sort'] ?? null, 'ลำดับ', false, 0, 100000) ?? 0,
        'active' => !isset($d['active']) || ($d['active'] !== '0' && $d['active'] !== '') ? 1 : 0,
    ];
    return tx(function () use ($id, $type, $data, $d) {
        if ($id) {
            $row = db_get('master_data', $id, 'รายการ');
            if ($row['type'] !== $type) throw new AppError('ประเภทข้อมูลไม่ตรงกัน');
            update_audited('master_data', 'MASTER_DATA', $id, $data);
            return $id;
        }
        $code = strtoupper((string) s($d['code'] ?? null));
        if ($code === '') $code = strtoupper(substr($type, 0, 1)) . substr(str_replace('-', '', uuid()), 0, 8);
        if (!preg_match('/^[A-Z0-9_]{1,80}$/', $code)) throw new AppError('รหัสใช้ได้เฉพาะ A-Z 0-9 และ _');
        if (val('SELECT id FROM master_data WHERE type = ? AND code = ?', [$type, $code])) throw new AppError('รหัสนี้มีอยู่แล้ว');
        return insert_audited('master_data', 'MASTER_DATA', $data + ['type' => $type, 'code' => $code]);
    });
}

// การตั้งค่าที่แก้ได้จากหน้าเว็บ: key => [label, type, hint]
const SETTINGS_FORM = [
    'company_name' => ['ชื่อบริษัท', 'text', null],
    'vat_rate' => ['อัตรา VAT (%)', 'number', 'ใช้เป็นค่าเริ่มต้นในใบเสนอราคา'],
    'quotation_valid_days' => ['ยืนราคาใบเสนอราคา (วัน)', 'number', null],
    'deposit_default_pct' => ['มัดจำเริ่มต้น (% ของยอดใบเสนอราคา)', 'number', 'ระบบสร้างรายการรอรับมัดจำให้อัตโนมัติเมื่อลูกค้าตอบรับ (0 = ไม่สร้าง)'],
    'won_require_quotation_accepted' => ['WON ต้องมี: ลูกค้าตอบรับใบเสนอราคา', 'bool', null],
    'won_require_deposit_received' => ['WON ต้องมี: ได้รับมัดจำ', 'bool', null],
    'won_require_deposit_verified' => ['WON ต้องมี: GM ยืนยันยอดมัดจำ', 'bool', 'เปิดถ้าต้องการให้ GM ตรวจยอดโอนก่อนปิดการขาย'],
    'won_require_contract_signed' => ['WON ต้องมี: ลงนามสัญญา', 'bool', null],
    'max_upload_mb' => ['ขนาดไฟล์แนบสูงสุด (MB)', 'number', 'ต้องไม่เกินค่า upload_max_filesize ของ PHP ในโฮสต์'],
    'login_max_attempts' => ['ใส่รหัสผิดได้กี่ครั้งก่อนล็อก', 'number', null],
    'login_lock_minutes' => ['ล็อกนานกี่นาที', 'number', null],
    'session_idle_minutes' => ['ออกจากระบบอัตโนมัติเมื่อไม่ได้ใช้งาน (นาที)', 'number', null],
];

function settings_save(array $d): void
{
    require_cap('admin.settings');
    tx(function () use ($d) {
        foreach (SETTINGS_FORM as $key => $def) {
            if ($def[1] === 'bool') {
                $v = !empty($d[$key]) && $d[$key] !== '0' ? '1' : '0';
            } elseif ($def[1] === 'number') {
                $v = v_decimal($d[$key] ?? null, $def[0], true);
                if ((float) $v > 100000) throw new AppError($def[0] . ' มากเกินไป');
            } else {
                $v = s($d[$key] ?? null);
                if ($v === null) throw new AppError('กรุณากรอก ' . $def[0]);
            }
            $old = setting($key);
            if ((string) $old !== (string) $v) {
                setting_set($key, $v);
                audit_log('SETTING', null, 'UPDATE', [['field' => $key, 'old' => $old, 'new' => $v]]);
            }
        }
        foreach (['login_max_attempts', 'session_idle_minutes'] as $k) {
            if ((float) setting($k) < 1) throw new AppError(SETTINGS_FORM[$k][0] . ' ต้องมากกว่า 0');
        }
    });
}
