<?php
// การเข้าสู่ระบบแต่ละเครื่อง (ตาราง user_sessions) + เครื่องมือของ GM: ดูผู้ใช้ออนไลน์ / คุมสถานะบัญชีของทุกคน
// GM = Manager ที่มี users.is_gm = 1 (คนเดียว ตั้งจากตัวติดตั้ง) — Manager คนอื่นแก้บัญชี GM ไม่ได้

const ONLINE_MINUTES = 5;           // ใช้งานภายใน 5 นาทีล่าสุด = ออนไลน์
const SESSION_TOUCH_SECONDS = 60;   // อัปเดตเวลาใช้งานล่าสุดไม่เกินนาทีละครั้ง
const SESSION_KEEP_DAYS = 90;       // เก็บประวัติการเข้าสู่ระบบ 90 วัน

function session_idle_minutes(): int
{
    return (int) setting('session_idle_minutes', 480);
}

/** เข้าสู่ระบบสำเร็จ → บันทึกเครื่องนี้ คืนรหัส session (เก็บใน $_SESSION['sid']) */
function session_record_start(string $userId): string
{
    $id = bin2hex(random_bytes(16));
    $now = now();
    $ua = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    db_insert('user_sessions', ['id' => $id, 'user_id' => $userId, 'ip' => client_ip(), 'user_agent' => $ua !== '' ? $ua : null, 'created_at' => $now, 'last_seen_at' => $now]);
    q('DELETE FROM user_sessions WHERE last_seen_at < ?', [date('Y-m-d H:i:s', time() - SESSION_KEEP_DAYS * 86400)]);
    return $id;
}

/** ทุก request: เครื่องนี้ยังไม่ถูกสั่งออก → อัปเดตเวลาใช้งานล่าสุด · คืน false = ต้องออกจากระบบ */
function session_record_touch(string $sid, string $userId): bool
{
    if ($sid === '') return false;
    $row = one('SELECT ended_at, last_seen_at FROM user_sessions WHERE id = ? AND user_id = ?', [$sid, $userId]);
    if (!$row || $row['ended_at'] !== null) return false;
    if (strtotime($row['last_seen_at']) < time() - SESSION_TOUCH_SECONDS) {
        q('UPDATE user_sessions SET last_seen_at = ?, ip = ? WHERE id = ?', [now(), client_ip(), $sid]);
    }
    return true;
}

function session_record_end(string $sid, string $reason): void
{
    q('UPDATE user_sessions SET ended_at = ?, end_reason = ? WHERE id = ? AND ended_at IS NULL', [now(), $reason, $sid]);
}

/** ปิดทุกเครื่องของผู้ใช้ (ยกเว้น $exceptSid) — ใช้คู่กับการเพิ่ม session_version */
function sessions_end_for_user(string $userId, string $reason, ?string $exceptSid = null): void
{
    q('UPDATE user_sessions SET ended_at = ?, end_reason = ? WHERE user_id = ? AND ended_at IS NULL AND id <> ?', [now(), $reason, $userId, $exceptSid ?? '']);
}

/** เวลาที่ใช้ตัดว่า session ยังเปิดอยู่ (ไม่ได้ใช้งานเกินกำหนด = ระบบให้ออกเองแล้ว) */
function sessions_live_since(): string
{
    return date('Y-m-d H:i:s', time() - session_idle_minutes() * 60);
}

/** online | idle (เปิดค้างไว้ ไม่ได้ใช้งาน) | offline */
function presence_of(int $liveDevices, ?string $lastSeen): string
{
    if ($liveDevices < 1 || !$lastSeen) return 'offline';
    return strtotime($lastSeen) >= time() - ONLINE_MINUTES * 60 ? 'online' : 'idle';
}

/** "Chrome · Windows" จาก User-Agent */
function ua_label(?string $ua): string
{
    $ua = (string) $ua;
    if ($ua === '') return 'ไม่ทราบ';
    $browser = 'เบราว์เซอร์อื่น';
    foreach (['Edg/' => 'Edge', 'OPR/' => 'Opera', 'SamsungBrowser' => 'Samsung Internet', 'Line/' => 'LINE', 'Firefox/' => 'Firefox', 'CriOS' => 'Chrome', 'Chrome/' => 'Chrome', 'Safari/' => 'Safari'] as $k => $name) {
        if (stripos($ua, $k) !== false) { $browser = $name; break; }
    }
    $os = '';
    foreach (['iPhone' => 'iPhone', 'iPad' => 'iPad', 'Android' => 'Android', 'Windows' => 'Windows', 'Macintosh' => 'Mac', 'CrOS' => 'ChromeOS', 'Linux' => 'Linux'] as $k => $name) {
        if (stripos($ua, $k) !== false) { $os = $name; break; }
    }
    if ($browser === 'เบราว์เซอร์อื่น' && $os === '') return mb_substr($ua, 0, 40);
    return $browser . ($os !== '' ? ' · ' . $os : '');
}

/** "3 นาทีที่แล้ว" */
function ago(?string $datetime): string
{
    if (!$datetime) return '—';
    $s = max(0, time() - (int) strtotime($datetime));
    if ($s < 60) return 'เมื่อสักครู่';
    if ($s < 3600) return intdiv($s, 60) . ' นาทีที่แล้ว';
    if ($s < 86400) return intdiv($s, 3600) . ' ชั่วโมงที่แล้ว';
    if ($s < 7 * 86400) return intdiv($s, 86400) . ' วันที่แล้ว';
    return dt($datetime);
}

// ---------------------------------------------------------------- GM: ข้อมูล

/** ทุกบัญชี + สถานะออนไลน์ / จำนวนเครื่องที่เปิดอยู่ / ล็อกจากรหัสผิด */
function gm_users(): array
{
    require_gm();
    $max = (int) setting('login_max_attempts', 5);
    $lockSince = date('Y-m-d H:i:s', time() - (int) setting('login_lock_minutes', 15) * 60);
    $rows = all("SELECT u.id, u.name, u.email, u.username, u.role, u.is_gm, u.active, u.activated_at, u.last_login_at,
                   (SELECT COUNT(*) FROM user_sessions s WHERE s.user_id = u.id AND s.ended_at IS NULL AND s.last_seen_at > ?) AS devices,
                   (SELECT MAX(s.last_seen_at) FROM user_sessions s WHERE s.user_id = u.id) AS last_seen_at,
                   (SELECT COUNT(*) FROM login_attempts a WHERE a.login IN (u.email, u.username) AND a.success = 0 AND a.created_at > ?) AS fails
                 FROM users u ORDER BY u.is_gm DESC, u.active DESC, u.role, u.name", [sessions_live_since(), $lockSince]);
    foreach ($rows as &$r) {
        $r['devices'] = (int) $r['devices'];
        $r['presence'] = presence_of($r['devices'], $r['last_seen_at']);
        $r['locked'] = (int) $r['fails'] >= $max;
    }
    unset($r);
    return $rows;
}

/** เครื่องที่เข้าสู่ระบบอยู่ตอนนี้ (ล่าสุดก่อน) */
function gm_sessions(): array
{
    require_gm();
    $rows = all('SELECT s.*, u.name, u.email, u.role, u.is_gm FROM user_sessions s JOIN users u ON u.id = s.user_id
                 WHERE s.ended_at IS NULL AND s.last_seen_at > ? ORDER BY s.last_seen_at DESC', [sessions_live_since()]);
    foreach ($rows as &$r) $r['presence'] = presence_of(1, $r['last_seen_at']);
    unset($r);
    return $rows;
}

/** ประวัติล่าสุด: เข้าสู่ระบบสำเร็จ / ออกจากระบบ / รหัสผ่านหรือรหัสยืนยันผิด */
function gm_activity(int $limit = 40): array
{
    require_gm();
    $events = [];
    foreach (all('SELECT s.created_at, s.ip, s.user_agent, u.name, u.email FROM user_sessions s JOIN users u ON u.id = s.user_id ORDER BY s.created_at DESC LIMIT ' . $limit) as $r) {
        $events[] = ['at' => $r['created_at'], 'kind' => 'login', 'name' => $r['name'], 'email' => $r['email'], 'ip' => $r['ip'], 'detail' => ua_label($r['user_agent'])];
    }
    foreach (all('SELECT s.ended_at, s.end_reason, s.ip, u.name, u.email FROM user_sessions s JOIN users u ON u.id = s.user_id WHERE s.ended_at IS NOT NULL ORDER BY s.ended_at DESC LIMIT ' . $limit) as $r) {
        $events[] = ['at' => $r['ended_at'], 'kind' => 'end', 'name' => $r['name'], 'email' => $r['email'], 'ip' => $r['ip'], 'detail' => session_end_label($r['end_reason'])];
    }
    foreach (all("SELECT a.created_at, a.login, a.ip, u.name FROM login_attempts a LEFT JOIN users u ON u.email = a.login OR u.username = a.login
                  WHERE a.success = 0 AND a.login NOT LIKE 'activate:%' ORDER BY a.created_at DESC LIMIT " . $limit) as $r) {
        $events[] = ['at' => $r['created_at'], 'kind' => 'fail', 'name' => $r['name'], 'email' => $r['login'], 'ip' => $r['ip'], 'detail' => $r['name'] ? 'รหัสผ่านหรือรหัสยืนยันผิด' : 'อีเมลที่ไม่มีบัญชี'];
    }
    usort($events, function ($a, $b) { return strcmp($b['at'], $a['at']); });
    return array_slice($events, 0, $limit);
}

function session_end_label(?string $reason): string
{
    $labels = ['logout' => 'ออกจากระบบเอง', 'idle' => 'ไม่ได้ใช้งานนานเกิน — ระบบให้ออก', 'revoked' => 'บัญชีถูกปิด / ตั้งรหัสผ่านใหม่', 'kicked' => 'GM สั่งออกจากระบบ'];
    return $labels[$reason] ?? 'ออกจากระบบ';
}

// ---------------------------------------------------------------- GM: คำสั่ง

/** ผู้ใช้เป้าหมายของคำสั่ง GM (ต้องไม่ใช่ตัวเอง) */
function gm_target(string $userId): array
{
    require_gm();
    $u = one('SELECT * FROM users WHERE id = ?', [$userId]);
    if (!$u) throw new NotFoundError('ไม่พบผู้ใช้');
    if ($u['id'] === current_user_id()) throw new AppError('ใช้กับบัญชีของตัวเองไม่ได้');
    return $u;
}

/** ให้เครื่องเดียวออกจากระบบ */
function gm_kick_session(string $sid): array
{
    require_gm();
    $s = one('SELECT s.*, u.name FROM user_sessions s JOIN users u ON u.id = s.user_id WHERE s.id = ?', [$sid]);
    if (!$s) throw new NotFoundError('ไม่พบการเข้าสู่ระบบนี้');
    if ($sid === ($_SESSION['sid'] ?? null)) throw new AppError('นี่คือเครื่องที่คุณใช้อยู่ — ใช้เมนู "ออกจากระบบ"');
    if ($s['ended_at'] !== null) throw new AppError('เครื่องนี้ออกจากระบบไปแล้ว');
    session_record_end($sid, 'kicked');
    return $s;
}

/** ให้ทุกเครื่องของผู้ใช้ออกจากระบบ (รวมคนที่กำลังรอใส่รหัสยืนยัน) */
function gm_logout_everywhere(string $userId): array
{
    $u = gm_target($userId);
    tx(function () use ($u) {
        q('UPDATE users SET session_version = session_version + 1, updated_at = ?, updated_by = ? WHERE id = ?', [now6(), current_user_id(), $u['id']]);
        q("UPDATE auth_tokens SET used_at = ? WHERE user_id = ? AND purpose = 'login' AND used_at IS NULL", [now(), $u['id']]);
        sessions_end_for_user($u['id'], 'kicked');
    });
    return $u;
}

/** เปิด/ปิดบัญชี (ปิด = ออกจากระบบทุกเครื่องทันที) */
function gm_set_active(string $userId, bool $active): array
{
    $u = gm_target($userId);
    tx(function () use ($u, $active) {
        if ($active) {
            q('UPDATE users SET active = 1, updated_at = ?, updated_by = ? WHERE id = ?', [now6(), current_user_id(), $u['id']]);
        } else {
            q('UPDATE users SET active = 0, session_version = session_version + 1, updated_at = ?, updated_by = ? WHERE id = ?', [now6(), current_user_id(), $u['id']]);
            q("UPDATE auth_tokens SET used_at = ? WHERE user_id = ? AND used_at IS NULL", [now(), $u['id']]);
            sessions_end_for_user($u['id'], 'revoked');
        }
        ensure_manager_remains();
    });
    return $u;
}

/** ปลดล็อกบัญชีที่ใส่รหัสผิดเกินกำหนด (ล้างประวัติรหัสผิดของบัญชีนี้) */
function gm_unlock(string $userId): array
{
    $u = gm_target($userId);
    q('DELETE FROM login_attempts WHERE login IN (?, ?) AND success = 0', [mb_strtolower((string) $u['email']), $u['username']]);
    return $u;
}
