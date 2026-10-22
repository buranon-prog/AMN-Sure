<?php
// รายการตัวเลือก + ค่าตั้งต้น (ตาราง L) — ดูได้ทุก role · แก้ได้ MANAGER เท่านั้น
// คำที่ logic ใช้ (is_system) ลบ/เปลี่ยนชื่อไม่ได้ แต่เลื่อนลำดับได้

function option_row(int $id): array
{
    $r = one('SELECT * FROM sure_options WHERE id = ?', [$id]);
    if (!$r) throw new NotFoundError('ไม่พบตัวเลือก');
    return $r;
}

function option_clean_value($value): string
{
    $v = s(is_string($value) ? $value : null);
    if ($v === null) throw new AppError('กรุณากรอกค่าตัวเลือก');
    v_maxlen($v, 255, 'ค่าตัวเลือก');
    return $v;
}

function option_add(string $list, $value, $sort = null): void
{
    require_manager();
    if (!isset(option_lists()[$list])) throw new AppError('ไม่พบรายการตัวเลือกนี้');
    $v = option_clean_value($value);
    if (val('SELECT id FROM sure_options WHERE list_key = ? AND value = ?', [$list, $v])) throw new AppError('"' . $v . '" มีอยู่ในรายการแล้ว');
    $sort = v_int($sort, 'ลำดับ') ?? ((int) val('SELECT COALESCE(MAX(sort), 0) FROM sure_options WHERE list_key = ?', [$list]) + 10);
    db_insert('sure_options', ['list_key' => $list, 'value' => $v, 'sort' => $sort, 'is_system' => 0]);
    options_reset();
}

/** แก้ชื่อ/ลำดับ — ค่าระบบเปลี่ยนชื่อไม่ได้ (ข้อมูลเดิมที่ใช้ค่าเก่าไม่ถูกเปลี่ยนตาม) */
function option_update(int $id, $value, $sort): void
{
    require_manager();
    $r = option_row($id);
    $v = option_clean_value($value);
    $data = ['sort' => v_int($sort, 'ลำดับ') ?? (int) $r['sort']];
    if ($v !== $r['value']) {
        if ((int) $r['is_system'] === 1) throw new AppError('"' . $r['value'] . '" เป็นค่าระบบที่ logic ใช้ — เปลี่ยนชื่อไม่ได้');
        if (val('SELECT id FROM sure_options WHERE list_key = ? AND value = ? AND id <> ?', [$r['list_key'], $v, $id])) throw new AppError('"' . $v . '" มีอยู่ในรายการแล้ว');
        $data['value'] = $v;
    }
    db_update('sure_options', 'id', $id, $data);
    options_reset();
}

function option_delete(int $id): void
{
    require_manager();
    $r = option_row($id);
    if ((int) $r['is_system'] === 1) throw new AppError('"' . $r['value'] . '" เป็นค่าระบบที่ logic ใช้ — ลบไม่ได้');
    q('DELETE FROM sure_options WHERE id = ?', [$id]);
    options_reset();
}

/** อัตรา VAT (%) — 0 ถึง 100 ทศนิยมไม่เกิน 2 ตำแหน่ง */
function vat_set($value): void
{
    require_manager();
    $v = s(is_string($value) ? $value : null);
    if ($v === null || !preg_match('/^\d{1,3}(\.\d{1,2})?$/', $v) || (float) $v > 100) throw new AppError('อัตรา VAT ต้องเป็นตัวเลข 0–100 (ทศนิยมไม่เกิน 2 ตำแหน่ง)');
    setting_set('vat_rate', rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'));
    ds_reset();
}
