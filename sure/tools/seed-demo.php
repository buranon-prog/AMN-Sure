<?php
// ใส่ข้อมูลตัวอย่าง (สมมติ) จากสเปกหัวข้อ 11 — สำหรับ dev/ทดสอบเท่านั้น ห้ามรันบน production
// วิธีใช้ (command line เท่านั้น):  php tools/seed-demo.php <อีเมล Manager>
// ปฏิเสธถ้า: เรียกผ่านเว็บ, ยังไม่ได้ติดตั้ง, หรือมีข้อมูลในตารางลูกค้า/เครื่อง/การขายอยู่แล้ว

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/demo_data.php';

if (!app_installed()) {
    fwrite(STDERR, "ยังไม่ได้ติดตั้งระบบ (ไม่พบ app/config.php)\n");
    exit(1);
}
$username = $argv[1] ?? null;
if (!$username) {
    fwrite(STDERR, "ระบุอีเมล Manager: php tools/seed-demo.php <email>\n");
    exit(1);
}
$mid = val('SELECT id FROM users WHERE (username = ? OR email = ?) AND role = ? AND active = 1', [mb_strtolower($username), mb_strtolower($username), ROLE_MANAGER]);
if (!$mid) {
    fwrite(STDERR, "ไม่พบบัญชี Manager $username\n");
    exit(1);
}
foreach (['sure_customers', 'sure_devices', 'sure_sales'] as $t) {
    if ((int) val('SELECT COUNT(*) FROM ' . $t) > 0) {
        fwrite(STDERR, "มีข้อมูลในตาราง $t อยู่แล้ว — ไม่ใส่ข้อมูลตัวอย่างทับ\n");
        exit(1);
    }
}
demo_seed($mid);
$n = [];
foreach (sure_tables() as $k => $d) $n[] = $d['title'] . ' ' . (int) val('SELECT COUNT(*) FROM ' . $d['table']);
echo "ใส่ข้อมูลตัวอย่างแล้ว: " . implode(' · ', $n) . "\n";
