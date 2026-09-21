<?php
require_once __DIR__ . '/../config.php';

function h(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function formatPrice(?string $price): string {
    if ($price === null || $price === '') {
        return 'สอบถามราคา';
    }
    return number_format((float) $price, 0) . ' บาท';
}

function statusLabel(string $status): string {
    return match ($status) {
        'available' => 'พร้อมขาย',
        'sold' => 'ขายแล้ว',
        'hidden' => 'ซ่อนอยู่',
        default => $status,
    };
}

/**
 * รับไฟล์อัปโหลดจาก $_FILES[$field], ตรวจชนิด/ขนาดไฟล์, บันทึกด้วยชื่อสุ่ม
 * คืนค่า path (สัมพัทธ์กับ UPLOAD_URL) หรือ null ถ้าไม่มีไฟล์แนบมา
 * โยน RuntimeException ถ้าไฟล์ไม่ผ่านการตรวจสอบ
 */
function handleImageUpload(string $field): ?string {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('อัปโหลดไฟล์ไม่สำเร็จ (error code: ' . $file['error'] . ')');
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('ไฟล์ใหญ่เกินไป (จำกัดไม่เกิน 5MB)');
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = mime_content_type($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('รองรับเฉพาะไฟล์รูปภาพ JPG, PNG หรือ WEBP เท่านั้น');
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $destination = UPLOAD_DIR . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('บันทึกไฟล์ไม่สำเร็จ');
    }

    return UPLOAD_URL . '/' . $filename;
}
