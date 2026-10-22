<?php

function c_quotations_index(): void
{
    require_cap('quotation.view');
    quotation_expire_due();
    $status = get('status');
    $q = get('q');
    $where = [];
    $p = [];
    if ($status && in_array($status, ['DRAFT', 'APPROVED', 'SENT', 'ACCEPTED', 'REJECTED', 'EXPIRED'], true)) { $where[] = 'qv.status = ?'; $p[] = $status; }
    if ($q) { $where[] = '(q.ref_no LIKE ? OR org.name LIKE ? OR s.ref_no LIKE ?)'; $l = like($q); array_push($p, $l, $l, $l); }
    $page = paginate('FROM quotations q JOIN quotation_versions qv ON qv.quotation_id = q.id AND qv.version_no = q.current_version_no
        JOIN sales_opportunities s ON s.id = q.sales_opportunity_id JOIN organizations org ON org.id = s.buyer_org_id'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : ''), $p,
        'q.*, qv.status AS v_status, qv.total, qv.valid_until, qv.approval_requested_at, s.ref_no AS so_ref, s.id AS so_id, org.name AS buyer_name', 'q.created_at DESC');
    render('quotations/index', ['title' => 'ใบเสนอราคา', 'page' => $page, 'status' => $status]);
}

function quotation_page_data(string $id): array
{
    require_cap('quotation.view');
    quotation_expire_due();
    $q = db_get('quotations', $id, 'ใบเสนอราคา');
    $versions = all('SELECT qv.*, a.name AS approver_name FROM quotation_versions qv LEFT JOIN users a ON a.id = qv.approved_by WHERE qv.quotation_id = ? ORDER BY qv.version_no DESC', [$id]);
    $show = (int) (get('v') ?? $q['current_version_no']);
    $v = null;
    foreach ($versions as $x) if ((int) $x['version_no'] === $show) $v = $x;
    if (!$v) $v = $versions[0];
    $so = db_get('sales_opportunities', $q['sales_opportunity_id']);
    return [
        'q' => $q, 'versions' => $versions, 'v' => $v, 'so' => $so,
        'buyer' => db_get('organizations', $so['buyer_org_id']),
        'contact' => $so['contact_id'] ? db_find('contacts', $so['contact_id']) : null,
        'lines' => all('SELECT * FROM quotation_version_lines WHERE quotation_version_id = ? ORDER BY sort', [$v['id']]),
        'matched' => all('SELECT m.device_id, dv.ref_no, dv.brand, dv.model FROM device_matches m JOIN devices dv ON dv.id = m.device_id WHERE m.sales_opportunity_id = ?', [$so['id']]),
        'below' => can('finance.view') ? qv_below_min_lines($v['id']) : [],
    ];
}

function c_quotations_view(): void
{
    $d = quotation_page_data((string) get('id', ''));
    $d['documents'] = documents_for('QUOTATION', $d['q']['id']);
    $d['tasks'] = tasks_for_parent('QUOTATION', $d['q']['id']);
    render('quotations/view', ['title' => 'ใบเสนอราคา ' . $d['q']['ref_no']] + $d);
}

function c_quotations_print(): void
{
    $d = quotation_page_data((string) get('id', ''));
    render('quotations/print', ['title' => 'ใบเสนอราคา ' . $d['q']['ref_no']] + $d, 'layout_print');
}

function quotation_redirect(string $versionId): void
{
    $v = db_get('quotation_versions', $versionId);
    redirect('quotations.view', ['id' => $v['quotation_id']]);
}

function c_quotations_post_save(): void
{
    $vid = (string) post('version_id', '');
    qv_save($vid, $_POST);
    if (post('action') === 'request') {
        qv_request_approval($vid);
        flash('success', 'บันทึกและส่งขออนุมัติแล้ว');
    } else {
        flash('success', 'บันทึกร่างแล้ว');
    }
    quotation_redirect($vid);
}

function c_quotations_post_approve(): void
{
    $vid = (string) post('version_id', '');
    qv_approve($vid);
    flash('success', 'อนุมัติใบเสนอราคาแล้ว');
    quotation_redirect($vid);
}

function c_quotations_post_send(): void
{
    $vid = (string) post('version_id', '');
    qv_send($vid);
    flash('success', 'บันทึกว่าส่งใบเสนอราคาให้ลูกค้าแล้ว');
    quotation_redirect($vid);
}

function c_quotations_post_respond(): void
{
    $vid = (string) post('version_id', '');
    $r = qv_respond($vid, (string) post('response', ''), post('note'));
    flash('success', 'บันทึกคำตอบของลูกค้าแล้ว');
    sales_flash_won_q($r);
    quotation_redirect($vid);
}

function sales_flash_won_q(array $r): void
{
    if (!empty($r['won'])) flash('success', '🎉 ปิดการขายได้ (WON)');
    elseif (!empty($r['missing'])) flash('info', 'ขั้นต่อไปเพื่อปิดการขาย: ' . implode(', ', $r['missing']));
    elseif (!empty($r['blocked'])) flash('warning', $r['blocked']);
}

function c_quotations_post_revise(): void
{
    $qid = (string) post('id', '');
    quotation_revise($qid);
    flash('success', 'ออกฉบับแก้ไขแล้ว (ฉบับเดิมเก็บไว้แบบอ่านอย่างเดียว)');
    redirect('quotations.view', ['id' => $qid]);
}
