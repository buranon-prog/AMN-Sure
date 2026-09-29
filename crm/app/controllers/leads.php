<?php

function c_leads_index(): void
{
    require_cap('lead.view');
    $f = [
        'kind' => in_array(get('kind'), ['DO', 'SO'], true) ? get('kind') : '',
        'owner' => get('owner', ''),
        'state' => in_array(get('state'), ['active', 'closed', 'all'], true) ? get('state') : 'active',
        'overdue' => (bool) get('overdue'),
        'q' => get('q'),
    ];
    $fq = followup_query($f);
    $page = $fq['sql']
        ? paginate($fq['sql'], $fq['params'], 'x.*, u.name AS owner_name', 'x.is_active DESC, x.next_follow_up_date IS NULL, x.next_follow_up_date, x.created_at DESC')
        : ['rows' => [], 'page' => 1, 'pages' => 1, 'total' => 0];
    render('leads/index', ['title' => 'ลีด / ติดตาม', 'page' => $page, 'f' => $f]);
}

function c_leads_new(): void
{
    require_cap('lead.edit');
    $type = get('type') === 'BUYER' ? 'BUYER' : 'SELLER';
    $org = get('org') ? db_find('organizations', get('org')) : null;
    render('leads/new', [
        'title' => $type === 'SELLER' ? 'ลีดผู้ขายใหม่' : 'ลีดผู้ซื้อใหม่',
        'type' => $type,
        'org' => $org,
        'contacts' => $org ? all('SELECT id, name, position FROM contacts WHERE organization_id = ? AND archived_at IS NULL ORDER BY is_primary DESC, name', [$org['id']]) : [],
        'devices' => $org ? all('SELECT id, ref_no, brand, model, serial_number FROM devices WHERE current_owner_org_id = ? AND archived_at IS NULL ORDER BY brand, model', [$org['id']]) : [],
    ]);
}

function c_leads_post_create(): void
{
    if (post('type') === 'BUYER') {
        $id = lead_create_buyer($_POST);
        flash('success', 'สร้างลีดผู้ซื้อแล้ว — ขั้นต่อไป: บันทึกความต้องการของผู้ซื้อ');
        redirect('sales.view', ['id' => $id]);
    }
    $id = lead_create_seller($_POST);
    flash('success', 'สร้างลีดผู้ขายแล้ว — ขั้นต่อไป: ตรวจข้อมูลเครื่องและขอตรวจเครื่อง');
    redirect('acq.view', ['id' => $id]);
}

function c_leads_view(): void
{
    require_cap('lead.view');
    $lead = db_get('leads', (string) get('id', ''), 'ลีด');
    render('leads/view', [
        'title' => 'ลีด ' . $lead['ref_no'],
        'lead' => $lead,
        'org' => db_get('organizations', $lead['organization_id']),
        'contact' => $lead['contact_id'] ? db_find('contacts', $lead['contact_id']) : null,
        'dos' => all('SELECT o.*, dv.brand, dv.model, dv.serial_number, dv.ref_no AS device_ref FROM device_opportunities o JOIN devices dv ON dv.id = o.device_id WHERE o.lead_id = ? ORDER BY o.created_at', [$lead['id']]),
        'sos' => can('sales.view') ? all('SELECT * FROM sales_opportunities WHERE lead_id = ? ORDER BY created_at', [$lead['id']]) : [],
        'devices' => all('SELECT id, ref_no, brand, model, serial_number FROM devices WHERE current_owner_org_id = ? AND archived_at IS NULL ORDER BY brand, model', [$lead['organization_id']]),
        'activities' => activities_for('LEAD', $lead['id']),
    ]);
}

function c_leads_post_update(): void
{
    $id = (string) post('id', '');
    lead_update($id, $_POST);
    flash('success', 'บันทึกแล้ว');
    redirect('leads.view', ['id' => $id]);
}

function c_leads_post_add_device(): void
{
    $oppId = lead_add_device((string) post('lead_id', ''), $_POST);
    flash('success', 'เพิ่มเครื่องในลีดแล้ว');
    redirect('acq.view', ['id' => $oppId]);
}

/** อัปเดตผู้รับผิดชอบ / next action / วันติดตาม ของดีล */
function c_leads_post_followup(): void
{
    $kind = post('kind') === 'SO' ? 'SO' : 'DO';
    $id = (string) post('id', '');
    followup_update($kind, $id, $_POST);
    flash('success', 'อัปเดตการติดตามแล้ว');
    redirect($kind === 'DO' ? 'acq.view' : 'sales.view', ['id' => $id]);
}
