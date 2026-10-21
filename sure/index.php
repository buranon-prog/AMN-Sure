<?php
// Front controller: ทุกหน้าผ่านไฟล์นี้ ?r=module.action
// GET  → c_{module}_{action}()        POST → c_{module}_post_{action}()  (ตรวจ CSRF ก่อนเสมอ)
// ตาราง C B A D S P V T (customers, branches, …) ใช้ controller กลาง table.php → t_{action}($tableKey)

require __DIR__ . '/app/bootstrap.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Robots-Tag: noindex, nofollow');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store');

if (!app_installed()) {
    header('Location: install.php');
    exit;
}

$debug = (bool) config('debug', false);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) use ($debug) {
    if (!(error_reporting() & $severity)) return false;
    if ($severity & (E_DEPRECATED | E_USER_DEPRECATED)) return true;
    // โหมด debug: warning/notice = error เพื่อให้เห็นบั๊กทันที; โหมดใช้งานจริง: บันทึก log แล้วทำงานต่อ
    if ($debug) throw new ErrorException($message, 0, $severity, $file, $line);
    app_log("PHP[$severity] $message @ $file:$line");
    return true;
});

session_boot();
// ค่าที่กรอกค้างไว้ก่อนเกิด error ใช้ได้กับหน้าถัดไปหน้าเดียวเท่านั้น (ไม่ให้ไปโผล่ในฟอร์มอื่นภายหลัง)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    $GLOBALS['__OLD_INPUT'] = $_SESSION['old_input'] ?? [];
    unset($_SESSION['old_input']);
}

$route = (string) ($_GET['r'] ?? 'home');
if (!preg_match('/^[a-z_]+(\.[a-z_]+)?$/', $route)) $route = 'errors.not_found';
$parts = explode('.', $route, 2);
$module = $parts[0];
$action = $parts[1] ?? 'index';
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$publicRoutes = ['auth.login', 'auth.verify', 'auth.resend', 'auth.first', 'auth.activate'];

try {
    try {
        schema_upgrade();
        $user = current_user();
        if ($isPost) csrf_check();
        if (!$user && !in_array("$module.$action", $publicRoutes, true)) {
            if ($isPost) throw new ForbiddenError('หมดเวลาการใช้งาน กรุณาเข้าสู่ระบบใหม่');
            redirect('auth.login');
        }
        if ($user && (int) $user['must_change_password'] === 1 && !in_array("$module.$action", ['auth.password', 'auth.logout'], true)) {
            if (!$isPost) redirect('auth.password');
            throw new ForbiddenError('กรุณาตั้งรหัสผ่านใหม่ก่อนใช้งาน');
        }
        $tableKey = table_key_for_route($module);
        if ($tableKey !== null) {
            require_once APP_DIR . '/controllers/table.php';
            $fn = 't_' . ($isPost ? 'post_' : '') . $action;
            if (!function_exists($fn)) throw new NotFoundError('ไม่พบหน้าที่ต้องการ');
            $fn($tableKey);
        } else {
            $file = APP_DIR . '/controllers/' . $module . '.php';
            if (!is_file($file)) throw new NotFoundError('ไม่พบหน้าที่ต้องการ');
            require_once $file;
            $fn = 'c_' . $module . '_' . ($isPost ? 'post_' : '') . $action;
            if (!function_exists($fn)) throw new NotFoundError('ไม่พบหน้าที่ต้องการ');
            $fn();
        }
    } catch (AppError $e) {
        if ($isPost) {
            remember_input();
            flash('error', $e->getMessage());
            redirect_back();
        }
        http_response_code(400);
        render('errors/error', ['title' => 'ทำรายการไม่ได้', 'message' => $e->getMessage()], current_user() ? 'layout' : 'layout_plain');
    } catch (ForbiddenError $e) {
        http_response_code(403);
        render('errors/error', ['title' => 'ไม่มีสิทธิ์เข้าถึง', 'message' => $e->getMessage()], current_user() ? 'layout' : 'layout_plain');
    } catch (NotFoundError $e) {
        http_response_code(404);
        render('errors/error', ['title' => 'ไม่พบข้อมูล', 'message' => $e->getMessage()], current_user() ? 'layout' : 'layout_plain');
    }
} catch (RedirectSignal $r) {
    // ส่ง header Location ไปแล้ว
} catch (ForeignDatabaseError $e) {
    // ไม่แตะตารางของเว็บ/ระบบอื่นเด็ดขาด — ให้ผู้ดูแลแก้ app/config.php
    app_log('DB GUARD: ' . $e->getMessage());
    http_response_code(503);
    render('errors/error', ['title' => 'ตั้งค่าฐานข้อมูลไม่ถูกต้อง', 'message' => 'app/config.php ชี้ไปที่ฐานข้อมูลที่ไม่ใช่ของ AMN SURE ระบบหยุดทำงานเพื่อไม่ให้ข้อมูลปนกับเว็บอื่น กรุณาแจ้งผู้ดูแลระบบ'], 'layout_plain');
} catch (Throwable $e) {
    $ref = substr(uuid(), 0, 8);
    app_log("ERROR $ref: " . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
    if (!headers_sent()) http_response_code(500);
    $msg = 'ระบบขัดข้อง กรุณาลองใหม่อีกครั้ง หากยังเกิดซ้ำให้แจ้งผู้ดูแลระบบพร้อมรหัส ' . $ref;
    if ($debug) $msg .= ' — ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
    try {
        render('errors/error', ['title' => 'เกิดข้อผิดพลาด', 'message' => $msg], 'layout_plain');
    } catch (Throwable $e2) {
        echo e($msg);
    }
}
