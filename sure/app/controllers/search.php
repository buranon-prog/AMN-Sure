<?php
// หน้าค้นหา (สเปก 7.2): ค้นหาเครื่อง / ค้นหาลูกค้า — พนักงานใช้ได้ ข้อมูลสรุปลูกค้าในหน้านี้อนุญาตให้พนักงานเห็น

function c_search_index(): void
{
    require_sure_view();
    $ds = ds();
    $code = get('code') !== null ? strtoupper((string) get('code')) : null;
    // รับทั้งรหัสอย่างเดียว และข้อความจากรายการ "รหัส · ชื่อ"
    if ($code !== null && strpos($code, ' ') !== false) $code = strtok($code, ' ');
    $mode = get('mode');
    // ช่องค้นหาด้านบน (ไม่ระบุโหมด): รหัสลูกค้า → ค้นหาลูกค้า นอกนั้นค้นหาเครื่อง
    if ($mode === null && $code !== null && isset($ds['C'][$code])) $mode = 'customer';
    $mode = $mode === 'customer' ? 'customer' : 'device';
    $vars = ['title' => 'ค้นหา', 'mode' => $mode, 'code' => $code, 'ds' => $ds, 'notFound' => false];

    if ($mode === 'device' && $code !== null) {
        $d = $ds['D'][$code] ?? null;
        if (!$d) {
            $vars['notFound'] = true;
        } else {
            $vars['dev'] = $d;
            $vars['sale'] = $d['soid'] ? $ds['S'][$d['soid']] : null;
            $vars['owner'] = $d['buyer'] ? ($ds['C'][$d['buyer']] ?? null) : null;
            $vars['branch'] = $d['branch'] ? ($ds['B'][$d['branch']] ?? null) : null;
            $vars['seller'] = $d['seller'] ? ($ds['C'][$d['seller']] ?? null) : null;
            $svc = array_values(array_filter($ds['V'], function ($v) use ($code) { return $v['dev'] === $code; }));
            usort($svc, function ($a, $b) { return [$b['date'], $b['_created_at']] <=> [$a['date'], $a['_created_at']]; });
            $vars['services'] = array_slice($svc, 0, 5);
        }
    }
    if ($mode === 'customer' && $code !== null) {
        $c = $ds['C'][$code] ?? null;
        if (!$c) {
            $vars['notFound'] = true;
        } else {
            $vars['cust'] = $c;
            $vars['summary'] = customer_summary($code);
            $vars['nextBranch'] = next_code('B', $code);
            // จำนวนเครื่องที่ติดตั้งต่อสาขา (รหัสสาขาที่ติดตั้ง ของเครื่อง)
            $installed = [];
            foreach ($ds['D'] as $dv) {
                if ($dv['branch']) $installed[$dv['branch']] = ($installed[$dv['branch']] ?? 0) + 1;
            }
            $branches = array_values(array_filter($ds['B'], function ($b) use ($code) { return $b['cust'] === $code; }));
            usort($branches, function ($a, $b) { return strcmp($a['id'], $b['id']); });
            $vars['branches'] = array_slice($branches, 0, 10);
            $vars['installed'] = $installed;
            $bought = array_values(array_filter($ds['S'], function ($s) use ($code) { return $s['cust'] === $code && $s['status'] !== SURE_CANCELLED; }));
            usort($bought, function ($a, $b) { return [$b['date'], $b['_created_at']] <=> [$a['date'], $a['_created_at']]; });
            $vars['bought'] = array_slice($bought, 0, 10);
            $contacts = array_values(array_filter($ds['A'], function ($a) use ($code) { return $a['cust'] === $code; }));
            usort($contacts, function ($a, $b) { return [$b['date'], $b['_created_at']] <=> [$a['date'], $a['_created_at']]; });
            $vars['contacts'] = array_slice($contacts, 0, 5);
        }
    }
    render('search/index', $vars);
}
