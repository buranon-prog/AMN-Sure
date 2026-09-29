<?php
// ป้ายภาษาไทยของค่า code ในระบบ และสีของ badge

const LABELS = [
    'status' => [
        // device opportunity (ฝั่งซื้อ)
        'NEW_SELLER_LEAD' => 'ลีดผู้ขายใหม่',
        'WAITING_INSPECTION' => 'รอตรวจเครื่อง',
        'INSPECTION_IN_PROGRESS' => 'กำลังตรวจเครื่อง',
        'INSPECTED' => 'ตรวจเครื่องแล้ว',
        'COSTED' => 'ทำ Cost Sheet แล้ว',
        'VALUED' => 'ประเมินมูลค่าแล้ว',
        'NEGOTIATING' => 'กำลังเจรจา',
        'PENDING_APPROVAL' => 'รอ GM อนุมัติ',
        'APPROVED' => 'อนุมัติแล้ว',
        'PURCHASED' => 'ซื้อแล้ว',
        'REJECTED' => 'ไม่อนุมัติ',
        'LOST' => 'ไม่สำเร็จ/ยกเลิก',
        // sales opportunity (ฝั่งขาย)
        'NEW_BUYER_LEAD' => 'ลีดผู้ซื้อใหม่',
        'REQUIREMENT_DEFINED' => 'ได้ความต้องการแล้ว',
        'MATCHING' => 'กำลังหาเครื่อง',
        'QUOTING' => 'เสนอราคา',
        'WON' => 'ปิดการขายได้ (WON)',
        // lead
        'ACTIVE' => 'กำลังดำเนินการ',
        'CLOSED' => 'ปิดแล้ว',
        // quotation version
        'DRAFT' => 'ร่าง',
        'SENT' => 'ส่งลูกค้าแล้ว',
        'ACCEPTED' => 'ลูกค้าตอบรับ',
        'EXPIRED' => 'หมดอายุ',
        'SUPERSEDED' => 'ถูกแทนที่ด้วยฉบับใหม่',
        // cost sheet
        'SUBMITTED' => 'ส่งแล้ว',
        // deposit / contract
        'WAITING_DEPOSIT' => 'รอรับมัดจำ',
        'DEPOSIT_RECEIVED' => 'ได้รับมัดจำแล้ว',
        'REFUNDED' => 'คืนมัดจำแล้ว',
        'FORFEITED' => 'ริบมัดจำ',
        'CONTRACT_SIGNED' => 'ลงนามสัญญาแล้ว',
        'VOID' => 'ยกเลิก',
        // sales transaction
        'IN_PREPARATION' => 'เตรียมส่งมอบ',
        'READY_FOR_DELIVERY' => 'พร้อมส่งมอบ',
        'DELIVERED' => 'ส่งมอบแล้ว',
        'TRANSACTION_COMPLETED' => 'เสร็จสมบูรณ์',
        'CANCELLED' => 'ยกเลิก',
        // inspection / job / task / case / checklist
        'REQUESTED' => 'รอตรวจ',
        'IN_PROGRESS' => 'กำลังดำเนินการ',
        'COMPLETED' => 'เสร็จแล้ว',
        'OPEN' => 'เปิดอยู่',
        'SCHEDULED' => 'นัดหมายแล้ว',
        'DONE' => 'เสร็จแล้ว',
        'RESOLVED' => 'แก้ไขแล้ว',
        // inventory
        'IN_STOCK' => 'อยู่ในสต็อก',
        'RESERVED' => 'จองแล้ว',
        'SOLD' => 'ขายแล้ว',
        'REMOVED' => 'ตัดออกจากสต็อก',
        // match
        'PROPOSED' => 'เสนอ',
        'SHORTLISTED' => 'คัดไว้',
        'SELECTED' => 'เลือกแล้ว',
    ],
    'approval' => [
        'PENDING' => 'รออนุมัติ',
        'APPROVED' => 'อนุมัติ',
        'APPROVED_WITH_CONDITION' => 'อนุมัติแบบมีเงื่อนไข',
        'REVISION_REQUIRED' => 'ให้แก้ไข',
        'REJECTED' => 'ไม่อนุมัติ',
    ],
    'commercial' => [
        'EXTERNAL' => 'เครื่องภายนอก',
        'UNDER_OFFER' => 'ผู้ขายกำลังเสนอขาย',
        'PURCHASED' => 'ซื้อแล้ว',
        'IN_INVENTORY' => 'อยู่ในสต็อก',
        'RESERVED' => 'จองให้ผู้ซื้อแล้ว',
        'DELIVERED' => 'ส่งมอบแล้ว',
        'INSTALLED_AT_CUSTOMER' => 'ติดตั้งที่ลูกค้าแล้ว',
    ],
    'technical' => [
        'UNKNOWN' => 'ยังไม่ได้ตรวจ',
        'INSPECTED_OK' => 'ตรวจแล้ว ใช้งานได้',
        'NEEDS_REPAIR' => 'ต้องซ่อม',
        'UNDER_REPAIR' => 'กำลังซ่อม',
        'READY' => 'พร้อมขาย',
        'QC_PASSED' => 'ผ่าน QC',
    ],
    'result' => [
        'PASS' => 'ผ่าน',
        'MINOR_ISSUE' => 'มีปัญหาเล็กน้อย',
        'MAJOR_ISSUE' => 'มีปัญหาใหญ่',
        'MISSING' => 'ไม่มี/สูญหาย',
        'NA' => 'ไม่เกี่ยวข้อง',
        'FAIL' => 'ไม่ผ่าน',
        'SUCCESS' => 'สำเร็จ',
        'PARTIAL' => 'สำเร็จบางส่วน',
        'FAILED' => 'ไม่สำเร็จ',
    ],
    'org_type' => [
        'CLINIC' => 'คลินิก',
        'HOSPITAL' => 'โรงพยาบาล',
        'DEALER' => 'ตัวแทน/ดีลเลอร์',
        'INDIVIDUAL' => 'บุคคล',
        'OTHER' => 'อื่น ๆ',
    ],
    'lead_type' => ['SELLER' => 'ผู้ขาย (เราซื้อ)', 'BUYER' => 'ผู้ซื้อ (เราขาย)'],
    'activity' => [
        'CALL' => 'โทรศัพท์',
        'LINE' => 'LINE',
        'EMAIL' => 'อีเมล',
        'MEETING' => 'ประชุม',
        'VISIT' => 'เข้าพบ',
        'NOTE' => 'บันทึก',
        'SYSTEM' => 'ระบบ',
    ],
    'priority' => ['LOW' => 'ต่ำ', 'MEDIUM' => 'ปานกลาง', 'HIGH' => 'สูง'],
    'risk' => ['LOW' => 'ต่ำ', 'MEDIUM' => 'ปานกลาง', 'HIGH' => 'สูง'],
    'demand' => ['LOW' => 'ต่ำ', 'MEDIUM' => 'ปานกลาง', 'HIGH' => 'สูง'],
    'condition' => ['EXCELLENT' => 'ดีมาก', 'GOOD' => 'ดี', 'FAIR' => 'พอใช้', 'POOR' => 'แย่'],
    'usage_unit' => ['SHOT' => 'shots', 'PULSE' => 'pulses', 'HOUR' => 'ชั่วโมง'],
    'insp_category' => [
        'EXTERIOR' => 'สภาพภายนอก',
        'FUNCTIONAL' => 'การทำงาน/ทดสอบระบบ',
        'HANDPIECE' => 'หัวทำงาน (Handpiece)',
        'ACCESSORIES' => 'อุปกรณ์เสริม',
        'ERRORS' => 'Error / ข้อผิดพลาด',
        'USAGE' => 'การใช้งาน (shot/pulse/ชม.)',
        'CONSUMABLES' => 'วัสดุสิ้นเปลือง',
        'SAFETY' => 'ความปลอดภัย',
        'SOFTWARE' => 'ซอฟต์แวร์',
        'PARTS' => 'สภาพชิ้นส่วน',
    ],
    'cost_category' => [
        'ACQUISITION_ASSUMPTION' => 'ราคาซื้อ (สมมติฐาน)',
        'REPAIR' => 'ค่าซ่อม',
        'PARTS' => 'ค่าอะไหล่',
        'REFURBISHMENT' => 'ค่าปรับสภาพ',
        'ACCESSORIES' => 'ค่าอุปกรณ์เสริม',
        'TRANSPORT' => 'ค่าขนส่ง',
        'INSTALLATION' => 'ค่าติดตั้ง',
        'WARRANTY_PROVISION' => 'สำรองค่ารับประกัน',
        'OTHER' => 'อื่น ๆ',
    ],
    'service_type' => [
        'PM' => 'บำรุงรักษาตามรอบ (PM)',
        'CM' => 'ซ่อมแก้ไข (CM)',
        'REPAIR' => 'ซ่อม',
        'PART_REPLACEMENT' => 'เปลี่ยนอะไหล่',
        'UPGRADE' => 'อัปเกรด',
    ],
    'service_source' => ['INTERNAL' => 'AMN Sure ทำเอง', 'EXTERNAL_RECORD' => 'ประวัติจากภายนอก'],
    'negotiation_party' => [
        'AMN_OFFER' => 'AMN Sure เสนอ',
        'SELLER_COUNTER' => 'ผู้ขายเสนอกลับ',
        'AGREED' => 'ราคาที่ตกลง',
    ],
    'payment_status' => ['UNPAID' => 'ยังไม่จ่าย', 'PARTIAL' => 'จ่ายบางส่วน', 'PAID' => 'จ่ายครบ'],
    'checklist_section' => [
        'COMMERCIAL' => 'การค้า (Commercial)',
        'SALES' => 'ฝ่ายขาย (Sales)',
        'MARKETING' => 'การตลาด (Marketing)',
        'TECHNICAL' => 'เทคนิค (Technical)',
    ],
    'job_type' => [
        'INSPECTION' => 'ตรวจเครื่อง',
        'REPAIR' => 'ซ่อม',
        'REFURBISH' => 'ปรับสภาพ',
        'QC' => 'QC',
        'DELIVERY' => 'จัดส่ง',
        'INSTALLATION' => 'ติดตั้ง',
        'PM' => 'บำรุงรักษา',
    ],
    'doc_category' => [
        'PHOTO' => 'รูปภาพ',
        'VIDEO' => 'วิดีโอ',
        'CONTRACT' => 'สัญญา',
        'PAYMENT_EVIDENCE' => 'หลักฐานการชำระเงิน',
        'INVOICE' => 'ใบแจ้งหนี้/ใบกำกับภาษี',
        'INSPECTION_REPORT' => 'รายงานตรวจเครื่อง',
        'QUOTATION' => 'ใบเสนอราคา',
        'OTHER' => 'อื่น ๆ',
    ],
    'match_source' => ['INVENTORY' => 'ของในสต็อก', 'SELLER_OPPORTUNITY' => 'เครื่องที่ผู้ขายกำลังเสนอ'],
    'ownership_source' => ['INITIAL' => 'ข้อมูลเริ่มต้น', 'ACQUISITION' => 'AMN Sure ซื้อ', 'SALE' => 'ขายให้ลูกค้า', 'MANUAL' => 'แก้ไขโดยผู้ดูแล'],
    'inventory_source' => ['ACQUISITION' => 'จากการซื้อ', 'OPENING' => 'ยอดยกมา'],
    'entity' => [
        'USER' => 'ผู้ใช้',
        'ORGANIZATION' => 'ลูกค้า/องค์กร',
        'CONTACT' => 'ผู้ติดต่อ',
        'LEAD' => 'ลีด',
        'DEVICE' => 'เครื่อง',
        'DEVICE_OPPORTUNITY' => 'ดีลซื้อ',
        'INSPECTION' => 'การตรวจเครื่อง',
        'SERVICE_HISTORY' => 'ประวัติซ่อม',
        'MA_RECORD' => 'สัญญา MA',
        'COST_SHEET' => 'Cost Sheet',
        'VALUATION' => 'Valuation',
        'NEGOTIATION' => 'การเจรจา',
        'APPROVAL' => 'การอนุมัติ',
        'ACQUISITION' => 'การซื้อ',
        'INVENTORY' => 'สต็อก',
        'SALES_OPPORTUNITY' => 'ดีลขาย',
        'DEVICE_MATCH' => 'การจับคู่เครื่อง',
        'QUOTATION' => 'ใบเสนอราคา',
        'QUOTATION_VERSION' => 'ใบเสนอราคา (ฉบับ)',
        'DEPOSIT' => 'มัดจำ',
        'CONTRACT' => 'สัญญา',
        'SALES_TRANSACTION' => 'ธุรกรรมขาย',
        'CHECKLIST_ITEM' => 'รายการเช็กลิสต์',
        'QC' => 'QC',
        'DELIVERY' => 'การส่งมอบ',
        'INSTALLATION' => 'การติดตั้ง',
        'TECHNICAL_JOB' => 'ใบงานช่าง',
        'SERVICE_CASE' => 'เคสบริการ',
        'TASK' => 'งาน',
        'DOCUMENT' => 'เอกสาร',
        'SETTING' => 'การตั้งค่า',
        'MASTER_DATA' => 'ข้อมูลหลัก',
    ],
    'audit_action' => [
        'CREATE' => 'สร้าง',
        'UPDATE' => 'แก้ไข',
        'STATUS_CHANGE' => 'เปลี่ยนสถานะ',
        'APPROVE' => 'อนุมัติ/ตัดสิน',
        'VOID' => 'ยกเลิก (void)',
        'ARCHIVE' => 'เก็บเข้าคลัง',
        'LOGIN' => 'เข้าสู่ระบบ',
        'LOGIN_FAILED' => 'เข้าสู่ระบบไม่สำเร็จ',
        'OVERRIDE' => 'แก้ไขโดยผู้ดูแล',
    ],
];

// สีของ badge: green / blue / amber / red / gray / purple
const BADGE_COLORS = [
    'green' => ['PURCHASED', 'WON', 'APPROVED', 'ACCEPTED', 'DEPOSIT_RECEIVED', 'CONTRACT_SIGNED', 'TRANSACTION_COMPLETED',
        'COMPLETED', 'DONE', 'PASS', 'SUCCESS', 'IN_STOCK', 'IN_INVENTORY', 'QC_PASSED', 'READY', 'INSPECTED_OK', 'SELECTED',
        'RESOLVED', 'PAID', 'INSTALLED_AT_CUSTOMER', 'SUBMITTED', 'APPROVED_WITH_CONDITION'],
    'blue' => ['NEW_SELLER_LEAD', 'NEW_BUYER_LEAD', 'INSPECTED', 'COSTED', 'VALUED', 'REQUIREMENT_DEFINED', 'MATCHING',
        'QUOTING', 'SENT', 'IN_PREPARATION', 'READY_FOR_DELIVERY', 'DELIVERED', 'IN_PROGRESS', 'SCHEDULED', 'ACTIVE',
        'SHORTLISTED', 'UNDER_OFFER', 'RESERVED', 'OPEN', 'INSPECTION_IN_PROGRESS'],
    'amber' => ['WAITING_INSPECTION', 'NEGOTIATING', 'PENDING_APPROVAL', 'PENDING', 'WAITING_DEPOSIT', 'REQUESTED',
        'MINOR_ISSUE', 'REVISION_REQUIRED', 'NEEDS_REPAIR', 'UNDER_REPAIR', 'PARTIAL', 'MEDIUM', 'DRAFT', 'PROPOSED', 'UNPAID'],
    'red' => ['REJECTED', 'LOST', 'MAJOR_ISSUE', 'MISSING', 'FAIL', 'FAILED', 'CANCELLED', 'VOID', 'FORFEITED', 'HIGH', 'EXPIRED'],
];

function label(string $group, ?string $code): string
{
    if ($code === null || $code === '') return '—';
    if (isset(LABELS[$group][$code])) return LABELS[$group][$code];
    foreach (['status', 'approval', 'result'] as $g) {
        if (isset(LABELS[$g][$code])) return LABELS[$g][$code];
    }
    return $code;
}

function labels(string $group): array
{
    return LABELS[$group] ?? [];
}

function badge_color(?string $code): string
{
    foreach (BADGE_COLORS as $color => $codes) {
        if (in_array($code, $codes, true)) return $color;
    }
    return 'gray';
}

function badge(string $group, ?string $code): string
{
    if ($code === null || $code === '') return '<span class="badge gray">—</span>';
    return '<span class="badge ' . badge_color($code) . '">' . e(label($group, $code)) . '</span>';
}

function role_name(string $code): string
{
    return ROLE_NAMES[$code] ?? $code;
}
