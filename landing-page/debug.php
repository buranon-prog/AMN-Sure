<?php
// ไฟล์นี้ใช้หาสาเหตุที่หน้าเว็บขึ้นขาวชั่วคราวเท่านั้น
// ลบไฟล์นี้ทิ้งทันทีหลังจากหาสาเหตุเจอแล้ว (ไม่ควรเก็บไว้บนเว็บจริง)
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo '<pre style="font-family:monospace;white-space:pre-wrap;padding:20px;">';
echo "PHP version: " . PHP_VERSION . "\n\n";

echo "Step 1: loading config.php ... ";
require_once __DIR__ . '/config.php';
echo "OK\n\n";

echo "Step 2: loading includes/db.php ... ";
require_once __DIR__ . '/includes/db.php';
echo "OK\n\n";

echo "Step 3: loading includes/helpers.php ... ";
require_once __DIR__ . '/includes/helpers.php';
echo "OK\n\n";

echo "Step 4: connecting to database ... ";
$db = getDb();
echo "OK\n\n";

echo "Step 5: querying products table ... ";
$stmt = $db->query('SELECT COUNT(*) FROM products');
$count = $stmt->fetchColumn();
echo "OK (พบสินค้า $count รายการ)\n\n";

echo "ทุกอย่างทำงานได้ปกติ!";
echo '</pre>';
