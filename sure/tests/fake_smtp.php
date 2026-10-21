<?php
// SMTP server ปลอม สำหรับทดสอบการส่งอีเมล (ไม่ส่งออกจริง เขียนอีเมลที่ได้รับเป็นไฟล์ .eml)
// php tests/fake_smtp.php <port> <dir> [plain|ssl] [cert.pem]
//   plain = รับแบบไม่เข้ารหัส และรองรับ STARTTLS ถ้าระบุ cert · ssl = เข้ารหัสตั้งแต่ต้น (port 465)
// รองรับ EHLO, STARTTLS, AUTH LOGIN (รหัส "wrong" = ไม่ผ่าน), MAIL FROM, RCPT TO, DATA, QUIT

if (PHP_SAPI !== 'cli') exit;
$port = (int) ($argv[1] ?? 2525);
$dir = $argv[2] ?? sys_get_temp_dir() . '/fake-smtp';
$mode = $argv[3] ?? 'plain';
$cert = $argv[4] ?? null;
@mkdir($dir, 0777, true);

$ctx = stream_context_create(['ssl' => ['local_cert' => $cert, 'verify_peer' => false, 'allow_self_signed' => true]]);
$server = stream_socket_server(($mode === 'ssl' ? 'ssl' : 'tcp') . "://127.0.0.1:$port", $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $ctx);
if (!$server) {
    fwrite(STDERR, "listen failed: $errstr\n");
    exit(1);
}
while (true) {
    $c = @stream_socket_accept($server, -1);
    if (!$c) continue;
    stream_set_timeout($c, 10);
    fake_smtp_session($c, $dir, $mode, $cert);
    @fclose($c);
}

function fake_smtp_session($c, string $dir, string $mode, ?string $cert): void
{
    $out = function (string $s) use ($c) { fwrite($c, $s . "\r\n"); };
    $out('220 fake.smtp ESMTP ready');
    $from = $to = null;
    while (($line = fgets($c)) !== false) {
        $cmd = strtoupper(substr(trim($line), 0, 4));
        if ($cmd === 'EHLO' || $cmd === 'HELO') {
            $out('250-fake.smtp');
            if ($cert && $mode === 'plain') $out('250-STARTTLS');
            $out('250 AUTH LOGIN');
        } elseif (strtoupper(trim($line)) === 'STARTTLS') {
            $out('220 go ahead');
            if (!@stream_socket_enable_crypto($c, true, STREAM_CRYPTO_METHOD_TLS_SERVER)) return;
        } elseif ($cmd === 'AUTH') {
            $out('334 VXNlcm5hbWU6');
            fgets($c);
            $out('334 UGFzc3dvcmQ6');
            $pass = base64_decode(trim((string) fgets($c)));
            if ($pass === 'wrong') { $out('535 5.7.8 authentication failed'); continue; }
            $out('235 2.7.0 authenticated');
        } elseif ($cmd === 'MAIL') {
            $from = trim(substr($line, 10));
            $out('250 ok');
        } elseif ($cmd === 'RCPT') {
            $to = trim(substr($line, 8));
            $out('250 ok');
        } elseif ($cmd === 'DATA') {
            $out('354 end with .');
            $data = '';
            while (($l = fgets($c)) !== false) {
                if (rtrim($l, "\r\n") === '.') break;
                $data .= (substr($l, 0, 2) === '..') ? substr($l, 1) : $l;
            }
            $t = microtime(true);
            file_put_contents($dir . '/' . date('Ymd-His', (int) $t) . sprintf('-%06d-', (int) (fmod($t, 1) * 1000000)) . bin2hex(random_bytes(4)) . '.eml', "X-Envelope-From: $from\r\nX-Envelope-To: $to\r\n" . $data);
            $out('250 queued');
        } elseif ($cmd === 'QUIT') {
            $out('221 bye');
            return;
        } else {
            $out('500 unknown command');
        }
    }
}
