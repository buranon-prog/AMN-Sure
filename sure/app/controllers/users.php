<?php
// บัญชีผู้ใช้ (หน้า admin/users) — MANAGER เท่านั้น: สร้าง (ด้วยอีเมล) / ปิด / ส่งลิงก์ตั้งรหัสผ่าน / กำหนดสิทธิ์ SURE
// ผู้ใช้สมัครเองไม่ได้ — ตั้งรหัสผ่านเองจากลิงก์ที่ระบบส่งไปอีเมลที่ Manager ระบุ

function c_users_index(): void
{
    require_manager();
    $rows = all('SELECT u.*, COALESCE(p.can_view, 0) AS can_view, COALESCE(p.can_edit, 0) AS can_edit FROM users u
                 LEFT JOIN user_permissions p ON p.user_id = u.id AND p.module = ? ORDER BY u.active DESC, u.role, u.name', [SURE_MODULE]);
    render('users/list', ['title' => 'บัญชีผู้ใช้', 'rows' => $rows]);
}

function c_users_new(): void
{
    require_manager();
    render('users/form', ['title' => 'เพิ่มผู้ใช้', 'u' => null]);
}

function c_users_edit(): void
{
    require_manager();
    $id = (string) get('id', '');
    $u = one('SELECT u.*, COALESCE(p.can_view, 0) AS can_view, COALESCE(p.can_edit, 0) AS can_edit FROM users u
              LEFT JOIN user_permissions p ON p.user_id = u.id AND p.module = ? WHERE u.id = ?', [SURE_MODULE, $id]);
    if (!$u) throw new NotFoundError('ไม่พบผู้ใช้');
    render('users/form', ['title' => 'แก้ไขผู้ใช้', 'u' => $u]);
}

function c_users_post_save(): void
{
    $id = post('id');
    $data = $_POST;
    $data['active'] = post_bool('active');
    $data['can_view'] = post_bool('can_view');
    $data['can_edit'] = post_bool('can_edit');
    $r = user_save($id ?: null, $data);
    $msg = $id ? 'บันทึกแล้ว สิทธิ์ใหม่มีผลทันที' : 'สร้างบัญชีแล้ว';
    if ($r['invite']) {
        $u = one('SELECT * FROM users WHERE id = ?', [$r['id']]);
        try {
            issue_activation($u);
            $msg .= ' — ส่งลิงก์ตั้งรหัสผ่านไปที่ ' . $u['email'] . ' แล้ว';
        } catch (MailError $e) {
            app_log('MAIL invite ' . $u['email'] . ': ' . $e->getMessage());
            flash('warning', $msg . ' แต่ส่งอีเมลไม่สำเร็จ (' . $e->getMessage() . ') — แก้การตั้งค่าอีเมลแล้วกด "ส่งลิงก์เปิดใช้งานอีกครั้ง"');
            redirect('users.edit', ['id' => $r['id']]);
        }
    }
    flash('success', $msg);
    redirect('users.edit', ['id' => $r['id']]);
}

function c_users_post_reset(): void
{
    $id = (string) post('id', '');
    try {
        $u = user_send_password_link($id);
    } catch (MailError $e) {
        app_log('MAIL reset link: ' . $e->getMessage());
        throw new AppError('ส่งอีเมลไม่สำเร็จ: ' . $e->getMessage());
    }
    flash('success', 'ส่งลิงก์ตั้งรหัสผ่านไปที่ ' . $u['email'] . ' แล้ว (ใช้ได้ ' . ACTIVATION_HOURS . ' ชั่วโมง)');
    redirect('users.edit', ['id' => $id]);
}
