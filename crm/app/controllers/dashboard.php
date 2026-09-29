<?php

function c_dashboard_index(): void
{
    $team = get('view') === 'team' && (can('task.view_all') || has_role('SALES_DIRECTOR') || has_role('SERVICE_DIRECTOR'));
    render('dashboard/index', ['title' => 'หน้าหลัก', 'd' => dashboard_data($team), 'team' => $team]);
}
