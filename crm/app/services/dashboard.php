<?php
// My Dashboard: งานวันนี้ → ติดตามที่เลยกำหนด → รออนุมัติ → ดีลที่ยังเปิด → ตัวเลขสรุป (ตามลำดับในสเปกข้อ 11)

function dashboard_data(bool $team): array
{
    $d = [];
    [$w, $p] = my_tasks_where('t');
    $d['tasks'] = all("SELECT t.*, u.name AS assignee_name FROM tasks t LEFT JOIN users u ON u.id = t.assignee_id
        WHERE $w AND t.status IN ('OPEN','IN_PROGRESS') AND (t.due_date IS NULL OR t.due_date <= ?)
        ORDER BY t.due_date IS NULL, t.due_date, FIELD(t.priority, 'HIGH', 'MEDIUM', 'LOW'), t.created_at LIMIT 50", array_merge($p, [today()]));
    $d['tasks_upcoming'] = (int) val("SELECT COUNT(*) FROM tasks t WHERE $w AND t.status IN ('OPEN','IN_PROGRESS') AND t.due_date > ?", array_merge($p, [today()]));

    $owner = $team ? '' : 'me';
    $fq = followup_query(['owner' => $owner, 'overdue' => true]);
    $d['overdue'] = $fq['sql'] ? all('SELECT x.*, u.name AS owner_name ' . $fq['sql'] . ' ORDER BY x.next_follow_up_date LIMIT 50', $fq['params']) : [];
    $fq = followup_query(['owner' => $owner, 'due_today' => true]);
    $d['due_today'] = $fq['sql'] ? all('SELECT x.*, u.name AS owner_name ' . $fq['sql'] . ' ORDER BY x.ref_no LIMIT 50', $fq['params']) : [];
    // ดีลที่ยังไม่มีวันติดตาม (ผิด BR-01)
    $fq = followup_query(['owner' => $owner, 'no_followup' => true]);
    $d['no_followup'] = $fq['sql'] ? all('SELECT x.*, u.name AS owner_name ' . $fq['sql'] . ' LIMIT 20', $fq['params']) : [];

    $d['approvals'] = [];
    if (can('acquisition.approve') || can('task.view_all')) {
        $d['approvals'] = all("SELECT a.*, o.ref_no, o.final_negotiated_price, o.id AS opp_id, dv.brand, dv.model, org.name AS seller_name, u.name AS requester_name
            FROM approvals a JOIN device_opportunities o ON o.id = a.subject_id JOIN devices dv ON dv.id = o.device_id
            JOIN organizations org ON org.id = o.seller_org_id JOIN users u ON u.id = a.requested_by
            WHERE a.decision = 'PENDING' AND a.subject_type = 'DEVICE_OPPORTUNITY' ORDER BY a.requested_at LIMIT 20");
    }
    $d['quote_approvals'] = [];
    if (can('quotation.approve')) {
        $d['quote_approvals'] = all("SELECT qv.*, q.ref_no, q.id AS quotation_id, org.name AS buyer_name FROM quotation_versions qv
            JOIN quotations q ON q.id = qv.quotation_id AND q.current_version_no = qv.version_no
            JOIN sales_opportunities s ON s.id = q.sales_opportunity_id JOIN organizations org ON org.id = s.buyer_org_id
            WHERE qv.status = 'DRAFT' AND qv.approval_requested_at IS NOT NULL ORDER BY qv.approval_requested_at LIMIT 20");
    }

    $fq = followup_query(['owner' => $owner, 'state' => 'active']);
    $d['active'] = $fq['sql'] ? all('SELECT x.*, u.name AS owner_name ' . $fq['sql'] . ' ORDER BY x.updated_at DESC LIMIT 15', $fq['params']) : [];
    $d['active_count'] = $fq['sql'] ? (int) val('SELECT COUNT(*) ' . $fq['sql'], $fq['params']) : 0;

    $d['trx_open'] = can('transaction.view') ? all("SELECT t.*, org.name AS buyer_name FROM sales_transactions t JOIN organizations org ON org.id = t.buyer_org_id
        WHERE t.status IN ('IN_PREPARATION','READY_FOR_DELIVERY','DELIVERED') ORDER BY t.won_at LIMIT 15") : [];

    $d['stats'] = [
        'in_stock' => can('inventory.view') ? (int) val("SELECT COUNT(*) FROM inventory WHERE status = 'IN_STOCK'") : null,
        'buy_deals' => can('lead.view') ? (int) val("SELECT COUNT(*) FROM device_opportunities WHERE status NOT IN ('PURCHASED','REJECTED','LOST')") : null,
        'sell_deals' => can('sales.view') ? (int) val("SELECT COUNT(*) FROM sales_opportunities WHERE status NOT IN ('WON','LOST')") : null,
        'won_month' => can('transaction.view') ? (int) val("SELECT COUNT(*) FROM sales_transactions WHERE status <> 'CANCELLED' AND won_at >= ?", [date('Y-m-01')]) : null,
        'won_value_month' => (can('transaction.view') && can('finance.view')) ? (float) val("SELECT COALESCE(SUM(total_price), 0) FROM sales_transactions WHERE status <> 'CANCELLED' AND won_at >= ?", [date('Y-m-01')]) : null,
    ];
    return $d;
}
