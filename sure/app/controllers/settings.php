<?php
// รายการตัวเลือก & ค่าตั้งต้น (สเปก 7.6) — ดูได้ทุกคนที่มีสิทธิ์ SURE · แก้ได้ MANAGER เท่านั้น

function c_settings_index(): void
{
    require_sure_view();
    $rows = [];
    foreach (all('SELECT * FROM sure_options ORDER BY list_key, sort, id') as $r) $rows[$r['list_key']][] = $r;
    render('settings/index', ['title' => 'รายการตัวเลือก & ค่าตั้งต้น', 'rows' => $rows, 'canEdit' => is_manager()]);
}

function c_settings_post_add(): void
{
    $list = (string) post('list', '');
    option_add($list, $_POST['value'] ?? null, $_POST['sort'] ?? null);
    flash('success', 'เพิ่มตัวเลือกแล้ว');
    redirect('settings', [], 'list-' . $list);
}

function c_settings_post_update(): void
{
    $id = (int) post('id', 0);
    $list = option_row($id)['list_key'];
    option_update($id, $_POST['value'] ?? null, $_POST['sort'] ?? null);
    flash('success', 'บันทึกแล้ว');
    redirect('settings', [], 'list-' . $list);
}

function c_settings_post_delete(): void
{
    $id = (int) post('id', 0);
    $list = option_row($id)['list_key'];
    option_delete($id);
    flash('success', 'ลบตัวเลือกแล้ว');
    redirect('settings', [], 'list-' . $list);
}

function c_settings_post_vat(): void
{
    vat_set($_POST['vat'] ?? null);
    flash('success', 'บันทึกอัตรา VAT แล้ว');
    redirect('settings');
}
