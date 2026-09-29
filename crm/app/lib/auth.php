<?php
// Session, login, CSRF
// - โหลดผู้ใช้และ role จากฐานข้อมูลทุก request: ปิดบัญชี/เปลี่ยนสิทธิ์แล้วมีผลทันที
// - session_version: เพิ่มค่าเมื่อรีเซ็ตรหัส → session เก่าทุกเครื่องหลุดทันที
// - จำกัดการเดารหัส: ผิดเกินกำหนดต่ออีเมลหรือต่อ IP → ล็อกชั่วคราว

function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = is_https();
    session_name('AMNCRMSESS');
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/; samesite=Lax', '', $secure, true);
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (($_SERVER['SERVER_PORT'] ?? null) == 443) return true;
    // หลัง reverse proxy / Cloudflare
    return strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/** ผู้ใช้ปัจจุบัน (array: id, name, email, roles[], caps{}) หรือ null */
function current_user(): ?array
{
    if (array_key_exists('__USER', $GLOBALS)) return $GLOBALS['__USER'];
    $GLOBALS['__USER'] = null;
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['uid'])) return null;

    $idleLimit = 60 * (int) setting('session_idle_minutes', 480);
    if (isset($_SESSION['last_seen']) && time() - (int) $_SESSION['last_seen'] > $idleLimit) {
        logout();
        return null;
    }
    $user = load_user_with_roles($_SESSION['uid']);
    if (!$user || !$user['active'] || (int) $user['session_version'] !== (int) ($_SESSION['sv'] ?? -1)) {
        logout();
        return null;
    }
    $_SESSION['last_seen'] = time();
    $GLOBALS['__USER'] = $user;
    return $user;
}

function current_user_id(): ?string
{
    $u = $GLOBALS['__USER'] ?? null;
    return $u['id'] ?? null;
}

function load_user_with_roles(string $id): ?array
{
    $user = one('SELECT id, username, email, name, phone, active, must_change_password, session_version FROM users WHERE id = ?', [$id]);
    if (!$user) return null;
    $user['roles'] = array_column(all('SELECT r.code FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? ORDER BY r.code', [$id]), 'code');
    $user['caps'] = caps_for_roles($user['roles']);
    return $user;
}

/** ใช้ใน automated test: ทำงานในนามผู้ใช้คนนี้ */
function act_as(?string $userId): void
{
    $GLOBALS['__USER'] = $userId ? load_user_with_roles($userId) : null;
}

/**
 * เข้าสู่ระบบด้วยชื่อผู้ใช้หรืออีเมล + รหัสผ่าน
 * คืน null ถ้าสำเร็จ หรือข้อความ error
 */
function attempt_login(string $login, string $password): ?string
{
    $login = mb_strtolower(trim($login));
    $ip = client_ip();
    $max = (int) setting('login_max_attempts', 5);
    $lockMin = (int) setting('login_lock_minutes', 15);
    $since = date('Y-m-d H:i:s', time() - $lockMin * 60);

    $failsLogin = (int) val('SELECT COUNT(*) FROM login_attempts WHERE login = ? AND success = 0 AND created_at > ?', [$login, $since]);
    $failsIp = (int) val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND created_at > ?', [$ip, $since]);
    if ($failsLogin >= $max || $failsIp >= $max * 4) {
        return "ใส่รหัสผ่านผิดหลายครั้งเกินไป กรุณารอ $lockMin นาทีแล้วลองใหม่";
    }

    $user = $login === '' ? null : one('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1', [$login, $login]);
    // เรียก password_verify เสมอ (แม้ไม่พบผู้ใช้) เพื่อไม่ให้เดาจากเวลาตอบกลับได้ว่าชื่อผู้ใช้มีจริงไหม
    $hash = $user['password_hash'] ?? password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
    $ok = password_verify($password, $hash) && $user && (int) $user['active'] === 1;

    db_insert('login_attempts', ['login' => mb_substr($login, 0, 190), 'ip' => $ip, 'success' => $ok ? 1 : 0, 'created_at' => now()]);
    if (!$ok) {
        audit_log('USER', $user['id'] ?? null, 'LOGIN_FAILED', null, 'login: ' . mb_substr($login, 0, 100));
        return 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
    $_SESSION['uid'] = $user['id'];
    $_SESSION['sv'] = (int) $user['session_version'];
    $_SESSION['last_seen'] = time();
    unset($GLOBALS['__USER']);
    q('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $user['id']]);
    act_as($user['id']);
    audit_log('USER', $user['id'], 'LOGIN');
    return null;
}

function logout(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
    $GLOBALS['__USER'] = null;
}

function password_policy(string $password): void
{
    if (mb_strlen($password) < 10) throw new AppError('รหัสผ่านต้องยาวอย่างน้อย 10 ตัวอักษร');
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        throw new AppError('รหัสผ่านต้องมีทั้งตัวอักษรและตัวเลข');
    }
}

// ---------------------------------------------------------------- CSRF

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        throw new ForbiddenError('แบบฟอร์มหมดอายุ กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
}
