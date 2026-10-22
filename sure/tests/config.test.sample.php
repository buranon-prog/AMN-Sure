<?php
// คัดลอกเป็น tests/config.test.php แล้วใส่ฐานข้อมูลสำหรับทดสอบ (ตารางในฐานข้อมูลนี้จะถูกลบทุกครั้งที่รัน)
return [
    'db' => ['host' => '127.0.0.1', 'port' => 3306, 'name' => 'sure_test', 'user' => 'sure_test', 'pass' => 'change-me'],
    'timezone' => 'Asia/Bangkok',
    'debug' => true,
    // (ไม่บังคับ) ฐานข้อมูลว่างอีกอันที่ผู้ใช้มีสิทธิ์เฉพาะฐานข้อมูลนั้น — ใช้ทดสอบว่าตัวติดตั้งยอมรับฐานข้อมูลที่แยกถูกต้อง
    // 'isolated_db' => ['host' => '127.0.0.1', 'port' => 3306, 'name' => 'sure_iso', 'user' => 'sure_iso', 'pass' => 'change-me'],
];
