<?php
// หน้าจอ workflow ฝั่งขาย (Sales Opportunity)

function sales_flash_won(array $r): void
{
    if (!empty($r['won'])) flash('success', '🎉 ปิดการขายได้ (WON) — สร้างธุรกรรมขายและเช็กลิสต์ให้แล้ว');
    elseif (!empty($r['blocked'])) flash('warning', 'ยังปิดการขายไม่ได้: ' . $r['blocked']);
    elseif (!empty($r['missing'])) flash('info', 'เงื่อนไข WON ที่ยังขาด: ' . implode(', ', $r['missing']));
}

function c_sales_index(): void
{
    require_cap('sales.view');
    $status = get('status', 'open');
    $q = get('q');
    $where = [];
    $p = [];
    if ($status === 'open') $where[] = "s.status NOT IN ('WON','LOST')";
    elseif ($status !== 'all' && in_array($status, SO_STATUSES, true)) { $where[] = 's.status = ?'; $p[] = $status; }
    if (get('owner') === 'me') { $where[] = 's.owner_id = ?'; $p[] = current_user_id(); }
    if ($q) {
        $where[] = '(s.ref_no LIKE ? OR org.name LIKE ? OR s.interested_device LIKE ? OR s.req_brand LIKE ? OR s.req_model LIKE ?)';
        $l = like($q);
        array_push($p, $l, $l, $l, $l, $l);
    }
    $page = paginate('FROM sales_opportunities s JOIN organizations org ON org.id = s.buyer_org_id JOIN users u ON u.id = s.owner_id'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : ''), $p,
        's.*, org.name AS buyer_name, u.name AS owner_name', 's.next_follow_up_date IS NULL, s.next_follow_up_date, s.created_at DESC');
    $counts = [];
    foreach (all('SELECT status, COUNT(*) AS n FROM sales_opportunities GROUP BY status') as $r) $counts[$r['status']] = (int) $r['n'];
    render('sales/index', ['title' => 'ดีลขาย', 'page' => $page, 'status' => $status, 'counts' => $counts]);
}

function c_sales_view(): void
{
    $d = so_view_data((string) get('id', ''));
    render('sales/view', ['title' => 'ดีลขาย ' . $d['so']['ref_no'], 'd' => $d]);
}

function c_sales_post_requirement(): void
{
    $id = (string) post('id', '');
    requirement_save($id, $_POST);
    flash('success', 'บันทึกความต้องการแล้ว');
    redirect('sales.view', ['id' => $id]);
}

function c_sales_match(): void
{
    require_cap('sales.view');
    $so = so_get((string) get('id', ''));
    render('sales/match', [
        'title' => 'จับคู่เครื่อง ' . $so['ref_no'],
        'so' => $so,
        'buyer' => db_get('organizations', $so['buyer_org_id']),
        'cands' => match_candidates($so['id'], get('q')),
    ]);
}

function c_sales_post_match_add(): void
{
    $id = (string) post('id', '');
    match_add($id, (string) post('source', ''), (string) post('ref_id', ''), post('note'));
    flash('success', 'เพิ่มเครื่องในรายการจับคู่แล้ว');
    redirect('sales.view', ['id' => $id], 'matches');
}

function c_sales_post_match_status(): void
{
    $m = db_get('device_matches', (string) post('match_id', ''), 'การจับคู่');
    match_set_status($m['id'], (string) post('status', ''));
    redirect('sales.view', ['id' => $m['sales_opportunity_id']], 'matches');
}

function c_sales_post_quotation(): void
{
    $id = (string) post('id', '');
    $qId = quotation_create($id);
    flash('success', 'สร้างใบเสนอราคาแล้ว — ตรวจราคาแล้วขออนุมัติ');
    redirect('quotations.view', ['id' => $qId]);
}

function c_sales_post_deposit(): void
{
    $id = (string) post('id', '');
    deposit_save($id, post('deposit_id'), $_POST);
    flash('success', 'บันทึกรายการมัดจำแล้ว');
    redirect('sales.view', ['id' => $id], 'deposits');
}

function c_sales_post_deposit_receive(): void
{
    $dep = db_get('deposits', (string) post('deposit_id', ''), 'รายการมัดจำ');
    $r = deposit_receive($dep['id'], $_POST, $_FILES['evidence'] ?? null);
    flash('success', 'บันทึกรับมัดจำแล้ว');
    sales_flash_won($r);
    redirect('sales.view', ['id' => $dep['sales_opportunity_id']], 'deposits');
}

function c_sales_post_deposit_verify(): void
{
    $dep = db_get('deposits', (string) post('deposit_id', ''), 'รายการมัดจำ');
    $r = deposit_verify($dep['id']);
    flash('success', 'ยืนยันยอดมัดจำแล้ว');
    sales_flash_won($r);
    redirect('sales.view', ['id' => $dep['sales_opportunity_id']], 'deposits');
}

function c_sales_post_deposit_close(): void
{
    $dep = db_get('deposits', (string) post('deposit_id', ''), 'รายการมัดจำ');
    deposit_close($dep['id'], (string) post('status', ''), (string) post('reason', ''));
    flash('success', 'ปิดรายการมัดจำแล้ว');
    redirect('sales.view', ['id' => $dep['sales_opportunity_id']], 'deposits');
}

function c_sales_post_contract(): void
{
    $id = (string) post('id', '');
    contract_save($id, post('contract_id'), $_POST);
    flash('success', 'บันทึกสัญญาแล้ว');
    redirect('sales.view', ['id' => $id], 'contracts');
}

function c_sales_post_contract_sign(): void
{
    $c = db_get('contracts', (string) post('contract_id', ''), 'สัญญา');
    $r = contract_sign($c['id'], $_POST, $_FILES['signed_file'] ?? null);
    flash('success', 'บันทึกการลงนามสัญญาแล้ว');
    sales_flash_won($r);
    redirect('sales.view', ['id' => $c['sales_opportunity_id']], 'contracts');
}

function c_sales_post_contract_void(): void
{
    $c = db_get('contracts', (string) post('contract_id', ''), 'สัญญา');
    contract_void($c['id'], (string) post('reason', ''));
    flash('success', 'ยกเลิกสัญญาแล้ว');
    redirect('sales.view', ['id' => $c['sales_opportunity_id']], 'contracts');
}

function c_sales_post_lost(): void
{
    $id = (string) post('id', '');
    sales_mark_lost($id, (string) post('reason', ''));
    flash('success', 'ปิดดีลแล้ว');
    redirect('sales.view', ['id' => $id]);
}
