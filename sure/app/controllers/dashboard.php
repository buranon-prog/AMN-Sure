<?php
// สรุป (สเปก 7.5) — MANAGER เท่านั้น · เลือก "ปีที่สรุป" (ค่าเริ่มต้น = ปีปัจจุบัน)

const STOCK_VALUE_STATUSES = ['รอตรวจสภาพ', 'กำลังซ่อม / Refurbish', 'พร้อมขาย', 'จองแล้ว', 'รอระบุ'];
const DEVICE_STATUS_ROWS = ['รอตรวจสภาพ', 'กำลังซ่อม / Refurbish', 'พร้อมขาย', 'จองแล้ว', 'ขายแล้ว – รอส่งมอบ', 'ส่งมอบแล้ว', 'คืนเจ้าของ (ฝากขาย)', 'ตัดจำหน่าย', 'รอระบุ'];

function c_dashboard_index(): void
{
    require_manager();
    $ds = ds(true);
    $today = sure_today();
    $thisYear = (int) substr($today, 0, 4);
    $years = [$thisYear => true];
    foreach ($ds['S'] as $s) $years[(int) substr($s['date'], 0, 4)] = true;
    foreach ($ds['C'] as $c) $years[(int) substr($c['since'], 0, 4)] = true;
    foreach ($ds['P'] as $p) $years[(int) substr($p['date'], 0, 4)] = true;
    krsort($years);
    $year = (int) (get('year') ?? $thisYear);
    if ($year < 2000 || $year > 2099) $year = $thisYear;
    $years[$year] = true;
    krsort($years);

    render('dashboard/index', ['title' => 'สรุป', 'year' => $year, 'years' => array_keys($years)] + dashboard_data($ds, $year, $today));
}

function dashboard_data(array $ds, int $year, string $today): array
{
    $y = (string) $year;
    $soldYear = array_filter($ds['S'], function ($s) use ($y) { return $s['status'] !== SURE_CANCELLED && substr($s['date'], 0, 4) === $y; });
    $k = [
        'customers' => count($ds['C']),
        'new_customers' => count(array_filter($ds['C'], function ($c) use ($y) { return substr($c['since'], 0, 4) === $y; })),
        'devices' => count($ds['D']),
        'ready' => count(array_filter($ds['D'], function ($d) { return $d['status'] === SURE_READY; })),
        'sold' => count($soldYear),
        'sales' => 0.0,
        'gp' => 0.0,
        'gpp' => null,
        'waiting' => count(array_filter($ds['D'], function ($d) { return $d['status'] === 'ขายแล้ว – รอส่งมอบ'; })),
        'outstanding' => 0.0,
        'stock_value' => 0.0,
        'received_month' => 0.0,
        'expiring' => count(array_filter($ds['D'], function ($d) { return in_array($d['cover'], ['ประกันใกล้หมด', 'MA ใกล้หมด'], true); })),
        'svc_open' => count(array_filter($ds['V'], function ($v) { return !in_array($v['jstatus'], [SURE_JOB_CLOSED, SURE_CANCELLED], true); })),
        'fu_due' => count(array_filter($ds['A'], function ($a) { return in_array($a['due'], ['เลยกำหนด', 'วันนี้'], true); })),
        'leads' => count(array_filter($ds['agg'], function ($g) { return $g['rel'] === 'ผู้สนใจ / Lead'; })),
    ];
    foreach ($soldYear as $s) {
        $k['sales'] += (float) $s['price'];
        if ($s['gp'] !== null) $k['gp'] += $s['gp'];
    }
    $k['gpp'] = $k['sales'] > 0 ? $k['gp'] / $k['sales'] : null;
    foreach ($ds['S'] as $s) $k['outstanding'] += $s['outstanding'];
    foreach ($ds['D'] as $d) {
        if (in_array($d['status'], STOCK_VALUE_STATUSES, true) && $d['acq'] !== SURE_CONSIGN) $k['stock_value'] += (float) ($d['totalcost'] ?? 0);
    }
    $month = substr($today, 0, 7);
    foreach ($ds['P'] as $p) {
        if (substr($p['date'], 0, 7) === $month) $k['received_month'] += $p['amount'];
    }
    foreach (['sales', 'gp', 'outstanding', 'stock_value', 'received_month'] as $f) $k[$f] = round($k[$f], 2);

    // เครื่องตามสถานะปัจจุบัน
    $byStatus = array_fill_keys(DEVICE_STATUS_ROWS, 0);
    foreach ($ds['D'] as $d) {
        if (isset($byStatus[$d['status']])) $byStatus[$d['status']]++;
    }
    // ลูกค้าตามภาค
    $byRegion = array_fill_keys(regions(), 0);
    foreach ($ds['C'] as $c) {
        if ($c['region'] && isset($byRegion[$c['region']])) $byRegion[$c['region']]++;
    }
    // ช่องทางที่ลูกค้ารู้จัก SURE
    $bySource = array_fill_keys(option_values('source'), 0);
    $noSource = 0;
    foreach ($ds['C'] as $c) {
        if ($c['source'] === null || $c['source'] === '') $noSource++;
        else $bySource[$c['source']] = ($bySource[$c['source']] ?? 0) + 1;
    }
    // ยอดขายรายเดือน
    $monthly = [];
    for ($m = 1; $m <= 12; $m++) $monthly[$m] = ['count' => 0, 'sales' => 0.0, 'gp' => 0.0, 'received' => 0.0];
    foreach ($soldYear as $s) {
        $m = (int) substr($s['date'], 5, 2);
        $monthly[$m]['count']++;
        $monthly[$m]['sales'] += (float) $s['price'];
        if ($s['gp'] !== null) $monthly[$m]['gp'] += $s['gp'];
    }
    foreach ($ds['P'] as $p) {
        if (substr($p['date'], 0, 4) !== $y) continue;
        $monthly[(int) substr($p['date'], 5, 2)]['received'] += $p['amount'];
    }
    // ผลงานพนักงานขาย (Sales ผู้ปิดการขาย)
    $bySales = [];
    foreach ($soldYear as $s) {
        $n = $s['sales'];
        if (!isset($bySales[$n])) $bySales[$n] = ['count' => 0, 'sales' => 0.0, 'gp' => 0.0];
        $bySales[$n]['count']++;
        $bySales[$n]['sales'] += (float) $s['price'];
        if ($s['gp'] !== null) $bySales[$n]['gp'] += $s['gp'];
    }
    uasort($bySales, function ($a, $b) { return $b['sales'] <=> $a['sales']; });

    return ['k' => $k, 'byStatus' => $byStatus, 'byRegion' => $byRegion, 'bySource' => $bySource, 'noSource' => $noSource,
        'monthly' => $monthly, 'bySales' => $bySales];
}
