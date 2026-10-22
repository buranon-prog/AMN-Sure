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
    // เครื่องอื่นหลุด เครื่องนี้ใช้ต่อได้
    sessions_end_for_user($u['id'], 'revoked', $_SESSION['sid'] ?? null);
}

/**
 * สร้าง/แก้ไขผู้ใช้ — อีเมลบังคับ (ใช้เข้าสู่ระบบและรับรหัสยืนยัน) · ผู้ใช้สมัครเองไม่ได้
 * $d: email, name, role, active, can_view, can_edit
 * คืน ['id' => …, 'invite' => true เมื่อต้องส่งลิงก์เปิดใช้งาน (บัญชีใหม่ หรือเปลี่ยนอีเมล)]
 */
function user_save(?string $id, array $d): array
{
    require_manager();
    $email = v_email($d['email'] ?? null, 'อีเมล', true);
    $name = s($d['name'] ?? '');
    $role = v_in($d['role'] ?? null, [ROLE_MANAGER, ROLE_EMPLOYEE], 'ประเภทบัญชี');
    $active = !empty($d['active']) ? 1 : 0;
    $canView = !empty($d['can_view']);
    $canEdit = !empty($d['can_edit']);

    if (!$name) throw new AppError('กรุณากรอกชื่อ-นามสกุล');
    v_maxlen($name, 190, 'ชื่อ-นามสกุล');
    v_maxlen($email, 190, 'อีเมล');

    return tx(function () use ($id, $email, $name, $role, $active, $canView, $canEdit) {
        if (val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $id ?? ''])) throw new AppError('อีเมลนี้มีบัญชีอยู่แล้ว');
        $invite = false;
        if ($id) {
            $before = one('SELECT * FROM users WHERE id = ? FOR UPDATE', [$id]);
            if (!$before) throw new NotFoundError('ไม่พบผู้ใช้');
            if ((int) $before['is_gm'] === 1 && !is_gm()) throw new ForbiddenError('บัญชี GM แก้ไขได้โดย GM เท่านั้น');
            if ($id === current_user_id() && (!$active || $role !== ROLE_MANAGER)) throw new AppError('ปิดบัญชีหรือลดสิทธิ์ของตัวเองไม่ได้');
            $data = ['email' => $email, 'name' => $name, 'role' => $role, 'active' => $active];
            $emailChanged = mb_strtolower((string) $before['email']) !== $email;
            if ($emailChanged && $id === current_user_id()) throw new AppError('เปลี่ยนอีเมลของตัวเองไม่ได้ — ให้ Manager อีกคนเปลี่ยนให้');
            // เปลี่ยนประเภทบัญชี / ปิดบัญชี / เปลี่ยนอีเมล → session เดิมหลุดทันที
            if ($before['role'] !== $role || ((int) $before['active'] === 1 && !$active) || $emailChanged) {
                $data['session_version'] = (int) $before['session_version'] + 1;
                sessions_end_for_user($id, 'revoked');
            }
            if ($emailChanged) {
                // อีเมลใหม่ต้องยืนยันก่อน: รหัสผ่านเดิมใช้ไม่ได้ ต้องตั้งใหม่จากลิงก์ที่ส่งไปอีเมลใหม่
                $data['password_hash'] = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                $data['activated_at'] = null;
                $invite = true;
            }
            db_update('users', 'id', $id, $data);
        } else {
            $id = create_user_invited($email, $name, $role, $canView, $canEdit);
            if (!$active) db_update('users', 'id', $id, ['active' => 0]);
            $invite = true;
        }
        set_user_permission($id, $canView, $canEdit);
        ensure_manager_remains();
        return ['id' => $id, 'invite' => $invite && $active];
    });
}

/** บัญชีที่ยังไม่มีรหัสผ่าน (รอผู้ใช้กดลิงก์ในอีเมลเพื่อตั้งรหัสเอง) คืน id */
function create_user_invited(string $email, string $name, string $role, bool $canView, bool $canEdit): string
{
    $id = uuid();
    db_insert('users', [
        'id' => $id,
        'username' => username_from_email($email),
        'email' => mb_strtolower($email),
        'name' => $name,
        'role' => $role,
        'active' => 1,
        'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        'must_change_password' => 0,
        'session_version' => 1,
        'activated_at' => null,
    ]);
    set_user_permission($id, $canView, $canEdit);
    return $id;
}

/** ชื่อผู้ใช้ภายใน (ไม่แสดงผู้ใช้) สร้างจากส่วนหน้าของอีเมล เช่น somchai.s@… → somchai.s (ซ้ำ → somchai.s2) */
function username_from_email(string $email): string
{
    $local = mb_strtolower(substr($email, 0, (int) strpos($email . '@', '@')));
    $base = trim(preg_replace('/[^a-z0-9._-]+/', '.', $local), '.-_');
    if (strlen($base) < 2) $base = 'user';
    $base = substr($base, 0, 50);
    $u = $base;
    for ($i = 2; val('SELECT id FROM users WHERE username = ?', [$u]); $i++) $u = $base . $i;
    return $u;
}

/**
 * Manager ส่งลิงก์ตั้งรหัสผ่านทางอีเมล (บัญชีที่ยังไม่เปิดใช้งาน = ส่งลิงก์เปิดใช้งานอีกครั้ง)
 * บัญชีที่ใช้งานอยู่: รหัสผ่านเดิมใช้ไม่ได้ทันที และหลุดออกจากระบบทุกเครื่อง จนกว่าจะตั้งรหัสใหม่จากลิงก์
 */
function user_send_password_link(string $id): array
{
    require_manager();
    $u = one('SELECT * FROM users WHERE id = ?', [$id]);
    if (!$u) throw new NotFoundError('ไม่พบผู้ใช้');
    if ((int) $u['is_gm'] === 1 && !is_gm()) throw new ForbiddenError('บัญชี GM แก้ไขได้โดย GM เท่านั้น');
    if ((int) $u['active'] !== 1) throw new AppError('บัญชีนี้ถูกปิดอยู่ — เปิดบัญชีก่อน');
    if (empty($u['email'])) throw new AppError('บัญชีนี้ยังไม่มีอีเมล — ใส่อีเมลก่อน');
    $reset = $u['activated_at'] !== null;
    if ($reset) {
        if ($id === current_user_id()) throw new AppError('ใช้หน้า "เปลี่ยนรหัสผ่าน" สำหรับบัญชีของตัวเอง');
        q('UPDATE users SET password_hash = ?, session_version = session_version + 1, updated_at = ?, updated_by = ? WHERE id = ?',
            [password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), now6(), current_user_id(), $id]);
        sessions_end_for_user($id, 'revoked');
    }
    issue_activation($u, $reset);
    return $u;
}

/** ต้องมี Manager ที่ใช้งานได้อย่างน้อย 1 คนเสมอ (กันล็อกตัวเองออกจากระบบ) */
function ensure_manager_remains(): void
{
    if ((int) val("SELECT COUNT(*) FROM users WHERE role = ? AND active = 1", [ROLE_MANAGER]) < 1) {
        throw new AppError('ต้องมีบัญชี Manager ที่ใช้งานได้อย่างน้อย 1 บัญชี');
    }
}
