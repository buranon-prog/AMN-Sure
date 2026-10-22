<?php
// ธุรกรรมขาย: เช็กลิสต์ → QC → ส่งมอบ → ติดตั้ง

function c_trx_index(): void
{
    require_cap('transaction.view');
    $status = get('status', 'open');
    $where = [];
    $p = [];
    if ($status === 'open') $where[] = "t.status IN ('IN_PREPARATION','READY_FOR_DELIVERY','DELIVERED')";
    elseif ($status !== 'all' && in_array($status, TRX_STATUSES, true)) { $where[] = 't.status = ?'; $p[] = $status; }
    if ($q = get('q')) { $where[] = '(t.ref_no LIKE ? OR org.name LIKE ?)'; $l = like($q); array_push($p, $l, $l); }
    $page = paginate('FROM sales_transactions t JOIN organizations org ON org.id = t.buyer_org_id JOIN sales_opportunities s ON s.id = t.sales_opportunity_id'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : ''), $p,
        "t.*, org.name AS buyer_name, s.ref_no AS so_ref,
         (SELECT COUNT(*) FROM checklist_items ci JOIN checklists c ON c.id = ci.checklist_id WHERE c.sales_transaction_id = t.id AND ci.is_mandatory = 1 AND ci.done = 0) AS checklist_left",
        't.won_at DESC');
    render('trx/index', ['title' => 'ธุรกรรมขาย', 'page' => $page, 'status' => $status]);
}

function c_trx_view(): void
{
    $d = trx_view_data((string) get('id', ''));
    render('trx/view', ['title' => 'ธุรกรรม ' . $d['trx']['ref_no'], 'd' => $d]);
}

function c_trx_post_checklist(): void
{
    $item = db_get('checklist_items', (string) post('item_id', ''), 'รายการเช็กลิสต์');
    $cl = db_get('checklists', $item['checklist_id']);
    checklist_item_set($item['id'], post('done') === '1', post('note'));
    redirect('trx.view', ['id' => $cl['sales_transaction_id']], 'checklist');
}

function c_trx_post_qc(): void
{
    $id = (string) post('id', '');
    qc_record($id, (string) post('device_id', ''), $_POST);
    $qc = latest_qc($id, (string) post('device_id', ''));
    flash($qc && $qc['overall_result'] === 'PASS' ? 'success' : 'warning', $qc && $qc['overall_result'] === 'PASS' ? 'QC ผ่าน' : 'QC ไม่ผ่าน — เปิดใบงานซ่อมให้แล้ว แก้ไขแล้วทำ QC ใหม่');
    redirect('trx.view', ['id' => $id], 'qc');
}

function c_trx_post_ready(): void
{
    $id = (string) post('id', '');
    trx_mark_ready($id);
    flash('success', 'พร้อมส่งมอบแล้ว');
    redirect('trx.view', ['id' => $id]);
}

function c_trx_post_delivery(): void
{
    $id = (string) post('id', '');
    delivery_record($id, (string) post('device_id', ''), $_POST, $_FILES['evidence'] ?? null);
    flash('success', 'บันทึกการส่งมอบแล้ว');
    redirect('trx.view', ['id' => $id], 'delivery');
}

function c_trx_post_installation(): void
{
    $id = (string) post('id', '');
    installation_record($id, (string) post('device_id', ''), $_POST, $_FILES['evidence'] ?? null);
    $t = db_get('sales_transactions', $id);
    flash('success', $t['status'] === 'TRANSACTION_COMPLETED' ? '🎉 ติดตั้งเสร็จ ธุรกรรมเสร็จสมบูรณ์' : 'บันทึกการติดตั้งแล้ว');
    redirect('trx.view', ['id' => $id], 'installation');
}

function c_trx_post_cancel(): void
{
    $id = (string) post('id', '');
    trx_cancel($id, (string) post('reason', ''));
    flash('success', 'ยกเลิกธุรกรรมแล้ว คืนเครื่องเข้าสต็อก');
    redirect('trx.view', ['id' => $id]);
}
