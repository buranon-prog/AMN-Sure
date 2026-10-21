<?php
// หน้าแรกหลังเข้าสู่ระบบ: Manager → สรุป · พนักงาน → ค้นหา · ไม่มีสิทธิ์ SURE → แจ้งให้ติดต่อ Manager

function c_home_index(): void
{
    if (is_manager()) redirect('dashboard');
    if (can_view_sure()) redirect('search');
    render('errors/noaccess', ['title' => 'ยังไม่ได้รับสิทธิ์']);
}
