<?php

function c_tasks_index(): void
{
    $scope = get('scope', 'mine');
    if ($scope === 'all' && !can('task.view_all')) $scope = 'mine';
    $status = get('status', 'open');
    $where = [];
    $p = [];
    if ($scope === 'mine') {
        [$w, $wp] = my_tasks_where('t');
        $where[] = $w;
        $p = array_merge($p, $wp);
    } elseif ($scope === 'created') {
        $where[] = 't.created_by = ?';
        $p[] = current_user_id();
    }
    if ($status === 'open') $where[] = "t.status IN ('OPEN','IN_PROGRESS')";
    elseif ($status === 'done') $where[] = "t.status IN ('DONE','CANCELLED')";
    if (get('overdue')) { $where[] = "t.status IN ('OPEN','IN_PROGRESS') AND t.due_date < ?"; $p[] = today(); }
    $from = 'FROM tasks t LEFT JOIN users u ON u.id = t.assignee_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
    $page = paginate($from, $p, 't.*, u.name AS assignee_name',
        "FIELD(t.status, 'IN_PROGRESS', 'OPEN', 'DONE', 'CANCELLED'), t.due_date IS NULL, t.due_date, FIELD(t.priority, 'HIGH', 'MEDIUM', 'LOW'), t.created_at DESC");
    render('tasks/index', ['title' => 'งานของฉัน', 'page' => $page, 'scope' => $scope, 'status' => $status]);
}

function c_tasks_post_create(): void
{
    task_create_manual($_POST);
    flash('success', 'มอบหมายงานแล้ว');
    $type = post('parent_type');
    if ($type && isset(PARENT_TYPES[$type])) {
        header('Location: ' . parent_url($type, (string) post('parent_id')) . '#tasks', true, 303);
        throw new RedirectSignal();
    }
    redirect('tasks');
}

function c_tasks_post_status(): void
{
    task_update_status((string) post('id', ''), (string) post('status', ''));
    flash('success', 'อัปเดตงานแล้ว');
    redirect_back('tasks');
}
