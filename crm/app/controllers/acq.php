<?php
// หน้าจอ workflow ฝั่งซื้อ (Device Opportunity)

function c_acq_index(): void
{
    require_cap('lead.view');
    $status = get('status');
    $q = get('q');
    $where = [];
    $p = [];
    if ($status === 'open' || $status === null) { $where[] = "o.status NOT IN ('PURCHASED','REJECTED','LOST')"; $status = 'open'; }
    elseif ($status !== 'all' && in_array($status, DO_STATUSES, true)) { $where[] = 'o.status = ?'; $p[] = $status; }
    if (get('owner') === 'me') { $where[] = 'o.owner_id = ?'; $p[] = current_user_id(); }
    if ($q) {
        $where[] = '(o.ref_no LIKE ? OR org.name LIKE ? OR dv.brand LIKE ? OR dv.model LIKE ? OR dv.serial_number LIKE ? OR dv.ref_no LIKE ?)';
        $l = like($q);
        array_push($p, $l, $l, $l, $l, $l, $l);
    }
    $page = paginate('FROM device_opportunities o JOIN devices dv ON dv.id = o.device_id JOIN organizations org ON org.id = o.seller_org_id JOIN users u ON u.id = o.owner_id'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : ''), $p,
        'o.*, dv.brand, dv.model, dv.serial_number, dv.ref_no AS device_ref, org.name AS seller_name, u.name AS owner_name',
        "FIELD(o.status, 'PENDING_APPROVAL', 'APPROVED', 'NEGOTIATING', 'VALUED', 'COSTED', 'INSPECTED', 'INSPECTION_IN_PROGRESS', 'WAITING_INSPECTION', 'NEW_SELLER_LEAD'), o.next_follow_up_date");
    $counts = [];
    foreach (all('SELECT status, COUNT(*) AS n FROM device_opportunities GROUP BY status') as $r) $counts[$r['status']] = (int) $r['n'];
    render('acq/index', ['title' => 'ดีลซื้อ', 'page' => $page, 'status' => $status, 'counts' => $counts]);
}

function c_acq_view(): void
{
    $d = acq_view_data((string) get('id', ''));
    render('acq/view', ['title' => 'ดีลซื้อ ' . $d['opp']['ref_no'], 'd' => $d]);
}

function c_acq_post_request_inspection(): void
{
    $id = (string) post('id', '');
    acq_request_inspection($id, $_POST);
    flash('success', 'ส่งคำขอตรวจเครื่องให้ Service Engineering แล้ว');
    redirect('acq.view', ['id' => $id]);
}

function c_acq_inspection(): void
{
    require_cap('inspection.view');
    $insp = db_get('inspections', (string) get('id', ''), 'การตรวจเครื่อง');
    render('acq/inspection', [
        'title' => 'ตรวจเครื่อง',
        'insp' => $insp,
        'device' => db_get('devices', $insp['device_id']),
        'opp' => $insp['device_opportunity_id'] ? db_find('device_opportunities', $insp['device_opportunity_id']) : null,
        'items' => all('SELECT * FROM inspection_items WHERE inspection_id = ? ORDER BY sort', [$insp['id']]),
        'documents' => documents_for('INSPECTION', $insp['id']),
    ]);
}

function c_acq_post_inspection_start(): void
{
    $id = (string) post('id', '');
    inspection_start($id);
    flash('success', 'เริ่มตรวจเครื่องแล้ว — บันทึกผลได้เลย');
    redirect('acq.inspection', ['id' => $id]);
}

function c_acq_post_inspection_save(): void
{
    $id = (string) post('id', '');
    $complete = post('action') === 'complete';
    inspection_save($id, $_POST, $complete);
    flash('success', $complete ? 'ปิดการตรวจเครื่องแล้ว แจ้งเจ้าของดีลและ Service Director ให้แล้ว' : 'บันทึกร่างผลตรวจแล้ว');
    $insp = db_get('inspections', $id);
    if ($complete && $insp['device_opportunity_id']) redirect('acq.view', ['id' => $insp['device_opportunity_id']]);
    redirect('acq.inspection', ['id' => $id]);
}

function c_acq_cost_sheet(): void
{
    require_cap('cost_sheet.edit');
    $opp = acq_get((string) get('id', ''));
    $draft = one("SELECT * FROM cost_sheets WHERE device_opportunity_id = ? AND status = 'DRAFT' ORDER BY version_no DESC LIMIT 1", [$opp['id']]);
    $base = $draft ?? latest_cost_sheet($opp['id']);
    render('acq/cost_sheet', [
        'title' => 'Cost Sheet ' . $opp['ref_no'],
        'opp' => $opp,
        'device' => db_get('devices', $opp['device_id']),
        'draft' => $draft,
        'base' => $base,
        'items' => $base ? all('SELECT * FROM cost_sheet_items WHERE cost_sheet_id = ? ORDER BY sort', [$base['id']]) : [],
        'inspections' => all("SELECT * FROM inspections WHERE device_opportunity_id = ? AND status = 'COMPLETED' ORDER BY completed_at DESC", [$opp['id']]),
    ]);
}

function c_acq_post_cost_sheet(): void
{
    $id = (string) post('id', '');
    $submit = post('action') === 'submit';
    cost_sheet_save($id, $_POST, $submit);
    flash('success', $submit ? 'ส่ง Cost Sheet แล้ว — แจ้ง Sales Director ให้ทำ Valuation' : 'บันทึกร่าง Cost Sheet แล้ว');
    if ($submit) redirect('acq.view', ['id' => $id], 'cost');
    redirect('acq.cost_sheet', ['id' => $id]);
}

function c_acq_valuation(): void
{
    require_cap('valuation.edit');
    $opp = acq_get((string) get('id', ''));
    $cs = latest_cost_sheet($opp['id']);
    if (!$cs) throw new AppError('ต้องมี Cost Sheet ที่ส่งแล้วก่อนทำ Valuation');
    render('acq/valuation', [
        'title' => 'Valuation ' . $opp['ref_no'],
        'opp' => $opp,
        'device' => db_get('devices', $opp['device_id']),
        'cs' => $cs,
        'csItems' => all('SELECT * FROM cost_sheet_items WHERE cost_sheet_id = ? ORDER BY sort', [$cs['id']]),
        'insp' => latest_completed_inspection($opp['id']),
        'prev' => latest_valuation($opp['id']),
        'history' => all("SELECT l.unit_price, qv.status, t.won_at FROM quotation_version_lines l JOIN quotation_versions qv ON qv.id = l.quotation_version_id
            JOIN sales_transactions t ON t.quotation_version_id = qv.id JOIN devices dv ON dv.id = l.device_id
            WHERE t.status <> 'CANCELLED' AND dv.brand = ? AND dv.model = ? ORDER BY t.won_at DESC LIMIT 5", [db_get('devices', $opp['device_id'])['brand'], db_get('devices', $opp['device_id'])['model']]),
    ]);
}

function c_acq_post_valuation(): void
{
    $id = (string) post('id', '');
    valuation_create($id, $_POST);
    flash('success', 'บันทึก Valuation แล้ว');
    redirect('acq.view', ['id' => $id], 'valuation');
}

function c_acq_post_negotiation(): void
{
    $id = (string) post('id', '');
    negotiation_add($id, $_POST);
    flash('success', 'บันทึกการเจรจาแล้ว');
    redirect('acq.view', ['id' => $id], 'negotiation');
}

function c_acq_post_submit_approval(): void
{
    $id = (string) post('id', '');
    approval_submit($id, $_POST);
    flash('success', 'ส่งขออนุมัติให้ GM แล้ว');
    redirect('acq.view', ['id' => $id]);
}

function c_acq_purchase(): void
{
    require_cap('acquisition.create');
    $opp = acq_get((string) get('id', ''));
    render('acq/purchase', [
        'title' => 'บันทึกการซื้อ ' . $opp['ref_no'],
        'opp' => $opp,
        'device' => db_get('devices', $opp['device_id']),
        'approval' => latest_approval($opp['id']),
        'seller' => db_get('organizations', $opp['seller_org_id']),
    ]);
}

function c_acq_post_purchase(): void
{
    $id = (string) post('id', '');
    acquisition_create($id, $_POST);
    flash('success', 'บันทึกการซื้อแล้ว เครื่องเป็นของ AMN Sure' . (post('takes_stock') !== '0' ? ' และเข้าสต็อกแล้ว' : ''));
    redirect('acq.view', ['id' => $id]);
}

function c_acq_post_lost(): void
{
    $id = (string) post('id', '');
    acq_mark_lost($id, (string) post('reason', ''));
    flash('success', 'ปิดดีลแล้ว');
    redirect('acq.view', ['id' => $id]);
}
