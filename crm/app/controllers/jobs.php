<?php
// ใบงานช่าง

function c_jobs_index(): void
{
    require_cap('job.view');
    $status = get('status', 'open');
    $where = [];
    $p = [];
    if ($status === 'open') $where[] = "j.status IN ('OPEN','SCHEDULED','IN_PROGRESS')";
    elseif ($status !== 'all' && in_array($status, JOB_STATUSES, true)) { $where[] = 'j.status = ?'; $p[] = $status; }
    if (get('mine')) { $where[] = 'j.assigned_engineer_id = ?'; $p[] = current_user_id(); }
    $page = paginate('FROM technical_jobs j LEFT JOIN devices dv ON dv.id = j.device_id LEFT JOIN users u ON u.id = j.assigned_engineer_id'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : ''), $p,
        'j.*, dv.ref_no AS device_ref, dv.brand, dv.model, u.name AS engineer_name', "FIELD(j.status, 'IN_PROGRESS', 'SCHEDULED', 'OPEN', 'DONE', 'CANCELLED'), j.scheduled_date IS NULL, j.scheduled_date, j.created_at");
    render('jobs/index', ['title' => 'ใบงานช่าง', 'page' => $page, 'status' => $status]);
}

function c_jobs_view(): void
{
    require_cap('job.view');
    $j = db_get('technical_jobs', (string) get('id', ''), 'ใบงาน');
    render('jobs/view', [
        'title' => 'ใบงาน ' . $j['ref_no'], 'j' => $j,
        'device' => $j['device_id'] ? db_find('devices', $j['device_id']) : null,
        'tasks' => tasks_for_parent('TECHNICAL_JOB', $j['id']),
        'documents' => documents_for('TECHNICAL_JOB', $j['id']),
        'activities' => activities_for('TECHNICAL_JOB', $j['id']),
    ]);
}

function c_jobs_new(): void
{
    require_cap('job.edit');
    $device = db_get('devices', (string) get('device', ''), 'เครื่อง');
    render('jobs/new', ['title' => 'เปิดใบงานช่าง', 'device' => $device]);
}

function c_jobs_post_create(): void
{
    $id = job_create($_POST);
    flash('success', 'เปิดใบงานแล้ว');
    redirect('jobs.view', ['id' => $id]);
}

function c_jobs_post_update(): void
{
    $id = (string) post('id', '');
    job_update($id, $_POST);
    flash('success', 'บันทึกใบงานแล้ว');
    redirect('jobs.view', ['id' => $id]);
}
