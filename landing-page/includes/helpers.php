<?php
require_once __DIR__ . '/../config.php';

function h(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function statusLabel(string $status): string {
    $labels = [
        'available' => 'พร้อมขาย',
        'sold' => 'ขายแล้ว',
        'hidden' => 'ซ่อนอยู่',
    ];
    return $labels[$status] ?? $status;
}

/**
 * ตรวจสอบและบันทึกไฟล์รูปภาพหนึ่งไฟล์ (ชนิด/ขนาด) ด้วยชื่อสุ่ม
 * คืนค่า path (สัมพัทธ์กับ UPLOAD_URL)
 * โยน RuntimeException ถ้าไฟล์ไม่ผ่านการตรวจสอบ
 */
function saveUploadedImage(string $tmpName, int $size): string {
    if ($size > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('มีไฟล์ที่ใหญ่เกินไป (จำกัดไม่เกิน 5MB ต่อรูป)');
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = mime_content_type($tmpName);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('รองรับเฉพาะไฟล์รูปภาพ JPG, PNG หรือ WEBP เท่านั้น');
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $destination = UPLOAD_DIR . '/' . $filename;
    if (!move_uploaded_file($tmpName, $destination)) {
        throw new RuntimeException('บันทึกไฟล์ไม่สำเร็จ');
    }

    return UPLOAD_URL . '/' . $filename;
}

/**
 * รับไฟล์อัปโหลดหลายไฟล์จาก $_FILES[$field] (input แบบ name="field[]" multiple)
 * คืนค่าเป็น array ของ path ที่บันทึกสำเร็จ (ว่างถ้าไม่มีไฟล์แนบมา)
 * โยน RuntimeException ถ้ามีไฟล์ใดไฟล์หนึ่งไม่ผ่านการตรวจสอบ
 */
function handleMultipleImageUploads(string $field): array {
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['tmp_name'] ?? null)) {
        return [];
    }

    $paths = [];
    $count = count($_FILES[$field]['tmp_name']);
    for ($i = 0; $i < $count; $i++) {
        $error = $_FILES[$field]['error'][$i];
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('อัปโหลดไฟล์ไม่สำเร็จ (error code: ' . $error . ')');
        }
        $paths[] = saveUploadedImage($_FILES[$field]['tmp_name'][$i], (int) $_FILES[$field]['size'][$i]);
    }

    return $paths;
}
