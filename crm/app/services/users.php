<?php
// บัญชีผู้ใช้: พนักงานแต่ละคนมีชื่อผู้ใช้/รหัสผ่านของตัวเอง สร้างโดย GM หรือ Admin เท่านั้น

function change_own_password(string $current, string $new, string $confirm): void
{
    $u = current_user();
    if (!$u) throw new ForbiddenError('กรุณาเข้าสู่ระบบ');
    $row = one('SELECT password_hash FROM users WHERE id = ?', [$u['id']]);
    if (!password_verify($current, $row['password_hash'])) throw new AppError('รหัสผ่านปัจจุบันไม่ถูกต้อง');
    password_policy($new);
    if ($new !== $confirm) throw new AppError('ยืนยันรหัสผ่านใหม่ไม่ตรงกัน');
    if (password_verify($new, $row['password_hash'])) throw new AppError('รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสเดิม');
    tx(function () use ($u, $new) {
        q('UPDATE users SET password_hash = ?, must_change_password = 0, session_version = session_version + 1, updated_at = ?, updated_by = ? WHERE id = ?',
            [password_hash($new, PASSWORD_DEFAULT), now(), $u['id'], $u['id']]);
        audit_log('USER', $u['id'], 'UPDATE', [['field' => 'password', 'old' => '***', 'new' => '***']], 'เปลี่ยนรหัสผ่านด้วยตนเอง');
    });
}

/** role ที่ผู้ใช้ปัจจุบันมอบให้คนอื่นได้ (Admin มอบ GM ไม่ได้ เพื่อไม่ให้ยกระดับสิทธิ์ตัวเองได้) */
function assignable_roles(): array
{
    if (has_role('GM')) return ROLE_CODES;
    return array_values(array_diff(ROLE_CODES, ['GM']));
}

function user_roles_of(string $userId): array
{
    return array_column(all('SELECT r.code FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?', [$userId]), 'code');
}

/**
 * สร้าง/แก้ไขผู้ใช้
 * $d: username, email, name, phone, roles[], active, password (เฉพาะตอนสร้าง = รหัสชั่วคราว)
 */
function user_save(?string $id, array $d): string
{
    require_cap('admin.users');
    $username = mb_strtolower((string) s($d['username'] ?? ''));
    $email = v_email($d['email'] ?? null, 'อีเมล');
    $name = s($d['name'] ?? '');
    $phone = s($d['phone'] ?? '');
    $roles = array_values(array_unique(array_filter((array) ($d['roles'] ?? []), 'is_string')));
    $active = !empty($d['active']) ? 1 : 0;

    if (!$name) throw new AppError('กรุณากรอกชื่อ-นามสกุล');
    if (!preg_match('/^[a-z0-9._-]{2,60}$/', $username)) throw new AppError('ชื่อผู้ใช้ใช้ได้เฉพาะ a-z 0-9 . _ - ยาว 2–60 ตัว');
    if (!$roles) throw new AppError('กรุณาเลือกอย่างน้อย 1 role');
    foreach ($roles as $r) {
        if (!in_array($r, ROLE_CODES, true)) throw new AppError('role ไม่ถูกต้อง');
    }

    return tx(function () use ($id, $username, $email, $name, $phone, $roles, $active, $d) {
        $dupe = val('SELECT id FROM users WHERE username = ? AND id <> ?', [$username, $id ?? '']);
        if ($dupe) throw new AppError('ชื่อผู้ใช้นี้มีคนใช้แล้ว');
        if ($email && val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $id ?? ''])) throw new AppError('อีเมลนี้มีคนใช้แล้ว');

        $before = $id ? user_roles_of($id) : [];
        $assignable = assignable_roles();
        // ห้ามเพิ่มหรือถอด role ที่ตัวเองไม่มีสิทธิ์มอบ (เช่น Admin แตะ role GM ไม่ได้)
        foreach (array_merge(array_diff($roles, $before), array_diff($before, $roles)) as $changed) {
            if (!in_array($changed, $assignable, true)) throw new AppError('คุณไม่มีสิทธิ์มอบหรือถอด role ' . role_name($changed));
        }

        if ($id) {
            db_get('users', $id, 'ผู้ใช้');
            if ($id === current_user_id() && !$active) throw new AppError('ปิดบัญชีของตัวเองไม่ได้');
            update_audited('users', 'USER', $id, ['username' => $username, 'email' => $email, 'name' => $name, 'phone' => $phone, 'active' => $active]);
        } else {
            $pw = (string) ($d['password'] ?? '');
            password_policy($pw);
            $id = db_insert('users', [
                'username' => $username, 'email' => $email, 'name' => $name, 'phone' => $phone,
                'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'active' => $active,
                'must_change_password' => 1,
            ]);
            audit_create('USER', $id, ['username' => $username, 'email' => $email, 'name' => $name, 'phone' => $phone, 'active' => $active]);
        }

        if ($before !== $roles || !$before) {
            q('DELETE FROM user_roles WHERE user_id = ?', [$id]);
            foreach ($roles as $code) {
                db_insert('user_roles', ['user_id' => $id, 'role_id' => val('SELECT id FROM roles WHERE code = ?', [$code]), 'created_at' => now()]);
            }
            sort($before);
            $sorted = $roles;
            sort($sorted);
            if ($before !== $sorted) {
                audit_log('USER', $id, 'UPDATE', [['field' => 'roles', 'old' => implode(',', $before), 'new' => implode(',', $sorted)]]);
            }
        }
        ensure_admin_remains();
        return $id;
    });
}

/** ต้องมี GM หรือ Admin ที่ active อย่างน้อย 1 คนเสมอ ไม่งั้นจะไม่มีใครจัดการผู้ใช้ได้ */
function ensure_admin_remains(): void
{
    $n = (int) val("SELECT COUNT(DISTINCT u.id) FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id
                    WHERE u.active = 1 AND r.code IN ('GM', 'ADMIN')");
    if ($n < 1) throw new AppError('ต้องมีผู้ใช้ GM หรือ Admin ที่ใช้งานได้อย่างน้อย 1 คน');
}

/** ตั้งรหัสผ่านชั่วคราวให้ผู้ใช้ (ผู้ใช้ต้องเปลี่ยนเองตอนเข้าสู่ระบบครั้งถัดไป) และเตะ session เดิมทิ้ง */
function user_reset_password(string $id, string $tempPassword): void
{
    require_cap('admin.users');
    password_policy($tempPassword);
    $target = db_get('users', $id, 'ผู้ใช้');
    if (in_array('GM', user_roles_of($id), true) && !has_role('GM')) throw new AppError('เฉพาะ GM เท่านั้นที่รีเซ็ตรหัสผ่านของ GM ได้');
    tx(function () use ($target, $tempPassword) {
        q('UPDATE users SET password_hash = ?, must_change_password = 1, session_version = session_version + 1, updated_at = ?, updated_by = ? WHERE id = ?',
            [password_hash($tempPassword, PASSWORD_DEFAULT), now(), current_user_id(), $target['id']]);
        audit_log('USER', $target['id'], 'UPDATE', [['field' => 'password', 'old' => '***', 'new' => '***']], 'รีเซ็ตรหัสผ่านโดยผู้ดูแล');
    });
}

/** ปิด/เปิดบัญชี ปิดแล้วถูกออกจากระบบทันที (ตรวจทุก request) */
function user_set_active(string $id, bool $active): void
{
    require_cap('admin.users');
    if ($id === current_user_id() && !$active) throw new AppError('ปิดบัญชีของตัวเองไม่ได้');
    if (in_array('GM', user_roles_of($id), true) && !has_role('GM')) throw new AppError('เฉพาะ GM เท่านั้นที่ปิดบัญชี GM ได้');
    tx(function () use ($id, $active) {
        update_audited('users', 'USER', $id, ['active' => $active ? 1 : 0]);
        if (!$active) q('UPDATE users SET session_version = session_version + 1 WHERE id = ?', [$id]);
        ensure_admin_remains();
    });
}

function random_temp_password(): string
{
    $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
    $digits = '23456789';
    $pw = '';
    for ($i = 0; $i < 8; $i++) $pw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    for ($i = 0; $i < 3; $i++) $pw .= $digits[random_int(0, strlen($digits) - 1)];
    return str_shuffle($pw);
}
