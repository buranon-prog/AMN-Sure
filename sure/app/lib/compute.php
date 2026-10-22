<?php
// ฟิลด์อัตโนมัติทั้งหมด (สเปกหัวข้อ 6) — คำนวณที่ server ตอนอ่าน ไม่เก็บลงฐานข้อมูล
//
// ds() โหลดทุกตารางแล้วคำนวณครั้งเดียวต่อ request (ข้อมูลระดับหลักพันแถว คำนวณใน PHP ได้เร็ว)
// ความปลอดภัย: ผู้ใช้ที่ไม่ใช่ Manager → ไม่ query ตาราง ต้นทุนเครื่อง เลย และไม่คำนวณฟิลด์ Manager เท่านั้น
//             แถวที่ส่งออกไปถูกตัดฟิลด์ 'mgr' ทิ้งอีกชั้นก่อนคืนค่า

/** วันที่ "วันนี้" ที่ใช้คำนวณ (tests ตั้งค่าได้ผ่าน $GLOBALS['SURE_TODAY']) */
function sure_today(): string
{
    return $GLOBALS['SURE_TODAY'] ?? today();
}

/** อัตรา VAT จากค่าตั้งต้น (เก็บเป็น % เช่น "7") */
function vat_rate(): float
{
    return ((float) setting('vat_rate', '7')) / 100;
}

function ds_reset(): void
{
    unset($GLOBALS['__DS']);
    options_reset();
    unset($GLOBALS['__SETTINGS']);
}

/** โหลดแถวดิบของตาราง เรียงตามลำดับที่บันทึก คืน [pk => row] (key = field_key, audit = _created_at ฯลฯ) */
function ds_load(string $t): array
{
    $d = tdef($t);
    $map = [];
    foreach ($d['fields'] as $k => $f) {
        if (isset($f['col'])) $map[$f['col']] = $k;
    }
    $rows = [];
    foreach (all('SELECT * FROM ' . db_ident($d['table']) . ' ORDER BY created_at, ' . db_ident($d['pk'])) as $r) {
        $row = [];
        foreach ($r as $col => $v) {
            if (in_array($col, ['created_at', 'updated_at', 'created_by', 'updated_by'], true)) {
                $row['_' . $col] = $v;
                continue;
            }
            $key = $map[$col] ?? $col;
            $f = $d['fields'][$key] ?? null;
            if ($v !== null && $f) {
                if ($f['type'] === 'money') $v = (float) $v;
                elseif ($f['type'] === 'int') $v = (int) $v;
            }
            $row[$key] = $v;
        }
        $rows[(string) $r[$d['pk']]] = $row;
    }
    return $rows;
}

/**
 * ชุดข้อมูลที่คำนวณแล้วของทุกตาราง สำหรับสิทธิ์ของผู้ใช้ปัจจุบัน
 * คืน ['C' => [...], 'B' => [...], 'A', 'D', 'S', 'P', 'V', 'T' (เฉพาะ Manager), 'agg' => สรุปต่อลูกค้า]
 */
function ds(?bool $manager = null): array
{
    $manager = $manager ?? is_manager();
    $cacheKey = $manager ? 'manager' : 'employee';
    if (isset($GLOBALS['__DS'][$cacheKey])) return $GLOBALS['__DS'][$cacheKey];

    $today = sure_today();
    $rate = vat_rate();
    $C = ds_load('C');
    $B = ds_load('B');
    $A = ds_load('A');
    $D = ds_load('D');
    $S = ds_load('S');
    $P = ds_load('P');
    $V = ds_load('V');
    $T = $manager ? ds_load('T') : [];

    // ---- ต้นทุนรวมต่อเครื่อง (Manager เท่านั้น)
    $costTotal = [];
    foreach ($T as $dev => $c) {
        $costTotal[$dev] = round((float) $c['cost'] + (float) ($c['refurb'] ?? 0) + (float) ($c['other'] ?? 0), 2);
    }

    // ---- รับชำระเงินรวมต่อการขาย
    $pay = [];
    foreach ($P as $p) {
        $so = $p['so'];
        if (!isset($pay[$so])) $pay[$so] = ['deposit' => 0.0, 'paid' => 0.0, 'lastpay' => null];
        $pay[$so]['paid'] += $p['amount'];
        if ($p['type'] === SURE_DEPOSIT) $pay[$so]['deposit'] += $p['amount'];
        if ($pay[$so]['lastpay'] === null || $p['date'] > $pay[$so]['lastpay']) $pay[$so]['lastpay'] = $p['date'];
    }

    // ---- การขาย
    foreach ($S as $id => &$s) {
        $dev = $D[$s['dev']] ?? [];
        $cust = $C[$s['cust']] ?? [];
        $br = $B[$s['branch']] ?? null;
        $s['devname'] = dev_name($dev);
        $s['serial'] = $dev['serial'] ?? null;
        $s['custname'] = $cust['name'] ?? null;
        $s['branchname'] = $br['bname'] ?? null;
        $s['phone'] = $cust['phone'] ?? null;
        $s['prov'] = $br ? $br['prov'] : ($cust['prov'] ?? null);
        $s['site'] = full_address($br ?: $cust);
        $price = (float) $s['price'];
        $s['vat'] = round($price * $rate, 2);
        $s['total'] = round($price + $s['vat'], 2);
        $agg = $pay[$id] ?? ['deposit' => 0.0, 'paid' => 0.0, 'lastpay' => null];
        $s['deposit'] = round($agg['deposit'], 2);
        $s['paid'] = round($agg['paid'], 2);
        $s['lastpay'] = $agg['lastpay'];
        $cancelled = $s['status'] === SURE_CANCELLED;
        $s['outstanding'] = $cancelled ? 0.0 : round($s['total'] - $s['paid'], 2);
        if ($cancelled) $s['paystatus'] = '–';
        elseif ($s['paid'] >= $s['total']) $s['paystatus'] = 'ชำระครบ';
        elseif ($s['paid'] > 0) $s['paystatus'] = 'ชำระบางส่วน';
        else $s['paystatus'] = 'ยังไม่ชำระ';
        $months = (int) ($s['wmonths'] ?? 0);
        $s['wstart'] = $s['deliv'];
        $s['wend'] = ($s['deliv'] && $months > 0) ? add_days(edate($s['deliv'], $months), -1) : null;
        if ($cancelled) $s['wstatus'] = '–';
        elseif (!$s['deliv']) $s['wstatus'] = 'รอส่งมอบ';
        elseif ($months <= 0) $s['wstatus'] = 'ไม่ระบุประกัน';
        elseif ($today > $s['wend']) $s['wstatus'] = 'หมดประกัน';
        elseif (days_between($today, $s['wend']) <= 30) $s['wstatus'] = 'ใกล้หมด (≤30 วัน)';
        else $s['wstatus'] = 'ในประกัน';
        if ($manager) {
            $s['cost'] = $costTotal[$s['dev']] ?? null;
            $s['gp'] = ($cancelled || $s['cost'] === null) ? null : round($price - $s['cost'], 2);
            $s['gpp'] = ($s['gp'] !== null && $price > 0) ? $s['gp'] / $price : null;
        }
    }
    unset($s);

    // ---- การขายล่าสุดของเครื่อง = รายการที่สถานะ ≠ ยกเลิก ที่บันทึกล่าสุด ($S เรียงตาม created_at แล้ว)
    $latest = [];
    foreach ($S as $id => $s) {
        if ($s['status'] !== SURE_CANCELLED) $latest[$s['dev']] = $id;
    }

    // ---- บริการรวมต่อเครื่อง
    $svc = [];
    foreach ($V as $v) {
        $k = $v['dev'];
        if (!isset($svc[$k])) $svc[$k] = ['n' => 0, 'last' => null, 'maend' => null];
        $svc[$k]['n']++;
        if ($svc[$k]['last'] === null || $v['date'] > $svc[$k]['last']) $svc[$k]['last'] = $v['date'];
        if ($v['maend'] && ($svc[$k]['maend'] === null || $v['maend'] > $svc[$k]['maend'])) $svc[$k]['maend'] = $v['maend'];
    }

    // ---- เครื่อง
    foreach ($D as $id => &$d) {
        $d['sellername'] = $d['seller'] ? ($C[$d['seller']]['name'] ?? null) : null;
        $L = isset($latest[$id]) ? $S[$latest[$id]] : null;
        $d['soid'] = $L['id'] ?? null;
        $d['buyer'] = $L['cust'] ?? null;
        $d['buyername'] = $L['custname'] ?? null;
        $d['branch'] = $L['branch'] ?? null;
        $d['branchname'] = $L['branchname'] ?? null;
        $d['saledate'] = $L['date'] ?? null;
        $d['saleprice'] = $L ? (float) $L['price'] : null;
        $d['delivdate'] = $L['deliv'] ?? null;
        $d['wend'] = $L['wend'] ?? null;
        if ($L) $d['status'] = $L['deliv'] ? 'ส่งมอบแล้ว' : 'ขายแล้ว – รอส่งมอบ';
        else $d['status'] = ($d['stock'] !== null && $d['stock'] !== '') ? $d['stock'] : 'รอระบุ';
        $d['maend'] = $svc[$id]['maend'] ?? null;
        $d['cover'] = $d['delivdate'] ? coverage_status($today, $d['wend'], $d['maend']) : '–';
        $d['nsvc'] = $svc[$id]['n'] ?? 0;
        $d['lastsvc'] = $svc[$id]['last'] ?? null;
        if ($manager) {
            $d['totalcost'] = $costTotal[$id] ?? null;
            $d['gp'] = ($d['saleprice'] !== null && $d['totalcost'] !== null) ? round($d['saleprice'] - $d['totalcost'], 2) : null;
            $d['gpp'] = ($d['gp'] !== null && $d['saleprice'] > 0) ? $d['gp'] / $d['saleprice'] : null;
        }
    }
    unset($d);

    // ---- บริการ
    foreach ($V as &$v) {
        $dev = $D[$v['dev']] ?? [];
        $owner = $dev['buyer'] ?? null;
        $v['devname'] = dev_name($dev);
        $v['serial'] = $dev['serial'] ?? null;
        $v['owner'] = $owner;
        $v['ownername'] = $owner ? ($C[$owner]['name'] ?? null) : null;
        $v['phone'] = $owner ? ($C[$owner]['phone'] ?? null) : null;
        $v['branchname'] = $dev['branchname'] ?? null;
        if (empty($dev['delivdate'])) $v['cover'] = 'เครื่องในสต็อก SURE';
        elseif ($dev['wend'] && $v['date'] <= $dev['wend']) $v['cover'] = 'ในประกัน';
        elseif ($dev['maend'] && $v['date'] <= $dev['maend']) $v['cover'] = 'อยู่ใน MA';
        else $v['cover'] = 'นอกประกัน – คิดค่าบริการ';
        $v['days'] = $v['close'] ? days_between($v['date'], $v['close']) : null;
    }
    unset($v);

    // ---- รับชำระเงิน
    foreach ($P as &$p) {
        $sale = $S[$p['so']] ?? [];
        $p['cust'] = $sale['cust'] ?? null;
        $p['custname'] = $sale['custname'] ?? null;
        $p['dev'] = $sale['dev'] ?? null;
        $p['balance'] = $sale['outstanding'] ?? null;
    }
    unset($p);

    // ---- สรุปต่อลูกค้า (ใช้ทั้งคอลัมน์ Manager ในตารางลูกค้า และหน้าค้นหาลูกค้า)
    $agg = [];
    foreach ($C as $id => $c) {
        $agg[$id] = ['nbranch' => 0, 'nbuy' => 0, 'amt' => 0.0, 'last' => null, 'due' => 0.0, 'nsell' => 0, 'lastct' => null, 'nextfu' => null];
    }
    foreach ($B as $b) {
        if (isset($agg[$b['cust']])) $agg[$b['cust']]['nbranch']++;
    }
    foreach ($S as $s) {
        if (!isset($agg[$s['cust']])) continue;
        $agg[$s['cust']]['due'] += $s['outstanding'];
        if ($s['status'] === SURE_CANCELLED) continue;
        $g = &$agg[$s['cust']];
        $g['nbuy']++;
        $g['amt'] += (float) $s['price'];
        if ($g['last'] === null || $s['date'] > $g['last']) $g['last'] = $s['date'];
        unset($g);
    }
    foreach ($D as $d) {
        if ($d['seller'] && isset($agg[$d['seller']])) $agg[$d['seller']]['nsell']++;
    }
    foreach ($A as $a) {
        if (!isset($agg[$a['cust']])) continue;
        $g = &$agg[$a['cust']];
        if ($g['lastct'] === null || $a['date'] > $g['lastct']) $g['lastct'] = $a['date'];
        if ($a['result'] === SURE_FU_OPEN && $a['nextdate'] && ($g['nextfu'] === null || $a['nextdate'] < $g['nextfu'])) $g['nextfu'] = $a['nextdate'];
        unset($g);
    }
    foreach ($agg as &$g) {
        $g['amt'] = round($g['amt'], 2);
        $g['due'] = round($g['due'], 2);
        if ($g['nbuy'] > 0 && $g['nsell'] > 0) $g['rel'] = 'ซื้อและขายเครื่อง';
        elseif ($g['nbuy'] > 0) $g['rel'] = 'ผู้ซื้อ';
        elseif ($g['nsell'] > 0) $g['rel'] = 'ผู้ขายเครื่องให้ SURE';
        else $g['rel'] = 'ผู้สนใจ / Lead';
    }
    unset($g);

    // ---- ลูกค้า
    foreach ($C as $id => &$c) {
        $c['region'] = region_of($c['prov']);
        if ($manager) $c += $agg[$id];
    }
    unset($c);

    // ---- สาขา
    foreach ($B as $id => &$b) {
        $b['custname'] = substr((string) $id, 0, 7) !== $b['cust'] ? '⚠ รหัสสาขาไม่ตรงกับรหัสลูกค้า' : ($C[$b['cust']]['name'] ?? null);
        $b['region'] = region_of($b['prov']);
    }
    unset($b);

    // ---- ติดตามลูกค้า
    foreach ($A as &$a) {
        $cust = $C[$a['cust']] ?? [];
        $a['custname'] = $cust['name'] ?? null;
        $a['phone'] = $cust['phone'] ?? null;
        $a['branchname'] = $a['branch'] ? ($B[$a['branch']]['bname'] ?? null) : null;
        $a['due'] = followup_due($a, $today);
    }
    unset($a);

    // ---- ต้นทุนเครื่อง (Manager เท่านั้น)
    foreach ($T as $dev => &$t) {
        $dv = $D[$dev] ?? [];
        $t['devname'] = dev_name($dv);
        $t['serial'] = $dv['serial'] ?? null;
        $t['acq'] = $dv['acq'] ?? null;
        $t['seller'] = $dv['sellername'] ?? null;
        $t['status'] = $dv['status'] ?? null;
        $t['totalcost'] = $costTotal[$dev];
        $t['listprice'] = $dv['listprice'] ?? null;
        $t['listgp'] = ($t['listprice'] !== null && $t['listprice'] > 0) ? ($t['listprice'] - $t['totalcost']) / $t['listprice'] : null;
        $t['saleprice'] = $dv['saleprice'] ?? null;
        $t['gp'] = $t['saleprice'] !== null ? round($t['saleprice'] - $t['totalcost'], 2) : null;
        $t['gpp'] = ($t['gp'] !== null && $t['saleprice'] > 0) ? $t['gp'] / $t['saleprice'] : null;
    }
    unset($t);

    $out = ['C' => $C, 'B' => $B, 'A' => $A, 'D' => $D, 'S' => $S, 'P' => $P, 'V' => $V, 'T' => $T, 'agg' => $agg];
    if (!$manager) $out = ds_strip_manager_fields($out);
    $GLOBALS['__DS'][$cacheKey] = $out;
    return $out;
}

/** ตัดฟิลด์ Manager เท่านั้นทิ้งทุกแถว (ป้องกันอีกชั้น) */
function ds_strip_manager_fields(array $ds): array
{
    $ds['T'] = [];
    foreach (sure_tables() as $t => $def) {
        $mgrKeys = [];
        foreach ($def['fields'] as $k => $f) {
            if ($f['mgr']) $mgrKeys[] = $k;
        }
        if (!$mgrKeys || empty($ds[$t])) continue;
        foreach ($ds[$t] as $pk => $row) {
            foreach ($mgrKeys as $k) unset($row[$k]);
            $ds[$t][$pk] = $row;
        }
    }
    return $ds;
}

function dev_name(array $dev): ?string
{
    if (!$dev) return null;
    $n = trim(($dev['brand'] ?? '') . ' ' . ($dev['model'] ?? ''));
    return $n === '' ? null : $n;
}

/** ที่อยู่เต็ม "เลขที่ แขวง เขต จังหวัด รหัสไปรษณีย์" */
function full_address(?array $r): ?string
{
    if (!$r) return null;
    $parts = [];
    foreach (['addr', 'subd', 'dist', 'prov', 'zip'] as $k) {
        if (isset($r[$k]) && trim((string) $r[$k]) !== '') $parts[] = trim((string) $r[$k]);
    }
    return $parts ? implode(' ', $parts) : null;
}

/** สถานะความคุ้มครองของเครื่องที่ส่งมอบแล้ว ณ วันนี้ */
function coverage_status(string $today, ?string $wend, ?string $maend): string
{
    if ($wend && $today <= $wend) return days_between($today, $wend) <= 30 ? 'ประกันใกล้หมด' : 'ในประกัน';
    if ($maend && $today <= $maend) return days_between($today, $maend) <= 30 ? 'MA ใกล้หมด' : 'อยู่ใน MA';
    return 'หมดประกัน / ไม่มี MA';
}

/** ครบกำหนดติดตาม (เฉพาะสถานะ เปิด / รอติดตาม ที่มีวันนัด) */
function followup_due(array $a, string $today): ?string
{
    if ($a['result'] !== SURE_FU_OPEN || !$a['nextdate']) return null;
    $diff = days_between($today, $a['nextdate']);
    if ($diff < 0) return 'เลยกำหนด';
    if ($diff === 0) return 'วันนี้';
    if ($diff <= 7) return 'ภายใน 7 วัน';
    return null;
}

/** สรุปกับ SURE ของลูกค้ารายเดียว (หน้าค้นหา — พนักงานดูได้ตามสเปก 7.2) */
function customer_summary(string $custId): array
{
    $agg = ds()['agg'];
    return $agg[$custId] ?? [];
}
