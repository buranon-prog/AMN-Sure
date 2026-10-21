<?php
// ตัวช่วยพื้นฐาน: exception, config, input, validation, การจัดรูปแบบ (AMN SURE)
// โค้ดทั้งหมดรองรับ PHP 7.4+ (ไม่ใช้ match, nullsafe, named args, enum)

class AppError extends RuntimeException {}        // ผิดเงื่อนไขธุรกิจ/ข้อมูลไม่ครบ → แจ้งผู้ใช้
class ForbiddenError extends RuntimeException {}  // ไม่มีสิทธิ์ → 403
class NotFoundError extends RuntimeException {}   // ไม่พบข้อมูล → 404

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) { return $needle === '' || strpos($haystack, $needle) !== false; }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) { return strncmp($haystack, $needle, strlen($needle)) === 0; }
}

function config(string $key, $default = null)
{
    $cfg = $GLOBALS['APP_CONFIG'] ?? [];
    return array_key_exists($key, $cfg) ? $cfg[$key] : $default;
}

// ---------------------------------------------------------------- input

/** ค่าจาก $_POST ตัดช่องว่าง ถ้าว่างคืน null */
function post(string $key, $default = null)
{
    if (!isset($_POST[$key])) return $default;
    $v = $_POST[$key];
    if (is_array($v)) return $v;
    $v = trim((string) $v);
    return $v === '' ? $default : $v;
}

function get(string $key, $default = null)
{
    if (!isset($_GET[$key]) || is_array($_GET[$key])) return $default;
    $v = trim((string) $_GET[$key]);
    return $v === '' ? $default : $v;
}

function post_bool(string $key): bool
{
    return isset($_POST[$key]) && $_POST[$key] !== '' && $_POST[$key] !== '0';
}

/** แปลงค่าเป็น string ที่ตัดช่องว่างแล้ว ว่าง = null */
function s($v): ?string
{
    if ($v === null) return null;
    if (is_array($v)) return null;
    $v = trim((string) $v);
    return $v === '' ? null : $v;
}

// ---------------------------------------------------------------- validation

/** ตรวจว่าฟิลด์ที่ต้องมีครบ ถ้าไม่ครบโยน AppError พร้อมรายชื่อฟิลด์ (ใช้ label ภาษาไทย) */
function v_required(array $data, array $fieldsWithLabels): void
{
    $missing = [];
    foreach ($fieldsWithLabels as $field => $label) {
        $v = $data[$field] ?? null;
        if ($v === null || (is_string($v) && trim($v) === '')) $missing[] = $label;
    }
    if ($missing) throw new AppError('กรุณากรอก: ' . implode(', ', $missing));
}

function v_in($value, array $allowed, string $label, bool $required = true): ?string
{
    $value = s($value);
    if ($value === null) {
        if ($required) throw new AppError('กรุณาเลือก ' . $label);
        return null;
    }
    if (!in_array($value, $allowed, true)) throw new AppError('ค่าของ ' . $label . ' ไม่ถูกต้อง');
    return $value;
}

/** รับจำนวนเงินแบบมีจุลภาค เช่น "1,250,000.50" → "1250000.50" · $signed = ใส่ติดลบได้ (คืนเงิน) */
function v_money($value, string $label, bool $required = false, bool $signed = false): ?string
{
    $value = s($value);
    if ($value === null) {
        if ($required) throw new AppError('กรุณากรอก ' . $label);
        return null;
    }
    $clean = str_replace([',', ' ', '฿'], '', $value);
    $re = $signed ? '/^-?\d{1,12}(\.\d{1,2})?$/' : '/^\d{1,12}(\.\d{1,2})?$/';
    if (!preg_match($re, $clean)) {
        throw new AppError($label . ($signed ? ' ต้องเป็นจำนวนเงินที่ถูกต้อง (ทศนิยมไม่เกิน 2 ตำแหน่ง)' : ' ต้องเป็นตัวเลข ≥ 0 (ทศนิยมไม่เกิน 2 ตำแหน่ง)'));
    }
    return number_format((float) $clean, 2, '.', '');
}

function v_int($value, string $label, bool $required = false, int $min = 0, int $max = 2147483647): ?int
{
    $value = s($value);
    if ($value === null) {
        if ($required) throw new AppError('กรุณากรอก ' . $label);
        return null;
    }
    $clean = str_replace(',', '', $value);
    if (!preg_match('/^-?\d+$/', $clean)) throw new AppError($label . ' ต้องเป็นตัวเลขจำนวนเต็ม');
    $n = (int) $clean;
    if ($n < $min || $n > $max) throw new AppError($label . " ต้องอยู่ระหว่าง $min ถึง $max");
    return $n;
}

const DATE_MIN = '2000-01-01';
const DATE_MAX = '2099-12-31';

/** รับวันที่รูปแบบ YYYY-MM-DD (จาก input type=date) เฉพาะช่วง 01/01/2000–31/12/2099 */
function v_date($value, string $label, bool $required = false): ?string
{
    $value = s($value);
    if ($value === null) {
        if ($required) throw new AppError('กรุณาระบุ ' . $label);
        return null;
    }
    $d = DateTime::createFromFormat('!Y-m-d', $value);
    if (!$d || $d->format('Y-m-d') !== $value) throw new AppError($label . ' ต้องเป็นวันที่ที่ถูกต้อง (เลือกจากปฏิทิน)');
    if ($value < DATE_MIN || $value > DATE_MAX) throw new AppError($label . ' ต้องอยู่ระหว่าง 01/01/2000 ถึง 31/12/2099');
    return $value;
}

function v_email($value, string $label, bool $required = false): ?string
{
    $value = s($value);
    if ($value === null) {
        if ($required) throw new AppError('กรุณากรอก ' . $label);
        return null;
    }
    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) throw new AppError($label . ' ไม่ใช่อีเมลที่ถูกต้อง');
    return mb_strtolower($value);
}

function v_maxlen(?string $value, int $max, string $label): ?string
{
    if ($value !== null && mb_strlen($value) > $max) throw new AppError($label . " ยาวเกิน $max ตัวอักษร");
    return $value;
}

// ---------------------------------------------------------------- dates & money

function today(): string { return date('Y-m-d'); }
function now(): string { return date('Y-m-d H:i:s'); }

function now6(): string
{
    $t = microtime(true);
    $sec = (int) floor($t);
    return date('Y-m-d H:i:s', $sec) . sprintf('.%06d', (int) round(($t - $sec) * 1000000) % 1000000);
}

function add_days(string $date, int $days): string
{
    return date('Y-m-d', strtotime($date . ' ' . ($days >= 0 ? '+' : '') . $days . ' days'));
}

/** แบบ EDATE ของ Excel: บวกเดือน ถ้าวันที่เกินสิ้นเดือนให้ใช้วันสุดท้ายของเดือน (31/01 + 1 เดือน = 28/02) */
function edate(string $date, int $months): string
{
    $y = (int) substr($date, 0, 4);
    $m = (int) substr($date, 5, 2) + $months;
    $d = (int) substr($date, 8, 2);
    $y += (int) floor(($m - 1) / 12);
    $m = (($m - 1) % 12 + 12) % 12 + 1;
    $dim = (int) date('t', mktime(0, 0, 0, $m, 1, $y));
    return sprintf('%04d-%02d-%02d', $y, $m, min($d, $dim));
}

/** จำนวนวันจาก $a ถึง $b (b − a) */
function days_between(string $a, string $b): int
{
    return (int) (new DateTime(substr($a, 0, 10)))->diff(new DateTime(substr($b, 0, 10)))->format('%r%a');
}

function money($v, bool $blankWhenNull = true): string
{
    if ($v === null || $v === '') return $blankWhenNull ? '—' : '0.00';
    return number_format((float) $v, 2);
}

function pct($ratio): string
{
    if ($ratio === null || $ratio === '') return '—';
    return number_format((float) $ratio * 100, 1) . '%';
}

/** แสดงวันที่แบบ วว/ดด/ปปปป */
function d($date): string
{
    if (!$date) return '—';
    $ts = strtotime((string) $date);
    return $ts ? date('d/m/Y', $ts) : '—';
}

function dt($datetime): string
{
    if (!$datetime) return '—';
    $ts = strtotime((string) $datetime);
    return $ts ? date('d/m/Y H:i', $ts) : '—';
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return substr($ip, 0, 45);
}

function app_log(string $message): void
{
    $dir = STORAGE_DIR . '/logs';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    @file_put_contents($dir . '/app-' . date('Y-m') . '.log', '[' . date('c') . '] ' . $message . "\n", FILE_APPEND | LOCK_EX);
}
