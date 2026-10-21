<?php
// สร้างตาราง + ข้อมูลตั้งต้น (รายการตัวเลือกหัวข้อ 10, VAT 7%) + บัญชีผู้ใช้

/** สร้างตารางทั้งหมดจาก app/sql/schema.sql */
function install_schema(PDO $pdo): void
{
    $sql = file_get_contents(APP_DIR . '/sql/schema.sql');
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (preg_split('/;\s*$/m', $sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt !== '') $pdo->exec($stmt);
    }
}

/** ใส่ค่าเริ่มต้นของรายการตัวเลือกและ VAT (ข้ามรายการที่มีอยู่แล้ว) */
function seed_base_data(): void
{
    $locked = option_locked();
    $now = now6();
    foreach (option_seed() as $list => $values) {
        foreach ($values as $i => $v) {
            q('INSERT IGNORE INTO sure_options (list_key, value, sort, is_system, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
                [$list, $v, ($i + 1) * 10, in_array($v, $locked[$list] ?? [], true) ? 1 : 0, $now, $now]);
        }
    }
    if (setting('vat_rate') === null) setting_set('vat_rate', '7');
    if (setting('schema_version') === null) setting_set('schema_version', (string) SCHEMA_VERSION);
    if (setting('app_id') === null) setting_set('app_id', APP_DB_ID);
    options_reset();
}

// ---------------------------------------------------------------- ฐานข้อมูลแยกของ AMN SURE (ไม่ปนกับเว็บหลัก / ระบบอื่นบนโฮสต์เดียวกัน)

/** เครื่องหมายใน sure_settings ว่าฐานข้อมูลนี้เป็นของ AMN SURE */
const APP_DB_ID = 'amn-sure';

/** config ชี้ไปที่ฐานข้อมูลที่ไม่ใช่ของ AMN SURE → หยุดก่อนอ่าน/สร้าง/แก้ตารางใด ๆ */
class ForeignDatabaseError extends RuntimeException {}

/**
 * ตรวจก่อนติดตั้ง: ฐานข้อมูลต้องว่างเปล่า และผู้ใช้ฐานข้อมูลต้องเข้าถึงได้เฉพาะฐานข้อมูลนี้
 * (ถ้าใช้ผู้ใช้เดียวกับเว็บหลักหรือผู้ใช้หลักของ cPanel ช่องโหว่ใด ๆ ในระบบนี้จะไปถึงข้อมูลของเว็บอื่นได้) → รายการปัญหา ว่าง = ผ่าน
 */
function db_isolation_problems(PDO $pdo): array
{
    $problems = [];
    $current = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $tables = $pdo->query('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name LIMIT 6')->fetchAll(PDO::FETCH_COLUMN);
    if ($tables) {
        $problems[] = 'ฐานข้อมูล ' . $current . ' มีตารางอยู่แล้ว (' . implode(', ', array_slice($tables, 0, 5)) . (count($tables) > 5 ? ', …' : '') . ')'
            . ' — ต้องใช้ฐานข้อมูลใหม่ที่ว่างเปล่าสำหรับ AMN SURE เท่านั้น ห้ามใช้ร่วมกับเว็บหลักหรือระบบอื่น';
    }
    $others = [];
    foreach ($pdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN) as $name) {
        if ($name === $current || in_array(strtolower($name), ['information_schema', 'performance_schema'], true)) continue;
        $others[] = $name;
    }
    if ($others) {
        $problems[] = 'ผู้ใช้ฐานข้อมูลนี้เข้าถึงฐานข้อมูลอื่นได้ด้วย (' . implode(', ', array_slice($others, 0, 5)) . (count($others) > 5 ? ', …' : '') . ')'
            . ' — สร้างผู้ใช้ฐานข้อมูลใหม่ที่ให้สิทธิ์เฉพาะฐานข้อมูล ' . $current . ' (ห้ามใช้ผู้ใช้ของเว็บหลักหรือผู้ใช้หลักของ cPanel)';
    }
    foreach ($pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN) as $grant) {
        if (preg_match('/^GRANT (.+?) ON \*\.\* TO /i', $grant, $m) && strtoupper(trim($m[1])) !== 'USAGE') {
            $problems[] = 'ผู้ใช้ฐานข้อมูลนี้มีสิทธิ์ทั้งเซิร์ฟเวอร์ (' . $m[1] . ') — ใช้ผู้ใช้ที่ให้สิทธิ์เฉพาะฐานข้อมูล ' . $current;
            break;
        }
    }
    return $problems;
}

/** ทุก request: ฐานข้อมูลใน config ต้องเป็นของ AMN SURE (กันกรณีแก้ config ไปชี้ฐานข้อมูลของเว็บหลัก/ระบบอื่น) */
function db_assert_ours(): void
{
    try {
        $id = setting('app_id');
    } catch (PDOException $e) {
        $id = false; // ไม่มีตาราง sure_settings
    }
    if ($id === APP_DB_ID) return;
    if ($id === null) {
        // ติดตั้งด้วยเวอร์ชัน 1.0.0 (ยังไม่มีเครื่องหมาย): ยอมรับเมื่อมีตารางหลักของ SURE ครบ แล้วใส่เครื่องหมาย
        $n = (int) val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('sure_customers', 'sure_sales', 'sure_costs', 'users', 'user_permissions')");
        if ($n === 5) {
            setting_set('app_id', APP_DB_ID);
            return;
        }
    }
    throw new ForeignDatabaseError('ฐานข้อมูล ' . val('SELECT DATABASE()') . ' ไม่ใช่ฐานข้อมูลของ AMN SURE');
}

/** เวอร์ชันโครงสร้างฐานข้อมูลของโค้ดนี้ (2 = ยืนยันตัวตนทางอีเมล) */
const SCHEMA_VERSION = 2;

/**
 * อัปเกรดฐานข้อมูลของระบบที่ติดตั้งไว้แล้วให้ตรงกับโค้ดเวอร์ชันนี้ (รันอัตโนมัติ ครั้งเดียว)
 * v1 → v2: เพิ่ม users.activated_at + ตาราง auth_tokens · บัญชีเดิมที่มีรหัสผ่านอยู่แล้วถือว่าเปิดใช้งานแล้ว
 */
function schema_upgrade(): void
{
    db_assert_ours();
    $v = (int) (setting('schema_version') ?? 1);
    if ($v >= SCHEMA_VERSION) return;
    if ($v < 2) {
        $has = (int) val("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'activated_at'");
        if (!$has) db()->exec('ALTER TABLE users ADD COLUMN activated_at DATETIME NULL AFTER last_login_at');
        db()->exec("CREATE TABLE IF NOT EXISTS auth_tokens (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  purpose VARCHAR(20) NOT NULL,
  secret_hash CHAR(64) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME(6) NOT NULL,
  KEY ix_auth_tokens_user (user_id, purpose, created_at),
  KEY ix_auth_tokens_hash (secret_hash),
  CONSTRAINT fk_auth_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        q('UPDATE users SET activated_at = created_at WHERE activated_at IS NULL');
    }
    setting_set('schema_version', (string) SCHEMA_VERSION);
}

function valid_username(string $u): bool
{
    return (bool) preg_match('/^[a-z0-9._-]{2,60}$/', $u);
}

/** สร้างผู้ใช้ (ใช้ทั้งตัวติดตั้งและหน้าจัดการผู้ใช้) คืน id */
function create_user_raw(string $username, ?string $email, string $name, string $password, string $role, bool $canView, bool $canEdit, bool $mustChange = true): string
{
    $id = uuid();
    db_insert('users', [
        'id' => $id,
        'username' => $username,
        'email' => $email,
        'name' => $name,
        'role' => $role,
        'active' => 1,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'must_change_password' => $mustChange ? 1 : 0,
        'session_version' => 1,
        'activated_at' => now(),
    ]);
    set_user_permission($id, $canView, $canEdit);
    return $id;
}

function set_user_permission(string $userId, bool $canView, bool $canEdit): void
{
    $now = now6();
    $uid = current_user_id();
    q('INSERT INTO user_permissions (user_id, module, can_view, can_edit, created_at, updated_at, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE can_view = VALUES(can_view), can_edit = VALUES(can_edit), updated_at = VALUES(updated_at), updated_by = VALUES(updated_by)',
        [$userId, SURE_MODULE, $canView ? 1 : 0, ($canView && $canEdit) ? 1 : 0, $now, $now, $uid, $uid]);
}
