<?php
// controller กลางของตาราง C B A D S P V T: รายการ (ค้นหา/กรอง/เรียง), เพิ่ม, แก้ไข, ลบ
// สิทธิ์: ดู = require_table_view · เพิ่ม/แก้ = require_table_edit · ลบ = Manager เท่านั้น (ใน rec_delete)

const LIST_PER_PAGE = 50;

/** กรอง ค้นหา เรียง แถวตามพารามิเตอร์ใน URL (ทำบนฟิลด์ที่ผู้ใช้เห็นได้เท่านั้น) */
function list_query(string $t, array $rows, array $fields): array
{
    $def = tdef($t);
    $filters = [];
    $filterOptions = [];
    foreach ($def['filters'] as $k) {
        if (!isset($fields[$k])) continue;
        $vals = [];
        foreach ($rows as $r) {
            $v = $r[$k] ?? null;
            if ($v !== null && $v !== '') $vals[(string) $v] = true;
        }
        $vals = array_keys($vals);
        sort($vals, SORT_STRING);
        $filterOptions[$k] = $vals;
        $sel = isset($_GET['f'][$k]) && is_string($_GET['f'][$k]) ? trim($_GET['f'][$k]) : '';
        if ($sel !== '') $filters[$k] = $sel;
    }
    if ($filters) {
        $rows = array_values(array_filter($rows, function ($r) use ($filters) {
            foreach ($filters as $k => $v) {
                if ((string) ($r[$k] ?? '') !== $v) return false;
            }
            return true;
        }));
    }
    $q = get('q');
    if ($q !== null) {
        $needle = mb_strtolower($q);
        $rows = array_values(array_filter($rows, function ($r) use ($fields, $needle) {
            foreach ($fields as $k => $f) {
                $v = $r[$k] ?? null;
                if ($v === null || $v === '') continue;
                if (mb_strpos(mb_strtolower((string) $v), $needle) !== false) return true;
                if ($f['type'] !== 'text' && $f['type'] !== 'textarea' && mb_strpos(fmt_value($f, $v), $needle) !== false) return true;
            }
            return false;
        }));
    }
    $sort = get('sort');
    $dir = get('dir') === 'asc' ? 'asc' : (get('dir') === 'desc' ? 'desc' : null);
    if ($sort === null || !isset($fields[$sort])) {
        $sort = $def['sort'][0];
        $dir = $dir ?? $def['sort'][1];
    }
    $dir = $dir ?? 'asc';
    $numeric = is_numeric_type($fields[$sort]);
    $pk = $def['pk'];
    usort($rows, function ($a, $b) use ($sort, $dir, $numeric, $pk) {
        $x = $a[$sort] ?? null;
        $y = $b[$sort] ?? null;
        // ค่าว่างไว้ท้ายเสมอ
        if (($x === null || $x === '') && ($y === null || $y === '')) $c = 0;
        elseif ($x === null || $x === '') return 1;
        elseif ($y === null || $y === '') return -1;
        else $c = $numeric ? ((float) $x <=> (float) $y) : strcmp((string) $x, (string) $y);
        if ($c === 0) $c = strnatcmp((string) $a[$pk], (string) $b[$pk]);
        return $dir === 'desc' ? -$c : $c;
    });
    return ['rows' => $rows, 'filters' => $filters, 'filterOptions' => $filterOptions, 'q' => $q, 'sort' => $sort, 'dir' => $dir];
}

function t_index(string $t): void
{
    require_table_view($t);
    $def = tdef($t);
    $fields = visible_fields($t);
    $ds = ds();
    $res = list_query($t, array_values($ds[$t]), $fields);
    $allCols = get('cols') === 'all';
    $cols = $allCols ? array_keys($fields) : array_values(array_filter($def['list'], function ($k) use ($fields) { return isset($fields[$k]); }));

    $total = count($res['rows']);
    $pages = max(1, (int) ceil($total / LIST_PER_PAGE));
    $page = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);
    $res['rows'] = array_slice($res['rows'], ($page - 1) * LIST_PER_PAGE, LIST_PER_PAGE);

    $vars = ['title' => $def['title'], 't' => $t, 'def' => $def, 'fields' => $fields, 'cols' => $cols, 'allCols' => $allCols,
        'pager' => ['page' => $page, 'pages' => $pages, 'total' => $total]] + $res;
    if ($t === 'T') {
        // เครื่องที่ยังไม่มีแถวต้นทุน
        $vars['uncosted'] = count(array_diff_key($ds['D'], $ds['T']));
    }
    render('table/list', $vars);
}

/** ค่าที่ฟอร์มต้องใช้: รหัสถัดไป, ค่าเริ่มต้นจาก URL, ตัวกรองสาขาตามลูกค้า */
function form_ctx(string $t, bool $isNew): array
{
    $def = tdef($t);
    $ctx = ['defaults' => [], 'filter_by' => [], 'next_codes' => [], 'fk_options' => []];
    if ($isNew) {
        foreach ($def['fields'] as $k => $f) {
            if ($f['how'] === 'fk' && get($k) !== null) $ctx['defaults'][$k] = strtoupper((string) get($k));
        }
        if (in_array($t, ['C', 'D', 'S', 'V'], true)) {
            $ctx['next_code'] = next_code($t);
            $ctx['defaults'][$def['pk']] = $ctx['next_code'];
        }
        if ($t === 'B') {
            $codes = next_branch_codes();
            $ctx['next_codes']['cust'] = $codes;
            $cust = $ctx['defaults']['cust'] ?? null;
            $ctx['next_code'] = $cust && isset($codes[$cust]) ? $codes[$cust] : 'เลือกรหัสลูกค้าก่อน';
            if ($cust && isset($codes[$cust])) $ctx['defaults']['id'] = $codes[$cust];
        }
        if ($t === 'T') {
            // 1 เครื่อง = 1 แถว: ให้เลือกเฉพาะเครื่องที่ยังไม่มีต้นทุน
            $ds = ds();
            $ctx['fk_options']['dev'] = array_diff_key(fk_options('D'), $ds['T']);
        }
    }
    if ($t === 'A' || $t === 'S') $ctx['filter_by']['branch'] = 'cust';
    return $ctx;
}

function t_new(string $t): void
{
    require_table_edit($t);
    $def = tdef($t);
    render('table/form', ['title' => 'เพิ่ม' . $def['title'], 't' => $t, 'def' => $def, 'row' => null, 'isNew' => true,
        'editable' => true, 'ctx' => form_ctx($t, true)]);
}

function t_edit(string $t): void
{
    require_table_view($t);
    $def = tdef($t);
    $pk = get('id');
    $row = $pk !== null ? rec_find($t, $pk) : null;
    if (!$row) throw new NotFoundError('ไม่พบ' . $def['title'] . ' ' . (string) $pk);
    $editable = $def['mgr'] ? is_manager() : can_edit_sure();
    render('table/form', ['title' => $def['title'] . ' ' . $pk, 't' => $t, 'def' => $def, 'row' => $row, 'isNew' => false,
        'editable' => $editable, 'ctx' => form_ctx($t, false)]);
}

function t_post_save(string $t): void
{
    $def = tdef($t);
    $pk = post('_pk');
    $input = [];
    foreach ($def['fields'] as $k => $f) {
        if (array_key_exists($k, $_POST)) $input[$k] = $_POST[$k];
    }
    $newPk = rec_save($t, $pk, $input);
    flash('success', ($pk ? 'บันทึกการแก้ไขแล้ว' : 'เพิ่ม' . $def['title'] . 'แล้ว') . ($def['pk'] === 'id' && !empty($def['autoid']) ? '' : ' — ' . $newPk));
    redirect($def['route'] . '.edit', ['id' => $newPk]);
}

function t_post_delete(string $t): void
{
    $def = tdef($t);
    $pk = (string) post('_pk', '');
    rec_delete($t, $pk);
    flash('success', 'ลบ' . $def['title'] . ' ' . $pk . ' แล้ว');
    redirect($def['route']);
}
