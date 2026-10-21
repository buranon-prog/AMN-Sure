<?php
// การเข้าถึงฐานข้อมูล: PDO + prepared statement เท่านั้น, audit fields, transaction, ค่าตั้งต้น

// ตารางที่ไม่มีคอลัมน์ created_at/created_by/updated_at/updated_by
const DB_NO_AUDIT_COLUMNS = ['login_attempts', 'auth_tokens'];

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

function is_uuid($v): bool
{
    return is_string($v) && (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $v);
}

function db_ident(string $name): string
{
    if (!preg_match('/^[a-z_][a-z0-9_]*$/', $name)) throw new InvalidArgumentException('Bad identifier: ' . $name);
    return '`' . $name . '`';
}

/** แทรกแถวใหม่ เติมคอลัมน์ audit ให้อัตโนมัติ คืน lastInsertId (ตารางที่ id อัตโนมัติ) */
function db_insert(string $table, array $data): string
{
    if (!in_array($table, DB_NO_AUDIT_COLUMNS, true)) {
        $uid = current_user_id();
        // created_at เก็บถึงระดับไมโครวินาที เพื่อให้ "บันทึกล่าสุด" เรียงถูกแม้บันทึกในวินาทีเดียวกัน
        $now = now6();
        $data += ['created_at' => $now, 'created_by' => $uid, 'updated_at' => $now, 'updated_by' => $uid];
    }
    $cols = [];
    $vals = [];
    foreach ($data as $col => $v) {
        $cols[] = db_ident($col);
        $vals[] = is_bool($v) ? ($v ? 1 : 0) : $v;
    }
    q('INSERT INTO ' . db_ident($table) . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', $vals);
    return (string) db()->lastInsertId();
}

/** อัปเดตแถวตาม primary key พร้อมเติม updated_at/updated_by */
function db_update(string $table, string $pkCol, $pk, array $data): void
{
    if (!$data) return;
    if (!in_array($table, DB_NO_AUDIT_COLUMNS, true)) {
        $data += ['updated_at' => now6(), 'updated_by' => current_user_id()];
    }
    $sets = [];
    $vals = [];
    foreach ($data as $col => $v) {
        $sets[] = db_ident($col) . ' = ?';
        $vals[] = is_bool($v) ? ($v ? 1 : 0) : $v;
    }
    $vals[] = $pk;
    q('UPDATE ' . db_ident($table) . ' SET ' . implode(', ', $sets) . ' WHERE ' . db_ident($pkCol) . ' = ?', $vals);
}

/** ล็อกแถวไว้จนจบ transaction (กันบันทึกชนกันเมื่อสองคนกดพร้อมกัน) */
function db_lock(string $table, string $pkCol, $pk): ?array
{
    return one('SELECT * FROM ' . db_ident($table) . ' WHERE ' . db_ident($pkCol) . ' = ? FOR UPDATE', [$pk]);
}

/** error ของ MySQL ว่าค่าซ้ำ (duplicate key) */
function is_duplicate_error(Throwable $e): bool
{
    return $e instanceof PDOException && (($e->errorInfo[1] ?? null) === 1062);
}

/** error ของ MySQL ว่าลบไม่ได้เพราะมีข้อมูลอื่นอ้างถึง (foreign key) */
function is_fk_error(Throwable $e): bool
{
    return $e instanceof PDOException && in_array($e->errorInfo[1] ?? null, [1451, 1452], true);
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

// ---------------------------------------------------------------- ค่าตั้งต้น (sure_settings)

function setting(string $key, $default = null)
{
    if (!isset($GLOBALS['__SETTINGS'])) {
        $GLOBALS['__SETTINGS'] = [];
        foreach (all('SELECT setting_key, setting_value FROM sure_settings') as $r) {
            $GLOBALS['__SETTINGS'][$r['setting_key']] = $r['setting_value'];
        }
    }
    return array_key_exists($key, $GLOBALS['__SETTINGS']) ? $GLOBALS['__SETTINGS'][$key] : $default;
}

function setting_set(string $key, ?string $value): void
{
    $now = now6();
    $uid = current_user_id();
    q('INSERT INTO sure_settings (setting_key, setting_value, created_at, updated_at, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at), updated_by = VALUES(updated_by)',
        [$key, $value, $now, $now, $uid, $uid]);
    unset($GLOBALS['__SETTINGS']);
}
