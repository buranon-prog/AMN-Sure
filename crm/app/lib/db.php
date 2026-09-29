<?php
// การเข้าถึงฐานข้อมูล: PDO + prepared statement เท่านั้น, UUID, เลขอ้างอิง, transaction

// ตารางที่ไม่มีคอลัมน์ created_at/created_by/updated_at/updated_by
const DB_NO_AUDIT_COLUMNS = ['user_roles', 'audit_logs', 'ref_counters', 'app_settings', 'login_attempts'];

function db(): PDO
{
    if (isset($GLOBALS['__PDO']) && $GLOBALS['__PDO'] instanceof PDO) return $GLOBALS['__PDO'];
    $c = config('db');
    if (!$c) throw new RuntimeException('Database is not configured');
    $GLOBALS['__PDO'] = db_connect($c);
    return $GLOBALS['__PDO'];
}

function db_connect(array $c): PDO
{
    $dsn = 'mysql:host=' . $c['host'] . ';port=' . (int) ($c['port'] ?? 3306) . ';dbname=' . $c['name'] . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // emulate = true เพื่อใช้ชื่อ placeholder ซ้ำในคำค้นได้ (PDO ยัง escape ให้ครบ)
        PDO::ATTR_EMULATE_PREPARES => true,
    ]);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    return $pdo;
}

function db_set(?PDO $pdo): void
{
    $GLOBALS['__PDO'] = $pdo;
    $GLOBALS['__TX_DEPTH'] = 0;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    foreach ($params as $k => $v) {
        if (is_bool($v)) $params[$k] = $v ? 1 : 0;
    }
    $st->execute($params);
    return $st;
}

function one(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function val(string $sql, array $params = [])
{
    $r = q($sql, $params)->fetchColumn();
    return $r === false ? null : $r;
}

function uuid(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    $h = bin2hex($b);
    return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4) . '-' . substr($h, 16, 4) . '-' . substr($h, 20, 12);
}

function db_ident(string $name): string
{
    if (!preg_match('/^[a-z_][a-z0-9_]*$/', $name)) throw new InvalidArgumentException('Bad identifier: ' . $name);
    return '`' . $name . '`';
}

/** แทรกแถวใหม่ เติม id และคอลัมน์ audit ให้อัตโนมัติ คืนค่า id */
function db_insert(string $table, array $data): string
{
    if (!array_key_exists('id', $data) && !in_array($table, ['user_roles', 'ref_counters', 'app_settings'], true)) {
        $data['id'] = uuid();
    }
    if (!in_array($table, DB_NO_AUDIT_COLUMNS, true)) {
        $uid = current_user_id();
        // created_at เก็บถึงระดับไมโครวินาที เพื่อให้ "ล่าสุด" เรียงถูกแม้บันทึกในวินาทีเดียวกัน
        $data += ['created_at' => now6(), 'created_by' => $uid, 'updated_at' => now(), 'updated_by' => $uid];
    }
    $cols = [];
    $vals = [];
    foreach ($data as $col => $v) {
        $cols[] = db_ident($col);
        $vals[] = is_bool($v) ? ($v ? 1 : 0) : $v;
    }
    q('INSERT INTO ' . db_ident($table) . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', $vals);
    return (string) ($data['id'] ?? '');
}

/** อัปเดตตาม id (ไม่เขียน audit — ใช้ update_audited() สำหรับข้อมูลธุรกิจ) */
function db_update(string $table, string $id, array $data): void
{
    if (!$data) return;
    if (!in_array($table, DB_NO_AUDIT_COLUMNS, true)) {
        $data += ['updated_at' => now(), 'updated_by' => current_user_id()];
    }
    $sets = [];
    $vals = [];
    foreach ($data as $col => $v) {
        $sets[] = db_ident($col) . ' = ?';
        $vals[] = is_bool($v) ? ($v ? 1 : 0) : $v;
    }
    $vals[] = $id;
    q('UPDATE ' . db_ident($table) . ' SET ' . implode(', ', $sets) . ' WHERE id = ?', $vals);
}

function db_find(string $table, ?string $id): ?array
{
    if (!$id || !is_uuid($id)) return null;
    return one('SELECT * FROM ' . db_ident($table) . ' WHERE id = ?', [$id]);
}

/** หาแถวหรือโยน NotFoundError */
function db_get(string $table, ?string $id, string $what = 'ข้อมูล'): array
{
    $row = db_find($table, $id);
    if (!$row) throw new NotFoundError('ไม่พบ' . $what);
    return $row;
}

/** ล็อกแถวไว้จนจบ transaction (กันกดซ้ำพร้อมกัน) */
function db_lock(string $table, string $id, string $what = 'ข้อมูล'): array
{
    $row = one('SELECT * FROM ' . db_ident($table) . ' WHERE id = ? FOR UPDATE', [$id]);
    if (!$row) throw new NotFoundError('ไม่พบ' . $what);
    return $row;
}

/**
 * รันโค้ดใน transaction เดียว ถ้าข้างในโยน exception ทุกอย่าง rollback
 * เรียกซ้อนกันได้ (เฉพาะชั้นนอกสุดที่ commit/rollback)
 */
function tx(callable $fn)
{
    $pdo = db();
    $depth = $GLOBALS['__TX_DEPTH'] ?? 0;
    if ($depth === 0) $pdo->beginTransaction();
    $GLOBALS['__TX_DEPTH'] = $depth + 1;
    try {
        $result = $fn();
        $GLOBALS['__TX_DEPTH'] = $depth;
        if ($depth === 0) $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        $GLOBALS['__TX_DEPTH'] = $depth;
        if ($depth === 0 && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * ออกเลขอ้างอิงถัดไป เช่น next_ref('DO') → DO-2026-0001, next_ref('DEV', false, 6) → DEV-000001
 * ใช้ SELECT ... FOR UPDATE ภายใน transaction เพื่อไม่ให้ได้เลขซ้ำเมื่อบันทึกพร้อมกัน
 */
function next_ref(string $prefix, bool $yearly = true, int $pad = 4): string
{
    $key = $yearly ? $prefix . '-' . date('Y') : $prefix;
    return tx(function () use ($key, $pad) {
        q('INSERT INTO ref_counters (counter_key, last_value) VALUES (?, 0) ON DUPLICATE KEY UPDATE counter_key = counter_key', [$key]);
        $next = (int) val('SELECT last_value FROM ref_counters WHERE counter_key = ? FOR UPDATE', [$key]) + 1;
        q('UPDATE ref_counters SET last_value = ? WHERE counter_key = ?', [$next, $key]);
        return $key . '-' . str_pad((string) $next, $pad, '0', STR_PAD_LEFT);
    });
}

// ---------------------------------------------------------------- settings

function setting(string $key, $default = null)
{
    if (!isset($GLOBALS['__SETTINGS'])) {
        $GLOBALS['__SETTINGS'] = [];
        foreach (all('SELECT setting_key, setting_value FROM app_settings') as $r) {
            $GLOBALS['__SETTINGS'][$r['setting_key']] = $r['setting_value'];
        }
    }
    return array_key_exists($key, $GLOBALS['__SETTINGS']) ? $GLOBALS['__SETTINGS'][$key] : $default;
}

function setting_set(string $key, ?string $value): void
{
    q('INSERT INTO app_settings (setting_key, setting_value, updated_at, updated_by) VALUES (?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at), updated_by = VALUES(updated_by)',
        [$key, $value, now(), current_user_id()]);
    unset($GLOBALS['__SETTINGS']);
}

// ---------------------------------------------------------------- paging

/** แบ่งหน้า: คืน ['rows' => ..., 'page' => n, 'pages' => n, 'total' => n] */
function paginate(string $fromWhereSql, array $params, string $select, string $orderBy, int $perPage = 50): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $total = (int) val('SELECT COUNT(*) ' . $fromWhereSql, $params);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;
    $rows = all('SELECT ' . $select . ' ' . $fromWhereSql . ' ORDER BY ' . $orderBy . ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset, $params);
    return ['rows' => $rows, 'page' => $page, 'pages' => $pages, 'total' => $total];
}

/** แปลงคำค้นเป็นรูปแบบ LIKE ที่ escape อักขระพิเศษแล้ว */
function like(string $term): string
{
    return '%' . addcslashes($term, '\\%_') . '%';
}
