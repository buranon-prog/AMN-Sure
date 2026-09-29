<?php

function c_customers_index(): void
{
    require_cap('customer.view');
    $q = get('q');
    $type = get('type');
    $owner = get('owner');
    $where = ['o.archived_at IS NULL'];
    $p = [];
    if (get('archived')) $where = ['o.archived_at IS NOT NULL'];
    if ($q) {
        $where[] = '(o.name LIKE ? OR o.ref_no LIKE ? OR o.phone LIKE ? OR o.province LIKE ? OR o.line_id LIKE ?)';
        $l = like($q);
        array_push($p, $l, $l, $l, $l, $l);
    }
    if ($type && in_array($type, ORG_TYPES, true)) { $where[] = 'o.type = ?'; $p[] = $type; }
    if ($owner === 'me') { $where[] = 'o.account_owner_id = ?'; $p[] = current_user_id(); }
    $from = 'FROM organizations o LEFT JOIN users u ON u.id = o.account_owner_id WHERE ' . implode(' AND ', $where);
    $page = paginate($from, $p, "o.*, u.name AS owner_name,
        (SELECT MAX(a.occurred_at) FROM activities a WHERE a.organization_id = o.id AND a.type <> 'SYSTEM') AS last_activity,
        (SELECT COUNT(*) FROM device_opportunities d WHERE d.seller_org_id = o.id AND d.status NOT IN ('PURCHASED','REJECTED','LOST'))
          + (SELECT COUNT(*) FROM sales_opportunities s WHERE s.buyer_org_id = o.id AND s.status NOT IN ('WON','LOST')) AS active_deals", 'o.name');
    render('customers/index', ['title' => 'ลูกค้า / องค์กร', 'page' => $page]);
}

function c_customers_new(): void
{
    require_cap('customer.edit');
    render('customers/form', ['title' => 'เพิ่มลูกค้า', 'org' => null]);
}

function c_customers_post_create(): void
{
    $id = org_create($_POST, post_bool('confirm_duplicate'));
    flash('success', 'เพิ่มลูกค้าแล้ว');
    redirect('customers.view', ['id' => $id]);
}

function c_customers_edit(): void
{
    require_cap('customer.edit');
    $org = db_get('organizations', (string) get('id', ''), 'ลูกค้า');
    render('customers/form', ['title' => 'แก้ไขลูกค้า', 'org' => $org]);
}

function c_customers_post_update(): void
{
    $id = (string) post('id', '');
    org_update($id, $_POST);
    flash('success', 'บันทึกแล้ว');
    redirect('customers.view', ['id' => $id]);
}

function c_customers_post_archive(): void
{
    $id = (string) post('id', '');
    org_archive($id, (string) post('reason', ''));
    flash('success', 'เก็บลูกค้าเข้าคลังแล้ว');
    redirect('customers');
}

function c_customers_view(): void
{
    $d = customer_360((string) get('id', ''));
    render('customers/view', ['title' => $d['org']['name'], 'd' => $d]);
}
