<?php

function c_auth_login(): void
{
    if (current_user()) redirect('home');
    render('auth/login', ['title' => 'เข้าสู่ระบบ'], 'layout_plain');
}

function c_auth_post_login(): void
{
    $error = attempt_login((string) ($_POST['login'] ?? ''), (string) ($_POST['password'] ?? ''));
    if ($error) {
        $_SESSION['old_input'] = ['login' => (string) ($_POST['login'] ?? '')];
        flash('error', $error);
        redirect('auth.login');
    }
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
