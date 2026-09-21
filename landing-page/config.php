<?php
// ตั้งค่าการเชื่อมต่อฐานข้อมูล MySQL — แก้ค่าด้านล่างให้ตรงกับฐานข้อมูลที่สร้างไว้ใน cPanel
// (MySQL Database Wizard) ก่อนอัปโหลดไฟล์นี้ขึ้นโฮสต์จริง
define('DB_HOST', 'localhost');
define('DB_NAME', 'amnsureco_amnsure_used');
define('DB_USER', 'amnsureco_gmamnsure');
define('DB_PASS', 'ใส่รหัสผ่านฐานข้อมูลที่คุณสุ่ม/ตั้งไว้ตอนสร้าง user ตรงนี้');

// ข้อมูลล็อกอินหน้าแอดมิน — เปลี่ยนรหัสผ่านก่อนใช้งานจริงเสมอ
// วิธีสร้างรหัสผ่านใหม่: รันคำสั่งนี้ครั้งเดียวแล้วลบไฟล์ทิ้ง
//   php -r "echo password_hash('รหัสผ่านใหม่ของคุณ', PASSWORD_DEFAULT);"
// แล้วนำค่าที่ได้มาแทนที่ ADMIN_PASSWORD_HASH ด้านล่าง
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', '$2y$12$qvoA5zfPLIed33lDI0ec1eCbvuMffPGpKeg/GKH1tjpmSAQ5E1iTW'); // ค่าเริ่มต้น: ChangeMe123!

define('SITE_NAME', 'AMN SURE');
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('UPLOAD_URL', 'uploads');
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5MB
