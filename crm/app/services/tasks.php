<?php
// งาน (Tasks): มอบให้คน (assignee_id) หรือให้ทั้ง role (assignee_role) ก็ได้
// งานที่ระบบสร้างจาก workflow จะถูกปิดอัตโนมัติเมื่อขั้นตอนนั้นเสร็จ

const TASK_STATUSES = ['OPEN', 'IN_PROGRESS', 'DONE', 'CANCELLED'];

/** $d: title, description, type, parent_type, parent_id, assignee_id, assignee_role, due_date, priority */
function task_create(array $d): string
{
    if (empty($d['assignee_id']) && empty($d['assignee_role'])) throw new AppError('กรุณาระบุผู้รับผิดชอบงาน');
    return db_insert('tasks', [
        'title' => mb_substr((string) $d['title'], 0, 255),
        'description' => $d['description'] ?? null,
        'type' => $d['type'] ?? 'GENERAL',
        'parent_type' => $d['parent_type'] ?? null,
        'parent_id' => $d['parent_id'] ?? null,
        'assignee_id' => $d['assignee_id'] ?? null,
        'assignee_role' => $d['assignee_role'] ?? null,
        'due_date' => $d['due_date'] ?? null,
        'priority' => $d['priority'] ?? 'MEDIUM',
        'status' => 'OPEN',
    ]);
}

/** ปิดงานที่ระบบสร้างไว้สำหรับ record นี้ (เลือกเฉพาะ type ได้) */
function tasks_close_for(string $parentType, string $parentId, ?array $types = null): void
{
    $sql = "UPDATE tasks SET status = 'DONE', completed_at = ?, completed_by = ?, updated_at = ?, updated_by = ?
            WHERE parent_type = ? AND parent_id = ? AND status IN ('OPEN', 'IN_PROGRESS')";
    $p = [now(), current_user_id(), now(), current_user_id(), $parentType, $parentId];
    if ($types) {
        $sql .= ' AND type IN (' . implode(',', array_fill(0, count($types), '?')) . ')';
        $p = array_merge($p, $types);
    }
    q($sql, $p);
}

function tasks_cancel_for(string $parentType, string $parentId): void
{
    q("UPDATE tasks SET status = 'CANCELLED', updated_at = ?, updated_by = ? WHERE parent_type = ? AND parent_id = ? AND status IN ('OPEN', 'IN_PROGRESS')",
        [now(), current_user_id(), $parentType, $parentId]);
}

/** เงื่อนไข SQL "งานของฉัน" (มอบให้ฉัน หรือมอบให้ role ที่ฉันมี) */
function my_tasks_where(string $alias = 't'): array
{
    $u = current_user();
    $roles = $u['roles'] ?: ['__none__'];
    $in = implode(',', array_fill(0, count($roles), '?'));
    return ["($alias.assignee_id = ? OR ($alias.assignee_id IS NULL AND $alias.assignee_role IN ($in)))", array_merge([$u['id']], $roles)];
}

function my_open_task_count(): int
{
    [$w, $p] = my_tasks_where();
    return (int) val("SELECT COUNT(*) FROM tasks t WHERE $w AND t.status IN ('OPEN','IN_PROGRESS')", $p);
}

function task_can_act(array $task): bool
{
    $u = current_user();
    if (!$u) return false;
    if (can('task.view_all')) return true;
    if ($task['assignee_id'] === $u['id'] || $task['created_by'] === $u['id']) return true;
    return !$task['assignee_id'] && in_array($task['assignee_role'], $u['roles'], true);
}

function task_update_status(string $id, string $status): void
{
    v_in($status, TASK_STATUSES, 'สถานะงาน');
    $task = db_get('tasks', $id, 'งาน');
    if (!task_can_act($task)) throw new ForbiddenError('งานนี้ไม่ได้มอบให้คุณ');
    $extra = [];
    if ($status === 'DONE') $extra = ['completed_at' => now(), 'completed_by' => current_user_id()];
    // รับงานที่มอบให้ทั้ง role → ผูกกับผู้ที่กดรับ
    if ($status === 'IN_PROGRESS' && !$task['assignee_id']) $extra['assignee_id'] = current_user_id();
    update_audited('tasks', 'TASK', $id, ['status' => $status] + $extra, 'STATUS_CHANGE');
}

/** สร้างงานด้วยมือจากหน้า My Tasks หรือจากหน้า record */
function task_create_manual(array $d): string
{
    $title = s($d['title'] ?? '');
    if (!$title) throw new AppError('กรุณากรอกชื่องาน');
    $assignee = v_uuid($d['assignee_id'] ?? null, 'ผู้รับผิดชอบ', false);
    $role = s($d['assignee_role'] ?? '');
    if ($role !== null) v_in($role, ROLE_CODES, 'role');
    if (!$assignee && !$role) $assignee = current_user_id();
    if ($assignee && !val('SELECT id FROM users WHERE id = ? AND active = 1', [$assignee])) throw new AppError('ไม่พบผู้รับผิดชอบ');
    $parentType = s($d['parent_type'] ?? '');
    $parentId = s($d['parent_id'] ?? '');
    if ($parentType) {
        v_in($parentType, array_keys(PARENT_TYPES), 'ประเภทข้อมูล');
        parent_require_view($parentType, (string) $parentId);
    }
    return tx(function () use ($d, $title, $assignee, $role, $parentType, $parentId) {
        $id = task_create([
            'title' => $title,
            'description' => s($d['description'] ?? ''),
            'type' => 'MANUAL',
            'parent_type' => $parentType,
            'parent_id' => $parentType ? $parentId : null,
            'assignee_id' => $assignee,
            'assignee_role' => $assignee ? null : $role,
            'due_date' => v_date($d['due_date'] ?? null, 'กำหนดเสร็จ'),
            'priority' => v_in($d['priority'] ?? 'MEDIUM', ['LOW', 'MEDIUM', 'HIGH'], 'ความสำคัญ'),
        ]);
        audit_log('TASK', $id, 'CREATE', [['field' => 'title', 'old' => null, 'new' => $title]]);
        return $id;
    });
}

function tasks_for_parent(string $parentType, string $parentId): array
{
    return all("SELECT t.*, u.name AS assignee_name FROM tasks t LEFT JOIN users u ON u.id = t.assignee_id
                WHERE t.parent_type = ? AND t.parent_id = ? ORDER BY FIELD(t.status, 'OPEN', 'IN_PROGRESS', 'DONE', 'CANCELLED'), t.due_date IS NULL, t.due_date, t.created_at DESC",
        [$parentType, $parentId]);
}
