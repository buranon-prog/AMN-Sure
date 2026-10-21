<?php
// อ่านอีเมลที่ระบบส่ง (ไฟล์ .eml จาก transport 'file' หรือ tests/fake_smtp.php) สำหรับ tests

/** อีเมลฉบับล่าสุดถึง $to ที่เขียนหลังเวลา $after (timestamp) → ['subject' => …, 'text' => เนื้อหาข้อความ] หรือ null */
function mailbox_latest(string $dir, string $to, float $after = 0): ?array
{
    $files = glob(rtrim($dir, '/') . '/*.eml') ?: [];
    rsort($files, SORT_STRING); // ชื่อไฟล์ขึ้นต้นด้วยเวลาถึงไมโครวินาที → ฉบับล่าสุดก่อน
    foreach ($files as $f) {
        if (filemtime($f) + 1 < $after) continue;
        $raw = (string) file_get_contents($f);
        if (stripos($raw, 'To: <' . $to . '>') === false) continue;
        return mailbox_parse($raw);
    }
    return null;
}

function mailbox_parse(string $raw): array
{
    $subject = preg_match('/^Subject: (.*)$/mi', $raw, $m) ? trim($m[1]) : '';
    if (preg_match('/^=\?UTF-8\?B\?(.*)\?=$/i', $subject, $mm)) $subject = (string) base64_decode($mm[1]);
    $text = '';
    $html = '';
    if (preg_match('/boundary="([^"]+)"/', $raw, $b)) {
        foreach (explode('--' . $b[1], $raw) as $part) {
            $split = preg_split("/\r?\n\r?\n/", $part, 2);
            if (count($split) < 2) continue;
            [$head, $body] = $split;
            $decoded = stripos($head, 'base64') !== false ? (string) base64_decode(preg_replace('/\s+/', '', $body)) : $body;
            if (stripos($head, 'text/plain') !== false) $text = $decoded;
            if (stripos($head, 'text/html') !== false) $html = $decoded;
        }
    }
    return ['subject' => $subject, 'text' => $text, 'html' => $html];
}

/** รหัส 6 หลักในอีเมลยืนยันเข้าสู่ระบบ ("123 456") */
function mailbox_code(?array $mail): ?string
{
    return $mail && preg_match('/(\d{3}) (\d{3})/', $mail['text'], $m) ? $m[1] . $m[2] : null;
}

/** token ในลิงก์เปิดใช้งาน / ตั้งรหัสผ่าน */
function mailbox_token(?array $mail): ?string
{
    return $mail && preg_match('/r=auth\.activate&t=([A-Za-z0-9_-]+)/', $mail['text'], $m) ? $m[1] : null;
}
