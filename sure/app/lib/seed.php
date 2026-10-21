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
    options_reset();
}

/** เวอร์ชันโครงสร้างฐานข้อมูลของโค้ดนี้ (2 = ยืนยันตัวตนทางอีเมล) */
const SCHEMA_VERSION = 2;

/**
 * อัปเกรดฐานข้อมูลของระบบที่ติดตั้งไว้แล้วให้ตรงกับโค้ดเวอร์ชันนี้ (รันอัตโนมัติ ครั้งเดียว)
 * v1 → v2: เพิ่ม users.activated_at + ตาราง auth_tokens · บัญชีเดิมที่มีรหัสผ่านอยู่แล้วถือว่าเปิดใช้งานแล้ว
 */
function schema_upgrade(): void
{
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
