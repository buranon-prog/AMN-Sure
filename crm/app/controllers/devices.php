<?php

function c_devices_index(): void
{
    require_cap('device.view');
    $q = get('q');
    $status = get('status');
    $where = ['dv.archived_at IS NULL'];
    $p = [];
    if ($q) {
        $norm = normalize_serial($q);
        $where[] = '(dv.ref_no LIKE ? OR dv.brand LIKE ? OR dv.model LIKE ? OR dv.serial_number LIKE ? OR dv.serial_normalized LIKE ? OR dv.category LIKE ? OR o.name LIKE ?)';
        $l = like($q);
        array_push($p, $l, $l, $l, $l, like((string) $norm), $l, $l);
    }
    if ($status && in_array($status, DEVICE_COMMERCIAL, true)) { $where[] = 'dv.commercial_status = ?'; $p[] = $status; }
    $page = paginate('FROM devices dv LEFT JOIN organizations o ON o.id = dv.current_owner_org_id WHERE ' . implode(' AND ', $where), $p,
        'dv.*, o.name AS owner_name', 'dv.brand, dv.model, dv.ref_no');
    render('devices/index', ['title' => 'เครื่องทั้งหมด', 'page' => $page]);
}

function c_devices_new(): void
{
    require_cap('device.edit');
    $dups = null;
    render('devices/form', ['title' => 'เพิ่มเครื่อง', 'dv' => ['current_owner_org_id' => get('org')], 'dups' => $dups]);
}

/** ตรวจเครื่องซ้ำก่อนบันทึก (ปุ่ม "ตรวจเครื่องซ้ำ" ในฟอร์ม) */
function c_devices_post_check(): void
{
    require_cap('device.edit');
    $dups = device_find_duplicates(post('serial_number'), post('brand'), post('model'), post('current_owner_org_id'), post('current_location'));
    remember_input();
    $_SESSION['dup_result'] = $dups;
    redirect('devices.new', [], 'dups');
}

function c_devices_post_create(): void
{
    $id = device_create($_POST, post_bool('confirm_duplicate'));
    flash('success', 'เพิ่มเครื่องแล้ว');
    redirect('devices.view', ['id' => $id]);
}

function c_devices_edit(): void
{
    require_cap('device.edit');
    render('devices/form', ['title' => 'แก้ไขเครื่อง', 'dv' => db_get('devices', (string) get('id', ''), 'เครื่อง'), 'dups' => null]);
}

function c_devices_post_update(): void
{
    $id = (string) post('id', '');
    device_update($id, $_POST);
    flash('success', 'บันทึกแล้ว');
    redirect('devices.view', ['id' => $id]);
}

function c_devices_view(): void
{
    $d = device_360((string) get('id', ''));
    render('devices/view', ['title' => $d['device']['brand'] . ' ' . $d['device']['model'], 'd' => $d]);
}

function c_devices_post_service(): void
{
    $id = (string) post('device_id', '');
    service_history_add($id, $_POST);
    flash('success', 'บันทึกประวัติซ่อม/บริการแล้ว');
    redirect('devices.view', ['id' => $id], 'service');
}

function c_devices_post_ma(): void
{
    $id = (string) post('device_id', '');
    ma_record_add($id, $_POST);
    flash('success', 'บันทึกสัญญา MA แล้ว');
    redirect('devices.view', ['id' => $id], 'service');
}

function c_devices_post_override(): void
{
    $id = (string) post('device_id', '');
    device_override_status($id, (string) post('commercial_status', ''), (string) post('technical_status', ''), (string) post('reason', ''));
    flash('success', 'แก้สถานะเครื่องแล้ว (บันทึกใน audit log)');
    redirect('devices.view', ['id' => $id]);
}

function c_devices_post_open_deal(): void
{
    // เปิดดีลซื้อจากหน้าเครื่อง (เช่น ลูกค้าเดิมอยากขายคืน)
    $dv = db_get('devices', (string) post('device_id', ''), 'เครื่อง');
    if (!$dv['current_owner_org_id']) throw new AppError('เครื่องนี้ไม่มีเจ้าของภายนอกในระบบ');
    redirect('leads.new', ['type' => 'SELLER', 'org' => $dv['current_owner_org_id']]);
}
