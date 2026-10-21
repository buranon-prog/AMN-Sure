<?php
// ส่งอีเมล (ลิงก์เปิดใช้งาน / รหัสยืนยันเข้าสู่ระบบ)
// ตั้งค่าใน app/config.php → 'mail' => [
//   'transport' => 'smtp' | 'mail' | 'file',
//   'host', 'port', 'encryption' => 'ssl' | 'tls' | 'none', 'username', 'password',   (เฉพาะ smtp)
//   'from' => 'noreply@amnsure.co.th', 'from_name' => 'AMN SURE',
//   'verify_peer' => true,                                                           (false ใช้กับ server ทดสอบเท่านั้น)
//   'dir' => '/path',                                                                 (เฉพาะ file: เขียนอีเมลเป็นไฟล์ ใช้ตอนพัฒนา/ทดสอบ)
// ]

class MailError extends RuntimeException {}

function mail_configured(): bool
{
    $c = config('mail');
    return is_array($c) && !empty($c['transport']) && !empty($c['from']);
}

/** ส่งอีเมล 1 ฉบับ (ข้อความ + HTML) ไม่สำเร็จโยน MailError */
function send_mail(string $to, string $subject, string $text, string $html, ?array $cfg = null): void
{
    $cfg = $cfg ?? config('mail');
    if (!is_array($cfg) || empty($cfg['transport']) || empty($cfg['from'])) throw new MailError('ยังไม่ได้ตั้งค่าการส่งอีเมลใน app/config.php');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) throw new MailError('อีเมลผู้รับไม่ถูกต้อง');
    $from = (string) $cfg['from'];
    $fromName = (string) ($cfg['from_name'] ?? APP_NAME);
    [$headers, $body] = mail_build($from, $fromName, $to, $subject, $text, $html);

    switch ($cfg['transport']) {
        case 'smtp':
            smtp_send($cfg, $from, $to, $headers . "\r\n" . $body);
            return;
        case 'mail':
            // PHP mail(): หัวเรื่องและผู้รับส่งแยก ส่วนหัวอื่นอยู่ใน $headers
            $extra = preg_replace('/^(To|Subject): .*\r\n/mi', '', $headers);
            $ok = @mail($to, mail_header_encode($subject), $body, rtrim($extra, "\r\n"), '-f' . $from);
            if (!$ok) throw new MailError('ฟังก์ชัน mail() ของโฮสต์ส่งอีเมลไม่สำเร็จ');
            return;
        case 'file':
            $dir = (string) ($cfg['dir'] ?? (STORAGE_DIR . '/mail'));
            if (!is_dir($dir)) @mkdir($dir, 0770, true);
            // ชื่อไฟล์เรียงตามเวลาถึงระดับไมโครวินาที (ฉบับล่าสุดอยู่ท้ายเสมอ)
            $t = microtime(true);
            $name = $dir . '/' . date('Ymd-His', (int) $t) . sprintf('-%06d-', (int) (fmod($t, 1) * 1000000)) . substr(uuid(), 0, 8) . '.eml';
            if (@file_put_contents($name, $headers . "\r\n" . $body) === false) throw new MailError('เขียนไฟล์อีเมลไม่ได้: ' . $dir);
            return;
    }
    throw new MailError('transport ของอีเมลไม่ถูกต้อง: ' . $cfg['transport']);
}

/** สร้างหัวและเนื้อหาอีเมลแบบ multipart/alternative (UTF-8, base64) */
function mail_build(string $from, string $fromName, string $to, string $subject, string $text, string $html): array
{
    $boundary = 'b' . bin2hex(random_bytes(12));
    $domain = substr(strrchr($from, '@') ?: '@localhost', 1);
    $headers = 'Date: ' . date('r') . "\r\n"
        . 'From: ' . mail_header_encode($fromName) . ' <' . $from . ">\r\n"
        . 'To: <' . $to . ">\r\n"
        . 'Subject: ' . mail_header_encode($subject) . "\r\n"
        . 'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . ">\r\n"
        . "MIME-Version: 1.0\r\n"
        . 'Content-Type: multipart/alternative; boundary="' . $boundary . "\"\r\n"
        . "Auto-Submitted: auto-generated\r\n";
    $body = '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text)) . "\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html)) . "\r\n"
        . '--' . $boundary . "--\r\n";
    return [$headers, $body];
}

/** หัวอีเมลภาษาไทยตาม RFC 2047 */
function mail_header_encode(string $s): string
{
    if (preg_match('/^[\x20-\x7e]*$/', $s)) return $s;
    return '=?UTF-8?B?' . base64_encode($s) . '?=';
}

// ---------------------------------------------------------------- SMTP client (ไม่ต้องติดตั้ง library เพิ่ม)

function smtp_send(array $cfg, string $from, string $to, string $message): void
{
    $host = (string) ($cfg['host'] ?? '');
    $port = (int) ($cfg['port'] ?? 465);
    $enc = (string) ($cfg['encryption'] ?? 'ssl');
    if ($host === '') throw new MailError('ยังไม่ได้ตั้งค่า SMTP host');
    $verify = !array_key_exists('verify_peer', $cfg) || $cfg['verify_peer'];
    $ctx = stream_context_create(['ssl' => ['verify_peer' => $verify, 'verify_peer_name' => $verify, 'allow_self_signed' => !$verify, 'SNI_enabled' => true, 'peer_name' => $host]]);
    $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) throw new MailError("เชื่อมต่อ SMTP $host:$port ไม่ได้ ($errstr)");
    stream_set_timeout($fp, 20);
    try {
        smtp_expect($fp, [220]);
        $ehlo = 'EHLO ' . (preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['SERVER_NAME'] ?? '') ?: 'localhost');
        smtp_cmd($fp, $ehlo, [250]);
        if ($enc === 'tls') {
            smtp_cmd($fp, 'STARTTLS', [220]);
            $method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) $method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            if (!@stream_socket_enable_crypto($fp, true, $method)) throw new MailError('เปิด TLS กับ SMTP ไม่สำเร็จ');
            smtp_cmd($fp, $ehlo, [250]);
        }
        if (!empty($cfg['username'])) {
            smtp_cmd($fp, 'AUTH LOGIN', [334]);
            smtp_cmd($fp, base64_encode((string) $cfg['username']), [334], true);
            smtp_cmd($fp, base64_encode((string) ($cfg['password'] ?? '')), [235], true);
        }
        smtp_cmd($fp, 'MAIL FROM:<' . $from . '>', [250]);
        smtp_cmd($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_cmd($fp, 'DATA', [354]);
        // dot-stuffing: บรรทัดที่ขึ้นต้นด้วย "." ต้องเติม "." อีกตัว
        $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $message));
        fwrite($fp, str_replace("\n", "\r\n", $data) . "\r\n.\r\n");
        smtp_expect($fp, [250]);
        @fwrite($fp, "QUIT\r\n");
    } finally {
        fclose($fp);
    }
}

function smtp_cmd($fp, string $cmd, array $ok, bool $secret = false): string
{
    fwrite($fp, $cmd . "\r\n");
    return smtp_expect($fp, $ok, $secret ? '(รหัส)' : $cmd);
}

/** อ่านคำตอบ SMTP (หลายบรรทัดได้) ตรวจรหัสตอบกลับ */
function smtp_expect($fp, array $ok, string $after = 'connect'): string
{
    $resp = '';
    while (($line = fgets($fp, 1024)) !== false) {
        $resp .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') break;
    }
    $code = (int) substr($resp, 0, 3);
    if (!in_array($code, $ok, true)) {
        $cmd = preg_replace('/\s.*/', '', $after);
        throw new MailError('SMTP ตอบกลับไม่สำเร็จหลัง ' . $cmd . ': ' . trim($resp ?: 'ไม่มีคำตอบ (หมดเวลา)'));
    }
    return $resp;
}
