<?php

function c_auth_login(): void
{
    if (current_user()) redirect('dashboard');
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
    redirect('dashboard');
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
    // session_version เปลี่ยน → เข้าสู่ระบบใหม่ในเครื่องนี้โดยอัตโนมัติ
    $u = one('SELECT session_version FROM users WHERE id = ?', [current_user_id()]);
    $_SESSION['sv'] = (int) $u['session_version'];
    session_regenerate_id(true);
    flash('success', 'เปลี่ยนรหัสผ่านแล้ว อุปกรณ์อื่นที่เคยเข้าสู่ระบบไว้จะถูกออกจากระบบ');
    redirect('dashboard');
}
