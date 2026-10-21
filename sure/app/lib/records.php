<?php
// เพิ่ม / แก้ไข / ลบ แถวของตาราง C B A D S P V T พร้อม validation ตามสเปกหัวข้อ 4–5
// - รหัสหลักตรวจรูปแบบและความซ้ำอีกครั้งตอนบันทึก (กันชนกันเมื่อสองคนบันทึกพร้อมกัน) และห้ามแก้หลังบันทึก
// - เขียนเฉพาะฟิลด์ที่ "กรอกเอง / dropdown / FK" ฟิลด์อัตโนมัติไม่รับค่าจากผู้ใช้เลย

/** รูปแบบรหัสของแต่ละตาราง [regex, ข้อความเมื่อผิด] */
function code_rule(string $t): ?array
{
    $rules = [
        'C' => ['/^SURE\d{3}$/', 'รหัสลูกค้าต้องเป็น SURE + เลข 3 หลัก (ยาว 7 ตัว) เช่น SURE001'],
        'B' => ['/^SURE\d{3}-\d{2}$/', 'รหัสสาขาต้องเป็น รหัสลูกค้า + - + เลข 2 หลัก (ยาว 10 ตัว) เช่น SURE001-01'],
        'D' => ['/^SURE\d{5}$/', 'รหัสเครื่องต้องเป็น SURE + ปี ค.ศ. 2 หลัก + เลข 3 หลัก (ยาว 9 ตัว) เช่น SURE26001'],
        'S' => ['/^SO\d{5}$/', 'เลขที่การขายต้องเป็น SO + ปี 2 หลัก + เลข 3 หลัก (ยาว 7 ตัว) เช่น SO26001'],
        'V' => ['/^SV\d{5}$/', 'เลขที่งานบริการต้องเป็น SV + ปี 2 หลัก + เลข 3 หลัก (ยาว 7 ตัว) เช่น SV26001'],
    ];
    return $rules[$t] ?? null;
}

/**
 * รหัสถัดไป: ลูกค้า = max+1 · เครื่อง/SO/SV = max+1 ของปีปัจจุบัน
 * สาขา = รหัสลูกค้า + '-' + (จำนวนสาขาของลูกค้านั้น + 1) แบบ 2 หลัก
 */
function next_code(string $t, ?string $cust = null): ?string
{
    $yy = substr(sure_today(), 2, 2);
    switch ($t) {
        case 'C':
            $max = (int) val("SELECT MAX(CAST(SUBSTRING(id, 5) AS UNSIGNED)) FROM sure_customers WHERE id REGEXP '^SURE[0-9]{3}$'");
            return 'SURE' . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
        case 'B':
            if ($cust === null || !preg_match('/^SURE\d{3}$/', $cust)) return null;
            $n = (int) val('SELECT COUNT(*) FROM sure_branches WHERE cust = ?', [$cust]);
            return $cust . '-' . str_pad((string) ($n + 1), 2, '0', STR_PAD_LEFT);
        case 'D':
        case 'S':
        case 'V':
            $prefix = ['D' => 'SURE', 'S' => 'SO', 'V' => 'SV'][$t] . $yy;
            $table = tdef($t)['table'];
            $max = (int) val('SELECT MAX(CAST(RIGHT(id, 3) AS UNSIGNED)) FROM ' . db_ident($table) . ' WHERE id LIKE ? AND CHAR_LENGTH(id) = ?',
                [$prefix . '%', strlen($prefix) + 3]);
            return $prefix . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }
    return null;
}

/** รหัสถัดไปของสาขาสำหรับลูกค้าทุกราย [รหัสลูกค้า => รหัสสาขาถัดไป] (ใช้เติมช่องรหัสในฟอร์มสาขา) */
function next_branch_codes(): array
{
    $out = [];
    foreach (all('SELECT c.id, COUNT(b.id) AS n FROM sure_customers c LEFT JOIN sure_branches b ON b.cust = c.id GROUP BY c.id') as $r) {
        $out[$r['id']] = $r['id'] . '-' . str_pad((string) ((int) $r['n'] + 1), 2, '0', STR_PAD_LEFT);
    }
    return $out;
}

/** แถวที่คำนวณแล้วของตาราง (ตามสิทธิ์ผู้ใช้ปัจจุบัน) หรือ null */
function rec_find(string $t, $pk): ?array
{
    $rows = ds()[$t] ?? [];
    return $rows[(string) $pk] ?? null;
}

/** ตรวจค่าของฟิลด์ที่ผู้ใช้กรอก 1 ช่อง คืนค่าที่พร้อมบันทึก */
function validate_field(string $key, array $f, $raw, $current)
{
    $label = $f['label'];
    switch ($f['type']) {
        case 'date':
            return v_date($raw, $label);
        case 'money':
            return v_money($raw, $label, false, !empty($f['signed']));
        case 'int':
            return v_int($raw, $label, false, 0);
    }
    $v = s(is_string($raw) || is_numeric($raw) ? (string) $raw : null);
    if ($v === null) return null;
    $max = $f['max'] ?? ($f['type'] === 'textarea' ? 5000 : 255);
    v_maxlen($v, $max, $label);
    if ($f['how'] === 'option') {
        // ค่าต้องอยู่ในรายการตัวเลือก — ยกเว้นค่าเดิมของแถวที่ Manager เอาออกจากรายการไปแล้ว (ยังเก็บไว้ได้ ไม่บังคับเปลี่ยน)
        if (!in_array($v, option_values($f['src']), true) && $v !== $current) {
            throw new AppError('ค่าของ "' . $label . '" ไม่อยู่ในรายการตัวเลือก');
        }
    }
    if ($key === 'taxid' && mb_strlen($v) !== 13) throw new AppError('เลขผู้เสียภาษีต้องยาว 13 ตัว (ได้ ' . mb_strlen($v) . ' ตัว)');
    return $v;
}

/** มีแถวนี้อยู่ในตารางหรือไม่ */
function rec_exists(string $t, $pk): bool
{
    $d = tdef($t);
    return (bool) val('SELECT 1 FROM ' . db_ident($d['table']) . ' WHERE ' . db_ident($d['pk']) . ' = ?', [$pk]);
}

/**
 * บันทึกแถว (เพิ่มเมื่อ $pk = null, แก้ไขเมื่อมี $pk) คืน primary key
 * $input = ค่าจากฟอร์ม key ตาม field_key
 */
function rec_save(string $t, $pk, array $input): string
{
    require_table_edit($t);
    $d = tdef($t);
    $fields = $d['fields'];
    $isNew = $pk === null || $pk === '';

    return tx(function () use ($t, $d, $fields, $pk, $isNew, $input) {
        $existing = null;
        if (!$isNew) {
            $existing = rec_raw_lock($t, $pk);
            if (!$existing) throw new NotFoundError('ไม่พบ' . $d['title'] . ' ' . $pk);
        }

        // 1) ฟิลด์บังคับ
        $missing = [];
        foreach ($fields as $k => $f) {
            if (!$f['req'] || $f['how'] === 'auto') continue;
            if ($f['how'] === 'pk' && !$isNew) continue;
            if ($k === $d['pk'] && !$isNew) continue;
            $raw = $input[$k] ?? null;
            if (s(is_array($raw) ? null : $raw) === null) $missing[] = $f['label'];
        }
        if ($missing) throw new AppError('กรุณากรอก: ' . implode(', ', $missing));

        // 2) รหัสหลัก
        $data = [];
        $newPk = $pk;
        if ($isNew && empty($d['autoid'])) {
            $f = $fields[$d['pk']];
            $code = s($input[$d['pk']] ?? null);
            if ($t !== 'P') $code = strtoupper((string) $code);
            v_maxlen($code, $f['max'] ?? 255, $f['label']);
            $rule = code_rule($t);
            if ($rule && !preg_match($rule[0], $code)) throw new AppError($rule[1] . ' (ได้ "' . $code . '")');
            if ($t === 'T' && !rec_exists('D', $code)) throw new AppError('ไม่พบรหัสเครื่อง ' . $code);
            if (rec_exists($t, $code)) {
                throw new AppError($t === 'T' ? 'รหัสเครื่อง ' . $code . ' มีต้นทุนอยู่แล้ว (1 เครื่อง = 1 แถว)' : $f['label'] . ' ' . $code . ' มีอยู่แล้ว (ห้ามซ้ำ)');
            }
            $data[$d['pk']] = $code;
            $newPk = $code;
        }

        // 3) ฟิลด์ที่ผู้ใช้กรอก
        foreach ($fields as $k => $f) {
            if ($k === $d['pk'] || $f['how'] === 'auto' || $f['how'] === 'pk') continue;
            $current = $existing[$k] ?? null;
            $v = validate_field($k, $f, $input[$k] ?? null, $current);
            if ($f['how'] === 'fk' && $v !== null) {
                $v = strtoupper($v); // รหัสทุกตารางเป็นตัวพิมพ์ใหญ่
                if (!rec_exists($f['src'], $v)) throw new AppError('ไม่พบ ' . $f['label'] . ' "' . $v . '" — ต้องบันทึกในตาราง' . tdef($f['src'])['title'] . 'ก่อน');
            }
            if ($f['req'] && $v === null) throw new AppError('กรุณากรอก: ' . $f['label']);
            $data[$k] = $v;
        }
        $row = $data + ($existing ?? []);
        if (!$isNew) $row[$d['pk']] = $pk;

        // 4) กฎข้ามฟิลด์
        rec_check_rules($t, $row, $isNew ? null : (string) $pk);

        // 5) เขียน
        $cols = [];
        foreach ($data as $k => $v) $cols[fcol($fields[$k] ?? [], $k)] = $v;
        try {
            if ($isNew) {
                $id = db_insert($d['table'], $cols);
                if (!empty($d['autoid'])) $newPk = $id;
            } else {
                unset($cols[$d['pk']]);
                db_update($d['table'], $d['pk'], $pk, $cols);
            }
        } catch (PDOException $e) {
            if (is_duplicate_error($e)) {
                if ($t === 'D' && stripos($e->getMessage(), 'serial') !== false) throw new AppError('Serial Number นี้มีอยู่แล้ว (ห้ามซ้ำ)');
                throw new AppError('รหัสนี้เพิ่งถูกบันทึกโดยผู้ใช้อื่น กรุณาใช้รหัสถัดไปแล้วบันทึกอีกครั้ง');
            }
            throw $e;
        }
        ds_reset();
        return (string) $newPk;
    });
}

/** แถวดิบ (key = field_key) พร้อมล็อกจนจบ transaction */
function rec_raw_lock(string $t, $pk): ?array
{
    $d = tdef($t);
    $r = db_lock($d['table'], $d['pk'], $pk);
    if (!$r) return null;
    $row = [];
    foreach ($d['fields'] as $k => $f) {
        $col = fcol($f, $k);
        if (array_key_exists($col, $r)) $row[$k] = $r[$col];
    }
    return $row;
}

/** กฎที่ต้องดูหลายฟิลด์/หลายตาราง (สเปกหัวข้อ 4–5) */
function rec_check_rules(string $t, array $row, ?string $pk): void
{
    if ($t === 'B') {
        if (substr((string) $row['id'], 0, 7) !== $row['cust']) throw new AppError('⚠ รหัสสาขาไม่ตรงกับรหัสลูกค้า');
    }
    if (($t === 'A' || $t === 'S') && !empty($row['branch'])) {
        $owner = val('SELECT cust FROM sure_branches WHERE id = ?', [$row['branch']]);
        if ($owner !== $row['cust']) throw new AppError('⚠ สาขานี้ไม่ใช่ของลูกค้านี้');
    }
    if ($t === 'S' && $row['status'] !== SURE_CANCELLED) {
        // ล็อกแถวเครื่องก่อนตรวจ เพื่อไม่ให้สองคนบันทึกการขายเครื่องเดียวกันพร้อมกันได้
        db_lock('sure_devices', 'id', $row['dev']);
        $other = val('SELECT id FROM sure_sales WHERE dev = ? AND status <> ? AND id <> ? LIMIT 1', [$row['dev'], SURE_CANCELLED, (string) $pk]);
        if ($other) throw new AppError('เครื่องนี้มีการขายที่ยังไม่ยกเลิกอยู่แล้ว (' . $other . ')');
    }
    if ($t === 'D') {
        $dup = val('SELECT id FROM sure_devices WHERE serial = ? AND id <> ? LIMIT 1', [$row['serial'], (string) $pk]);
        if ($dup) throw new AppError('Serial Number นี้มีอยู่แล้ว (เครื่อง ' . $dup . ') — ห้ามซ้ำ');
    }
}

/** ลบแถว — Manager เท่านั้น (พนักงานใช้การเปลี่ยนสถานะแทน) */
function rec_delete(string $t, $pk): void
{
    require_manager();
    $d = tdef($t);
    tx(function () use ($t, $d, $pk) {
        if (!rec_raw_lock($t, $pk)) throw new NotFoundError('ไม่พบ' . $d['title'] . ' ' . $pk);
        try {
            q('DELETE FROM ' . db_ident($d['table']) . ' WHERE ' . db_ident($d['pk']) . ' = ?', [$pk]);
        } catch (PDOException $e) {
            if (is_fk_error($e)) throw new AppError('ลบไม่ได้ เพราะมีข้อมูลในตารางอื่นอ้างถึงรายการนี้ — ลบรายการที่อ้างถึงก่อน หรือใช้การเปลี่ยนสถานะแทน');
            throw $e;
        }
    });
    ds_reset();
}
