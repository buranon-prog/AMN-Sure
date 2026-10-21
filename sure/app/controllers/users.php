<?php
// บัญชีผู้ใช้ (หน้า admin/users) — MANAGER เท่านั้น: สร้าง / ปิด / รีเซ็ตรหัสผ่าน / กำหนดสิทธิ์ SURE

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
    render('users/form', ['title' => 'เพิ่มผู้ใช้', 'u' => null, 'temp' => random_temp_password()]);
}

function c_users_edit(): void
{
    require_manager();
    $id = (string) get('id', '');
    $u = one('SELECT u.*, COALESCE(p.can_view, 0) AS can_view, COALESCE(p.can_edit, 0) AS can_edit FROM users u
              LEFT JOIN user_permissions p ON p.user_id = u.id AND p.module = ? WHERE u.id = ?', [SURE_MODULE, $id]);
    if (!$u) throw new NotFoundError('ไม่พบผู้ใช้');
    render('users/form', ['title' => 'แก้ไขผู้ใช้', 'u' => $u, 'temp' => random_temp_password()]);
}

function c_users_post_save(): void
{
    $id = post('id');
    $data = $_POST;
    $data['active'] = post_bool('active');
    $data['can_view'] = post_bool('can_view');
    $data['can_edit'] = post_bool('can_edit');
    $newId = user_save($id ?: null, $data);
    if (!$id) {
        flash('success', 'สร้างบัญชีแล้ว — แจ้งผู้ใช้: ชื่อผู้ใช้ "' . mb_strtolower((string) post('username')) . '" รหัสผ่านชั่วคราว "' . (string) post('password') . '" (ระบบจะให้เปลี่ยนรหัสเองตอนเข้าครั้งแรก)');
    } else {
        flash('success', 'บันทึกแล้ว สิทธิ์ใหม่มีผลทันที');
    }
    redirect('users.edit', ['id' => $newId]);
}

function c_users_post_reset(): void
{
    $id = (string) post('id', '');
    $pw = (string) (post('temp_password') ?? random_temp_password());
    user_reset_password($id, $pw);
    flash('success', 'ตั้งรหัสผ่านชั่วคราวแล้ว: "' . $pw . '" — ผู้ใช้ถูกออกจากระบบทุกเครื่อง และต้องเปลี่ยนรหัสใหม่ตอนเข้าครั้งถัดไป');
    redirect('users.edit', ['id' => $id]);
}
