<?php
// Audit log (ดูอย่างเดียว)

function c_audit_index(): void
{
    require_cap('audit.view');
    $where = [];
    $p = [];
    $entity = get('entity');
    if ($entity && isset(LABELS['entity'][$entity])) { $where[] = 'a.entity_type = ?'; $p[] = $entity; }
    if (($eid = get('entity_id')) && is_uuid($eid)) { $where[] = 'a.entity_id = ?'; $p[] = $eid; }
    if (($uid = get('user')) && is_uuid($uid)) { $where[] = 'a.user_id = ?'; $p[] = $uid; }
    if ($action = get('action')) { $where[] = 'a.action = ?'; $p[] = $action; }
    if ($from = v_date(get('from'), 'จากวันที่')) { $where[] = 'a.created_at >= ?'; $p[] = $from . ' 00:00:00'; }
    if ($to = v_date(get('to'), 'ถึงวันที่')) { $where[] = 'a.created_at <= ?'; $p[] = $to . ' 23:59:59'; }
    $page = paginate('FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : ''), $p,
        'a.*, u.name AS user_name', 'a.created_at DESC', 100);
    render('audit/index', ['title' => 'Audit log', 'page' => $page]);
}
