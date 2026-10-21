<?php
// ยืนยันตัวตนทางอีเมล
// 1) ครั้งแรก: Manager สร้างบัญชีด้วยอีเมล (สมัครเองไม่ได้) → ระบบส่งลิงก์เปิดใช้งาน → ผู้ใช้กดลิงก์ ตั้งรหัสผ่านเอง
//    ลิงก์ขอใหม่ได้จากหน้า "เข้าใช้ครั้งแรก" (เฉพาะอีเมลที่ Manager สร้างไว้และยังไม่เปิดใช้งาน)
// 2) ทุกครั้งที่เข้าสู่ระบบ: อีเมล + รหัสผ่านถูก → ระบบส่งรหัส 6 หลักไปที่อีเมล → ใส่รหัสถูกจึงเข้าได้
// เก็บเฉพาะ HMAC ของลิงก์/รหัสในตาราง auth_tokens · ลิงก์ใช้ได้ครั้งเดียว 48 ชม. · รหัสใช้ได้ 10 นาที ผิดได้ 5 ครั้ง

const ACTIVATION_HOURS = 48;
const LOGIN_CODE_MINUTES = 10;
const LOGIN_CODE_MAX_TRIES = 5;
const LOGIN_CODE_RESEND_SECONDS = 60;
const LOGIN_CODE_MAX_SENDS = 5;

/** ปิดชั่วคราวได้ด้วย 'email_2fa' => false ใน app/config.php (กรณีส่งอีเมลไม่ได้ — แก้ผ่าน File Manager ของ cPanel) */
function email_2fa_enabled(): bool
{
    return config('email_2fa', true) !== false;
}

function app_key(): string
{
    $k = config('app_key');
    return is_string($k) && $k !== '' ? $k : hash('sha256', 'sure|' . json_encode(config('db')));
}

function token_hash(string $secret): string
{
    return hash_hmac('sha256', $secret, app_key());
}

/** ที่อยู่เว็บสำหรับลิงก์ในอีเมล (ตั้งตอนติดตั้ง — ไม่เชื่อ Host header ของ request) */
function base_url(): string
{
    $u = config('base_url');
    if (is_string($u) && $u !== '') return rtrim($u, '/') . '/';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return (is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir . '/';
}

/** b•••••n@amn.co.th */
function mask_email(string $email): string
{
    $at = strrpos($email, '@');
    if ($at === false) return $email;
    $local = substr($email, 0, $at);
    $n = strlen($local);
    $shown = $n <= 2 ? substr($local, 0, 1) . '•' : substr($local, 0, 1) . str_repeat('•', min(6, $n - 2)) . substr($local, -1);
    return $shown . substr($email, $at);
}

// ---------------------------------------------------------------- ลิงก์เปิดใช้งาน / ตั้งรหัสผ่านใหม่

/**
 * ออกลิงก์ใหม่ (ลิงก์เก่าที่ยังไม่ใช้ใช้ไม่ได้ทันที) แล้วส่งอีเมล · ส่งไม่สำเร็จโยน MailError
 * $reset = true เมื่อ Manager สั่งตั้งรหัสผ่านใหม่ให้บัญชีที่เคยใช้งานแล้ว
 */
function issue_activation(array $user, bool $reset = false): void
{
    if (empty($user['email'])) throw new MailError('บัญชีนี้ยังไม่มีอีเมล');
    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    q("UPDATE auth_tokens SET used_at = ? WHERE user_id = ? AND purpose = 'activate' AND used_at IS NULL", [now(), $user['id']]);
    db_insert('auth_tokens', [
        'user_id' => $user['id'], 'purpose' => 'activate', 'secret_hash' => token_hash('activate|' . $token),
        'expires_at' => date('Y-m-d H:i:s', time() + ACTIVATION_HOURS * 3600), 'ip' => client_ip(), 'created_at' => now6(),
    ]);
    $link = base_url() . 'index.php?r=auth.activate&t=' . $token;
    $name = (string) $user['name'];
    if ($reset) {
        $subject = 'ตั้งรหัสผ่านใหม่ ' . APP_NAME;
        $lead = 'Manager ขอให้คุณตั้งรหัสผ่านใหม่สำหรับบัญชี ' . APP_NAME . ' (' . $user['email'] . ')';
        $button = 'ตั้งรหัสผ่านใหม่';
    } else {
        $subject = 'เปิดใช้งานบัญชี ' . APP_NAME;
        $lead = 'Manager สร้างบัญชี ' . APP_NAME . ' ให้คุณแล้ว (' . $user['email'] . ')';
        $button = 'ตั้งรหัสผ่านและเข้าใช้งาน';
    }
    $note = 'ลิงก์นี้ใช้ได้ครั้งเดียว ภายใน ' . ACTIVATION_HOURS . ' ชั่วโมง · ถ้าคุณไม่ได้คาดว่าจะได้รับอีเมลนี้ ไม่ต้องทำอะไร';
    $text = "สวัสดีคุณ $name\n\n$lead\nกดลิงก์ด้านล่างเพื่อ$button:\n\n$link\n\n$note\n";
    $html = mail_layout(
        '<p style="margin:0 0 12px">สวัสดีคุณ ' . e($name) . '</p><p style="margin:0 0 24px">' . e($lead) . '</p>'
        . '<p style="margin:0 0 24px"><a href="' . e($link) . '" style="display:inline-block;background:#0E7C74;color:#ffffff;text-decoration:none;font-weight:600;padding:14px 28px;border-radius:10px">' . e($button) . '</a></p>'
        . '<p style="margin:0 0 8px;font-size:13px;color:#5B6B6E">ถ้าปุ่มกดไม่ได้ คัดลอกลิงก์นี้ไปเปิดในเบราว์เซอร์:</p>'
        . '<p style="margin:0 0 24px;font-size:13px;word-break:break-all"><a href="' . e($link) . '" style="color:#0E7C74">' . e($link) . '</a></p>'
        . '<p style="margin:0;font-size:13px;color:#5B6B6E">' . e($note) . '</p>'
    );
    send_mail((string) $user['email'], $subject, $text, $html);
}

/** ลิงก์ที่ยังใช้ได้ → [token row + ข้อมูลผู้ใช้] หรือ null */
function find_activation(string $token): ?array
{
    if (!preg_match('/^[A-Za-z0-9_-]{40,60}$/', $token)) return null;
    return one("SELECT t.id AS token_id, u.id, u.name, u.email, u.active FROM auth_tokens t JOIN users u ON u.id = t.user_id
                WHERE t.purpose = 'activate' AND t.secret_hash = ? AND t.used_at IS NULL AND t.expires_at > ? AND u.active = 1",
        [token_hash('activate|' . $token), now()]);
}

/** ตั้งรหัสผ่านจากลิงก์ → เปิดใช้งานบัญชี คืนแถวผู้ใช้ (ลิงก์ในอีเมล + รหัสใหม่ = ยืนยันตัวตนครบแล้ว) */
function complete_activation(string $token, string $password, string $confirm): array
{
    return tx(function () use ($token, $password, $confirm) {
        $a = find_activation($token);
        if (!$a) throw new AppError('ลิงก์นี้ใช้ไม่ได้แล้ว (หมดอายุ ใช้ไปแล้ว หรือมีลิงก์ใหม่กว่า) — ขอลิงก์ใหม่ได้ที่หน้า "เข้าใช้ครั้งแรก" หรือให้ Manager ส่งให้ใหม่');
        $row = one('SELECT id, used_at FROM auth_tokens WHERE id = ? FOR UPDATE', [$a['token_id']]);
        if (!$row || $row['used_at'] !== null) throw new AppError('ลิงก์นี้ถูกใช้ไปแล้ว');
        password_policy($password);
        if ($password !== $confirm) throw new AppError('ยืนยันรหัสผ่านไม่ตรงกัน');
        q('UPDATE auth_tokens SET used_at = ? WHERE id = ?', [now(), $a['token_id']]);
        q('UPDATE users SET password_hash = ?, activated_at = COALESCE(activated_at, ?), must_change_password = 0, session_version = session_version + 1, updated_at = ?, updated_by = id WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), now(), now6(), $a['id']]);
        return one('SELECT * FROM users WHERE id = ?', [$a['id']]);
    });
}

/**
 * หน้า "เข้าใช้ครั้งแรก": ส่งลิงก์ให้เฉพาะอีเมลที่ Manager สร้างบัญชีไว้และยังไม่เปิดใช้งาน
 * ตอบเหมือนกันทุกกรณี (ไม่บอกว่าอีเมลไหนมีบัญชี) · จำกัด 3 ครั้ง / อีเมล / 15 นาที และ 10 ครั้ง / IP
 */
function request_activation(string $email): void
{
    $email = mb_strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new AppError('กรุณากรอกอีเมลให้ถูกต้อง');
    $since = date('Y-m-d H:i:s', time() - 15 * 60);
    $key = 'activate:' . mb_substr($email, 0, 180);
    $byEmail = (int) val('SELECT COUNT(*) FROM login_attempts WHERE login = ? AND created_at > ?', [$key, $since]);
    $byIp = (int) val("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND login LIKE 'activate:%' AND created_at > ?", [client_ip(), $since]);
    if ($byEmail >= 3 || $byIp >= 10) throw new AppError('ขอลิงก์บ่อยเกินไป กรุณารอ 15 นาทีแล้วลองใหม่');
    db_insert('login_attempts', ['login' => $key, 'ip' => client_ip(), 'success' => 0, 'created_at' => now()]);
    $user = one('SELECT * FROM users WHERE email = ? AND active = 1 AND activated_at IS NULL', [$email]);
    if (!$user) return;
    try {
        issue_activation($user);
    } catch (MailError $e) {
        app_log('MAIL activation ' . $email . ': ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------- รหัสยืนยันเข้าสู่ระบบ

/**
 * ขั้นที่ 1: ตรวจอีเมล/ชื่อผู้ใช้ + รหัสผ่าน (จำกัดการเดา 5 ครั้ง / 15 นาที ต่อบัญชี, 20 ครั้งต่อ IP)
 * คืน ['user' => แถวผู้ใช้] หรือ ['error' => ข้อความ]
 */
function password_step(string $login, string $password): array
{
    $login = mb_strtolower(trim($login));
    $ip = client_ip();
    $max = (int) setting('login_max_attempts', 5);
    $lockMin = (int) setting('login_lock_minutes', 15);
    $since = date('Y-m-d H:i:s', time() - $lockMin * 60);
    $failsLogin = (int) val('SELECT COUNT(*) FROM login_attempts WHERE login = ? AND success = 0 AND created_at > ?', [$login, $since]);
    $failsIp = (int) val("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND login NOT LIKE 'activate:%' AND created_at > ?", [$ip, $since]);
    if ($failsLogin >= $max || $failsIp >= $max * 4) {
        return ['error' => "ใส่รหัสผิดหลายครั้งเกินไป กรุณารอ $lockMin นาทีแล้วลองใหม่"];
    }
    $user = $login === '' ? null : one('SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1', [$login, $login]);
    // เรียก password_verify เสมอ (แม้ไม่พบผู้ใช้) เพื่อไม่ให้เดาจากเวลาตอบกลับได้ว่าบัญชีมีจริงไหม
    $hash = $user['password_hash'] ?? password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
    $ok = password_verify($password, $hash) && $user && (int) $user['active'] === 1 && $user['activated_at'] !== null;
    db_insert('login_attempts', ['login' => mb_substr($login, 0, 190), 'ip' => $ip, 'success' => $ok ? 1 : 0, 'created_at' => now()]);
    if (!$ok) return ['error' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง'];
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    return ['user' => $user];
}

/** ขั้นที่ 2: ออกรหัส 6 หลักใหม่ (รหัสเก่าใช้ไม่ได้ทันที) ส่งอีเมล และจำสถานะ "รอยืนยัน" ไว้ใน session */
function start_login_challenge(array $user): void
{
    if (empty($user['email'])) throw new AppError('บัญชีนี้ยังไม่มีอีเมลสำหรับรับรหัสยืนยัน — ติดต่อ Manager');
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    q("UPDATE auth_tokens SET used_at = ? WHERE user_id = ? AND purpose = 'login' AND used_at IS NULL", [now(), $user['id']]);
    $tid = db_insert('auth_tokens', [
        'user_id' => $user['id'], 'purpose' => 'login', 'secret_hash' => token_hash('login|' . $user['id'] . '|' . $code),
        'expires_at' => date('Y-m-d H:i:s', time() + LOGIN_CODE_MINUTES * 60), 'ip' => client_ip(), 'created_at' => now6(),
    ]);
    $pending = $_SESSION['mfa'] ?? null;
    $sends = ($pending && ($pending['uid'] ?? null) === $user['id']) ? (int) $pending['sends'] + 1 : 1;
    if (session_status() === PHP_SESSION_ACTIVE && $sends === 1) session_regenerate_id(true);
    $_SESSION['mfa'] = ['uid' => $user['id'], 'tid' => (int) $tid, 'sent' => time(), 'started' => $pending['started'] ?? time(), 'sends' => $sends];

    $when = date('d/m/Y H:i');
    $pretty = substr($code, 0, 3) . ' ' . substr($code, 3);
    $text = "รหัสยืนยันเข้าสู่ระบบ " . APP_NAME . " ของคุณคือ\n\n    $pretty\n\nใช้ได้ภายใน " . LOGIN_CODE_MINUTES . " นาที\n"
        . "มีการเข้าสู่ระบบด้วยบัญชีของคุณเมื่อ $when จาก IP " . client_ip() . "\n"
        . "ถ้าไม่ใช่คุณ อย่าบอกรหัสนี้กับใคร และแจ้ง Manager ให้ตั้งรหัสผ่านใหม่\n";
    $html = mail_layout(
        '<p style="margin:0 0 16px">รหัสยืนยันเข้าสู่ระบบของคุณ</p>'
        . '<p style="margin:0 0 16px;font-size:36px;font-weight:700;letter-spacing:8px;color:#0B2A30">' . e($pretty) . '</p>'
        . '<p style="margin:0 0 24px;color:#5B6B6E">ใช้ได้ภายใน ' . LOGIN_CODE_MINUTES . ' นาที</p>'
        . '<p style="margin:0;font-size:13px;color:#5B6B6E">มีการเข้าสู่ระบบด้วยบัญชีของคุณเมื่อ ' . e($when) . ' จาก IP ' . e(client_ip())
        . '<br>ถ้าไม่ใช่คุณ อย่าบอกรหัสนี้กับใคร และแจ้ง Manager ให้ตั้งรหัสผ่านใหม่</p>'
    );
    send_mail((string) $user['email'], 'รหัสยืนยันเข้าสู่ระบบ ' . APP_NAME, $text, $html);
}

/** ผู้ใช้ที่กำลังรอใส่รหัส (ยังไม่หมดเวลา) หรือ null */
function pending_login(): ?array
{
    $p = $_SESSION['mfa'] ?? null;
    if (!$p || time() - (int) $p['started'] > 30 * 60) return null;
    $u = one('SELECT * FROM users WHERE id = ? AND active = 1', [$p['uid']]);
    return $u ? ['user' => $u] + $p : null;
}

/** ตรวจรหัส 6 หลัก · ถูก → เข้าสู่ระบบ คืน null · ผิด → ข้อความ (ผิดครบ 5 ครั้งต้องเริ่มใหม่) */
function verify_login_code(string $code): ?string
{
    $p = pending_login();
    if (!$p) return 'หมดเวลายืนยัน กรุณาเข้าสู่ระบบใหม่';
    $code = preg_replace('/\D/', '', $code);
    return tx(function () use ($p, $code) {
        $t = one("SELECT * FROM auth_tokens WHERE id = ? AND user_id = ? AND purpose = 'login' FOR UPDATE", [$p['tid'], $p['uid']]);
        if (!$t || $t['used_at'] !== null) return 'รหัสนี้ใช้ไม่ได้แล้ว — กด "ส่งรหัสใหม่"';
        if ($t['expires_at'] <= now()) return 'รหัสหมดอายุแล้ว — กด "ส่งรหัสใหม่"';
        if (strlen($code) === 6 && hash_equals($t['secret_hash'], token_hash('login|' . $p['uid'] . '|' . $code))) {
            q('UPDATE auth_tokens SET used_at = ? WHERE id = ?', [now(), $t['id']]);
            complete_login($p['user']);
            return null;
        }
        $tries = (int) $t['attempts'] + 1;
        q('UPDATE auth_tokens SET attempts = ?, used_at = ? WHERE id = ?', [$tries, $tries >= LOGIN_CODE_MAX_TRIES ? now() : null, $t['id']]);
        db_insert('login_attempts', ['login' => mb_substr(mb_strtolower((string) $p['user']['email']), 0, 190), 'ip' => client_ip(), 'success' => 0, 'created_at' => now()]);
        if ($tries >= LOGIN_CODE_MAX_TRIES) {
            unset($_SESSION['mfa']);
            return 'ใส่รหัสผิดเกินกำหนด กรุณาเข้าสู่ระบบใหม่';
        }
        return 'รหัสไม่ถูกต้อง (ลองได้อีก ' . (LOGIN_CODE_MAX_TRIES - $tries) . ' ครั้ง)';
    });
}

/** ส่งรหัสใหม่ (รอ 60 วินาทีระหว่างครั้ง สูงสุด 5 ครั้งต่อการเข้าสู่ระบบ 1 ครั้ง) */
function resend_login_code(): void
{
    $p = pending_login();
    if (!$p) throw new AppError('หมดเวลายืนยัน กรุณาเข้าสู่ระบบใหม่');
    $wait = LOGIN_CODE_RESEND_SECONDS - (time() - (int) $p['sent']);
    if ($wait > 0) throw new AppError("ส่งรหัสใหม่ได้อีกครั้งใน $wait วินาที");
    if ((int) $p['sends'] >= LOGIN_CODE_MAX_SENDS) {
        unset($_SESSION['mfa']);
        throw new AppError('ขอรหัสบ่อยเกินไป กรุณาเข้าสู่ระบบใหม่');
    }
    start_login_challenge($p['user']);
}

/** เข้าสู่ระบบสำเร็จ: สร้าง session ใหม่ ผูกกับ session_version ปัจจุบัน */
function complete_login(array $user): void
{
    if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
    unset($_SESSION['mfa']);
    $_SESSION['uid'] = $user['id'];
    $_SESSION['sv'] = (int) val('SELECT session_version FROM users WHERE id = ?', [$user['id']]);
    $_SESSION['last_seen'] = time();
    unset($GLOBALS['__USER']);
    q('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $user['id']]);
    act_as($user['id']);
}

// ---------------------------------------------------------------- หน้าตาอีเมล

function mail_layout(string $inner): string
{
    return '<!doctype html><html lang="th"><body style="margin:0;padding:0;background:#F6F4EE">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F6F4EE;padding:32px 12px"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:16px;overflow:hidden;font-family:Tahoma,\'Leelawadee UI\',sans-serif;color:#10262B;font-size:15px;line-height:1.6">'
        . '<tr><td style="background:#0B2A30;padding:28px 32px;text-align:center">'
        . '<div style="font-family:Georgia,serif;font-size:22px;font-weight:700;letter-spacing:6px;color:#F4EFE3">' . e(APP_NAME) . '</div>'
        . '<div style="width:40px;height:2px;background:#D4AF5A;margin:12px auto 0"></div></td></tr>'
        . '<tr><td style="padding:32px">' . $inner . '</td></tr></table>'
        . '<p style="font-family:Tahoma,sans-serif;font-size:12px;color:#5B6B6E;margin:16px 0 0">อีเมลอัตโนมัติจากระบบ ' . e(APP_NAME) . ' — ไม่ต้องตอบกลับ</p>'
        . '</td></tr></table></body></html>';
}
