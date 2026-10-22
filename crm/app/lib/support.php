<?php
// ตัวช่วยพื้นฐาน: exception, config, input, validation, การจัดรูปแบบ
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

/** รับจำนวนเงินแบบมีจุลภาค เช่น "1,250,000.50" → "1250000.50" */
function v_money($value, string $label, bool $required = false, bool $allowZero = true): ?string
{
    $value = s($value);
    if ($value === null) {
        if ($required) throw new AppError('กรุณากรอก ' . $label);
        return null;
    }
    $clean = str_replace([',', ' ', '฿'], '', $value);
    if (!preg_match('/^\d{1,12}(\.\d{1,2})?$/', $clean)) throw new AppError($label . ' ต้องเป็นจำนวนเงินที่ถูกต้อง (ไม่ติดลบ ทศนิยมไม่เกิน 2 ตำแหน่ง)');
    if (!$allowZero && (float) $clean <= 0) throw new AppError($label . ' ต้องมากกว่า 0');
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

function v_decimal($value, string $label, bool $required = false): ?string
{
    $value = s($value);
    if ($value === null) {
        if ($required) throw new AppError('กรุณากรอก ' . $label);
        return null;
    }
    $clean = str_replace(',', '', $value);
    if (!is_numeric($clean) || (float) $clean < 0) throw new AppError($label . ' ต้องเป็นตัวเลขที่ไม่ติดลบ');
    return (string) (0 + $clean);
}

/** รับวันที่รูปแบบ YYYY-MM-DD (จาก input type=date) */
function v_date($value, string $label, bool $required = false): ?string
{
    $value = s($value);
    if ($value === null) {
        if ($required) throw new AppError('กรุณาระบุ ' . $label);
        return null;
    }
    $d = DateTime::createFromFormat('!Y-m-d', $value);
    if (!$d || $d->format('Y-m-d') !== $value) throw new AppError($label . ' ต้องเป็นวันที่ที่ถูกต้อง');
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

function v_uuid($value, string $label, bool $required = true): ?string
{
    $value = s($value);
    if ($value === null) {
        if ($required) throw new AppError('กรุณาเลือก ' . $label);
        return null;
    }
    if (!is_uuid($value)) throw new AppError('ค่าของ ' . $label . ' ไม่ถูกต้อง');
    return $value;
}

function is_uuid($v): bool
{
    return is_string($v) && (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $v);
}

function v_maxlen(?string $value, int $max, string $label): ?string
{
    if ($value !== null && mb_strlen($value) > $max) throw new AppError($label . " ยาวเกิน $max ตัวอักษร");
    return $value;
}

/** ตัดช่องว่าง/ขีด/จุด และแปลงเป็นตัวใหญ่ ใช้เทียบ serial ซ้ำ */
function normalize_serial(?string $serial): ?string
{
    $serial = s($serial);
    if ($serial === null) return null;
    $n = strtoupper(preg_replace('/[\s\-_.\/]+/u', '', $serial));
    return $n === '' ? null : $n;
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

function money($v, bool $blankWhenNull = true): string
{
    if ($v === null || $v === '') return $blankWhenNull ? '—' : '0.00';
    return number_format((float) $v, 2);
}

function money0($v): string
{
    if ($v === null || $v === '') return '—';
    return number_format((float) $v, 0);
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

/** จำนวนวันจากวันนี้ (ติดลบ = เลยมาแล้ว) */
function days_from_today(?string $date): ?int
{
    if (!$date) return null;
    $a = new DateTime(today());
    $b = new DateTime(substr($date, 0, 10));
    return (int) $a->diff($b)->format('%r%a');
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
