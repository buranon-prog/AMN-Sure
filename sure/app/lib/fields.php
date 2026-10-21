<?php
// ทะเบียนฟิลด์ของทุกตาราง ตามสเปกหัวข้อ 9 (ลำดับและ label ตรงตัวอักษร) + รายการตัวเลือกหัวข้อ 10
// ทุกหน้า (list, ฟอร์ม, ค้นหา, export) อ่านโครงจากไฟล์นี้ — ฟิลด์ 'mgr' => true ไม่ถูกคำนวณ/ส่งให้ EMPLOYEE เลย
//
// fd(label, type, how, required, source, extra)
//   type: text | textarea | date | money | int | percent
//   how : pk (กรอกเอง) | input (กรอกเอง) | option (dropdown ตัวเลือก) | fk (FK dropdown) | auto (คำนวณ/ดึงอัตโนมัติ)
//   source: option → list_key ในหัวข้อ 10 ('prov' = 77 จังหวัด) · fk → key ของตาราง (C, B, D, S)
//   extra: mgr (Manager เท่านั้น), hint (หมายเหตุในสเปก), col (ชื่อคอลัมน์ ถ้าไม่ตรง field_key), max (ความยาวสูงสุด)
// ฟิลด์ key / seq / seqb / seqc / seqd เป็นตัวช่วยของ Google Sheets — ไม่สร้าง (สเปกหัวข้อ 6, 9)

const SURE_CANCELLED = 'ยกเลิก';
const SURE_JOB_CLOSED = 'ปิดงาน';
const SURE_READY = 'พร้อมขาย';
const SURE_DEPOSIT = 'มัดจำ';
const SURE_FU_OPEN = 'เปิด / รอติดตาม';
const SURE_CONSIGN = 'ฝากขาย (Consignment)';
const SURE_BRANCH_CLOSED = 'ปิดสาขา';
const SURE_CUST_STOPPED = 'หยุดติดต่อ';
const SURE_MA_RENEW = 'ต่อสัญญา MA';

function fd(string $label, string $type, string $how, bool $req = false, ?string $src = null, array $extra = []): array
{
    return ['label' => $label, 'type' => $type, 'how' => $how, 'req' => $req, 'src' => $src] + $extra + ['mgr' => false];
}

function sure_tables(): array
{
    static $t = null;
    if ($t !== null) return $t;
    $mgr = ['mgr' => true];
    $staff = 'staff';
    $t = [
        // ------------------------------------------------------------ C ลูกค้า
        'C' => [
            'title' => 'ลูกค้า', 'route' => 'customers', 'table' => 'sure_customers', 'pk' => 'id', 'mgr' => false,
            'fields' => [
                'id' => fd('รหัสลูกค้า', 'text', 'pk', true, null, ['hint' => "รูปแบบ SURE + เลข 3 หลัก เช่น SURE001 · ใช้ตามช่อง 'รหัสถัดไป' ด้านบน · ห้ามซ้ำ ห้ามเปลี่ยน", 'max' => 7]),
                'since' => fd('วันที่เป็นลูกค้า', 'date', 'input', true, null, ['hint' => 'วันที่บันทึกลูกค้ารายนี้ครั้งแรก']),
                'name' => fd('ชื่อลูกค้า / ชื่อกิจการ', 'text', 'input', true, null, ['hint' => 'ชื่อเจ้าของ / กิจการ เช่น ชื่อคลินิก โรงพยาบาล — แต่ละสาขาบันทึกในชีตสาขา']),
                'ctype' => fd('ประเภทลูกค้า', 'text', 'option', true, 'ctype'),
                'legal' => fd('ชื่อนิติบุคคล (ออกใบกำกับภาษี)', 'text', 'input', false, null, ['hint' => 'ชื่อตามหนังสือรับรองบริษัท']),
                'taxid' => fd('เลขผู้เสียภาษี 13 หลัก', 'text', 'input', false, null, ['hint' => 'เก็บเป็นข้อความเพื่อไม่ให้เลข 0 หาย', 'max' => 13]),
                'contact' => fd('ผู้ติดต่อหลัก / ผู้ตัดสินใจ', 'text', 'input', true, null, ['hint' => 'เจ้าของ / ผู้มีอำนาจตัดสินใจซื้อ']),
                'pos' => fd('ตำแหน่ง', 'text', 'input', false, null, ['hint' => 'เช่น เจ้าของคลินิก แพทย์ ผู้จัดการ จัดซื้อ']),
                'addr' => fd('เลขที่ / อาคาร / ถนน', 'text', 'input', false, null, ['hint' => 'ที่อยู่ตามทะเบียน / ที่อยู่ออกใบกำกับภาษี (สำนักงานใหญ่)']),
                'subd' => fd('แขวง / ตำบล', 'text', 'input'),
                'dist' => fd('เขต / อำเภอ', 'text', 'input'),
                'prov' => fd('จังหวัด', 'text', 'option', true, 'prov', ['hint' => 'เลือกจาก 77 จังหวัด → ภาคขึ้นอัตโนมัติ']),
                'zip' => fd('รหัสไปรษณีย์', 'text', 'input'),
                'region' => fd('ภาค', 'text', 'auto', false, null, ['hint' => 'ดึงจากจังหวัดอัตโนมัติ']),
                'phone' => fd('เบอร์โทรหลัก', 'text', 'input', true, null, ['hint' => 'เก็บเป็นข้อความ เช่น 081-234-5678']),
                'phone2' => fd('เบอร์โทรสำรอง', 'text', 'input'),
                'line' => fd('LINE ID', 'text', 'input'),
                'email' => fd('อีเมล', 'text', 'input'),
                'social' => fd('Facebook / IG / TikTok', 'text', 'input', false, null, ['hint' => 'ชื่อเพจ หรือ ลิงก์', 'max' => 500]),
                'docs' => fd('ลิงก์โฟลเดอร์เอกสาร (Google Drive)', 'text', 'input', false, null, ['hint' => 'โฟลเดอร์เก็บหนังสือรับรอง ภพ.20 สำเนาบัตร ฯลฯ — เก็บเป็นลิงก์ ไม่ต้องพิมพ์เลขบัตรลงชีต', 'max' => 500]),
                'source' => fd('รู้จัก SURE จาก', 'text', 'option', false, 'source', ['hint' => 'ใช้วัดผลช่องทางการตลาด']),
                'amn' => fd('ลูกค้าเดิมของ AMN', 'text', 'option', false, 'yesno'),
                'sales' => fd('Sales ผู้ดูแล', 'text', 'option', false, $staff),
                'status' => fd('สถานะลูกค้า', 'text', 'option', false, 'cstatus', ['hint' => "ไม่ลบลูกค้า — เปลี่ยนเป็น 'หยุดติดต่อ' แทน เพื่อไม่ให้ประวัติหาย"]),
                'note' => fd('หมายเหตุ', 'textarea', 'input'),
                'rel' => fd('ความสัมพันธ์กับ SURE', 'text', 'auto', false, null, $mgr),
                'nbranch' => fd('จำนวนสาขา', 'int', 'auto', false, null, $mgr),
                'nbuy' => fd('เครื่องที่ซื้อ (เครื่อง)', 'int', 'auto', false, null, $mgr),
                'amt' => fd('ยอดซื้อรวม ก่อน VAT (บาท)', 'money', 'auto', false, null, $mgr),
                'last' => fd('ซื้อล่าสุด', 'date', 'auto', false, null, $mgr),
                'due' => fd('ยอดค้างชำระ (บาท)', 'money', 'auto', false, null, $mgr),
                'nsell' => fd('เครื่องที่ขาย/ฝากขายให้ SURE', 'int', 'auto', false, null, $mgr),
                'lastct' => fd('ติดต่อล่าสุด', 'date', 'auto', false, null, $mgr),
                'nextfu' => fd('นัดติดตามถัดไป', 'date', 'auto', false, null, $mgr),
            ],
            'groups' => [
                ['ข้อมูลลูกค้า (เจ้าของ)', ['id', 'since', 'name', 'ctype', 'legal', 'taxid', 'contact', 'pos']],
                ['ที่อยู่ตามทะเบียน / ออกใบกำกับภาษี', ['addr', 'subd', 'dist', 'prov', 'zip', 'region']],
                ['ช่องทางติดต่อ & เอกสาร', ['phone', 'phone2', 'line', 'email', 'social', 'docs']],
                ['การตลาด & ผู้ดูแล', ['source', 'amn', 'sales', 'status', 'note']],
                ['', ['rel', 'nbranch', 'nbuy', 'amt', 'last', 'due', 'nsell', 'lastct', 'nextfu']],
            ],
            'list' => ['id', 'name', 'ctype', 'prov', 'phone', 'sales', 'status', 'rel', 'nbuy', 'amt', 'due', 'nextfu'],
            'filters' => ['ctype', 'region', 'sales', 'status', 'rel'],
            'sort' => ['id', 'desc'],
        ],
        // ------------------------------------------------------------ B สาขา
        'B' => [
            'title' => 'สาขา', 'route' => 'branches', 'table' => 'sure_branches', 'pk' => 'id', 'mgr' => false,
            'fields' => [
                'id' => fd('รหัสสาขา', 'text', 'pk', true, null, ['hint' => 'รหัสลูกค้า + - + ลำดับสาขา 2 หลัก เช่น SURE001-01 · สาขาแรก / สำนักงานใหญ่ = -01 · ห้ามซ้ำ ห้ามเปลี่ยน', 'max' => 10]),
                'cust' => fd('รหัสลูกค้า (เจ้าของ)', 'text', 'fk', true, 'C', ['hint' => 'ต้องตรงกับ 7 ตัวแรกของรหัสสาขา']),
                'custname' => fd('ชื่อลูกค้า', 'text', 'auto'),
                'bname' => fd('ชื่อสาขา', 'text', 'input', true, null, ['hint' => 'เช่น สำนักงานใหญ่ / สาขาทองหล่อ / อาคาร B แผนกผิวหนัง']),
                'taxbranch' => fd('สาขาตามภาษี', 'text', 'input', false, null, ['hint' => 'สำนักงานใหญ่ หรือ เลขสาขา 5 หลัก เช่น 00001 — ใช้เมื่อออกใบกำกับภาษีในนามสาขานี้']),
                'since' => fd('วันที่บันทึกสาขา', 'date', 'input', true),
                'addr' => fd('เลขที่ / อาคาร / ถนน', 'text', 'input', true, null, ['hint' => 'ที่อยู่ที่ส่งเครื่อง / ติดตั้ง / เข้างานบริการ']),
                'subd' => fd('แขวง / ตำบล', 'text', 'input'),
                'dist' => fd('เขต / อำเภอ', 'text', 'input'),
                'prov' => fd('จังหวัด', 'text', 'option', true, 'prov'),
                'zip' => fd('รหัสไปรษณีย์', 'text', 'input'),
                'region' => fd('ภาค', 'text', 'auto'),
                'maps' => fd('ลิงก์ Google Maps', 'text', 'input', false, null, ['hint' => 'ลิงก์ปักหมุด ให้ทีมส่งของ / ช่างหาเจอ', 'max' => 500]),
                'contact' => fd('ผู้ติดต่อประจำสาขา', 'text', 'input'),
                'phone' => fd('เบอร์โทรสาขา', 'text', 'input'),
                'line' => fd('LINE สาขา', 'text', 'input'),
                'hours' => fd('วัน-เวลาที่เข้างานได้', 'text', 'input', false, null, ['hint' => 'เช่น จ–ศ 10:00–18:00 · ใช้นัดส่งของ / ติดตั้ง / ซ่อม']),
                'status' => fd('สถานะสาขา', 'text', 'option', false, 'bstatus', ['hint' => "ไม่ลบสาขา — เปลี่ยนเป็น 'ปิดสาขา' แทน"]),
                'note' => fd('หมายเหตุ', 'textarea', 'input'),
            ],
            'groups' => [
                ['สาขา', ['id', 'cust', 'custname', 'bname', 'taxbranch', 'since']],
                ['ที่อยู่สาขา (ใช้ส่งเครื่อง / ติดตั้ง / เข้างานบริการ)', ['addr', 'subd', 'dist', 'prov', 'zip', 'region', 'maps']],
                ['ติดต่อหน้างาน', ['contact', 'phone', 'line', 'hours']],
                ['', ['status', 'note']],
            ],
            'list' => ['id', 'cust', 'custname', 'bname', 'prov', 'region', 'contact', 'phone', 'status'],
            'filters' => ['region', 'status'],
            'sort' => ['id', 'asc'],
        ],
        // ------------------------------------------------------------ A ติดตามลูกค้า
        'A' => [
            'title' => 'ติดตามลูกค้า', 'route' => 'followups', 'table' => 'sure_followups', 'pk' => 'id', 'mgr' => false, 'autoid' => true,
            'fields' => [
                'date' => fd('วันที่ติดต่อ', 'date', 'input', true),
                'cust' => fd('รหัสลูกค้า', 'text', 'fk', true, 'C', ['hint' => 'ลูกค้า/Lead ต้องมีรหัสในชีตลูกค้าก่อน']),
                'custname' => fd('ชื่อลูกค้า', 'text', 'auto'),
                'phone' => fd('เบอร์โทร', 'text', 'auto'),
                'branch' => fd('รหัสสาขา', 'text', 'fk', false, 'B', ['hint' => 'สาขาที่คุยด้วย / เกี่ยวข้อง (เช่น สาขาที่ติดตั้งเครื่อง หรือสาขาที่จะเปิดใหม่) — ต้องเป็นสาขาของลูกค้ารายนี้']),
                'branchname' => fd('ชื่อสาขา', 'text', 'auto'),
                'channel' => fd('ช่องทาง', 'text', 'option', false, 'achannel'),
                'topic' => fd('เรื่อง', 'text', 'option', true, 'atopic'),
                'interest' => fd('หมวดเครื่องที่สนใจ', 'text', 'option', false, 'cat', ['hint' => 'กรณีลูกค้าสนใจซื้อ / เสนอขายเครื่อง']),
                'model' => fd('ยี่ห้อ / รุ่นที่สนใจ', 'text', 'input'),
                'budget' => fd('งบประมาณ (บาท)', 'money', 'input'),
                'dev' => fd('รหัสเครื่องที่เกี่ยวข้อง', 'text', 'fk', false, 'D', ['hint' => 'ถ้าคุยเรื่องเครื่องที่มีในระบบแล้ว (ไม่บังคับ)']),
                'detail' => fd('รายละเอียด / ผลการคุย', 'textarea', 'input'),
                'result' => fd('สถานะ', 'text', 'option', true, 'astatus'),
                'nextdate' => fd('นัดติดตามครั้งถัดไป', 'date', 'input'),
                'by' => fd('ผู้บันทึก', 'text', 'option', true, $staff, ['col' => 'by_name']),
                'due' => fd('ครบกำหนดติดตาม', 'text', 'auto'),
            ],
            'groups' => [
                ['การติดต่อ', ['date', 'cust', 'custname', 'phone', 'branch', 'branchname', 'channel', 'topic']],
                ['ความต้องการลูกค้า', ['interest', 'model', 'budget', 'dev']],
                ['ผล & นัดถัดไป', ['detail', 'result', 'nextdate', 'by']],
                ['', ['due']],
            ],
            'list' => ['date', 'cust', 'custname', 'branchname', 'channel', 'topic', 'detail', 'result', 'nextdate', 'by', 'due'],
            'filters' => ['topic', 'result', 'due', 'by'],
            'sort' => ['date', 'desc'],
        ],
        // ------------------------------------------------------------ D เครื่อง
        'D' => [
            'title' => 'เครื่อง', 'route' => 'devices', 'table' => 'sure_devices', 'pk' => 'id', 'mgr' => false,
            'fields' => [
                'id' => fd('รหัสเครื่อง', 'text', 'pk', true, null, ['hint' => 'SURE + ปี ค.ศ. 2 หลัก + ลำดับ 3 หลัก เช่น SURE26001 · ออกรหัสตอนรับเครื่องเข้า · ใช้รหัสเดิมตลอดอายุเครื่อง', 'max' => 9]),
                'regdate' => fd('วันที่รับเข้า / ลงทะเบียน', 'date', 'input', true),
                'cat' => fd('หมวดเครื่อง', 'text', 'option', true, 'cat'),
                'brand' => fd('ยี่ห้อ', 'text', 'input', true),
                'model' => fd('รุ่น', 'text', 'input', true),
                'serial' => fd('Serial Number', 'text', 'input', true, null, ['hint' => 'ห้ามซ้ำ — ใช้ยืนยันตัวเครื่องจริง']),
                'year' => fd('ปีที่ผลิต (ค.ศ.)', 'int', 'input'),
                'fda' => fd('เลขที่ใบอนุญาต / ทะเบียน อย.', 'text', 'input'),
                'acc' => fd('อุปกรณ์ / Handpiece ที่มากับเครื่อง', 'text', 'input', false, null, ['max' => 500]),
                'usage' => fd('Shot / ชั่วโมงใช้งาน ตอนรับเข้า', 'text', 'input', false, null, ['col' => 'usage_info']),
                'grade' => fd('เกรดสภาพ', 'text', 'option', false, 'grade'),
                'photos' => fd('ลิงก์รูป / รายงานตรวจสภาพ (Drive)', 'text', 'input', false, null, ['max' => 500]),
                'acq' => fd('ประเภทการได้มา', 'text', 'option', true, 'acq'),
                'seller' => fd('รับเครื่องจาก (รหัสลูกค้า)', 'text', 'fk', false, 'C', ['hint' => 'ผู้ขาย / ฝากขาย / เทิร์นเครื่องให้ SURE — ต้องมีในชีตลูกค้าก่อน']),
                'sellername' => fd('ชื่อผู้ขาย / เจ้าของเดิม', 'text', 'auto'),
                'sourcer' => fd('ผู้จัดหา / ตรวจสภาพ', 'text', 'option', false, $staff),
                'inspect' => fd('วันที่ตรวจสภาพ', 'date', 'input'),
                'evalform' => fd('เลขที่แบบฟอร์มประเมินราคา - ขออนุมัติซื้อ', 'text', 'input'),
                'approver' => fd('ผู้อนุมัติซื้อ', 'text', 'input'),
                'buyform' => fd('เลขที่แบบฟอร์มสัญญารับซื้อ', 'text', 'input'),
                'listprice' => fd('ราคาตั้งขาย (บาท)', 'money', 'input', false, null, ['hint' => 'ราคาที่อนุมัติให้ขาย (ตามแบบฟอร์มขออนุมัติตั้งราคาขายสินค้า)']),
                'priceform' => fd('เลขที่แบบฟอร์มขออนุมัติตั้งราคาขายสินค้า', 'text', 'input'),
                'stock' => fd('สถานะสต็อก', 'text', 'option', false, 'stock', ['hint' => "ใช้ก่อนขาย — เมื่อบันทึกการขายแล้ว 'สถานะปัจจุบัน' จะเปลี่ยนเองอัตโนมัติ"]),
                'loc' => fd('ที่เก็บเครื่อง', 'text', 'input'),
                'note' => fd('หมายเหตุ', 'textarea', 'input'),
                'status' => fd('สถานะปัจจุบัน', 'text', 'auto', false, null, ['hint' => 'ส่งมอบแล้ว / ขายแล้ว – รอส่งมอบ / หรือสถานะสต็อก']),
                'soid' => fd('เลขที่การขาย (ล่าสุด)', 'text', 'auto', false, null, ['hint' => 'การขายล่าสุดของเครื่องนี้ที่ไม่ถูกยกเลิก']),
                'buyer' => fd('รหัสลูกค้าผู้ซื้อ', 'text', 'auto'),
                'buyername' => fd('ชื่อลูกค้าผู้ซื้อ', 'text', 'auto'),
                'branch' => fd('รหัสสาขาที่ติดตั้ง', 'text', 'auto'),
                'branchname' => fd('สาขาที่ติดตั้ง', 'text', 'auto'),
                'saledate' => fd('วันที่ขาย', 'date', 'auto'),
                'saleprice' => fd('ราคาขาย ก่อน VAT (บาท)', 'money', 'auto'),
                'delivdate' => fd('วันที่ส่งสินค้า / ส่งมอบ', 'date', 'auto'),
                'wend' => fd('วันหมดประกัน', 'date', 'auto'),
                'maend' => fd('MA ถึงวันที่', 'date', 'auto', false, null, ['hint' => "วันหมดสัญญา MA ล่าสุด จากงาน 'ต่อสัญญา MA'"]),
                'cover' => fd('สถานะความคุ้มครอง', 'text', 'auto', false, null, ['hint' => 'ในประกัน / ประกันใกล้หมด (≤30 วัน) / อยู่ใน MA / MA ใกล้หมด / หมดประกัน']),
                'nsvc' => fd('งานบริการ (ครั้ง)', 'int', 'auto'),
                'lastsvc' => fd('บริการล่าสุด', 'date', 'auto'),
                'totalcost' => fd('ต้นทุนรวม (บาท)', 'money', 'auto', false, null, $mgr),
                'gp' => fd('กำไรขั้นต้น (บาท)', 'money', 'auto', false, null, $mgr),
                'gpp' => fd('GP %', 'percent', 'auto', false, null, $mgr),
            ],
            'groups' => [
                ['ข้อมูลเครื่อง', ['id', 'regdate', 'cat', 'brand', 'model', 'serial', 'year', 'fda', 'acc', 'usage', 'grade', 'photos']],
                ['ที่มา · อนุมัติซื้อ · ราคาขาย', ['acq', 'seller', 'sellername', 'sourcer', 'inspect', 'evalform', 'approver', 'buyform', 'listprice', 'priceform']],
                ['สต็อก', ['stock', 'loc', 'note']],
                ['การขาย & ส่งมอบ (อัตโนมัติ)', ['status', 'soid', 'buyer', 'buyername', 'branch', 'branchname', 'saledate', 'saleprice', 'delivdate']],
                ['ประกัน & บริการ (อัตโนมัติ)', ['wend', 'maend', 'cover', 'nsvc', 'lastsvc']],
                ['', ['totalcost', 'gp', 'gpp']],
            ],
            'list' => ['id', 'cat', 'brand', 'model', 'serial', 'listprice', 'status', 'buyername', 'cover', 'totalcost', 'gp', 'gpp'],
            'filters' => ['cat', 'acq', 'status', 'cover'],
            'sort' => ['id', 'desc'],
        ],
        // ------------------------------------------------------------ S การขาย
        'S' => [
            'title' => 'การขาย', 'route' => 'sales', 'table' => 'sure_sales', 'pk' => 'id', 'mgr' => false,
            'fields' => [
                'id' => fd('เลขที่การขาย', 'text', 'pk', true, null, ['hint' => 'SO + ปี 2 หลัก + ลำดับ 3 หลัก เช่น SO26001 · 1 แถว = 1 เครื่อง', 'max' => 7]),
                'date' => fd('วันที่ขาย / ทำสัญญา', 'date', 'input', true),
                'status' => fd('สถานะการขาย', 'text', 'option', true, 'sstatus', ['hint' => "ถ้ายกเลิก ให้เลือก 'ยกเลิก' (ไม่ต้องลบแถว) — เครื่องจะกลับไปใช้สถานะสต็อก"]),
                'dev' => fd('รหัสเครื่อง', 'text', 'fk', true, 'D', ['hint' => 'ต้องลงทะเบียนในชีตเครื่องก่อน']),
                'devname' => fd('ยี่ห้อ / รุ่น', 'text', 'auto'),
                'serial' => fd('Serial Number', 'text', 'auto'),
                'cust' => fd('รหัสลูกค้า', 'text', 'fk', true, 'C', ['hint' => 'ต้องมีในชีตลูกค้าก่อน']),
                'custname' => fd('ชื่อลูกค้า', 'text', 'auto'),
                'branch' => fd('รหัสสาขาที่ส่ง', 'text', 'fk', true, 'B', ['hint' => 'สาขาที่ส่งเครื่อง / ติดตั้ง — ต้องเป็นสาขาของลูกค้ารายนี้ (บันทึกในชีตสาขาก่อน)']),
                'branchname' => fd('ชื่อสาขา', 'text', 'auto'),
                'phone' => fd('เบอร์โทร', 'text', 'auto'),
                'prov' => fd('จังหวัดที่ส่ง', 'text', 'auto'),
                'channel' => fd('ช่องทางการขาย', 'text', 'option', true, 'channel', ['hint' => 'ใช้คำนวณ Incentive ตัวแทน / ผู้แนะนำ']),
                'agent' => fd('ผู้แนะนำ / ตัวแทน (ถ้ามี)', 'text', 'input'),
                'sales' => fd('Sales ผู้ปิดการขาย', 'text', 'option', true, $staff),
                'docno' => fd('เลขที่ใบเสนอราคาและสัญญาซื้อขาย', 'text', 'input'),
                'price' => fd('ราคาขาย ก่อน VAT (บาท)', 'money', 'input', true),
                'vat' => fd('VAT (บาท)', 'money', 'auto'),
                'total' => fd('ยอดรวม VAT (บาท)', 'money', 'auto'),
                'terms' => fd('เงื่อนไขการชำระ', 'text', 'option', false, 'terms'),
                'deposit' => fd('เงินมัดจำ (บาท)', 'money', 'auto'),
                'paid' => fd('รับชำระแล้ว (บาท)', 'money', 'auto', false, null, ['hint' => 'รวมทุกรายการในชีตรับชำระเงินของเลขที่การขายนี้']),
                'lastpay' => fd('รับเงินล่าสุด', 'date', 'auto'),
                'outstanding' => fd('ยอดคงค้าง (บาท)', 'money', 'auto'),
                'paystatus' => fd('สถานะชำระเงิน', 'text', 'auto'),
                'plandate' => fd('วันนัดส่ง', 'date', 'input'),
                'deliv' => fd('วันที่ส่งสินค้า / ส่งมอบจริง', 'date', 'input', false, null, ['hint' => "กรอกเมื่อส่งมอบเสร็จ → เครื่องเปลี่ยนเป็น 'ส่งมอบแล้ว' และเริ่มนับประกันอัตโนมัติ"]),
                'site' => fd('สถานที่ส่ง / ติดตั้ง', 'text', 'auto', false, null, ['hint' => 'ที่อยู่ของสาขาที่ส่ง · ถ้าไม่ระบุสาขา ใช้ที่อยู่ลูกค้า']),
                'eng' => fd('วิศวกรผู้ติดตั้ง', 'text', 'option', false, $staff),
                'moveform' => fd('เลขที่แบบฟอร์มขนย้ายเครื่อง', 'text', 'input'),
                'trained' => fd('อบรมการใช้งานแล้ว', 'text', 'option', false, 'yesno'),
                'wmonths' => fd('ระยะประกัน (เดือน)', 'int', 'input', false, null, ['hint' => 'ตามนโยบายประกันของ SURE / ที่ตกลงในสัญญา']),
                'wstart' => fd('วันเริ่มประกัน', 'date', 'auto'),
                'wend' => fd('วันหมดประกัน', 'date', 'auto'),
                'wstatus' => fd('สถานะประกัน', 'text', 'auto'),
                'note' => fd('หมายเหตุ', 'textarea', 'input'),
                'cost' => fd('ต้นทุนเครื่อง (บาท)', 'money', 'auto', false, null, $mgr),
                'gp' => fd('กำไรขั้นต้น (บาท)', 'money', 'auto', false, null, $mgr),
                'gpp' => fd('GP %', 'percent', 'auto', false, null, $mgr),
            ],
            'groups' => [
                ['การขาย', ['id', 'date', 'status']],
                ['เครื่องที่ขาย', ['dev', 'devname', 'serial']],
                ['ลูกค้าผู้ซื้อ & สาขาที่ส่ง', ['cust', 'custname', 'branch', 'branchname', 'phone', 'prov']],
                ['ช่องทาง & เอกสาร', ['channel', 'agent', 'sales', 'docno']],
                ['ราคา & การชำระเงิน', ['price', 'vat', 'total', 'terms', 'deposit', 'paid', 'lastpay', 'outstanding', 'paystatus']],
                ['การส่งมอบ / ติดตั้ง', ['plandate', 'deliv', 'site', 'eng', 'moveform', 'trained']],
                ['ประกัน', ['wmonths', 'wstart', 'wend', 'wstatus']],
                ['', ['note']],
                ['', ['cost', 'gp', 'gpp']],
            ],
            'list' => ['id', 'date', 'status', 'devname', 'custname', 'sales', 'price', 'outstanding', 'paystatus', 'wstatus', 'cost', 'gp', 'gpp'],
            'filters' => ['status', 'channel', 'sales', 'paystatus', 'wstatus'],
            'sort' => ['id', 'desc'],
        ],
        // ------------------------------------------------------------ P รับชำระเงิน
        'P' => [
            'title' => 'รับชำระเงิน', 'route' => 'payments', 'table' => 'sure_payments', 'pk' => 'receipt', 'mgr' => false,
            'fields' => [
                'receipt' => fd('เลขที่ใบเสร็จ / ใบกำกับภาษี', 'text', 'pk', true, null, ['hint' => 'ใช้เลขที่ตามใบเสร็จจริงของฝ่ายบัญชี · ห้ามซ้ำ', 'max' => 50]),
                'date' => fd('วันที่รับเงิน', 'date', 'input', true),
                'so' => fd('เลขที่การขาย', 'text', 'fk', true, 'S'),
                'cust' => fd('รหัสลูกค้า', 'text', 'auto'),
                'custname' => fd('ชื่อลูกค้า', 'text', 'auto'),
                'dev' => fd('รหัสเครื่อง', 'text', 'auto'),
                'type' => fd('ประเภทรับเงิน', 'text', 'option', true, 'ptype'),
                'method' => fd('วิธีชำระ', 'text', 'option', true, 'pmethod'),
                'amount' => fd('จำนวนเงิน (บาท)', 'money', 'input', true, null, ['hint' => 'รวม VAT · คืนเงินให้ใส่ยอดติดลบ', 'signed' => true]),
                'ref' => fd('อ้างอิง (เลขที่โอน / เช็ค / ไฟแนนซ์)', 'text', 'input'),
                'by' => fd('ผู้รับเงิน / ผู้บันทึก', 'text', 'option', true, $staff, ['col' => 'by_name']),
                'note' => fd('หมายเหตุ', 'textarea', 'input'),
                'balance' => fd('ยอดคงค้างของการขายนี้ (ปัจจุบัน)', 'money', 'auto'),
            ],
            'groups' => [
                ['การรับเงิน', ['receipt', 'date', 'so']],
                ['ดึงจากการขาย', ['cust', 'custname', 'dev']],
                ['รายละเอียด', ['type', 'method', 'amount', 'ref', 'by', 'note']],
                ['', ['balance']],
            ],
            'list' => ['receipt', 'date', 'so', 'cust', 'custname', 'dev', 'type', 'method', 'amount', 'by', 'balance'],
            'filters' => ['type', 'method', 'by'],
            'sort' => ['date', 'desc'],
        ],
        // ------------------------------------------------------------ V บริการ
        'V' => [
            'title' => 'บริการ', 'route' => 'services', 'table' => 'sure_services', 'pk' => 'id', 'mgr' => false,
            'fields' => [
                'id' => fd('เลขที่งานบริการ', 'text', 'pk', true, null, ['hint' => 'SV + ปี 2 หลัก + ลำดับ 3 หลัก เช่น SV26001', 'max' => 7]),
                'date' => fd('วันที่แจ้ง / เปิดงาน', 'date', 'input', true),
                'dev' => fd('รหัสเครื่อง', 'text', 'fk', true, 'D'),
                'devname' => fd('ยี่ห้อ / รุ่น', 'text', 'auto'),
                'serial' => fd('Serial Number', 'text', 'auto'),
                'owner' => fd('รหัสลูกค้า (เจ้าของเครื่อง)', 'text', 'auto'),
                'ownername' => fd('ชื่อลูกค้า', 'text', 'auto'),
                'phone' => fd('เบอร์โทร', 'text', 'auto'),
                'branchname' => fd('สาขาที่ตั้งเครื่อง', 'text', 'auto'),
                'cover' => fd('ความคุ้มครอง ณ วันแจ้ง', 'text', 'auto', false, null, ['hint' => 'ใช้ตัดสินว่างานนี้คิดค่าบริการหรือไม่']),
                'type' => fd('ประเภทงาน', 'text', 'option', true, 'svtype'),
                'detail' => fd('อาการ / รายละเอียดงาน', 'textarea', 'input'),
                'eng' => fd('วิศวกรผู้รับผิดชอบ', 'text', 'option', true, $staff),
                'appt' => fd('วันนัดเข้างาน', 'date', 'input'),
                'close' => fd('วันที่ปิดงาน', 'date', 'input', false, null, ['col' => 'close_date']),
                'jstatus' => fd('สถานะงาน', 'text', 'option', true, 'svstatus'),
                'fee' => fd('ค่าบริการ / อะไหล่ (บาท)', 'money', 'input'),
                'payst' => fd('การชำระค่าบริการ', 'text', 'option', false, 'svpay'),
                'maend' => fd('MA ถึงวันที่ (เฉพาะงานต่อ MA)', 'date', 'input', false, null, ['hint' => "กรอกเฉพาะงาน 'ต่อสัญญา MA' → ชีตเครื่องแสดงวันหมด MA อัตโนมัติ"]),
                'days' => fd('ใช้เวลาปิดงาน (วัน)', 'int', 'auto'),
                'note' => fd('หมายเหตุ', 'textarea', 'input'),
            ],
            'groups' => [
                ['งานบริการ', ['id', 'date']],
                ['เครื่อง & เจ้าของ (อัตโนมัติ)', ['dev', 'devname', 'serial', 'owner', 'ownername', 'phone', 'branchname', 'cover']],
                ['รายละเอียดงาน', ['type', 'detail', 'eng', 'appt', 'close', 'jstatus']],
                ['ค่าใช้จ่าย & MA', ['fee', 'payst', 'maend', 'days']],
                ['', ['note']],
            ],
            'list' => ['id', 'date', 'dev', 'devname', 'ownername', 'cover', 'type', 'eng', 'jstatus', 'fee', 'payst', 'days'],
            'filters' => ['type', 'eng', 'jstatus', 'cover', 'payst'],
            'sort' => ['id', 'desc'],
        ],
        // ------------------------------------------------------------ T ต้นทุนเครื่อง (MANAGER เท่านั้น)
        'T' => [
            'title' => 'ต้นทุนเครื่อง', 'route' => 'costs', 'table' => 'sure_costs', 'pk' => 'dev', 'mgr' => true,
            'fields' => [
                'dev' => fd('รหัสเครื่อง', 'text', 'fk', true, 'D', ['hint' => '1 เครื่อง = 1 แถว · ห้ามซ้ำ · เลือกจากเครื่องที่พนักงานลงทะเบียนแล้ว', 'mgr' => true]),
                'devname' => fd('ยี่ห้อ / รุ่น', 'text', 'auto', false, null, $mgr),
                'serial' => fd('Serial Number', 'text', 'auto', false, null, $mgr),
                'acq' => fd('ประเภทการได้มา', 'text', 'auto', false, null, $mgr),
                'seller' => fd('รับเครื่องจาก', 'text', 'auto', false, null, $mgr),
                'status' => fd('สถานะปัจจุบัน', 'text', 'auto', false, null, $mgr),
                'cost' => fd('ต้นทุนรับซื้อ (บาท)', 'money', 'input', true, null, ['hint' => 'ฝากขาย: ใส่ยอดที่ต้องจ่ายเจ้าของเมื่อขายได้', 'mgr' => true]),
                'refurb' => fd('ค่าซ่อม / Refurbish / อะไหล่ (บาท)', 'money', 'input', false, null, $mgr),
                'other' => fd('ค่าใช้จ่ายอื่น (ขนส่ง ตรวจสภาพ ฯลฯ) (บาท)', 'money', 'input', false, null, $mgr),
                'totalcost' => fd('ต้นทุนรวม (บาท)', 'money', 'auto', false, null, $mgr),
                'paidseller' => fd('วันที่จ่ายเงินผู้ขาย', 'date', 'input', false, null, $mgr),
                'listprice' => fd('ราคาตั้งขาย (บาท)', 'money', 'auto', false, null, $mgr),
                'listgp' => fd('GP % ตามราคาตั้งขาย', 'percent', 'auto', false, null, $mgr),
                'saleprice' => fd('ราคาขายจริง ก่อน VAT (บาท)', 'money', 'auto', false, null, $mgr),
                'gp' => fd('กำไรขั้นต้น (บาท)', 'money', 'auto', false, null, $mgr),
                'gpp' => fd('GP %', 'percent', 'auto', false, null, $mgr),
                'note' => fd('หมายเหตุ', 'textarea', 'input', false, null, $mgr),
            ],
            'groups' => [
                ['เครื่อง', ['dev', 'devname', 'serial', 'acq', 'seller', 'status']],
                ['ต้นทุน (ผู้บริหารกรอก)', ['cost', 'refurb', 'other', 'totalcost', 'paidseller']],
                ['ราคา & กำไร (อัตโนมัติ)', ['listprice', 'listgp', 'saleprice', 'gp', 'gpp']],
                ['', ['note']],
            ],
            'list' => ['dev', 'devname', 'serial', 'acq', 'status', 'cost', 'refurb', 'other', 'totalcost', 'listprice', 'listgp', 'saleprice', 'gp', 'gpp'],
            'filters' => ['acq', 'status'],
            'sort' => ['dev', 'desc'],
        ],
    ];
    return $t;
}

function tdef(string $t): array
{
    $all = sure_tables();
    if (!isset($all[$t])) throw new NotFoundError('ไม่พบตาราง');
    return $all[$t];
}

/** key ของตารางจากชื่อ route เช่น customers → C */
function table_key_for_route(string $route): ?string
{
    foreach (sure_tables() as $k => $d) {
        if ($d['route'] === $route) return $k;
    }
    return null;
}

/** ชื่อคอลัมน์ในฐานข้อมูลของฟิลด์ */
function fcol(array $f, string $key): string
{
    return $f['col'] ?? $key;
}

/** ฟิลด์ที่ผู้ใช้ปัจจุบันเห็นได้ (EMPLOYEE ไม่ได้ฟิลด์ Manager เท่านั้น) */
function visible_fields(string $t, ?bool $manager = null): array
{
    $manager = $manager ?? is_manager();
    $out = [];
    foreach (tdef($t)['fields'] as $k => $f) {
        if ($f['mgr'] && !$manager) continue;
        $out[$k] = $f;
    }
    return $out;
}

// ---------------------------------------------------------------- รายการตัวเลือก (หัวข้อ 10)

/** [list_key => ชื่อรายการ] ตามลำดับในสเปก */
function option_lists(): array
{
    return [
        'ctype' => 'ประเภทลูกค้า',
        'source' => 'รู้จัก SURE จาก',
        'yesno' => 'ใช่ / ไม่ใช่',
        'cstatus' => 'สถานะลูกค้า',
        'bstatus' => 'สถานะสาขา',
        'achannel' => 'ช่องทางติดต่อ',
        'atopic' => 'เรื่องที่ติดต่อ',
        'astatus' => 'สถานะการติดตาม',
        'cat' => 'หมวดเครื่อง',
        'grade' => 'เกรดสภาพเครื่อง',
        'acq' => 'ประเภทการได้มา',
        'stock' => 'สถานะสต็อก',
        'sstatus' => 'สถานะการขาย',
        'channel' => 'ช่องทางการขาย',
        'terms' => 'เงื่อนไขการชำระ',
        'ptype' => 'ประเภทรับเงิน',
        'pmethod' => 'วิธีชำระ',
        'svtype' => 'ประเภทงานบริการ',
        'svstatus' => 'สถานะงานบริการ',
        'svpay' => 'การชำระค่าบริการ',
        'staff' => 'พนักงาน (แก้เป็นชื่อจริง)',
        'staffrole' => 'ตำแหน่ง',
    ];
}

/** ค่าเริ่มต้นของแต่ละรายการ (seed ตอนติดตั้ง) */
function option_seed(): array
{
    return [
        'ctype' => ['คลินิกความงาม', 'คลินิกผิวหนัง / เลเซอร์', 'ศูนย์ศัลยกรรม', 'โรงพยาบาล', 'Wellness / Spa', 'แพทย์ (บุคคล)', 'ตัวแทนจำหน่าย / บริษัท', 'อื่นๆ'],
        'source' => ['Facebook', 'Instagram', 'TikTok', 'LINE OA', 'เว็บไซต์ SURE', 'Google', 'ลูกค้าเดิมของ AMN', 'ลูกค้าแนะนำ', 'Sales เข้าพบ', 'งานแสดงสินค้า / สัมมนา', 'อื่นๆ'],
        'yesno' => ['ใช่', 'ไม่ใช่'],
        'cstatus' => ['ใช้งาน', 'หยุดติดต่อ'],
        'bstatus' => ['เปิดดำเนินการ', 'ปิดสาขา'],
        'achannel' => ['โทรศัพท์', 'LINE', 'เข้าพบ', 'อีเมล', 'Facebook / IG', 'งานแสดงสินค้า', 'อื่นๆ'],
        'atopic' => ['สนใจซื้อเครื่อง', 'เสนอขาย / ฝากขายเครื่องให้ SURE', 'ส่งใบเสนอราคา / ต่อรอง', 'ติดตามการชำระเงิน', 'นัดส่งมอบ / ติดตั้ง', 'บริการ / ซ่อม / MA', 'ติดตามความพึงพอใจ', 'อื่นๆ'],
        'astatus' => ['เปิด / รอติดตาม', 'ปิด – สำเร็จ', 'ปิด – ไม่สำเร็จ'],
        'cat' => ['Laser', 'HIFU / Ultrasound Lifting', 'RF / RF Microneedling', 'IPL / Light Therapy', 'Body Contouring / Cryolipolysis', 'Facial / Skin Treatment', 'Skin Analysis / Diagnostic', 'อุปกรณ์การแพทย์อื่นๆ'],
        'grade' => ['A – ดีมาก', 'B – ดี', 'C – พอใช้', 'D – ต้องซ่อม'],
        'acq' => ['ซื้อขาด', 'ฝากขาย (Consignment)', 'เทิร์น-แลกเครื่อง', 'เครื่อง Demo'],
        'stock' => ['รอตรวจสภาพ', 'กำลังซ่อม / Refurbish', 'พร้อมขาย', 'จองแล้ว', 'คืนเจ้าของ (ฝากขาย)', 'ตัดจำหน่าย'],
        'sstatus' => ['มัดจำ / จองแล้ว', 'ทำสัญญาแล้ว', 'ส่งมอบแล้ว', 'ยกเลิก'],
        'channel' => ['SURE ขายตรง', 'ผ่าน AMN', 'ตัวแทนภายนอก (Agent)', 'ลูกค้าแนะนำ (Lead Referral)'],
        'terms' => ['ชำระเต็มจำนวน', 'แบ่งชำระเป็นงวด', 'เช่าซื้อ / ลีสซิ่ง', 'เครดิตเทอม'],
        'ptype' => ['มัดจำ', 'ชำระงวด', 'ชำระส่วนที่เหลือ / เต็มจำนวน', 'คืนเงิน (ใส่ยอดติดลบ)'],
        'pmethod' => ['โอนเงิน', 'เงินสด', 'เช็ค', 'บัตรเครดิต', 'ไฟแนนซ์ / ลีสซิ่ง'],
        'svtype' => ['ติดตั้ง / อบรมการใช้งาน', 'PM ตรวจเช็คตามรอบ', 'ซ่อม', 'ต่อสัญญา MA', 'ขนย้ายเครื่อง', 'อื่นๆ'],
        'svstatus' => ['รับเรื่อง', 'กำลังดำเนินการ', 'รออะไหล่', 'ปิดงาน', 'ยกเลิก'],
        'svpay' => ['ไม่มีค่าใช้จ่าย', 'รอชำระ', 'ชำระแล้ว'],
        'staff' => ['General Manager', 'Sales Executive 1', 'Sales Coordinator 1', 'Sourcing Engineer 1', 'Service Engineer 1', 'Finance & Accounting 1', 'Digital Marketing Executive 1'],
        'staffrole' => ['General Manager', 'Sales Executive', 'Sales Coordinator', 'Sourcing Engineer', 'Service Engineer', 'Finance & Accounting', 'Digital Marketing Executive'],
    ];
}

/** คำที่ logic ใช้ — ทำเป็นค่าระบบ ลบ/เปลี่ยนชื่อไม่ได้ (สเปกหัวข้อ 5) */
function option_locked(): array
{
    return [
        'sstatus' => [SURE_CANCELLED],
        'svstatus' => [SURE_CANCELLED, SURE_JOB_CLOSED],
        'stock' => [SURE_READY],
        'ptype' => [SURE_DEPOSIT],
        'astatus' => [SURE_FU_OPEN],
        'acq' => [SURE_CONSIGN],
        'bstatus' => [SURE_BRANCH_CLOSED],
        'cstatus' => [SURE_CUST_STOPPED],
        'svtype' => [SURE_MA_RENEW],
    ];
}

/** ค่าในรายการตัวเลือก (เรียงตาม sort) */
function option_values(string $listKey): array
{
    if ($listKey === 'prov') return array_keys(provinces());
    if (!isset($GLOBALS['__OPTIONS'])) {
        $GLOBALS['__OPTIONS'] = [];
        foreach (all('SELECT list_key, value FROM sure_options ORDER BY list_key, sort, id') as $r) {
            $GLOBALS['__OPTIONS'][$r['list_key']][] = $r['value'];
        }
    }
    return $GLOBALS['__OPTIONS'][$listKey] ?? [];
}

function options_reset(): void
{
    unset($GLOBALS['__OPTIONS']);
}

// ---------------------------------------------------------------- จังหวัด → ภาค (ค่าคงที่ 77 จังหวัด)

function regions(): array
{
    return ['กรุงเทพฯ และปริมณฑล', 'ภาคกลาง', 'ภาคเหนือ', 'ภาคตะวันออกเฉียงเหนือ', 'ภาคตะวันออก', 'ภาคตะวันตก', 'ภาคใต้'];
}

/** [จังหวัด => ภาค] */
function provinces(): array
{
    static $map = null;
    if ($map !== null) return $map;
    $groups = [
        'กรุงเทพฯ และปริมณฑล' => ['กรุงเทพมหานคร', 'นนทบุรี', 'ปทุมธานี', 'สมุทรปราการ', 'สมุทรสาคร', 'นครปฐม'],
        'ภาคกลาง' => ['พระนครศรีอยุธยา', 'อ่างทอง', 'ลพบุรี', 'สิงห์บุรี', 'ชัยนาท', 'สระบุรี', 'นครนายก', 'สุพรรณบุรี', 'สมุทรสงคราม', 'นครสวรรค์', 'อุทัยธานี', 'กำแพงเพชร', 'พิจิตร', 'พิษณุโลก', 'เพชรบูรณ์', 'สุโขทัย'],
        'ภาคเหนือ' => ['เชียงใหม่', 'เชียงราย', 'ลำปาง', 'ลำพูน', 'แม่ฮ่องสอน', 'น่าน', 'พะเยา', 'แพร่', 'อุตรดิตถ์'],
        'ภาคตะวันออกเฉียงเหนือ' => ['นครราชสีมา', 'ขอนแก่น', 'อุดรธานี', 'อุบลราชธานี', 'กาฬสินธุ์', 'ชัยภูมิ', 'นครพนม', 'บึงกาฬ', 'บุรีรัมย์', 'มหาสารคาม', 'มุกดาหาร', 'ยโสธร', 'ร้อยเอ็ด', 'เลย', 'ศรีสะเกษ', 'สกลนคร', 'สุรินทร์', 'หนองคาย', 'หนองบัวลำภู', 'อำนาจเจริญ'],
        'ภาคตะวันออก' => ['ชลบุรี', 'ระยอง', 'จันทบุรี', 'ตราด', 'ฉะเชิงเทรา', 'ปราจีนบุรี', 'สระแก้ว'],
        'ภาคตะวันตก' => ['กาญจนบุรี', 'ราชบุรี', 'เพชรบุรี', 'ประจวบคีรีขันธ์', 'ตาก'],
        'ภาคใต้' => ['ภูเก็ต', 'สงขลา', 'สุราษฎร์ธานี', 'นครศรีธรรมราช', 'กระบี่', 'ชุมพร', 'ตรัง', 'นราธิวาส', 'ปัตตานี', 'พังงา', 'พัทลุง', 'ยะลา', 'ระนอง', 'สตูล'],
    ];
    $map = [];
    foreach ($groups as $region => $list) {
        foreach ($list as $p) $map[$p] = $region;
    }
    return $map;
}

function region_of(?string $prov): ?string
{
    if ($prov === null || $prov === '') return null;
    return provinces()[$prov] ?? null;
}
