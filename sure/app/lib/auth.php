<?php
// Session, CSRF (ขั้นตอนเข้าสู่ระบบ + ยืนยันทางอีเมล อยู่ใน twofactor.php)
// - โหลดผู้ใช้และ role จากฐานข้อมูลทุก request: ปิดบัญชี/เปลี่ยนสิทธิ์แล้วมีผลทันที
// - session_version: เพิ่มค่าเมื่อรีเซ็ตรหัส/เปลี่ยนอีเมล → session เก่าทุกเครื่องหลุดทันที

function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = is_https();
    session_name('AMNSURESESS');
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

/** ผู้ใช้ปัจจุบัน (array: id, username, name, role, sure_view, sure_edit …) หรือ null */
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
    $user = load_user($_SESSION['uid']);
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

function load_user(string $id): ?array
{
    $user = one('SELECT u.id, u.username, u.email, u.name, u.role, u.active, u.must_change_password, u.session_version,
                        COALESCE(p.can_view, 0) AS sure_view, COALESCE(p.can_edit, 0) AS sure_edit
                 FROM users u LEFT JOIN user_permissions p ON p.user_id = u.id AND p.module = ?
                 WHERE u.id = ?', [SURE_MODULE, $id]);
    if (!$user) return null;
    $user['active'] = (int) $user['active'];
    $user['sure_view'] = (int) $user['sure_view'];
    $user['sure_edit'] = (int) $user['sure_edit'];
    return $user;
}

/** ใช้ใน automated test: ทำงานในนามผู้ใช้คนนี้ */
function act_as(?string $userId): void
{
    $GLOBALS['__USER'] = $userId ? load_user($userId) : null;
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
