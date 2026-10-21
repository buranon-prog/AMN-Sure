<?php
// บัญชีผู้ใช้: สร้าง / แก้ไข / ปิด / รีเซ็ตรหัสผ่าน — Manager เท่านั้น (สเปกหัวข้อ 3)

function change_own_password(string $current, string $new, string $confirm): void
{
    $u = current_user();
    if (!$u) throw new ForbiddenError('กรุณาเข้าสู่ระบบ');
    $row = one('SELECT password_hash FROM users WHERE id = ?', [$u['id']]);
    if (!password_verify($current, $row['password_hash'])) throw new AppError('รหัสผ่านปัจจุบันไม่ถูกต้อง');
    password_policy($new);
    if ($new !== $confirm) throw new AppError('ยืนยันรหัสผ่านใหม่ไม่ตรงกัน');
    if (password_verify($new, $row['password_hash'])) throw new AppError('รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสเดิม');
    q('UPDATE users SET password_hash = ?, must_change_password = 0, session_version = session_version + 1, updated_at = ?, updated_by = ? WHERE id = ?',
        [password_hash($new, PASSWORD_DEFAULT), now6(), $u['id'], $u['id']]);
}

/**
 * สร้าง/แก้ไขผู้ใช้
 * $d: username, email, name, role, active, can_view, can_edit, password (เฉพาะตอนสร้าง = รหัสชั่วคราว)
 */
function user_save(?string $id, array $d): string
{
    require_manager();
    $username = mb_strtolower((string) s($d['username'] ?? ''));
    $email = v_email($d['email'] ?? null, 'อีเมล');
    $name = s($d['name'] ?? '');
    $role = v_in($d['role'] ?? null, [ROLE_MANAGER, ROLE_EMPLOYEE], 'ประเภทบัญชี');
    $active = !empty($d['active']) ? 1 : 0;
    $canView = !empty($d['can_view']);
    $canEdit = !empty($d['can_edit']);

    if (!$name) throw new AppError('กรุณากรอกชื่อ-นามสกุล');
    v_maxlen($name, 190, 'ชื่อ-นามสกุล');
    if (!valid_username($username)) throw new AppError('ชื่อผู้ใช้ใช้ได้เฉพาะ a-z 0-9 . _ - ยาว 2–60 ตัว');

    return tx(function () use ($id, $username, $email, $name, $role, $active, $canView, $canEdit, $d) {
        if (val('SELECT id FROM users WHERE username = ? AND id <> ?', [$username, $id ?? ''])) throw new AppError('ชื่อผู้ใช้นี้มีคนใช้แล้ว');
        if ($email && val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $id ?? ''])) throw new AppError('อีเมลนี้มีคนใช้แล้ว');
        if ($id) {
            $before = one('SELECT * FROM users WHERE id = ? FOR UPDATE', [$id]);
            if (!$before) throw new NotFoundError('ไม่พบผู้ใช้');
            if ($id === current_user_id() && (!$active || $role !== ROLE_MANAGER)) throw new AppError('ปิดบัญชีหรือลดสิทธิ์ของตัวเองไม่ได้');
            $data = ['username' => $username, 'email' => $email, 'name' => $name, 'role' => $role, 'active' => $active];
            // เปลี่ยนประเภทบัญชี / ปิดบัญชี → session เดิมหลุดทันที
            if ($before['role'] !== $role || ((int) $before['active'] === 1 && !$active)) $data['session_version'] = (int) $before['session_version'] + 1;
            db_update('users', 'id', $id, $data);
        } else {
            $pw = (string) ($d['password'] ?? '');
            password_policy($pw);
            $id = create_user_raw($username, $email, $name, $pw, $role, $canView, $canEdit, true);
            if (!$active) db_update('users', 'id', $id, ['active' => 0]);
        }
        set_user_permission($id, $canView, $canEdit);
        ensure_manager_remains();
        return $id;
    });
}

function user_reset_password(string $id, string $tempPassword): void
{
    require_manager();
    password_policy($tempPassword);
    if (!val('SELECT id FROM users WHERE id = ?', [$id])) throw new NotFoundError('ไม่พบผู้ใช้');
    q('UPDATE users SET password_hash = ?, must_change_password = 1, session_version = session_version + 1, updated_at = ?, updated_by = ? WHERE id = ?',
        [password_hash($tempPassword, PASSWORD_DEFAULT), now6(), current_user_id(), $id]);
}

/** ต้องมี Manager ที่ใช้งานได้อย่างน้อย 1 คนเสมอ (กันล็อกตัวเองออกจากระบบ) */
function ensure_manager_remains(): void
{
    if ((int) val("SELECT COUNT(*) FROM users WHERE role = ? AND active = 1", [ROLE_MANAGER]) < 1) {
        throw new AppError('ต้องมีบัญชี Manager ที่ใช้งานได้อย่างน้อย 1 บัญชี');
    }
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
