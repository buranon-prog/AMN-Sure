<?php
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json; charset=utf-8');

function respond(bool $success, string $message = ''): void {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respond(false, 'Method not allowed');
}

// honeypot: real visitors never fill this hidden field, bots often do
if (!empty($_POST['website'])) {
    respond(true);
}

$name = trim($_POST['name'] ?? '');
$clinicName = trim($_POST['clinic_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$product = trim($_POST['product'] ?? '');

if ($name === '' || $phone === '') {
    respond(false, 'กรุณากรอกชื่อและเบอร์โทรศัพท์');
}

if (mb_strlen($name) > 150 || mb_strlen($clinicName) > 150 || mb_strlen($phone) > 30 || mb_strlen($product) > 255) {
    respond(false, 'ข้อมูลที่กรอกยาวเกินไป');
}

try {
    $stmt = getDb()->prepare(
        'INSERT INTO inquiries (name, clinic_name, phone, interested_product) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$name, $clinicName ?: null, $phone, $product ?: null]);
    respond(true);
} catch (PDOException $e) {
    http_response_code(500);
    respond(false, 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง');
}
