<?php
// สิทธิ์ (สเปกหัวข้อ 3) — ตรวจที่ server ทุกหน้า ทุก action และทุก export
// MANAGER: เห็นและทำได้ทุกอย่าง
// EMPLOYEE: ต้องได้สิทธิ์โมดูล SURE — canView = ดูตาราง C B A D S P V + หน้าค้นหา, canEdit = เพิ่ม/แก้ไขตารางเหล่านั้น
//           ห้ามลบแถว ห้ามเห็นต้นทุน/กำไร/GP/หน้าสรุป/หน้ากำไร/ฟิลด์ Manager เท่านั้น

const SURE_MODULE = 'SURE';
const ROLE_MANAGER = 'MANAGER';
const ROLE_EMPLOYEE = 'EMPLOYEE';

function role_name(string $role): string
{
    return $role === ROLE_MANAGER ? 'Manager' : 'พนักงาน (Employee)';
}

function is_manager(): bool
{
    $u = current_user();
    return $u !== null && $u['role'] === ROLE_MANAGER;
}

function can_view_sure(): bool
{
    $u = current_user();
    if (!$u) return false;
    return $u['role'] === ROLE_MANAGER || $u['sure_view'] === 1;
}

function can_edit_sure(): bool
{
    $u = current_user();
    if (!$u) return false;
    return $u['role'] === ROLE_MANAGER || ($u['sure_view'] === 1 && $u['sure_edit'] === 1);
}

function require_sure_view(): void
{
    if (!can_view_sure()) throw new ForbiddenError('บัญชีนี้ยังไม่ได้รับสิทธิ์ใช้งานโมดูล SURE — ติดต่อ Manager');
}

function require_sure_edit(): void
{
    require_sure_view();
    if (!can_edit_sure()) throw new ForbiddenError('บัญชีนี้มีสิทธิ์ดูอย่างเดียว เพิ่ม/แก้ไขข้อมูลไม่ได้');
}

function require_manager(): void
{
    if (!is_manager()) throw new ForbiddenError('หน้านี้สำหรับ Manager เท่านั้น');
}

/** สิทธิ์ดูตาราง: ต้นทุนเครื่อง (T) = Manager เท่านั้น */
function require_table_view(string $t): void
{
    if (tdef($t)['mgr']) require_manager();
    require_sure_view();
}

function require_table_edit(string $t): void
{
    if (tdef($t)['mgr']) require_manager();
    require_sure_edit();
}
