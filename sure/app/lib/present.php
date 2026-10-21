<?php
// การแสดงค่า: รูปแบบวันที่/เงิน/เปอร์เซ็นต์, ป้ายสถานะสี (สเปกหัวข้อ 8), ช่องกรอกของแต่ละฟิลด์

/** สีป้ายสถานะตามสเปกหัวข้อ 8 */
function badge_class($value): ?string
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (['พร้อมขาย', 'ในประกัน', 'อยู่ใน MA', 'ชำระครบ', 'ปิดงาน', 'ภายใน 7 วัน'] as $v) $map[$v] = 'green';
        foreach (['ขายแล้ว – รอส่งมอบ', 'จองแล้ว', 'กำลังซ่อม / Refurbish', 'รอตรวจสภาพ', 'ประกันใกล้หมด', 'MA ใกล้หมด', 'ใกล้หมด (≤30 วัน)',
                     'ชำระบางส่วน', 'รับเรื่อง', 'กำลังดำเนินการ', 'วันนี้', 'เปิด / รอติดตาม', 'นอกประกัน – คิดค่าบริการ'] as $v) $map[$v] = 'amber';
        foreach (['หมดประกัน / ไม่มี MA', 'หมดประกัน', 'ยังไม่ชำระ', 'รออะไหล่', 'เลยกำหนด', 'รอชำระ'] as $v) $map[$v] = 'red';
        foreach (['ส่งมอบแล้ว', 'รอส่งมอบ', 'ยกเลิก', 'หยุดติดต่อ', 'ปิดสาขา'] as $v) $map[$v] = 'gray';
    }
    if (!is_string($value) || $value === '') return null;
    if (mb_substr($value, 0, 1) === '⚠') return 'red';
    return $map[$value] ?? null;
}

/** ค่าเป็นข้อความธรรมดา (ใช้ในตาราง ฟอร์ม และ export) */
function fmt_value(array $f, $v, bool $forCsv = false): string
{
    if ($v === null || $v === '') return '';
    switch ($f['type']) {
        case 'date':
            return $forCsv ? (string) $v : d($v);
        case 'money':
            return $forCsv ? number_format((float) $v, 2, '.', '') : number_format((float) $v, 2);
        case 'int':
            return $forCsv ? (string) (int) $v : number_format((int) $v);
        case 'percent':
            return number_format((float) $v * 100, 1) . '%';
    }
    return (string) $v;
}

/** ค่าเป็น HTML พร้อมป้ายสี / สีแดงเมื่อติดลบ */
function fmt_html(string $t, string $key, array $f, $v): string
{
    $text = fmt_value($f, $v);
    if ($text === '') return '';
    if (in_array($f['type'], ['money', 'percent', 'int'], true)) {
        $neg = (float) $v < 0;
        // หน้ารับชำระเงิน: ยอดคงค้างของการขาย > 0 แสดงสีแดง
        if ($t === 'P' && $key === 'balance' && (float) $v > 0) $neg = true;
        return $neg ? '<span class="neg">' . e($text) . '</span>' : e($text);
    }
    if (in_array($f['how'], ['option', 'auto'], true)) {
        $b = badge_class($text);
        if ($b) return '<span class="badge ' . $b . '">' . e($text) . '</span>';
    }
    if (in_array($key, ['docs', 'maps', 'photos'], true) && preg_match('#^https?://#i', $text)) {
        return '<a href="' . e($text) . '" target="_blank" rel="noopener noreferrer">' . e($text) . '</a>';
    }
    return e($text);
}

function is_numeric_type(array $f): bool
{
    return in_array($f['type'], ['money', 'int', 'percent'], true);
}

/** ตัวเลือกของ FK dropdown [รหัส => ข้อความ] + attribute ของแต่ละตัวเลือก */
function fk_options(string $src): array
{
    $ds = ds();
    $out = [];
    switch ($src) {
        case 'C':
            foreach ($ds['C'] as $id => $r) $out[$id] = ['label' => $id . ' · ' . $r['name'], 'attrs' => []];
            break;
        case 'B':
            foreach ($ds['B'] as $id => $r) $out[$id] = ['label' => $id . ' · ' . $r['bname'] . ($r['status'] === SURE_BRANCH_CLOSED ? ' (ปิดสาขา)' : ''), 'attrs' => ['data-parent' => $r['cust']]];
            break;
        case 'D':
            foreach ($ds['D'] as $id => $r) $out[$id] = ['label' => $id . ' · ' . dev_name($r) . ' · ' . $r['serial'] . ' · ' . $r['status'], 'attrs' => []];
            break;
        case 'S':
            foreach ($ds['S'] as $id => $r) $out[$id] = ['label' => $id . ' · ' . $r['custname'] . ' · ' . $r['dev'] . ($r['status'] === SURE_CANCELLED ? ' (ยกเลิก)' : ''), 'attrs' => []];
            break;
    }
    ksort($out, SORT_STRING);
    return $out;
}

function select_html(string $name, string $id, array $options, $value, array $attrs = [], string $blank = '— เลือก —'): string
{
    $html = '<select' . attrs(['name' => $name, 'id' => $id] + $attrs) . '>';
    $html .= '<option value="">' . e($blank) . '</option>';
    foreach ($options as $k => $o) {
        $label = is_array($o) ? $o['label'] : $o;
        $oa = is_array($o) ? $o['attrs'] : [];
        $html .= '<option' . attrs(['value' => (string) $k, 'selected' => (string) $k === (string) $value] + $oa) . '>' . e($label) . '</option>';
    }
    return $html . '</select>';
}

/**
 * ช่องของฟิลด์ในฟอร์ม
 * ฟิลด์อัตโนมัติ / รหัสหลักของแถวที่บันทึกแล้ว / ผู้ใช้ที่แก้ไม่ได้ → แสดง read-only สีเทา
 */
function form_field(string $t, string $key, array $f, ?array $row, bool $isNew, bool $editable, array $ctx = []): string
{
    $id = 'f_' . $key;
    $req = $f['req'] && $f['how'] !== 'auto' ? ' <span class="req">*</span>' : '';
    $label = '<label for="' . e($id) . '">' . e($f['label']) . $req . '</label>';
    $current = $row[$key] ?? null;
    $wide = $f['type'] === 'textarea' ? ' full' : '';

    $readonly = $f['how'] === 'auto' || !$editable || (!$isNew && ($f['how'] === 'pk' || $key === tdef($t)['pk']));
    if ($readonly) {
        $html = fmt_html($t, $key, $f, $current);
        $cls = $f['how'] === 'auto' ? 'auto' : 'auto locked';
        if ($f['type'] === 'textarea') $cls .= ' pre';
        return '<div class="field' . $wide . '">' . $label . '<div class="' . $cls . '" id="' . e($id) . '">' . ($html === '' ? '<span class="muted">—</span>' : $html) . '</div></div>';
    }

    $value = old($key, $current);
    if ($isNew && $value === null && isset($ctx['defaults'][$key])) $value = $ctx['defaults'][$key];
    $hint = isset($f['hint']) ? '<div class="hint">' . e($f['hint']) . '</div>' : '';
    $a = ['name' => $key, 'id' => $id, 'required' => $f['req']];

    if ($f['how'] === 'option') {
        $opts = [];
        foreach (option_values($f['src']) as $o) $opts[$o] = $o;
        if ($current !== null && $current !== '' && !isset($opts[$current])) $opts[$current] = $current . ' (ไม่อยู่ในรายการแล้ว)';
        $a['data-search'] = count($opts) > 12 ? '1' : null;
        $control = select_html($key, $id, $opts, $value, ['required' => $f['req'], 'data-search' => $a['data-search']]);
    } elseif ($f['how'] === 'fk') {
        $sa = ['required' => $f['req'], 'data-search' => '1'];
        if (isset($ctx['filter_by'][$key])) $sa['data-filter-by'] = 'f_' . $ctx['filter_by'][$key];
        if (isset($ctx['next_codes'][$key])) {
            $sa['data-next-codes'] = json_encode($ctx['next_codes'][$key], JSON_UNESCAPED_UNICODE);
            $sa['data-next-target'] = 'f_' . tdef($t)['pk'];
        }
        $opts = $ctx['fk_options'][$key] ?? fk_options($f['src']);
        $control = select_html($key, $id, $opts, $value, $sa);
    } elseif ($f['type'] === 'textarea') {
        $control = '<textarea' . attrs($a + ['rows' => 3]) . '>' . e($value) . '</textarea>';
    } else {
        $a['value'] = $value;
        if ($f['type'] === 'date') {
            $a['type'] = 'date';
            $a['min'] = DATE_MIN;
            $a['max'] = DATE_MAX;
        } elseif ($f['type'] === 'money') {
            $a['type'] = 'text';
            $a['inputmode'] = 'decimal';
            $a['data-money'] = !empty($f['signed']) ? 'signed' : '1';
            if ($value !== null && $value !== '' && is_numeric($value)) $a['value'] = number_format((float) $value, 2, '.', ',');
        } elseif ($f['type'] === 'int') {
            $a['type'] = 'number';
            $a['min'] = '0';
            $a['step'] = '1';
        } else {
            $a['type'] = 'text';
            $a['maxlength'] = (string) ($f['max'] ?? 255);
            if ($f['how'] === 'pk') $a['autocomplete'] = 'off';
        }
        $control = '<input' . attrs($a) . '>';
    }
    if ($f['how'] === 'pk' && isset($ctx['next_code'])) {
        $hint = '<div class="hint next-code">รหัสถัดไป: <b data-next-label>' . e($ctx['next_code']) . '</b></div>' . $hint;
    }
    return '<div class="field' . $wide . '">' . $label . $control . $hint . '</div>';
}

/** "บันทึกโดย / แก้ไขล่าสุดโดย" */
function audit_line(array $row): string
{
    if (empty($row['_created_at'])) return '';
    return 'บันทึกโดย ' . e(user_name($row['_created_by'] ?? null)) . ' · ' . e(dt($row['_created_at']))
        . ' / แก้ไขล่าสุดโดย ' . e(user_name($row['_updated_by'] ?? null)) . ' · ' . e(dt($row['_updated_at']));
}

/** ลิงก์ไปหน้ารายละเอียดของแถว */
function rec_url(string $t, $pk): string
{
    return url(tdef($t)['route'] . '.edit', ['id' => (string) $pk]);
}
