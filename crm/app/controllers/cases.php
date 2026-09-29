<?php
// เคสบริการหลังการขาย

function c_cases_index(): void
{
    require_cap('service_case.view');
    $status = get('status', 'open');
    $where = [];
    $p = [];
    if ($status === 'open') $where[] = "c.status IN ('OPEN','IN_PROGRESS')";
    elseif ($status !== 'all' && in_array($status, CASE_STATUSES, true)) { $where[] = 'c.status = ?'; $p[] = $status; }
    $page = paginate('FROM service_cases c JOIN devices dv ON dv.id = c.device_id LEFT JOIN organizations o ON o.id = c.organization_id LEFT JOIN users u ON u.id = c.assigned_to'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : ''), $p,
        'c.*, dv.ref_no AS device_ref, dv.brand, dv.model, o.name AS org_name, u.name AS assignee_name', 'c.reported_at DESC');
    render('cases/index', ['title' => 'เคสบริการ', 'page' => $page, 'status' => $status]);
}

function c_cases_new(): void
{
    require_cap('service_case.edit');
    $device = db_get('devices', (string) get('device', ''), 'เครื่อง');
    render('cases/form', ['title' => 'เปิดเคสบริการ', 'device' => $device, 'c' => null]);
}

function c_cases_post_save(): void
{
    $id = case_save(post('id'), $_POST);
    flash('success', 'บันทึกเคสบริการแล้ว');
    redirect('cases.view', ['id' => $id]);
}

function c_cases_view(): void
{
    require_cap('service_case.view');
    $c = db_get('service_cases', (string) get('id', ''), 'เคสบริการ');
    render('cases/view', [
        'title' => 'เคส ' . $c['ref_no'], 'c' => $c,
        'device' => db_get('devices', $c['device_id']),
        'org' => $c['organization_id'] ? db_find('organizations', $c['organization_id']) : null,
        'activities' => activities_for('SERVICE_CASE', $c['id']),
        'documents' => documents_for('SERVICE_CASE', $c['id']),
        'tasks' => tasks_for_parent('SERVICE_CASE', $c['id']),
    ]);
}
