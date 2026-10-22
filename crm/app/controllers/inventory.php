<?php

function c_inventory_index(): void
{
    require_cap('inventory.view');
    $status = get('status', 'IN_STOCK');
    $q = get('q');
    $where = [];
    $p = [];
    if ($status !== 'ALL') { $where[] = 'i.status = ?'; $p[] = in_array($status, ['IN_STOCK', 'RESERVED', 'SOLD', 'REMOVED'], true) ? $status : 'IN_STOCK'; }
    if ($q) {
        $where[] = '(i.ref_no LIKE ? OR dv.ref_no LIKE ? OR dv.brand LIKE ? OR dv.model LIKE ? OR dv.serial_number LIKE ?)';
        $l = like($q);
        array_push($p, $l, $l, $l, $l, $l);
    }
    $page = paginate('FROM inventory i JOIN devices dv ON dv.id = i.device_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : ''), $p,
        'i.*, dv.ref_no AS device_ref, dv.brand, dv.model, dv.serial_number, dv.manufacture_year, dv.technical_status,
         (SELECT t.ref_no FROM sales_transactions t WHERE t.id = i.reserved_for_transaction_id) AS trx_ref,
         DATEDIFF(CURDATE(), i.received_date) AS days_in_stock', 'i.received_date');
    $rows = redact('inventory', $page['rows']);
    $page['rows'] = $rows;
    $totals = can('finance.view') ? one("SELECT COUNT(*) AS n, COALESCE(SUM(book_cost), 0) AS cost, COALESCE(SUM(list_price), 0) AS list FROM inventory WHERE status IN ('IN_STOCK','RESERVED')") : null;
    render('inventory/index', ['title' => 'สต็อก', 'page' => $page, 'status' => $status, 'totals' => $totals]);
}

function c_inventory_post_update(): void
{
    inventory_update((string) post('id', ''), $_POST);
    flash('success', 'บันทึกแล้ว');
    redirect_back('inventory');
}
