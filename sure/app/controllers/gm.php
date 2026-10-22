<?php
// หน้า GM: ผู้ใช้ออนไลน์ + คุมสถานะบัญชีของทุกคน (ออกจากระบบรายเครื่อง/ทุกเครื่อง, ปิด-เปิดบัญชี, ปลดล็อก)

function c_gm_index(): void
{
    require_gm();
    render('gm/index', ['title' => 'ผู้ใช้ออนไลน์', 'users' => gm_users(), 'sessions' => gm_sessions(), 'activity' => gm_activity()]);
}

function c_gm_post_kick(): void
{
    $s = gm_kick_session((string) post('sid', ''));
    flash('success', 'ให้ ' . $s['name'] . ' ออกจากระบบบนเครื่องนั้นแล้ว (' . ua_label($s['user_agent']) . ' · ' . $s['ip'] . ')');
    redirect('gm');
}

function c_gm_post_logout_all(): void
{
    $u = gm_logout_everywhere((string) post('id', ''));
    flash('success', 'ให้ ' . $u['name'] . ' ออกจากระบบทุกเครื่องแล้ว (เข้าสู่ระบบใหม่ได้ด้วยรหัสผ่าน + รหัสทางอีเมล)');
    redirect('gm');
}

function c_gm_post_active(): void
{
    $on = post('active') === '1';
    $u = gm_set_active((string) post('id', ''), $on);
    flash('success', $on ? 'เปิดบัญชี ' . $u['name'] . ' แล้ว' : 'ปิดบัญชี ' . $u['name'] . ' แล้ว — ออกจากระบบทุกเครื่องทันที');
    redirect('gm');
}

function c_gm_post_unlock(): void
{
    $u = gm_unlock((string) post('id', ''));
    flash('success', 'ปลดล็อก ' . $u['name'] . ' แล้ว — เข้าสู่ระบบได้ทันที');
    redirect('gm');
}
