<?php
// เข้าสู่ระบบ 2 ขั้นตอน: อีเมล + รหัสผ่าน → รหัส 6 หลักทางอีเมล
// เข้าใช้ครั้งแรก: ลิงก์เปิดใช้งานทางอีเมล (เฉพาะอีเมลที่ Manager สร้างบัญชีไว้) → ตั้งรหัสผ่านเอง

function c_auth_login(): void
{
    if (current_user()) redirect('home');
    render('auth/login', ['title' => 'เข้าสู่ระบบ'], 'layout_auth');
}

function c_auth_post_login(): void
{
    $login = (string) ($_POST['login'] ?? '');
    $r = password_step($login, (string) ($_POST['password'] ?? ''));
    if (isset($r['error'])) {
        $_SESSION['old_input'] = ['login' => $login];
        flash('error', $r['error']);
        redirect('auth.login');
    }
    if (!email_2fa_enabled()) {
        complete_login($r['user']);
        redirect('home');
    }
    try {
        start_login_challenge($r['user']);
    } catch (MailError $e) {
        unset($_SESSION['mfa']);
        app_log('MAIL login code ' . $r['user']['email'] . ': ' . $e->getMessage());
        $_SESSION['old_input'] = ['login' => $login];
        flash('error', 'ส่งรหัสยืนยันทางอีเมลไม่สำเร็จ กรุณาลองใหม่อีกครั้ง หรือแจ้ง Manager');
        redirect('auth.login');
    }
    redirect('auth.verify');
}

function c_auth_verify(): void
{
    if (current_user()) redirect('home');
    $p = pending_login();
    if (!$p) redirect('auth.login');
    render('auth/verify', ['title' => 'ยืนยันทางอีเมล', 'email' => mask_email((string) $p['user']['email'])], 'layout_auth');
}

function c_auth_post_verify(): void
{
    $err = verify_login_code((string) ($_POST['code'] ?? ''));
    if ($err === null) redirect('home');
    flash('error', $err);
    redirect(pending_login() ? 'auth.verify' : 'auth.login');
}

function c_auth_post_resend(): void
{
    try {
        resend_login_code();
    } catch (MailError $e) {
        app_log('MAIL resend code: ' . $e->getMessage());
        throw new AppError('ส่งรหัสยืนยันทางอีเมลไม่สำเร็จ กรุณาลองใหม่อีกครั้ง หรือแจ้ง Manager');
    }
    flash('success', 'ส่งรหัสใหม่แล้ว — รหัสก่อนหน้าใช้ไม่ได้แล้ว');
    redirect('auth.verify');
}

function c_auth_first(): void
{
    render('auth/first', ['title' => 'เข้าใช้ครั้งแรก', 'sent' => get('sent') === '1'], 'layout_auth');
}

function c_auth_post_first(): void
{
    request_activation((string) ($_POST['email'] ?? ''));
    redirect('auth.first', ['sent' => '1']);
}

function c_auth_activate(): void
{
    $token = (string) get('t', '');
    $a = find_activation($token);
    if (!$a) {
        render('auth/link_invalid', ['title' => 'ลิงก์ใช้ไม่ได้'], 'layout_auth');
        return;
    }
    render('auth/activate', ['title' => 'ตั้งรหัสผ่าน', 'token' => $token, 'name' => $a['name'], 'email' => mask_email((string) $a['email'])], 'layout_auth');
}

function c_auth_post_activate(): void
{
    $user = complete_activation((string) ($_POST['t'] ?? ''), (string) ($_POST['new_password'] ?? ''), (string) ($_POST['password_confirm'] ?? ''));
    // กดลิงก์จากอีเมล + ตั้งรหัสผ่านใหม่ = ยืนยันตัวตนครบทั้ง 2 อย่างแล้ว จึงเข้าสู่ระบบได้เลย
    complete_login($user);
    flash('success', 'ตั้งรหัสผ่านเรียบร้อย ครั้งต่อไปเข้าสู่ระบบด้วยอีเมล + รหัสผ่าน และรหัสยืนยันที่ส่งไปทางอีเมล');
    redirect('home');
}

function c_auth_post_logout(): void
{
    logout();
    session_boot();
    flash('success', 'ออกจากระบบแล้ว');
    redirect('auth.login');
}

function c_auth_password(): void
{
    render('auth/password', ['title' => 'เปลี่ยนรหัสผ่าน', 'forced' => (int) current_user()['must_change_password'] === 1]);
}

function c_auth_post_password(): void
{
    change_own_password((string) ($_POST['current_password'] ?? ''), (string) ($_POST['new_password'] ?? ''), (string) ($_POST['password_confirm'] ?? ''));
    // session_version เปลี่ยน → อุปกรณ์อื่นหลุด ส่วนเครื่องนี้ใช้งานต่อได้
    $_SESSION['sv'] = (int) val('SELECT session_version FROM users WHERE id = ?', [current_user_id()]);
    if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
    flash('success', 'เปลี่ยนรหัสผ่านแล้ว อุปกรณ์อื่นที่เคยเข้าสู่ระบบไว้จะถูกออกจากระบบ');
    redirect('home');
}
