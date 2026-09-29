<?php
// ผู้ใช้และสิทธิ์ / ข้อมูลหลัก / ตั้งค่า

function c_admin_users(): void
{
    require_cap('admin.users');
    $rows = all("SELECT u.*, GROUP_CONCAT(r.code ORDER BY r.code SEPARATOR ',') AS role_codes FROM users u
        LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id
        GROUP BY u.id ORDER BY u.active DESC, u.name");
    render('admin/users', ['title' => 'ผู้ใช้และสิทธิ์', 'rows' => $rows]);
}

function c_admin_user_new(): void
{
    require_cap('admin.users');
    render('admin/user_form', ['title' => 'เพิ่มผู้ใช้', 'u' => null, 'roles' => [], 'temp' => random_temp_password()]);
}

function c_admin_user(): void
{
    require_cap('admin.users');
    $u = db_get('users', (string) get('id', ''), 'ผู้ใช้');
    render('admin/user_form', ['title' => 'แก้ไขผู้ใช้', 'u' => $u, 'roles' => user_roles_of($u['id']), 'temp' => random_temp_password()]);
}

function c_admin_post_user_save(): void
{
    $id = post('id');
    $data = $_POST;
    $data['roles'] = isset($_POST['roles']) && is_array($_POST['roles']) ? $_POST['roles'] : [];
    $data['active'] = post_bool('active');
    $newId = user_save($id ?: null, $data);
    if (!$id) {
        flash('success', 'สร้างบัญชีแล้ว — แจ้งพนักงาน: ชื่อผู้ใช้ "' . mb_strtolower((string) post('username')) . '" รหัสผ่านชั่วคราว "' . (string) post('password') . '" (ระบบจะให้เปลี่ยนรหัสเองตอนเข้าครั้งแรก)');
    } else {
        flash('success', 'บันทึกแล้ว สิทธิ์ใหม่มีผลทันที');
    }
    redirect('admin.user', ['id' => $newId]);
}

function c_admin_post_user_reset(): void
{
    $id = (string) post('id', '');
    $pw = (string) (post('temp_password') ?? random_temp_password());
    user_reset_password($id, $pw);
    $u = db_get('users', $id);
    flash('success', 'ตั้งรหัสผ่านชั่วคราวให้ ' . $u['name'] . ' แล้ว: "' . $pw . '" — ผู้ใช้จะถูกออกจากระบบทุกเครื่องและต้องเปลี่ยนรหัสใหม่ตอนเข้าครั้งถัดไป');
    redirect('admin.user', ['id' => $id]);
}

function c_admin_post_user_active(): void
{
    $id = (string) post('id', '');
    $active = post('active') === '1';
    user_set_active($id, $active);
    flash('success', $active ? 'เปิดใช้บัญชีแล้ว' : 'ปิดบัญชีแล้ว — ผู้ใช้ถูกออกจากระบบทันที');
    redirect('admin.user', ['id' => $id]);
}

function c_admin_master(): void
{
    require_cap('admin.master_data');
    $type = get('type', 'LEAD_SOURCE');
    if (!isset(MASTER_TYPES[$type])) $type = 'LEAD_SOURCE';
    $rows = all('SELECT * FROM master_data WHERE type = ? ORDER BY active DESC, sort, label', [$type]);
    render('admin/master', ['title' => 'ข้อมูลหลัก', 'type' => $type, 'rows' => $rows]);
}

function c_admin_post_master_save(): void
{
    $type = (string) post('type', '');
    master_save(post('id') ?: null, $type, $_POST);
    flash('success', 'บันทึกแล้ว');
    redirect('admin.master', ['type' => $type]);
}

function c_admin_settings(): void
{
    require_cap('admin.settings');
    render('admin/settings', ['title' => 'ตั้งค่า']);
}

function c_admin_post_settings(): void
{
    settings_save($_POST);
    flash('success', 'บันทึกการตั้งค่าแล้ว');
    redirect('admin.settings');
}
