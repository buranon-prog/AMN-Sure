<?php

function c_contacts_index(): void
{
    require_cap('customer.view');
    $q = get('q');
    $where = ['c.archived_at IS NULL'];
    $p = [];
    if ($q) {
        $where[] = '(c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR c.line_id LIKE ? OR o.name LIKE ?)';
        $l = like($q);
        array_push($p, $l, $l, $l, $l, $l);
    }
    $page = paginate('FROM contacts c LEFT JOIN organizations o ON o.id = c.organization_id WHERE ' . implode(' AND ', $where), $p,
        'c.*, o.name AS org_name', 'c.name');
    render('contacts/index', ['title' => 'ผู้ติดต่อ', 'page' => $page]);
}

function c_contacts_new(): void
{
    require_cap('customer.edit');
    render('contacts/form', ['title' => 'เพิ่มผู้ติดต่อ', 'c' => ['organization_id' => get('org')]]);
}

function c_contacts_post_create(): void
{
    $id = contact_create($_POST);
    flash('success', 'เพิ่มผู้ติดต่อแล้ว');
    $org = post('organization_id');
    if ($org) redirect('customers.view', ['id' => $org], 'contacts');
    redirect('contacts.view', ['id' => $id]);
}

function c_contacts_edit(): void
{
    require_cap('customer.edit');
    render('contacts/form', ['title' => 'แก้ไขผู้ติดต่อ', 'c' => db_get('contacts', (string) get('id', ''), 'ผู้ติดต่อ')]);
}

function c_contacts_post_update(): void
{
    $id = (string) post('id', '');
    contact_update($id, $_POST);
    flash('success', 'บันทึกแล้ว');
    redirect('contacts.view', ['id' => $id]);
}

function c_contacts_post_archive(): void
{
    $c = db_get('contacts', (string) post('id', ''), 'ผู้ติดต่อ');
    contact_archive($c['id'], (string) post('reason', ''));
    flash('success', 'นำผู้ติดต่อออกจากรายการแล้ว');
    if ($c['organization_id']) redirect('customers.view', ['id' => $c['organization_id']], 'contacts');
    redirect('contacts');
}

function c_contacts_view(): void
{
    require_cap('customer.view');
    $c = db_get('contacts', (string) get('id', ''), 'ผู้ติดต่อ');
    $org = $c['organization_id'] ? db_find('organizations', $c['organization_id']) : null;
    $leads = can('lead.view') ? all('SELECT l.*, u.name AS owner_name FROM leads l JOIN users u ON u.id = l.owner_id WHERE l.contact_id = ? ORDER BY l.created_at DESC', [$c['id']]) : [];
    render('contacts/view', [
        'title' => $c['name'], 'c' => $c, 'org' => $org, 'leads' => $leads,
        'activities' => activities_for('CONTACT', $c['id']),
    ]);
}
