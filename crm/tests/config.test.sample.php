<?php
// คัดลอกเป็น tests/config.test.php แล้วใส่ฐานข้อมูลสำหรับทดสอบ (ตารางในฐานข้อมูลนี้จะถูกลบทุกครั้งที่รัน)
return [
    'db' => ['host' => '127.0.0.1', 'port' => 3306, 'name' => 'amncrm_test', 'user' => 'amncrm', 'pass' => 'devpass'],
    'timezone' => 'Asia/Bangkok',
    'debug' => true,
];
