<?php
// Export CSV ของตาราง C B A D S P V T — กรองฟิลด์ตามสิทธิ์ที่ server
// EMPLOYEE: ไม่ได้ตาราง ต้นทุนเครื่อง และไม่มีคอลัมน์ Manager เท่านั้น (แถวถูกคำนวณแบบไม่มีข้อมูลต้นทุนตั้งแต่ต้น)

function c_export_index(): void
{
    require_sure_view();
    $tables = array_filter(sure_tables(), function ($d) { return !$d['mgr'] || is_manager(); });
    render('export/index', ['title' => 'Export', 'tables' => $tables]);
}

function c_export_csv(): void
{
    $t = (string) get('t', '');
    if (!isset(sure_tables()[$t])) throw new NotFoundError('ไม่พบตาราง');
    require_table_view($t);
    $fields = visible_fields($t);
    $rows = ds()[$t];
    ksort($rows, SORT_NATURAL);
    $def = tdef($t);

    $name = 'sure-' . $def['route'] . '-' . date('Ymd-His', strtotime(now())) . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM ให้ Excel อ่านภาษาไทยถูก
    fputcsv($out, array_map(function ($f) { return $f['label']; }, array_values($fields)), ',', '"', '\\');
    foreach ($rows as $r) {
        $line = [];
        foreach ($fields as $k => $f) {
            $v = fmt_value($f, $r[$k] ?? null, true);
            // กันสูตร Excel (CSV injection) ในช่องข้อความ
            if (in_array($f['type'], ['text', 'textarea'], true) && $v !== '' && strpos('=+-@', $v[0]) !== false) $v = "'" . $v;
            $line[] = $v;
        }
        fputcsv($out, $line, ',', '"', '\\');
    }
    fclose($out);
}
