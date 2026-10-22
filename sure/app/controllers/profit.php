<?php
// กำไรรายการขาย (สเปก 7.4) — MANAGER เท่านั้น

function c_profit_index(): void
{
    require_manager();
    $ds = ds(true);
    $rows = array_values($ds['S']);
    usort($rows, function ($a, $b) { return strcmp($b['id'], $a['id']); });
    $totals = profit_totals($rows);
    render('profit/index', ['title' => 'กำไรรายการขาย', 'rows' => $rows, 'totals' => $totals, 'ds' => $ds]);
}

/** รวม (ไม่รวมยกเลิก): ยอดขายรวม, ต้นทุนรวม, กำไรขั้นต้นรวม, GP % รวม (= กำไรขั้นต้นรวม ÷ ยอดขายรวม) */
function profit_totals(array $sales): array
{
    $t = ['sales' => 0.0, 'cost' => 0.0, 'gp' => 0.0, 'gpp' => null, 'count' => 0];
    foreach ($sales as $s) {
        if ($s['status'] === SURE_CANCELLED) continue;
        $t['count']++;
        $t['sales'] += (float) $s['price'];
        if ($s['cost'] !== null) $t['cost'] += $s['cost'];
        if ($s['gp'] !== null) $t['gp'] += $s['gp'];
    }
    foreach (['sales', 'cost', 'gp'] as $k) $t[$k] = round($t[$k], 2);
    $t['gpp'] = $t['sales'] > 0 ? $t['gp'] / $t['sales'] : null;
    return $t;
}
