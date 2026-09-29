<?php
// การแสดงผล: template, URL, redirect, flash message, ตัวช่วยสร้างฟอร์ม
// กฎ: ทุกค่าที่มาจากผู้ใช้/ฐานข้อมูลต้องผ่าน e() ก่อนแสดง

function e($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** ลิงก์ภายในแอป เช่น url('customers.view', ['id' => $id]) → ?r=customers.view&id=... */
function url(string $route, array $params = []): string
{
    $q = ['r' => $route] + array_filter($params, function ($v) { return $v !== null && $v !== ''; });
    return '?' . http_build_query($q);
}

function asset(string $path): string
{
    $file = ROOT_DIR . '/assets/' . $path;
    $v = is_file($file) ? filemtime($file) : '1';
    return 'assets/' . $path . '?v=' . $v;
}

function redirect(string $route, array $params = [], string $anchor = ''): void
{
    header('Location: ' . url($route, $params) . ($anchor ? '#' . $anchor : ''), true, 303);
    throw new RedirectSignal();
}

/** ใช้หยุดการทำงานหลัง redirect (จับไว้ที่ front controller) */
class RedirectSignal extends Exception {}

function redirect_back(string $fallbackRoute = 'dashboard'): void
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($ref && parse_url($ref, PHP_URL_HOST) === $host) {
        $q = parse_url($ref, PHP_URL_QUERY);
        header('Location: ' . ($q ? '?' . $q : url($fallbackRoute)), true, 303);
        throw new RedirectSignal();
    }
    redirect($fallbackRoute);
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** ค่าที่ผู้ใช้กรอกไว้ก่อนเกิด error (คืนค่าให้ฟอร์มหลัง redirect) */
function old(string $key, $default = null)
{
    $old = $GLOBALS['__OLD_INPUT'] ?? null;
    if ($old === null) {
        $old = $_SESSION['old_input'] ?? [];
        unset($_SESSION['old_input']);
        $GLOBALS['__OLD_INPUT'] = $old;
    }
    if (array_key_exists($key, $old)) return $old[$key];
    return $default;
}

function remember_input(): void
{
    $in = $_POST;
    unset($in['_csrf'], $in['password'], $in['password_confirm'], $in['new_password'], $in['current_password']);
    $_SESSION['old_input'] = $in;
}

/** แสดง view ภายใน layout */
function render(string $view, array $vars = [], string $layout = 'layout'): void
{
    $content = render_to_string($view, $vars);
    if ($layout) {
        echo render_to_string($layout, $vars + ['content' => $content]);
    } else {
        echo $content;
    }
}

function render_to_string(string $view, array $vars = []): string
{
    if (!preg_match('#^[a-z0-9_/]+$#', $view)) throw new InvalidArgumentException('Bad view name');
    $file = APP_DIR . '/views/' . $view . '.php';
    if (!is_file($file)) throw new RuntimeException('View not found: ' . $view);
    extract($vars, EXTR_SKIP);
    ob_start();
    try {
        include $file;
    } catch (Throwable $t) {
        ob_end_clean();
        throw $t;
    }
    return ob_get_clean();
}

function partial(string $view, array $vars = []): void
{
    echo render_to_string('partials/' . $view, $vars);
}

// ---------------------------------------------------------------- form helpers

function attrs(array $a): string
{
    $out = '';
    foreach ($a as $k => $v) {
        if ($v === false || $v === null) continue;
        $out .= $v === true ? ' ' . e($k) : ' ' . e($k) . '="' . e($v) . '"';
    }
    return $out;
}

/**
 * ช่องกรอกข้อความ
 * $o: type, required, placeholder, hint, class, attrs(array), readonly
 */
function f_input(string $name, string $label, $value = null, array $o = []): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    $value = old($name, $value);
    $type = $o['type'] ?? 'text';
    $a = [
        'type' => $type, 'name' => $name, 'id' => $id, 'value' => $value,
        'required' => !empty($o['required']), 'placeholder' => $o['placeholder'] ?? null,
        'readonly' => !empty($o['readonly']), 'list' => $o['list'] ?? null,
        'autocomplete' => $o['autocomplete'] ?? null,
    ];
    if ($type === 'money') {
        $a['type'] = 'text';
        $a['inputmode'] = 'decimal';
        $a['data-money'] = '1';
        if ($value !== null && $value !== '' && is_numeric($value)) $a['value'] = number_format((float) $value, 2, '.', ',');
    }
    $a += $o['attrs'] ?? [];
    return field_wrap($id, $label, '<input' . attrs($a) . '>', $o);
}

function f_textarea(string $name, string $label, $value = null, array $o = []): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    $value = old($name, $value);
    $a = ['name' => $name, 'id' => $id, 'rows' => $o['rows'] ?? 3, 'required' => !empty($o['required']), 'placeholder' => $o['placeholder'] ?? null];
    return field_wrap($id, $label, '<textarea' . attrs($a) . '>' . e($value) . '</textarea>', $o);
}

/** $options: [value => label] ; $o['blank'] = ข้อความตัวเลือกว่าง ; $o['search'] = true เพิ่มช่องค้นหา */
function f_select(string $name, string $label, array $options, $value = null, array $o = []): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    $value = old($name, $value);
    $a = ['name' => $name, 'id' => $id, 'required' => !empty($o['required']), 'data-search' => !empty($o['search']) ? '1' : null];
    $a += $o['attrs'] ?? [];
    $html = '<select' . attrs($a) . '>';
    if (array_key_exists('blank', $o)) $html .= '<option value="">' . e($o['blank']) . '</option>';
    foreach ($options as $k => $v) {
        $sel = ((string) $k === (string) $value) ? ' selected' : '';
        $html .= '<option value="' . e($k) . '"' . $sel . '>' . e($v) . '</option>';
    }
    $html .= '</select>';
    return field_wrap($id, $label, $html, $o);
}

function f_checkbox(string $name, string $label, bool $checked = false, array $o = []): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    $oldv = old($name, null);
    if ($oldv !== null) $checked = $oldv !== '0' && $oldv !== '';
    $hint = isset($o['hint']) ? '<div class="hint">' . e($o['hint']) . '</div>' : '';
    return '<div class="field check' . (isset($o['class']) ? ' ' . e($o['class']) : '') . '"><label for="' . e($id) . '"><input type="hidden" name="' . e($name) . '" value="0"><input type="checkbox" name="' . e($name) . '" id="' . e($id) . '" value="1"' . ($checked ? ' checked' : '') . '> ' . e($label) . '</label>' . $hint . '</div>';
}

function field_wrap(string $id, string $label, string $control, array $o): string
{
    $req = !empty($o['required']) ? ' <span class="req">*</span>' : '';
    $hint = isset($o['hint']) ? '<div class="hint">' . e($o['hint']) . '</div>' : '';
    $cls = 'field' . (isset($o['class']) ? ' ' . $o['class'] : '');
    return '<div class="' . e($cls) . '"><label for="' . e($id) . '">' . e($label) . $req . '</label>' . $control . $hint . '</div>';
}

/** ตัวเลือกผู้ใช้ที่ active (สำหรับ owner/assignee) */
function user_options(?array $roles = null): array
{
    if ($roles) {
        $in = implode(',', array_fill(0, count($roles), '?'));
        $rows = all("SELECT DISTINCT u.id, u.name FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id
                     WHERE u.active = 1 AND r.code IN ($in) ORDER BY u.name", $roles);
    } else {
        $rows = all('SELECT id, name FROM users WHERE active = 1 ORDER BY name');
    }
    $out = [];
    foreach ($rows as $r) $out[$r['id']] = $r['name'];
    return $out;
}

function master_options(string $type): array
{
    $out = [];
    foreach (all('SELECT code, label FROM master_data WHERE type = ? AND active = 1 ORDER BY sort, label', [$type]) as $r) {
        $out[$r['code']] = $r['label'];
    }
    return $out;
}

function org_options(): array
{
    $out = [];
    foreach (all('SELECT id, name, ref_no FROM organizations WHERE archived_at IS NULL ORDER BY name LIMIT 5000') as $r) {
        $out[$r['id']] = $r['name'] . ' (' . $r['ref_no'] . ')';
    }
    return $out;
}

function user_name(?string $id): string
{
    if (!$id) return '—';
    static $cache = [];
    if (!array_key_exists($id, $cache)) $cache[$id] = val('SELECT name FROM users WHERE id = ?', [$id]) ?: '—';
    return $cache[$id];
}

/** badge "เลยกำหนด" หรือ "วันนี้" สำหรับวันติดตาม */
function followup_badge(?string $date, bool $active = true): string
{
    if (!$date) return $active ? '<span class="badge red">ไม่มีวันติดตาม</span>' : '';
    $days = days_from_today($date);
    if (!$active) return e(d($date));
    if ($days < 0) return e(d($date)) . ' <span class="badge red">OVERDUE ' . abs($days) . ' วัน</span>';
    if ($days === 0) return e(d($date)) . ' <span class="badge amber">วันนี้</span>';
    return e(d($date));
}

function pager(array $page): string
{
    if ($page['pages'] <= 1) return '<div class="pager-info">ทั้งหมด ' . number_format($page['total']) . ' รายการ</div>';
    $params = $_GET;
    $html = '<nav class="pager"><span class="pager-info">ทั้งหมด ' . number_format($page['total']) . ' รายการ</span>';
    foreach ([['«', 1], ['‹', max(1, $page['page'] - 1)]] as $b) {
        $params['page'] = $b[1];
        $html .= '<a href="?' . e(http_build_query($params)) . '">' . $b[0] . '</a>';
    }
    $html .= '<span>หน้า ' . $page['page'] . ' / ' . $page['pages'] . '</span>';
    foreach ([['›', min($page['pages'], $page['page'] + 1)], ['»', $page['pages']]] as $b) {
        $params['page'] = $b[1];
        $html .= '<a href="?' . e(http_build_query($params)) . '">' . $b[0] . '</a>';
    }
    return $html . '</nav>';
}

/** ปุ่ม POST ขนาดเล็ก (ฟอร์มในตัว) พร้อมกล่องยืนยัน */
function post_button(string $route, array $params, string $label, array $o = []): string
{
    $html = '<form method="post" action="' . e(url($route)) . '" class="inline"' . (isset($o['confirm']) ? ' data-confirm="' . e($o['confirm']) . '"' : '') . '>' . csrf_field();
    foreach ($params as $k => $v) $html .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    $html .= '<button type="submit" class="btn ' . e($o['class'] ?? '') . '">' . e($label) . '</button></form>';
    return $html;
}
