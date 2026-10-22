<?php
// กล่องอนุมัติการซื้อของ GM

function approvals_require_view(): void
{
    require_cap('acquisition.approve', 'task.view_all', 'acquisition.submit');
}

function c_approvals_index(): void
{
    approvals_require_view();
    $pending = all("SELECT a.*, o.ref_no, o.id AS opp_id, o.final_negotiated_price, dv.brand, dv.model, dv.ref_no AS device_ref, org.name AS seller_name, u.name AS requester_name
        FROM approvals a JOIN device_opportunities o ON o.id = a.subject_id JOIN devices dv ON dv.id = o.device_id
        JOIN organizations org ON org.id = o.seller_org_id JOIN users u ON u.id = a.requested_by
        WHERE a.subject_type = 'DEVICE_OPPORTUNITY' AND a.decision = 'PENDING' ORDER BY a.requested_at");
    $recent = all("SELECT a.*, o.ref_no, o.id AS opp_id, dv.brand, dv.model, org.name AS seller_name, ap.name AS approver_name
        FROM approvals a JOIN device_opportunities o ON o.id = a.subject_id JOIN devices dv ON dv.id = o.device_id
        JOIN organizations org ON org.id = o.seller_org_id LEFT JOIN users ap ON ap.id = a.approver_id
        WHERE a.subject_type = 'DEVICE_OPPORTUNITY' AND a.decision <> 'PENDING' ORDER BY a.decided_at DESC LIMIT 30");
    render('approvals/index', ['title' => 'อนุมัติการซื้อ', 'pending' => $pending, 'recent' => $recent]);
}

function c_approvals_view(): void
{
    approvals_require_view();
    $a = db_get('approvals', (string) get('id', ''), 'คำขออนุมัติ');
    $opp = db_get('device_opportunities', $a['subject_id']);
    $pkg = json_decode($a['package_snapshot'], true) ?: [];
    // ผู้ที่ไม่มี finance.view เห็นเฉพาะส่วนที่ไม่ใช่ข้อมูลการเงิน
    if (!can('finance.view')) {
        unset($pkg['cost_sheet'], $pkg['at_final_price']);
        $pkg['valuation'] = can('valuation.recommended_view') ? array_intersect_key($pkg['valuation'] ?? [], ['recommended_acq_price' => 1]) : [];
        foreach ($pkg['service_history'] ?? [] as $i => $s) unset($pkg['service_history'][$i]['cost']);
    }
    render('approvals/view', ['title' => 'อนุมัติการซื้อ ' . $opp['ref_no'], 'a' => $a, 'opp' => $opp, 'pkg' => $pkg]);
}

function c_approvals_post_decide(): void
{
    $id = (string) post('id', '');
    approval_decide($id, $_POST);
    flash('success', 'บันทึกผลการพิจารณาแล้ว');
    $a = db_get('approvals', $id);
    redirect('acq.view', ['id' => $a['subject_id']]);
}
