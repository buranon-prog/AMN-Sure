<?php
// Audit log (append-only), activity timeline และตัวช่วยอัปเดตพร้อมบันทึกค่าเก่า/ใหม่

// ฟิลด์ที่ไม่เก็บค่าลง audit
const AUDIT_SKIP_FIELDS = ['password_hash', 'updated_at', 'updated_by', 'created_at', 'created_by'];

function audit_log(string $entityType, ?string $entityId, string $action, ?array $changes = null, ?string $note = null): void
{
    db_insert('audit_logs', [
        'id' => uuid(),
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'action' => $action,
        'changes' => $changes ? json_encode($changes, JSON_UNESCAPED_UNICODE) : null,
        'note' => $note !== null ? mb_substr($note, 0, 500) : null,
        'user_id' => current_user_id(),
        'ip' => isset($_SERVER['REMOTE_ADDR']) ? client_ip() : null,
        'created_at' => now6(),
    ]);
}

/** บันทึกการสร้าง record พร้อมค่าเริ่มต้นของทุกฟิลด์ */
function audit_create(string $entityType, string $id, array $data, ?string $note = null): void
{
    $changes = [];
    foreach ($data as $k => $v) {
        if (in_array($k, AUDIT_SKIP_FIELDS, true) || $k === 'id' || $v === null) continue;
        $changes[] = ['field' => $k, 'old' => null, 'new' => $v];
    }
    audit_log($entityType, $id, 'CREATE', $changes, $note);
}

/** db_insert + audit CREATE ใน transaction เดียวกัน */
function insert_audited(string $table, string $entityType, array $data, ?string $note = null): string
{
    return tx(function () use ($table, $entityType, $data, $note) {
        $id = db_insert($table, $data);
        audit_create($entityType, $id, $data, $note);
        return $id;
    });
}

function values_equal($a, $b): bool
{
    if ($a === null || $b === null) return $a === $b || ((string) $a === '' && (string) $b === '');
    if (is_numeric($a) && is_numeric($b)) return abs((float) $a - (float) $b) < 0.000001;
    return (string) $a === (string) $b;
}

/**
 * อัปเดตเฉพาะฟิลด์ที่เปลี่ยนจริง และบันทึกค่าเก่า/ใหม่ลง audit_logs
 * คืนค่ารายการที่เปลี่ยน ([] ถ้าไม่มีอะไรเปลี่ยน)
 */
function update_audited(string $table, string $entityType, string $id, array $data, string $action = 'UPDATE', ?string $note = null): array
{
    return tx(function () use ($table, $entityType, $id, $data, $action, $note) {
        $old = db_lock($table, $id);
        $changed = [];
        $changes = [];
        foreach ($data as $k => $v) {
            if (is_bool($v)) $v = $v ? 1 : 0;
            if (!array_key_exists($k, $old)) throw new InvalidArgumentException("Unknown column $table.$k");
            if (values_equal($old[$k], $v)) continue;
            $changed[$k] = $v;
            if (!in_array($k, AUDIT_SKIP_FIELDS, true)) $changes[] = ['field' => $k, 'old' => $old[$k], 'new' => $v];
        }
        if (!$changed) return [];
        db_update($table, $id, $changed);
        if ($changes || $note) audit_log($entityType, $id, $action, $changes, $note);
        return $changes;
    });
}

/** เปลี่ยนสถานะพร้อมตรวจสถานะต้นทาง (กันการข้ามขั้น/กดซ้ำ) */
function transition(string $table, string $entityType, string $id, array $allowedFrom, string $to, array $extra = [], ?string $note = null): array
{
    return tx(function () use ($table, $entityType, $id, $allowedFrom, $to, $extra, $note) {
        $row = db_lock($table, $id);
        if (!in_array($row['status'], $allowedFrom, true)) {
            throw new AppError('ทำรายการไม่ได้ในสถานะปัจจุบัน (' . label('status', $row['status']) . ')');
        }
        update_audited($table, $entityType, $id, ['status' => $to] + $extra, 'STATUS_CHANGE', $note);
        $row['status'] = $to;
        return array_merge($row, $extra);
    });
}

// ---------------------------------------------------------------- activities

/**
 * บันทึกกิจกรรมลง timeline
 * $opts: organization_id, device_id, outcome, occurred_at, user_id
 */
function log_activity(string $parentType, string $parentId, string $type, string $summary, array $opts = []): string
{
    return db_insert('activities', [
        'parent_type' => $parentType,
        'parent_id' => $parentId,
        'organization_id' => $opts['organization_id'] ?? null,
        'device_id' => $opts['device_id'] ?? null,
        'type' => $type,
        'occurred_at' => $opts['occurred_at'] ?? now6(),
        'user_id' => array_key_exists('user_id', $opts) ? $opts['user_id'] : current_user_id(),
        'summary' => mb_substr($summary, 0, 5000),
        'outcome' => $opts['outcome'] ?? null,
    ]);
}
