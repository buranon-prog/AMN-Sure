<?php
// เก็บไฟล์อัปโหลดใน storage/uploads (ปิดการเข้าถึงตรงด้วย .htaccess) ดาวน์โหลดผ่าน PHP เท่านั้นหลังตรวจสิทธิ์

const UPLOAD_ALLOWED = [
    'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'gif' => ['image/gif'],
    'webp' => ['image/webp'], 'heic' => ['image/heic', 'image/heif', 'application/octet-stream'],
    'pdf' => ['application/pdf'],
    'doc' => ['application/msword', 'application/octet-stream'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
    'ppt' => ['application/vnd.ms-powerpoint', 'application/octet-stream'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
    'txt' => ['text/plain'], 'csv' => ['text/plain', 'text/csv', 'application/csv'],
    'mp4' => ['video/mp4'], 'mov' => ['video/quicktime'],
];

/**
 * ตรวจและย้ายไฟล์ที่อัปโหลด คืน ['file_name', 'mime_type', 'size_bytes', 'storage_key'] หรือ null ถ้าไม่ได้เลือกไฟล์
 * $file = รายการหนึ่งจาก $_FILES
 */
function store_upload(?array $file): ?array
{
    if (!$file || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new AppError('ไฟล์ใหญ่เกินกว่าที่เซิร์ฟเวอร์อนุญาต (' . ini_get('upload_max_filesize') . ')');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) throw new AppError('อัปโหลดไฟล์ไม่สำเร็จ (รหัส ' . (int) $file['error'] . ')');

    $maxMb = (float) setting('max_upload_mb', 10);
    if ($file['size'] > $maxMb * 1024 * 1024) throw new AppError('ไฟล์ใหญ่เกิน ' . $maxMb . ' MB — ถ้าเป็นวิดีโอแนะนำให้แนบเป็นลิงก์แทน');

    $original = basename(str_replace('\\', '/', (string) $file['name']));
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!isset(UPLOAD_ALLOWED[$ext])) {
        throw new AppError('ไม่รองรับไฟล์ชนิด .' . e($ext) . ' (รองรับ: ' . implode(', ', array_keys(UPLOAD_ALLOWED)) . ')');
    }
    $mime = 'application/octet-stream';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $detected = finfo_file($fi, $file['tmp_name']);
        finfo_close($fi);
        if ($detected) $mime = $detected;
    }
    if (!in_array($mime, UPLOAD_ALLOWED[$ext], true)) {
        throw new AppError('เนื้อหาไฟล์ไม่ตรงกับนามสกุล .' . $ext);
    }

    $sub = date('Y/m');
    $dir = STORAGE_DIR . '/uploads/' . $sub;
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) throw new AppError('สร้างโฟลเดอร์เก็บไฟล์ไม่ได้ ตรวจสิทธิ์โฟลเดอร์ storage/');
    $key = $sub . '/' . uuid() . '.' . $ext;
    $dest = STORAGE_DIR . '/uploads/' . $key;
    $moved = is_uploaded_file($file['tmp_name']) ? move_uploaded_file($file['tmp_name'], $dest) : (defined('TESTING') && rename($file['tmp_name'], $dest));
    if (!$moved) throw new AppError('บันทึกไฟล์ไม่สำเร็จ');
    @chmod($dest, 0640);

    return [
        'file_name' => mb_substr($original, 0, 255),
        'mime_type' => $mime,
        'size_bytes' => (int) $file['size'],
        'storage_key' => $key,
    ];
}

function storage_path(string $key): string
{
    if (!preg_match('#^\d{4}/\d{2}/[0-9a-f\-]{36}\.[a-z0-9]{1,5}$#', $key)) throw new NotFoundError('ไม่พบไฟล์');
    return STORAGE_DIR . '/uploads/' . $key;
}

function send_file(string $path, string $downloadName, string $mime): void
{
    if (!is_file($path)) throw new NotFoundError('ไม่พบไฟล์บนเซิร์ฟเวอร์');
    $inline = (bool) preg_match('#^(image/(jpeg|png|gif|webp)|application/pdf|video/mp4)$#', $mime);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'; sandbox");
    $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName);
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
    header('Cache-Control: private, max-age=0');
    readfile($path);
}

function human_size(?int $bytes): string
{
    if ($bytes === null) return '—';
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 0) . ' KB';
    return number_format($bytes / 1048576, 1) . ' MB';
}
